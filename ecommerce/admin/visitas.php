<?php
require 'includes/header.php';

$filtro_estado = trim($_GET['estado'] ?? '');
$filtro_fecha_desde = trim($_GET['fecha_desde'] ?? '');
$filtro_fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

$estados_validos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];
$mensaje = '';
$error = '';

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_visitas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        descripcion TEXT NULL,
        cliente_nombre VARCHAR(150) NULL,
        telefono VARCHAR(60) NULL,
        direccion VARCHAR(255) NULL,
        fecha_visita DATE NOT NULL,
        hora_visita TIME NULL,
        estado ENUM('pendiente','en_proceso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        creado_por INT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fecha_visita (fecha_visita),
        INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $tiene_hora_visita = false;
    $stmt_col = $pdo->query("SHOW COLUMNS FROM ecommerce_visitas LIKE 'hora_visita'");
    if ($stmt_col && $stmt_col->fetch(PDO::FETCH_ASSOC)) {
        $tiene_hora_visita = true;
    }

    if (!$tiene_hora_visita) {
        $pdo->exec("ALTER TABLE ecommerce_visitas ADD COLUMN hora_visita TIME NULL AFTER fecha_visita");
    }
} catch (Throwable $e) {
    $error = 'No se pudo preparar la tabla de visitas: ' . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['action'] ?? '';

    try {
        if ($accion === 'crear_visita') {
            $titulo = trim($_POST['titulo'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');
            $fecha_visita = trim($_POST['fecha_visita'] ?? '');
            $hora_visita = trim($_POST['hora_visita'] ?? '');

            if ($titulo === '' || $fecha_visita === '') {
                throw new Exception('El título y la fecha de visita son obligatorios.');
            }

            if ($hora_visita !== '' && !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $hora_visita)) {
                throw new Exception('La hora de visita es inválida.');
            }

            $hora_visita_db = $hora_visita !== '' ? ($hora_visita . ':00') : null;

            $creado_por = null;
            if (isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
                $creado_por = (int)$_SESSION['user']['id'];
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_visitas
                (titulo, descripcion, cliente_nombre, telefono, direccion, fecha_visita, hora_visita, estado, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente', ?)");
            $stmt->execute([
                $titulo,
                $descripcion !== '' ? $descripcion : null,
                $cliente_nombre !== '' ? $cliente_nombre : null,
                $telefono !== '' ? $telefono : null,
                $direccion !== '' ? $direccion : null,
                $fecha_visita,
                $hora_visita_db,
                $creado_por
            ]);

            $mensaje = 'Visita cargada correctamente.';
        }

        if ($accion === 'cambiar_estado') {
            $visita_id = (int)($_POST['visita_id'] ?? 0);
            $nuevo_estado = trim($_POST['nuevo_estado'] ?? '');

            if ($visita_id <= 0 || !in_array($nuevo_estado, $estados_validos, true)) {
                throw new Exception('Datos inválidos para actualizar estado.');
            }

            $stmt = $pdo->prepare("UPDATE ecommerce_visitas SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $visita_id]);

            $mensaje = 'Estado de visita actualizado.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$where = [];
$params = [];

if ($filtro_estado !== '' && in_array($filtro_estado, $estados_validos, true)) {
    $where[] = 'v.estado = ?';
    $params[] = $filtro_estado;
}

if ($filtro_fecha_desde !== '') {
    $where[] = 'v.fecha_visita >= ?';
    $params[] = $filtro_fecha_desde;
}

if ($filtro_fecha_hasta !== '') {
    $where[] = 'v.fecha_visita <= ?';
    $params[] = $filtro_fecha_hasta;
}

$sql = "
    SELECT v.*
    FROM ecommerce_visitas v
";

if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= "
    ORDER BY
        FIELD(v.estado, 'pendiente', 'en_proceso', 'completada', 'cancelada'),
        v.fecha_visita ASC,
    COALESCE(v.hora_visita, '23:59:59') ASC,
        v.id DESC
";

$visitas = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    if ($error === '') {
        $error = 'No se pudieron cargar las visitas: ' . $e->getMessage();
    }
}

$resumen = [
    'pendiente' => 0,
    'en_proceso' => 0,
    'completada' => 0,
    'cancelada' => 0,
];

try {
    $stmt = $pdo->query("SELECT estado, COUNT(*) AS cantidad FROM ecommerce_visitas GROUP BY estado");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        if (isset($resumen[$fila['estado']])) {
            $resumen[$fila['estado']] = (int)$fila['cantidad'];
        }
    }
} catch (Throwable $e) {
    // Mantener la página funcional incluso si falla el resumen.
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Visitas</h1>
        <p class="text-muted mb-0">Módulo tipo To Do para organizar visitas por fecha y estado.</p>
    </div>
    <a href="instalaciones.php" class="btn btn-outline-secondary">Ver instalaciones</a>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="small text-muted">Pendientes</div>
                <div class="h4 mb-0"><?= (int)$resumen['pendiente'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <div class="small text-muted">En proceso</div>
                <div class="h4 mb-0"><?= (int)$resumen['en_proceso'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <div class="small text-muted">Completadas</div>
                <div class="h4 mb-0"><?= (int)$resumen['completada'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary">
            <div class="card-body text-center">
                <div class="small text-muted">Canceladas</div>
                <div class="h4 mb-0"><?= (int)$resumen['cancelada'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">Cargar nueva visita</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="crear_visita">

            <div class="col-md-6">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" maxlength="180" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha de visita</label>
                <input type="date" name="fecha_visita" class="form-control" value="<?= date('Y-m-d') ?>" required>
            </div>

            <div class="col-md-3">
                <label class="form-label">Hora de visita</label>
                <input type="time" name="hora_visita" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <input type="text" name="cliente_nombre" class="form-control" maxlength="150">
            </div>

            <div class="col-md-4">
                <label class="form-label">Teléfono</label>
                <input type="text" name="telefono" class="form-control" maxlength="60">
            </div>

            <div class="col-md-8">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" class="form-control" maxlength="255">
            </div>

            <div class="col-12">
                <label class="form-label">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3" placeholder="Detalles de la visita..."></textarea>
            </div>

            <div class="col-12">
                <button type="submit" class="btn btn-success">Guardar visita</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $filtro_estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="en_proceso" <?= $filtro_estado === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
                    <option value="completada" <?= $filtro_estado === 'completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($filtro_fecha_desde) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($filtro_fecha_hasta) ?>">
            </div>

            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="visitas.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tarea / Visita</th>
                        <th>Cliente</th>
                        <th>Contacto</th>
                        <th>Estado</th>
                        <th style="width: 280px;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($visitas)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No hay visitas cargadas para los filtros elegidos.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($visitas as $visita): ?>
                            <?php
                            $estado = $visita['estado'];
                            $badge = 'secondary';
                            if ($estado === 'pendiente') {
                                $badge = 'warning text-dark';
                            } elseif ($estado === 'en_proceso') {
                                $badge = 'primary';
                            } elseif ($estado === 'completada') {
                                $badge = 'success';
                            }
                            ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars(date('d/m/Y', strtotime($visita['fecha_visita']))) ?>
                                    <?php if (!empty($visita['hora_visita'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars(date('H:i', strtotime($visita['hora_visita']))) ?> hs</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($visita['titulo']) ?></div>
                                    <?php if (!empty($visita['descripcion'])): ?>
                                        <div class="small text-muted"><?= nl2br(htmlspecialchars($visita['descripcion'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($visita['direccion'])): ?>
                                        <div class="small text-muted">📍 <?= htmlspecialchars($visita['direccion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($visita['cliente_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($visita['telefono'] ?? '-') ?></td>
                                <td>
                                    <span class="badge bg-<?= $badge ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', ucfirst($estado))) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if ($estado !== 'pendiente'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="visita_id" value="<?= (int)$visita['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="pendiente">
                                                <button class="btn btn-sm btn-outline-warning" type="submit">Pendiente</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($estado !== 'en_proceso'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="visita_id" value="<?= (int)$visita['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="en_proceso">
                                                <button class="btn btn-sm btn-outline-primary" type="submit">En proceso</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($estado !== 'completada'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="visita_id" value="<?= (int)$visita['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="completada">
                                                <button class="btn btn-sm btn-outline-success" type="submit">Completar</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($estado !== 'cancelada'): ?>
                                            <form method="POST">
                                                <input type="hidden" name="action" value="cambiar_estado">
                                                <input type="hidden" name="visita_id" value="<?= (int)$visita['id'] ?>">
                                                <input type="hidden" name="nuevo_estado" value="cancelada">
                                                <button class="btn btn-sm btn-outline-secondary" type="submit">Cancelar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
