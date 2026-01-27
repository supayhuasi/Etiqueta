<?php
require 'config.php';
require 'includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

// Verificar que sea admin
if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden acceder a este módulo.");
}

// Obtener mes seleccionado o usar mes actual
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$tipo_filtro = $_GET['tipo'] ?? 'todos';
$estado_filtro = $_GET['estado'] ?? 'todos';

// Obtener todos los meses disponibles
$stmt_meses = $pdo->query("SELECT DISTINCT DATE_FORMAT(fecha, '%Y-%m') as mes FROM gastos ORDER BY mes DESC");
$meses_disponibles = $stmt_meses->fetchAll(PDO::FETCH_COLUMN);

// Obtener tipos y estados para filtros
$stmt_tipos = $pdo->query("SELECT id, nombre, color FROM tipos_gastos WHERE activo = 1 ORDER BY nombre");
$tipos_gastos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

$stmt_estados = $pdo->query("SELECT id, nombre, color FROM estados_gastos WHERE activo = 1 ORDER BY nombre");
$estados_gastos = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Construir query con filtros
$query = "SELECT g.*, t.nombre as tipo_nombre, t.color as tipo_color, 
          e.nombre as estado_nombre, e.color as estado_color, u.nombre as usuario_nombre,
          emp.nombre as empleado_nombre
          FROM gastos g
          LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
          LEFT JOIN estados_gastos e ON g.estado_gasto_id = e.id
          LEFT JOIN usuarios u ON g.usuario_registra = u.id
          LEFT JOIN empleados emp ON g.empleado_id = emp.id
          WHERE DATE_FORMAT(g.fecha, '%Y-%m') = ?";

$params = [$mes_filtro];

if ($tipo_filtro !== 'todos') {
    $query .= " AND g.tipo_gasto_id = ?";
    $params[] = $tipo_filtro;
}

if ($estado_filtro !== 'todos') {
    $query .= " AND g.estado_gasto_id = ?";
    $params[] = $estado_filtro;
}

$query .= " ORDER BY g.fecha DESC, g.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales del mes
$stmt_total = $pdo->prepare("
    SELECT 
        COUNT(*) as total_gastos,
        SUM(monto) as monto_total,
        SUM(CASE WHEN estado_gasto_id IN (SELECT id FROM estados_gastos WHERE nombre = 'Pagado') THEN monto ELSE 0 END) as monto_pagado,
        SUM(CASE WHEN estado_gasto_id NOT IN (SELECT id FROM estados_gastos WHERE nombre = 'Pagado') THEN monto ELSE 0 END) as monto_pendiente
    FROM gastos
    WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
");
$stmt_total->execute([$mes_filtro]);
$totales = $stmt_total->fetch(PDO::FETCH_ASSOC);

// Gastos por tipo
$stmt_por_tipo = $pdo->prepare("
    SELECT t.nombre, t.color, COUNT(*) as cantidad, SUM(g.monto) as total
    FROM gastos g
    LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
    WHERE DATE_FORMAT(g.fecha, '%Y-%m') = ?
    GROUP BY g.tipo_gasto_id
    ORDER BY total DESC
");
$stmt_por_tipo->execute([$mes_filtro]);
$gastos_por_tipo = $stmt_por_tipo->fetchAll(PDO::FETCH_ASSOC);
?>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>💸 Administración de Gastos</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="gastos_crear.php" class="btn btn-primary">+ Nuevo Gasto</a>
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
                    <label for="mes" class="form-label">Mes</label>
                    <input type="month" name="mes" id="mes" class="form-control" value="<?= $mes_filtro ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo de Gasto</label>
                    <select name="tipo" id="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="todos">Todos los tipos</option>
                        <?php foreach ($tipos_gastos as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>" <?= $tipo_filtro == $tipo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select" onchange="this.form.submit()">
                        <option value="todos">Todos los estados</option>
                        <?php foreach ($estados_gastos as $estado): ?>
                            <option value="<?= $estado['id'] ?>" <?= $estado_filtro == $estado['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($estado['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="?mes=<?= date('Y-m') ?>" class="btn btn-secondary w-100">Mes Actual</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen de gastos del mes -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Total de Gastos</h6>
                    <h3><?= count($gastos) ?></h3>
                    <small>Monto: $<?= number_format($totales['monto_total'] ?? 0, 2, ',', '.') ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Total Invertido</h6>
                    <h3>$<?= number_format($totales['monto_total'] ?? 0, 0, ',', '.') ?></h3>
                    <small>En <?= $mes_filtro ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>Pagados</h6>
                    <h3>$<?= number_format($totales['monto_pagado'] ?? 0, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body text-center">
                    <h6>Pendientes</h6>
                    <h3>$<?= number_format($totales['monto_pendiente'] ?? 0, 0, ',', '.') ?></h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-8">
            <!-- Tabla de gastos -->
            <div class="card">
                <div class="card-header">
                    <h5>Gastos del Mes</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($gastos)): ?>
                        <p class="text-muted text-center">No hay gastos registrados para este período</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Descripción</th>
                                        <th>Beneficiario</th>
                                        <th>Monto</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($gastos as $gasto): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($gasto['fecha'])) ?></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($gasto['tipo_color'] ?? '#999') ?>">
                                                <?= htmlspecialchars($gasto['tipo_nombre'] ?? 'Sin tipo') ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars(substr($gasto['descripcion'], 0, 30)) ?></td>
                                        <td>
                                            <?php if (!empty($gasto['empleado_nombre'])): ?>
                                                <strong><?= htmlspecialchars($gasto['empleado_nombre']) ?></strong>
                                            <?php else: ?>
                                                <span class="text-muted">Sin asignar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>$<?= number_format($gasto['monto'], 2, ',', '.') ?></strong></td>
                                        <td>
                                            <span class="badge" style="background-color: <?= htmlspecialchars($gasto['estado_color'] ?? '#999') ?>">
                                                <?= htmlspecialchars($gasto['estado_nombre'] ?? 'Sin estado') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="gastos_cambiar_estado.php?id=<?= $gasto['id'] ?>" class="btn btn-info" title="Cambiar estado">✎</a>
                                                <a href="gastos_editar.php?id=<?= $gasto['id'] ?>" class="btn btn-warning" title="Editar">📝</a>
                                                <a href="gastos_eliminar.php?id=<?= $gasto['id'] ?>" class="btn btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro?')">🗑️</a>
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

        <div class="col-md-4">
            <!-- Gastos por tipo -->
            <div class="card">
                <div class="card-header">
                    <h5>Gastos por Tipo</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($gastos_por_tipo)): ?>
                        <p class="text-muted text-center">Sin datos</p>
                    <?php else: ?>
                        <?php foreach ($gastos_por_tipo as $tipo): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="badge" style="background-color: <?= htmlspecialchars($tipo['color'] ?? '#999') ?>">
                                        <?= htmlspecialchars($tipo['nombre'] ?? 'Sin nombre') ?>
                                    </span>
                                    <strong>$<?= number_format($tipo['total'] ?? 0, 0, ',', '.') ?></strong>
                                </div>
                                <small class="text-muted"><?= $tipo['cantidad'] ?> gasto(s)</small>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>

