<?php
require 'includes/header.php';

$errores = [];

function tabla_existe($pdo, $tabla) {
    // SHOW TABLES LIKE ? no se puede preparar como statement nativo en este servidor
    // (PDO::ATTR_EMULATE_PREPARES está desactivado en config.php); information_schema sí soporta placeholders.
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tabla]);
    return $stmt->rowCount() > 0;
}

// Producto más vendido
$productos_mas_vendidos = [];
try {
    if (tabla_existe($pdo, 'ecommerce_pedido_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_pedidos')) {
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
    }
} catch (Throwable $e) {
    $errores[] = "Productos más vendidos: " . $e->getMessage();
}

// Vendedor con más ventas (por cotizaciones convertidas)
$vendedores = [];
try {
    if (tabla_existe($pdo, 'ecommerce_cotizaciones') && tabla_existe($pdo, 'usuarios')) {
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
    }
} catch (Throwable $e) {
    $errores[] = "Mejores vendedores: " . $e->getMessage();
}

// Cliente que más compró
$mejores_clientes = [];
try {
    if (tabla_existe($pdo, 'ecommerce_pedidos') && tabla_existe($pdo, 'ecommerce_clientes')) {
        $stmt = $pdo->query("
            SELECT 
                c.nombre,
                c.email,
                COUNT(p.id) as total_pedidos,
                SUM(p.total) as monto_total,
                MAX(p.fecha_pedido) as ultima_compra
            FROM ecommerce_pedidos p
            JOIN ecommerce_clientes c ON p.cliente_id = c.id
            WHERE p.estado NOT IN ('cancelado')
            GROUP BY p.cliente_id, c.nombre, c.email
            ORDER BY monto_total DESC
            LIMIT 5
        ");
        $mejores_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = "Mejores clientes: " . $e->getMessage();
}

// Estadísticas generales
$stats_pedidos = ['total_pedidos' => 0, 'monto_total_pedidos' => 0, 'promedio_pedido' => 0];
$stats_cotizaciones = ['total_cotizaciones' => 0, 'cotizaciones_convertidas' => 0, 'monto_total_cotizaciones' => 0];
$stats_mes = ['pedidos_mes' => 0, 'monto_mes' => 0];

try {
    if (tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_pedidos,
                SUM(total) as monto_total_pedidos,
                AVG(total) as promedio_pedido
            FROM ecommerce_pedidos
            WHERE estado NOT IN ('cancelado')
        ");
        $stats_pedidos = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_pedidos;

        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as pedidos_mes,
                SUM(total) as monto_mes
            FROM ecommerce_pedidos
            WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE())
            AND YEAR(fecha_pedido) = YEAR(CURRENT_DATE())
            AND estado NOT IN ('cancelado')
        ");
        $stats_mes = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_mes;
    }
} catch (Throwable $e) {
    $errores[] = "Estadísticas de pedidos: " . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_cotizaciones')) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_cotizaciones,
                SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
                SUM(total) as monto_total_cotizaciones
            FROM ecommerce_cotizaciones
        ");
        $stats_cotizaciones = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_cotizaciones;
    }
} catch (Throwable $e) {
    $errores[] = "Estadísticas de cotizaciones: " . $e->getMessage();
}

$anio_seleccionado = (int)($_GET['anio'] ?? date('Y'));
if ($anio_seleccionado < 2000 || $anio_seleccionado > 2100) {
    $anio_seleccionado = (int)date('Y');
}

$ventas_anio = 0.0;
$compras_anio = 0.0;
$gastos_anio = 0.0;
$sueldos_anio = 0.0;
$ingresos_periodo = 0.0;
$egresos_periodo = 0.0;
$neto_periodo = 0.0;
$pasivos_totales = 0.0;
$pasivos_gastos = 0.0;
$pasivos_sueldos = 0.0;
$pasivos_compras = 0.0;
$productos_ventas_ranking = [];
$productos_compra_ranking = [];
$proveedores_ranking = [];
$gastos_ranking = [];
$labels_meses = [];
$ventas_mensuales = [];
$compras_mensuales = [];
$gastos_mensuales = [];
$sueldos_mensuales = [];
$egresos_mensuales = [];

try {
    if (tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE YEAR(fecha_pedido) = {$anio_seleccionado} AND estado != 'cancelado'");
        $ventas_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Ventas anuales: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(ci.subtotal),0) as total FROM ecommerce_compra_items ci JOIN ecommerce_compras c ON c.id = ci.compra_id WHERE YEAR(c.fecha_compra) = {$anio_seleccionado}");
        $compras_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Compras anuales: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'gastos')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(monto),0) as total FROM gastos WHERE YEAR(fecha) = {$anio_seleccionado}");
        $gastos_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Gastos anuales: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'pagos_sueldos')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE DATE_FORMAT(fecha_pago, '%Y') = '{$anio_seleccionado}' OR DATE_FORMAT(CAST(mes_pago AS CHAR), '%Y') = '{$anio_seleccionado}' OR mes_pago LIKE '{$anio_seleccionado}-%'");
        $sueldos_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Sueldos anuales: ' . $e->getMessage();
}

$ingresos_periodo = $ventas_anio;
$egresos_periodo = $gastos_anio + $compras_anio + $sueldos_anio;
$neto_periodo = $ingresos_periodo - $egresos_periodo;

try {
    if (tabla_existe($pdo, 'gastos') && tabla_existe($pdo, 'estados_gastos')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(g.monto),0) as total FROM gastos g LEFT JOIN estados_gastos e ON e.id = g.estado_gasto_id WHERE e.nombre IS NULL OR LOWER(e.nombre) <> 'pagado' AND YEAR(g.fecha) = {$anio_seleccionado}");
        $pasivos_gastos = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_gastos = 0.0;
}

try {
    if (tabla_existe($pdo, 'pagos_sueldos')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(CASE WHEN sueldo_total > 0 THEN (sueldo_total - monto_pagado) ELSE 0 END),0) as total FROM pagos_sueldos WHERE (DATE_FORMAT(fecha_pago, '%Y') = '{$anio_seleccionado}' OR DATE_FORMAT(CAST(mes_pago AS CHAR), '%Y') = '{$anio_seleccionado}' OR mes_pago LIKE '{$anio_seleccionado}-%')");
        $pasivos_sueldos = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_sueldos = 0.0;
}

try {
    if (tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->query("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_compras WHERE YEAR(fecha_compra) = {$anio_seleccionado} AND estado NOT IN ('cancelado', 'pagada', 'cerrada')");
        $pasivos_compras = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_compras = 0.0;
}

$pasivos_totales = $pasivos_gastos + $pasivos_sueldos + $pasivos_compras;

try {
    if (tabla_existe($pdo, 'ecommerce_pedido_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->query("SELECT p.nombre, SUM(pi.cantidad) as cantidad, SUM(pi.subtotal) as total FROM ecommerce_pedido_items pi JOIN ecommerce_productos p ON p.id = pi.producto_id JOIN ecommerce_pedidos ped ON ped.id = pi.pedido_id WHERE ped.estado != 'cancelado' AND YEAR(ped.fecha_pedido) = {$anio_seleccionado} GROUP BY pi.producto_id, p.nombre ORDER BY cantidad DESC, total DESC LIMIT 5");
        $productos_ventas_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking productos vendidos: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->query("SELECT p.nombre, SUM(ci.cantidad) as cantidad, SUM(ci.subtotal) as total FROM ecommerce_compra_items ci JOIN ecommerce_productos p ON p.id = ci.producto_id JOIN ecommerce_compras c ON c.id = ci.compra_id WHERE YEAR(c.fecha_compra) = {$anio_seleccionado} GROUP BY ci.producto_id, p.nombre ORDER BY cantidad DESC, total DESC LIMIT 5");
        $productos_compra_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking productos comprados: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compras') && tabla_existe($pdo, 'ecommerce_proveedores')) {
        $stmt = $pdo->query("SELECT pr.nombre, SUM(c.total) as total, COUNT(c.id) as compras FROM ecommerce_compras c JOIN ecommerce_proveedores pr ON pr.id = c.proveedor_id WHERE YEAR(c.fecha_compra) = {$anio_seleccionado} GROUP BY c.proveedor_id, pr.nombre ORDER BY total DESC LIMIT 5");
        $proveedores_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking proveedores: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'gastos') && tabla_existe($pdo, 'tipos_gastos')) {
        $stmt = $pdo->query("SELECT tg.nombre, SUM(g.monto) as total, COUNT(g.id) as cantidad FROM gastos g JOIN tipos_gastos tg ON tg.id = g.tipo_gasto_id WHERE YEAR(g.fecha) = {$anio_seleccionado} GROUP BY g.tipo_gasto_id, tg.nombre ORDER BY total DESC LIMIT 5");
        $gastos_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking gastos: ' . $e->getMessage();
}

$meses_names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
for ($i = 1; $i <= 12; $i++) {
    $labels_meses[] = $meses_names[$i - 1];

    $month_start = sprintf('%04d-%02d-01', $anio_seleccionado, $i);
    $next_month = new DateTime($month_start);
    $next_month->modify('first day of next month');
    $month_end = (clone $next_month)->modify('-1 second');
    $inicio = $month_start . ' 00:00:00';
    $fin = $month_end->format('Y-m-d H:i:s');

    try {
        if (tabla_existe($pdo, 'ecommerce_pedidos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'");
            $stmt->execute([$inicio, $fin]);
            $ventas_mensuales[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } else {
            $ventas_mensuales[] = 0;
        }
    } catch (Throwable $e) {
        $ventas_mensuales[] = 0;
    }

    try {
        if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_compras')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(ci.subtotal),0) as total FROM ecommerce_compra_items ci JOIN ecommerce_compras c ON c.id = ci.compra_id WHERE c.fecha_compra BETWEEN ? AND ?");
            $stmt->execute([$inicio, $fin]);
            $compras_mensuales[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } else {
            $compras_mensuales[] = 0;
        }
    } catch (Throwable $e) {
        $compras_mensuales[] = 0;
    }

    try {
        if (tabla_existe($pdo, 'gastos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM gastos WHERE fecha BETWEEN ? AND ?");
            $stmt->execute([$inicio, $fin]);
            $gastos_mensuales[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } else {
            $gastos_mensuales[] = 0;
        }
    } catch (Throwable $e) {
        $gastos_mensuales[] = 0;
    }

    try {
        if (tabla_existe($pdo, 'pagos_sueldos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE mes_pago = ? OR DATE_FORMAT(fecha_pago, '%Y-%m') = ?");
            $mes_key = $anio_seleccionado . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
            $stmt->execute([$mes_key, $mes_key]);
            $sueldos_mensuales[] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        } else {
            $sueldos_mensuales[] = 0;
        }
    } catch (Throwable $e) {
        $sueldos_mensuales[] = 0;
    }

    $egresos_mensuales[] = $gastos_mensuales[$i - 1] + $compras_mensuales[$i - 1] + $sueldos_mensuales[$i - 1];
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1>📊 Panel de Control</h1>
        <p class="text-muted">Métricas operativas, compras, proveedores, gastos, sueldos, pasivos e ingresos para decisiones</p>
        <?php if (!empty($errores)): ?>
            <div class="alert alert-warning mt-3">
                <strong>Algunas métricas no pudieron cargarse:</strong>
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $es_operario_dashboard = (strtolower((string)($role ?? '')) === 'operario'); ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Año</label>
                <select name="anio" class="form-select" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $anio_seleccionado ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-9 text-end">
                <div class="small text-muted">Comparativa: ingresos vs egresos totales (gastos + sueldo + compras)</div>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas generales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-2">📦 Total Pedidos</h6>
                <h3 class="mb-0"><?= number_format($stats_pedidos['total_pedidos']) ?></h3>
                <?php if ($es_operario_dashboard): ?>
                    <small class="text-white-50">Monto oculto</small>
                <?php else: ?>
                    <small>Monto: $<?= number_format($stats_pedidos['monto_total_pedidos'], 2) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-2">💰 Ventas del Mes</h6>
                <?php if ($es_operario_dashboard): ?>
                    <h3 class="mb-0">--</h3>
                    <small>Importe oculto</small>
                <?php else: ?>
                    <h3 class="mb-0">$<?= number_format($stats_mes['monto_mes'], 2) ?></h3>
                    <small><?= $stats_mes['pedidos_mes'] ?> pedidos</small>
                <?php endif; ?>
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
                <?php if ($es_operario_dashboard): ?>
                    <h3 class="mb-0">--</h3>
                    <small>Oculto</small>
                <?php else: ?>
                    <h3 class="mb-0">$<?= number_format($stats_pedidos['promedio_pedido'], 2) ?></h3>
                    <small>por pedido</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-primary">Ingresos <?= $anio_seleccionado ?></h6>
                <h3>$<?= number_format($ventas_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-success">Compras anual</h6>
                <h3>$<?= number_format($compras_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-danger">Gastos anual</h6>
                <h3>$<?= number_format($gastos_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-secondary">
            <div class="card-body text-center">
                <h6 class="text-secondary">Sueldos anual</h6>
                <h3>$<?= number_format($sueldos_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-dark">
            <div class="card-body text-center">
                <h6 class="text-dark">Egresos totales</h6>
                <h3>$<?= number_format($egresos_periodo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-warning">Neto acumulado</h6>
                <h3>$<?= number_format($neto_periodo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-danger">Pasivos totales</h6>
                <h3>$<?= number_format($pasivos_totales, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-info">Gastos pendientes</h6>
                <h3>$<?= number_format($pasivos_gastos, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏆 Productos Más Vendidos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_ventas_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($productos_ventas_ranking as $i => $prod): ?>
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
                                            <?= number_format((float)$prod['cantidad'], 0, ',', '.') ?> unidades<br>
                                            Monto: $<?= number_format((float)$prod['total'], 2, ',', '.') ?>
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

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">📦 Productos Comprados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_compra_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($productos_compra_ranking as $i => $prod): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-success rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((float)$prod['cantidad'], 0, ',', '.') ?> unidades<br>
                                            Costo: $<?= number_format((float)$prod['total'], 2, ',', '.') ?>
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

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">🏭 Proveedores</h5>
            </div>
            <div class="card-body">
                <?php if (empty($proveedores_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($proveedores_ranking as $i => $prov): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-info rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prov['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((int)$prov['compras'], 0, ',', '.') ?> compras<br>
                                            Total: $<?= number_format((float)$prov['total'], 2, ',', '.') ?>
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

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">💸 Gastos por categoría</h5>
            </div>
            <div class="card-body">
                <?php if (empty($gastos_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($gastos_ranking as $i => $gasto): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-warning rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($gasto['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((int)$gasto['cantidad'], 0, ',', '.') ?> movimientos<br>
                                            Total: $<?= number_format((float)$gasto['total'], 2, ',', '.') ?>
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

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">📈 Comparativa anual: ingresos vs egresos</h5>
            </div>
            <div class="card-body">
                <canvas id="panelComparativoMensual" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">💳 Sector de Finanzas y Pasivos</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h6 class="text-danger">Pasivos totales</h6>
                        <h4>$<?= number_format($pasivos_totales, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-warning">Gastos pendientes</h6>
                        <h4>$<?= number_format($pasivos_gastos, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-secondary">Sueldos pendientes</h6>
                        <h4>$<?= number_format($pasivos_sueldos, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-success">Compras pendientes</h6>
                        <h4>$<?= number_format($pasivos_compras, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const panelLabels = <?= json_encode($labels_meses) ?>;
const panelVentas = <?= json_encode($ventas_mensuales) ?>;
const panelCompras = <?= json_encode($compras_mensuales) ?>;
const panelGastos = <?= json_encode($gastos_mensuales) ?>;
const panelSueldos = <?= json_encode($sueldos_mensuales) ?>;
const panelEgresos = <?= json_encode($egresos_mensuales) ?>;

new Chart(document.getElementById('panelComparativoMensual'), {
    type: 'line',
    data: {
        labels: panelLabels,
        datasets: [
            { label: 'Ingresos', data: panelVentas, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.12)', tension: 0.3, fill: false },
            { label: 'Egresos (gastos + sueldo + compras)', data: panelEgresos, borderColor: '#dc3545', backgroundColor: 'rgba(220, 53, 69, 0.12)', tension: 0.3, fill: false },
            { label: 'Compras', data: panelCompras, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.12)', tension: 0.3, fill: false, hidden: true },
            { label: 'Gastos', data: panelGastos, borderColor: '#fd7e14', backgroundColor: 'rgba(253, 126, 20, 0.12)', tension: 0.3, fill: false, hidden: true },
            { label: 'Sueldos', data: panelSueldos, borderColor: '#6c757d', backgroundColor: 'rgba(108, 117, 125, 0.12)', tension: 0.3, fill: false, hidden: true }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: false } }
    }
});
</script>

<?php require 'includes/footer.php'; ?>
