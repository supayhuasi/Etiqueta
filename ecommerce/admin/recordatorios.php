<?php
require 'includes/header.php';

function rec_ensure_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_recordatorios_usuarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NULL,
        creado_por INT NULL,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        estado ENUM('pendiente','revisado') NOT NULL DEFAULT 'pendiente',
        fecha_recordatorio DATE NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fecha_revisado DATETIME NULL,
        INDEX idx_usuario (usuario_id),
        INDEX idx_estado (estado),
        INDEX idx_fecha_recordatorio (fecha_recordatorio),
        INDEX idx_fecha_creacion (fecha_creacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

rec_ensure_schema($pdo);

$usuario_actual_id = (int)($_SESSION['user']['id'] ?? 0);
$is_admin = (($role ?? '') === 'admin');

$estado_filtro = trim((string)($_GET['estado'] ?? ''));
if (!in_array($estado_filtro, ['', 'pendiente', 'revisado'], true)) {
    $estado_filtro = '';
}

$fecha_desde = trim((string)($_GET['fecha_desde'] ?? date('Y-m-d')));
$fecha_hasta = trim((string)($_GET['fecha_hasta'] ?? date('Y-m-d')));
if ($fecha_desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = date('Y-m-d');
}
if ($fecha_hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = date('Y-m-d');
}
if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
    [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    $accion = trim((string)($_POST['accion'] ?? ''));

    try {
        if ($accion === 'crear') {
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $fecha_recordatorio = trim((string)($_POST['fecha_recordatorio'] ?? ''));
            $usuario_id_destino = (int)($_POST['usuario_id'] ?? 0);

            if ($titulo === '') {
                throw new Exception('Ingresá un título para el recordatorio.');
            }

            if (!$is_admin) {
                $usuario_id_destino = $usuario_actual_id;
            }

            if ($fecha_recordatorio !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_recordatorio)) {
                $fecha_recordatorio = '';
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_recordatorios_usuarios (usuario_id, creado_por, titulo, descripcion, estado, fecha_recordatorio) VALUES (?, ?, ?, ?, 'pendiente', ?)");
            $stmt->execute([
                $usuario_id_destino > 0 ? $usuario_id_destino : null,
                $usuario_actual_id > 0 ? $usuario_actual_id : null,
                $titulo,
                $descripcion !== '' ? $descripcion : null,
                $fecha_recordatorio !== '' ? $fecha_recordatorio : null,
            ]);

            $mensaje = 'Recordatorio cargado correctamente.';
        }

        if ($accion === 'estado') {
            $recordatorio_id = (int)($_POST['recordatorio_id'] ?? 0);
            $nuevo_estado = trim((string)($_POST['nuevo_estado'] ?? ''));
            if ($recordatorio_id <= 0 || !in_array($nuevo_estado, ['pendiente', 'revisado'], true)) {
                throw new Exception('Datos inválidos para actualizar estado.');
            }

            if (!$is_admin) {
                $stmt = $pdo->prepare("SELECT id FROM ecommerce_recordatorios_usuarios WHERE id = ? AND (usuario_id = ? OR creado_por = ?)");
                $stmt->execute([$recordatorio_id, $usuario_actual_id, $usuario_actual_id]);
                if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                    throw new Exception('No tenés permisos para modificar este recordatorio.');
                }
            }

            if ($nuevo_estado === 'revisado') {
                $stmt = $pdo->prepare("UPDATE ecommerce_recordatorios_usuarios SET estado = ?, fecha_revisado = NOW() WHERE id = ?");
                $stmt->execute([$nuevo_estado, $recordatorio_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_recordatorios_usuarios SET estado = ?, fecha_revisado = NULL WHERE id = ?");
                $stmt->execute([$nuevo_estado, $recordatorio_id]);
            }

            $mensaje = 'Estado actualizado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$usuarios = [];
if ($is_admin) {
    try {
        $stmt = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(nombre), ''), usuario) AS nombre FROM usuarios WHERE COALESCE(activo,1) = 1 ORDER BY nombre ASC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $usuarios = [];
    }
}

$where = ["1=1"];
$params = [];

if (!$is_admin) {
    $where[] = "(r.usuario_id = ? OR r.usuario_id IS NULL OR r.creado_por = ?)";
    $params[] = $usuario_actual_id;
    $params[] = $usuario_actual_id;
}

if ($estado_filtro !== '') {
    $where[] = "r.estado = ?";
    $params[] = $estado_filtro;
}
if ($fecha_desde !== '') {
    $where[] = "COALESCE(r.fecha_recordatorio, DATE(r.fecha_creacion)) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $where[] = "COALESCE(r.fecha_recordatorio, DATE(r.fecha_creacion)) <= ?";
    $params[] = $fecha_hasta;
}

$recordatorios = [];
try {
    $sql = "SELECT
        r.*,
        COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario, 'General') AS usuario_nombre,
        COALESCE(NULLIF(TRIM(c.nombre), ''), c.usuario, '-') AS creado_por_nombre
    FROM ecommerce_recordatorios_usuarios r
    LEFT JOIN usuarios u ON u.id = r.usuario_id
    LEFT JOIN usuarios c ON c.id = r.creado_por
    WHERE " . implode(' AND ', $where) . "
    ORDER BY FIELD(r.estado, 'pendiente', 'revisado'), COALESCE(r.fecha_recordatorio, DATE(r.fecha_creacion)) ASC, r.fecha_creacion DESC
    LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $recordatorios = [];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📝 Recordatorios</h1>
        <p class="text-muted mb-0">Módulo para cargar pendientes de revisión y seguimiento.</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="" <?= $estado_filtro === '' ? 'selected' : '' ?>>Todos</option>
                    <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="revisado" <?= $estado_filtro === 'revisado' ? 'selected' : '' ?>>Revisado</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Aplicar</button>
                <a href="recordatorios.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Cargar recordatorio</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <input type="hidden" name="accion" value="crear">
            <?php if ($is_admin): ?>
                <div class="col-md-3">
                    <label class="form-label">Para usuario</label>
                    <select name="usuario_id" class="form-select">
                        <option value="0">General</option>
                        <?php foreach ($usuarios as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-md-<?= $is_admin ? '3' : '4' ?>">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" placeholder="Ej: Revisar cotización pendiente" required>
            </div>
            <div class="col-md-<?= $is_admin ? '3' : '5' ?>">
                <label class="form-label">Descripción</label>
                <input type="text" name="descripcion" class="form-control" placeholder="Detalle opcional">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha</label>
                <input type="date" name="fecha_recordatorio" class="form-control">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-dark">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Listado</h5>
        <span class="badge bg-dark">Total: <?= count($recordatorios) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($recordatorios)): ?>
            <div class="alert alert-info mb-0">No hay recordatorios para los filtros seleccionados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Para</th>
                            <th>Título</th>
                            <th>Descripción</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Creado por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recordatorios as $r): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($r['usuario_nombre'] ?? 'General') ?></strong></td>
                                <td><?= htmlspecialchars($r['titulo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($r['descripcion'] ?? '-') ?></td>
                                <td>
                                    <?php
                                        $fecha_ref = $r['fecha_recordatorio'] ?? null;
                                        if (empty($fecha_ref)) {
                                            $fecha_ref = $r['fecha_creacion'] ?? null;
                                        }
                                    ?>
                                    <?= !empty($fecha_ref) ? date('d/m/Y', strtotime((string)$fecha_ref)) : '-' ?>
                                </td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="estado">
                                        <input type="hidden" name="recordatorio_id" value="<?= (int)$r['id'] ?>">
                                        <select name="nuevo_estado" class="form-select form-select-sm" onchange="this.form.submit()">
                                            <option value="pendiente" <?= ($r['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                            <option value="revisado" <?= ($r['estado'] ?? '') === 'revisado' ? 'selected' : '' ?>>Revisado</option>
                                        </select>
                                    </form>
                                </td>
                                <td><?= htmlspecialchars($r['creado_por_nombre'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
