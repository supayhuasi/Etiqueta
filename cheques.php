<?php
require 'config.php';
require 'includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

// Obtener mes seleccionado o usar mes actual
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$filtro_estado = $_GET['estado'] ?? 'todos'; // todos, pagados, pendientes

// Obtener todos los meses disponibles con cheques
$stmt_meses = $pdo->query("
    SELECT DISTINCT mes_emision 
    FROM cheques 
    ORDER BY mes_emision DESC
");
$meses_disponibles = $stmt_meses->fetchAll(PDO::FETCH_COLUMN);

// Construir query con filtros
$query = "
    SELECT * FROM cheques
    WHERE mes_emision = ?
";

$params = [$mes_filtro];

if ($filtro_estado === 'pagados') {
    $query .= " AND pagado = 1";
} elseif ($filtro_estado === 'pendientes') {
    $query .= " AND pagado = 0";
}

$query .= " ORDER BY fecha_emision DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$cheques = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales del mes
$stmt_total = $pdo->prepare("
    SELECT 
        COUNT(*) as total_cheques,
        SUM(monto) as monto_total,
        SUM(CASE WHEN pagado = 1 THEN monto ELSE 0 END) as monto_pagado,
        SUM(CASE WHEN pagado = 0 THEN monto ELSE 0 END) as monto_pendiente,
        SUM(CASE WHEN pagado = 1 THEN 1 ELSE 0 END) as cheques_pagados,
        SUM(CASE WHEN pagado = 0 THEN 1 ELSE 0 END) as cheques_pendientes
    FROM cheques
    WHERE mes_emision = ?
");
$stmt_total->execute([$mes_filtro]);
$totales = $stmt_total->fetch(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Administración de Cheques</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="cheques_crear.php" class="btn btn-primary">+ Nuevo Cheque</a>
        </div>
    </div>

    <!-- Filtro por mes y estado -->
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
                    <label for="estado" class="form-label">Estado</label>
                    <select name="estado" id="estado" class="form-select" onchange="this.form.submit()">
                        <option value="todos" <?= $filtro_estado === 'todos' ? 'selected' : '' ?>>Todos</option>
                        <option value="pagados" <?= $filtro_estado === 'pagados' ? 'selected' : '' ?>>Pagados</option>
                        <option value="pendientes" <?= $filtro_estado === 'pendientes' ? 'selected' : '' ?>>Pendientes</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <a href="?mes=<?= date('Y-m') ?>" class="btn btn-secondary w-100">Mes Actual</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen de cheques del mes -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Resumen de Cheques - Mes: <strong><?= $mes_filtro ?></strong></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Total de Cheques</h6>
                                <h3 class="text-primary"><?= $totales['total_cheques'] ?? 0 ?></h3>
                                <small class="text-muted">Monto Total: $<?= number_format($totales['monto_total'] ?? 0, 2, ',', '.') ?></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Pagados</h6>
                                <h3 class="text-success"><?= $totales['cheques_pagados'] ?? 0 ?></h3>
                                <small class="text-muted">$<?= number_format($totales['monto_pagado'] ?? 0, 2, ',', '.') ?></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Pendientes</h6>
                                <h3 class="text-danger"><?= $totales['cheques_pendientes'] ?? 0 ?></h3>
                                <small class="text-muted">$<?= number_format($totales['monto_pendiente'] ?? 0, 2, ',', '.') ?></small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>% Pagado</h6>
                                <?php $porcentaje = $totales['monto_total'] > 0 ? round(($totales['monto_pagado'] / $totales['monto_total']) * 100, 1) : 0; ?>
                                <h3 class="text-info"><?= $porcentaje ?>%</h3>
                                <div class="progress mt-2" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $porcentaje ?>%;" aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de cheques -->
    <div class="card">
        <div class="card-header">
            <h5>Cheques del Mes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($cheques)): ?>
                <p class="text-muted text-center">No hay cheques registrados para este período</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>N° Cheque</th>
                                <th>Beneficiario</th>
                                <th>Banco</th>
                                <th>Monto</th>
                                <th>Fecha Emisión</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cheques as $cheque): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cheque['numero_cheque']) ?></strong></td>
                                <td><?= htmlspecialchars($cheque['beneficiario']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($cheque['banco']) ?></span>
                                </td>
                                <td>
                                    <strong>$<?= number_format($cheque['monto'], 2, ',', '.') ?></strong>
                                </td>
                                <td><?= date('d/m/Y', strtotime($cheque['fecha_emision'])) ?></td>
                                <td>
                                    <?php if ($cheque['pagado']): ?>
                                        <span class="badge bg-success">✓ Pagado</span>
                                        <br><small class="text-muted"><?= date('d/m/Y', strtotime($cheque['fecha_pago'])) ?></small>
                                    <?php else: ?>
                                        <span class="badge bg-warning">⏳ Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (!$cheque['pagado']): ?>
                                            <a href="cheques_pagar.php?id=<?= $cheque['id'] ?>" class="btn btn-success" title="Marcar como pagado">💰</a>
                                        <?php endif; ?>
                                        <a href="cheques_editar.php?id=<?= $cheque['id'] ?>" class="btn btn-warning" title="Editar">✎</a>
                                        <a href="cheques_eliminar.php?id=<?= $cheque['id'] ?>&mes=<?= $mes_filtro ?>" class="btn btn-danger" title="Eliminar" onclick="return confirm('¿Estás seguro?')">🗑️</a>
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

<?php require 'includes/footer.php'; ?>
