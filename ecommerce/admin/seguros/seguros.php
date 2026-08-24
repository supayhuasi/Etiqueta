<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($can_access) || !$can_access('seguros')) {
    die("Acceso denegado. No tenés permisos para este módulo.");
}

$dias_alerta = max(1, (int)($_GET['dias'] ?? 30));
$patente_filtro = trim((string)($_GET['patente'] ?? ''));
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$estado_filtro = $_GET['estado'] ?? 'todos';

$stmt_tipos = $pdo->query("SELECT id, nombre, color FROM tipos_seguros_permisos WHERE activo = 1 ORDER BY nombre");
$tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

$query = "
    SELECT sp.*, t.nombre AS tipo_nombre, t.color AS tipo_color,
           DATEDIFF(sp.fecha_vencimiento, CURDATE()) AS dias_para_vencer
    FROM seguros_permisos sp
    LEFT JOIN tipos_seguros_permisos t ON sp.tipo_id = t.id
    WHERE 1 = 1
";
$params = [];

if ($patente_filtro !== '') {
    $query .= " AND sp.vehiculo_patente LIKE ?";
    $params[] = '%' . $patente_filtro . '%';
}

if ($tipo_filtro !== 'todos') {
    $query .= " AND sp.tipo_id = ?";
    $params[] = $tipo_filtro;
}

if ($estado_filtro === 'vencido') {
    $query .= " AND sp.fecha_vencimiento < CURDATE()";
} elseif ($estado_filtro === 'por_vencer') {
    $query .= " AND sp.fecha_vencimiento >= CURDATE() AND sp.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY)";
    $params[] = $dias_alerta;
} elseif ($estado_filtro === 'vigente') {
    $query .= " AND sp.fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL ? DAY)";
    $params[] = $dias_alerta;
}

$query .= " ORDER BY sp.fecha_vencimiento ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$registros = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totales generales (sin filtro de estado, para las tarjetas resumen)
$stmt_totales = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN fecha_vencimiento < CURDATE() THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN fecha_vencimiento >= CURDATE() AND fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS por_vencer,
        SUM(CASE WHEN fecha_vencimiento > DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS vigentes
    FROM seguros_permisos
");
$stmt_totales->execute([$dias_alerta, $dias_alerta]);
$totales = $stmt_totales->fetch(PDO::FETCH_ASSOC);

function seguros_estado_badge(int $dias): array
{
    if ($dias < 0) {
        return ['danger', 'Vencido'];
    }
    if ($dias <= 30) {
        return ['warning', 'Por vencer'];
    }
    return ['success', 'Vigente'];
}
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-1">🚗 Seguros y Permisos</h2>
            <p class="text-muted mb-0">Control de vigencia de seguros y permisos por vehículo</p>
        </div>
        <div class="col-md-6 text-md-end mt-3 mt-md-0">
            <a href="tipos_seguros.php" class="btn btn-outline-secondary">⚙️ Tipos</a>
            <a href="seguros_crear.php" class="btn btn-primary">+ Nuevo Registro</a>
        </div>
    </div>

    <!-- Resumen -->
    <div class="row mb-4 g-3">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body text-center">
                    <h6>Total registrados</h6>
                    <h3><?= (int)($totales['total'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body text-center">
                    <h6>Vigentes</h6>
                    <h3><?= (int)($totales['vigentes'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body text-center">
                    <h6>Por vencer (<?= $dias_alerta ?> días)</h6>
                    <h3><?= (int)($totales['por_vencer'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body text-center">
                    <h6>Vencidos</h6>
                    <h3><?= (int)($totales['vencidos'] ?? 0) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtros</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="patente" class="form-label">Patente / Vehículo</label>
                    <input type="text" name="patente" id="patente" class="form-control" value="<?= htmlspecialchars($patente_filtro) ?>" placeholder="Ej: AB123CD">
                </div>
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select name="tipo" id="tipo" class="form-select">
                        <option value="todos">Todos los tipos</option>
                        <?php foreach ($tipos as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>" <?= $tipo_filtro == $tipo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select">
                        <option value="todos" <?= $estado_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="vigente" <?= $estado_filtro === 'vigente' ? 'selected' : '' ?>>Vigente</option>
                        <option value="por_vencer" <?= $estado_filtro === 'por_vencer' ? 'selected' : '' ?>>Por vencer</option>
                        <option value="vencido" <?= $estado_filtro === 'vencido' ? 'selected' : '' ?>>Vencido</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="dias" class="form-label">Alertar dentro de (días)</label>
                    <input type="number" name="dias" id="dias" class="form-control" min="1" value="<?= $dias_alerta ?>">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card">
        <div class="card-header">
            <h5>Registros</h5>
        </div>
        <div class="card-body">
            <?php if (empty($registros)): ?>
                <p class="text-muted text-center">No hay registros para los filtros seleccionados</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Patente</th>
                                <th>Vehículo</th>
                                <th>Tipo</th>
                                <th>Número</th>
                                <th>Entidad</th>
                                <th>Vence</th>
                                <th>Estado</th>
                                <th>Adjunto</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registros as $r): ?>
                                <?php
                                    $dias = (int)$r['dias_para_vencer'];
                                    [$badgeClase, $badgeTexto] = seguros_estado_badge($dias);
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($r['vehiculo_patente']) ?></strong></td>
                                    <td><?= htmlspecialchars($r['vehiculo_descripcion'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($r['tipo_color'] ?? '#999') ?>">
                                            <?= htmlspecialchars($r['tipo_nombre'] ?? 'Sin tipo') ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($r['numero'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($r['entidad'] ?? '-') ?></td>
                                    <td><?= date('d/m/Y', strtotime($r['fecha_vencimiento'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $badgeClase ?>"><?= $badgeTexto ?></span>
                                        <div class="small text-muted">
                                            <?php if ($dias < 0): ?>
                                                Venció hace <?= abs($dias) ?> día(s)
                                            <?php elseif ($dias === 0): ?>
                                                Vence hoy
                                            <?php else: ?>
                                                En <?= $dias ?> día(s)
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['archivo'])): ?>
                                            <a href="seguros_archivo.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank" title="Ver adjunto">📎 Ver</a>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin adjunto</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="seguros_editar.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-warning" title="Editar">📝</a>
                                            <a href="seguros_crear.php?renovar_id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-success" title="Renovar (crea un registro nuevo a partir de este)">🔄</a>
                                            <a href="seguros_eliminar.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro?')">🗑️</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
