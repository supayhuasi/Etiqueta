<?php
require 'includes/header.php';

// Producto más vendido
$stmt = $pdo->query("
    SELECT 
        p.nombre,
        p.imagen,
        SUM(pi.cantidad) as total_vendido,
        SUM(pi.subtotal) as monto_total,
        COUNT(DISTINCT pi.pedido_id) as num_pedidos
    FROM ecommerce_pedido_items pi
    JOIN ecommerce_productos p ON pi.producto_id = p.id
    JOIN ecommerce_pedidos ped ON pi.pedido_id = ped.id
    WHERE ped.estado NOT IN ('cancelado')
    GROUP BY pi.producto_id, p.nombre, p.imagen
    ORDER BY total_vendido DESC
    LIMIT 5
");
$productos_mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Vendedor con más ventas (por cotizaciones convertidas)
$stmt = $pdo->query("
    SELECT 
        u.nombre as vendedor,
        COUNT(c.id) as total_cotizaciones,
        SUM(CASE WHEN c.estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
        SUM(CASE WHEN c.estado = 'convertida' THEN c.total ELSE 0 END) as monto_convertido,
        SUM(c.total) as monto_total_cotizaciones
    FROM ecommerce_cotizaciones c
    JOIN usuarios u ON c.creado_por = u.id
    GROUP BY c.creado_por, u.nombre
    ORDER BY cotizaciones_convertidas DESC, monto_convertido DESC
    LIMIT 5
");
$vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cliente que más compró
$stmt = $pdo->query("
    SELECT 
        c.nombre,
        c.email,
        COUNT(p.id) as total_pedidos,
        SUM(p.total) as monto_total,
        MAX(p.fecha_creacion) as ultima_compra
    FROM ecommerce_pedidos p
    JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.estado NOT IN ('cancelado')
    GROUP BY p.cliente_id, c.nombre, c.email
    ORDER BY monto_total DESC
    LIMIT 5
");
$mejores_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas generales
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_pedidos,
        SUM(total) as monto_total_pedidos,
        AVG(total) as promedio_pedido
    FROM ecommerce_pedidos
    WHERE estado NOT IN ('cancelado')
");
$stats_pedidos = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_cotizaciones,
        SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
        SUM(total) as monto_total_cotizaciones
    FROM ecommerce_cotizaciones
");
$stats_cotizaciones = $stmt->fetch(PDO::FETCH_ASSOC);

// Ventas del mes actual
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as pedidos_mes,
        SUM(total) as monto_mes
    FROM ecommerce_pedidos
    WHERE MONTH(fecha_creacion) = MONTH(CURRENT_DATE())
    AND YEAR(fecha_creacion) = YEAR(CURRENT_DATE())
    AND estado NOT IN ('cancelado')
");
$stats_mes = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1>📊 Tablero de Control</h1>
        <p class="text-muted">Estadísticas y métricas de ventas</p>
    </div>
</div>

<!-- Estadísticas generales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-2">📦 Total Pedidos</h6>
                <h3 class="mb-0"><?= number_format($stats_pedidos['total_pedidos']) ?></h3>
                <small>Monto: $<?= number_format($stats_pedidos['monto_total_pedidos'], 2) ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-2">💰 Ventas del Mes</h6>
                <h3 class="mb-0">$<?= number_format($stats_mes['monto_mes'], 2) ?></h3>
                <small><?= $stats_mes['pedidos_mes'] ?> pedidos</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="mb-2">📋 Cotizaciones</h6>
                <h3 class="mb-0"><?= number_format($stats_cotizaciones['total_cotizaciones']) ?></h3>
                <small><?= $stats_cotizaciones['cotizaciones_convertidas'] ?> convertidas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="mb-2">📊 Ticket Promedio</h6>
                <h3 class="mb-0">$<?= number_format($stats_pedidos['promedio_pedido'], 2) ?></h3>
                <small>por pedido</small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Productos más vendidos -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏆 Productos Más Vendidos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_mas_vendidos)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($productos_mas_vendidos as $i => $prod): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-primary rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format($prod['total_vendido']) ?> unidades vendidas<br>
                                            Monto: $<?= number_format($prod['monto_total'], 2) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Vendedores con más ventas -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">🎯 Mejores Vendedores</h5>
            </div>
            <div class="card-body">
                <?php if (empty($vendedores)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($vendedores as $i => $vendedor): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-success rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0">👤 <?= htmlspecialchars($vendedor['vendedor']) ?></h6>
                                        <small class="text-muted">
                                            <?= $vendedor['total_cotizaciones'] ?> cotizaciones (<?= $vendedor['cotizaciones_convertidas'] ?> convertidas)<br>
                                            Monto convertido: $<?= number_format($vendedor['monto_convertido'], 2) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mejores clientes -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">⭐ Mejores Clientes</h5>
            </div>
            <div class="card-body">
                <?php if (empty($mejores_clientes)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($mejores_clientes as $i => $cliente): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-info rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($cliente['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= $cliente['total_pedidos'] ?> pedidos<br>
                                            Total comprado: $<?= number_format($cliente['monto_total'], 2) ?><br>
                                            Última compra: <?= date('d/m/Y', strtotime($cliente['ultima_compra'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Gráfico de resumen -->
<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">📈 Resumen de Conversión</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3 class="text-primary"><?= number_format($stats_cotizaciones['total_cotizaciones']) ?></h3>
                        <p class="text-muted mb-0">Total Cotizaciones</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-success"><?= number_format($stats_cotizaciones['cotizaciones_convertidas']) ?></h3>
                        <p class="text-muted mb-0">Cotizaciones Convertidas</p>
                    </div>
                    <div class="col-md-4">
                        <?php 
                        $tasa_conversion = $stats_cotizaciones['total_cotizaciones'] > 0 
                            ? ($stats_cotizaciones['cotizaciones_convertidas'] / $stats_cotizaciones['total_cotizaciones']) * 100 
                            : 0;
                        ?>
                        <h3 class="text-warning"><?= number_format($tasa_conversion, 1) ?>%</h3>
                        <p class="text-muted mb-0">Tasa de Conversión</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
