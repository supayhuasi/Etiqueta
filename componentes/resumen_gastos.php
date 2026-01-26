<?php
// Este componente puede incluirse en dashboard.php
// Uso: <?php include 'componentes/resumen_gastos.php'; ?>

require_once __DIR__ . '/../config.php';

// Gastos del mes actual
$mes_actual = date('Y-m');
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_gastos,
        SUM(monto) as monto_total,
        SUM(CASE WHEN estado_gasto_id IN (SELECT id FROM estados_gastos WHERE nombre = 'Pagado') THEN monto ELSE 0 END) as monto_pagado
    FROM gastos
    WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
");
$stmt->execute([$mes_actual]);
$resumen = $stmt->fetch(PDO::FETCH_ASSOC);

// Top 5 tipos de gastos
$stmt = $pdo->prepare("
    SELECT t.nombre, t.color, COUNT(*) as cantidad, SUM(g.monto) as total
    FROM gastos g
    LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
    WHERE DATE_FORMAT(g.fecha, '%Y-%m') = ?
    GROUP BY g.tipo_gasto_id
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([$mes_actual]);
$top_tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Últimos 5 gastos
$stmt = $pdo->prepare("
    SELECT g.*, t.nombre as tipo_nombre, e.nombre as estado_nombre
    FROM gastos g
    LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
    LEFT JOIN estados_gastos e ON g.estado_gasto_id = e.id
    WHERE DATE_FORMAT(g.fecha, '%Y-%m') = ?
    ORDER BY g.fecha DESC, g.id DESC
    LIMIT 5
");
$stmt->execute([$mes_actual]);
$ultimos_gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h4>💸 Resumen de Gastos - <?= date('F Y', strtotime($mes_actual)) ?></h4>
    </div>
</div>

<!-- Cards resumen -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h6>Total de Gastos</h6>
                <h3><?= $resumen['total_gastos'] ?? 0 ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h6>Total Invertido</h6>
                <h3>$<?= number_format($resumen['monto_total'] ?? 0, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h6>Ya Pagado</h6>
                <h3>$<?= number_format($resumen['monto_pagado'] ?? 0, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h6>Pendiente</h6>
                <h3>$<?= number_format(($resumen['monto_total'] - $resumen['monto_pagado']) ?? 0, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Top tipos -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>Top Tipos de Gastos</h6>
            </div>
            <div class="card-body">
                <?php if (empty($top_tipos)): ?>
                    <p class="text-muted">Sin gastos</p>
                <?php else: ?>
                    <?php foreach ($top_tipos as $tipo): ?>
                        <div class="mb-2">
                            <div class="d-flex justify-content-between">
                                <span class="badge" style="background-color: <?= htmlspecialchars($tipo['color'] ?? '#999') ?>">
                                    <?= htmlspecialchars($tipo['nombre'] ?? 'Sin nombre') ?>
                                </span>
                                <strong>$<?= number_format($tipo['total'] ?? 0, 0, ',', '.') ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <hr>
                <a href="gastos.php" class="btn btn-sm btn-primary">Ver todos</a>
            </div>
        </div>
    </div>

    <!-- Últimos gastos -->
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6>Últimos Gastos</h6>
            </div>
            <div class="card-body">
                <?php if (empty($ultimos_gastos)): ?>
                    <p class="text-muted">Sin gastos</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_gastos as $gasto): ?>
                                <tr>
                                    <td><?= date('d/m', strtotime($gasto['fecha'])) ?></td>
                                    <td>
                                        <span class="badge" style="background-color: #999; font-size: 10px;">
                                            <?= htmlspecialchars(substr($gasto['tipo_nombre'] ?? 'Sin tipo', 0, 10)) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($gasto['monto'], 0, ',', '.') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <hr>
                <a href="gastos_crear.php" class="btn btn-sm btn-success">Nuevo gasto</a>
            </div>
        </div>
    </div>
</div>
