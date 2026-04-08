<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

ensureContabilidadSchema($pdo);

function fin_table_exists(PDO $pdo, $table)
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
    }

    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function fin_column_exists(PDO $pdo, $table, $column)
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function fin_scalar(PDO $pdo, $sql, $default = 0)
{
    try {
        $value = $pdo->query($sql)->fetchColumn();
        return ($value !== false && $value !== null) ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function fin_first_existing_column(PDO $pdo, string $table, array $columns): ?string
{
    foreach ($columns as $column) {
        if (fin_column_exists($pdo, $table, $column)) {
            return $column;
        }
    }

    return null;
}

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$ingresos_mes = 0.0;
$egresos_mes = 0.0;
$saldo_flujo_mes = 0.0;

if (fin_table_exists($pdo, 'flujo_caja')) {
    $ingresos_mes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM flujo_caja WHERE tipo = 'ingreso' AND categoria <> 'Pago de Sueldo' AND DATE_FORMAT(fecha, '%Y-%m') = '" . $mes . "'", 0);
    $egresos_mes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM flujo_caja WHERE (tipo = 'egreso' OR categoria = 'Pago de Sueldo') AND DATE_FORMAT(fecha, '%Y-%m') = '" . $mes . "'", 0);
    $saldo_flujo_mes = $ingresos_mes - $egresos_mes;
}

$gastos_mes = 0.0;
$gastos_pendientes = 0.0;
if (fin_table_exists($pdo, 'gastos')) {
    $gastos_mes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM gastos WHERE DATE_FORMAT(fecha, '%Y-%m') = '" . $mes . "'", 0);

    if (fin_table_exists($pdo, 'estados_gastos')) {
        $gastos_pendientes = (float) fin_scalar($pdo, "
            SELECT SUM(g.monto)
            FROM gastos g
            WHERE DATE_FORMAT(g.fecha, '%Y-%m') = '" . $mes . "'
              AND g.estado_gasto_id NOT IN (SELECT id FROM estados_gastos WHERE LOWER(nombre) = 'pagado')
        ", 0);
    }
}

$cheques_pendientes = 0.0;
if (fin_table_exists($pdo, 'cheques')) {
    $cheques_pendientes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM cheques WHERE estado = 'pendiente'", 0);
}

$cxc_pedidos = 0.0;
if (fin_table_exists($pdo, 'ecommerce_pedidos') && fin_table_exists($pdo, 'ecommerce_pedido_pagos')) {
    // Total de pedidos no cancelados
    $total_pedidos = (float) fin_scalar($pdo, "
        SELECT SUM(total)
        FROM ecommerce_pedidos
        WHERE estado != 'cancelado'
    ", 0);
    // Total pagado en pedidos no cancelados
    $total_pagado = (float) fin_scalar($pdo, "
        SELECT SUM(pp.monto)
        FROM ecommerce_pedido_pagos pp
        JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
        WHERE p.estado != 'cancelado'
    ", 0);
    $cxc_pedidos = $total_pedidos - $total_pagado;
}

$valor_stock_productos = 0.0;
$valor_stock_materiales = 0.0;
$productos_con_stock = 0;
$materiales_con_stock = 0;
$detalle_valorizacion_productos = 'Productos sin valorización disponible';
$detalle_valorizacion_materiales = 'Materiales sin valorización disponible';

if (fin_table_exists($pdo, 'ecommerce_productos') && fin_column_exists($pdo, 'ecommerce_productos', 'stock')) {
    $productos_con_stock = (int) fin_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_productos WHERE COALESCE(stock, 0) > 0", 0);

    $tiene_costos_compra = fin_table_exists($pdo, 'ecommerce_compra_items')
        && fin_column_exists($pdo, 'ecommerce_compra_items', 'producto_id')
        && fin_column_exists($pdo, 'ecommerce_compra_items', 'costo_unitario');

    if ($tiene_costos_compra) {
        $valor_stock_productos = (float) fin_scalar($pdo, "
            SELECT COALESCE(SUM(GREATEST(COALESCE(p.stock, 0), 0) * COALESCE(uc.costo_unitario, p.precio_base, 0)), 0)
            FROM ecommerce_productos p
            LEFT JOIN (
                SELECT ci.producto_id, ci.costo_unitario
                FROM ecommerce_compra_items ci
                INNER JOIN (
                    SELECT producto_id, MAX(id) AS max_id
                    FROM ecommerce_compra_items
                    WHERE COALESCE(costo_unitario, 0) > 0
                    GROUP BY producto_id
                ) ult ON ult.max_id = ci.id
            ) uc ON uc.producto_id = p.id
        ", 0);
        $detalle_valorizacion_productos = 'Productos valorizados al último costo de compra';
    } elseif (fin_column_exists($pdo, 'ecommerce_productos', 'precio_base')) {
        $valor_stock_productos = (float) fin_scalar($pdo, "SELECT COALESCE(SUM(GREATEST(COALESCE(stock, 0), 0) * COALESCE(precio_base, 0)), 0) FROM ecommerce_productos", 0);
        $detalle_valorizacion_productos = 'Productos valorizados a precio base';
    }
}

if (fin_table_exists($pdo, 'ecommerce_materiales') && fin_column_exists($pdo, 'ecommerce_materiales', 'stock')) {
    $materiales_con_stock = (int) fin_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_materiales WHERE COALESCE(stock, 0) > 0", 0);

    $columnaCostoMaterial = fin_first_existing_column($pdo, 'ecommerce_materiales', ['costo_unitario', 'costo', 'precio', 'precio_base']);
    if ($columnaCostoMaterial !== null) {
        $costo_material = 'COALESCE(`' . $columnaCostoMaterial . '`, 0)';
        $valor_stock_materiales = (float) fin_scalar($pdo, "SELECT COALESCE(SUM(GREATEST(COALESCE(stock, 0), 0) * {$costo_material}), 0) FROM ecommerce_materiales", 0);
        $detalle_valorizacion_materiales = 'Materiales valorizados por ' . str_replace('_', ' ', $columnaCostoMaterial);
    }
}

$valor_total_stock = $valor_stock_productos + $valor_stock_materiales;

$items_reponer = 0;
if (fin_table_exists($pdo, 'ecommerce_productos') && fin_column_exists($pdo, 'ecommerce_productos', 'stock') && fin_column_exists($pdo, 'ecommerce_productos', 'stock_minimo')) {
    $items_reponer += (int) fin_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_productos WHERE stock <= stock_minimo", 0);
}
if (fin_table_exists($pdo, 'ecommerce_materiales') && fin_column_exists($pdo, 'ecommerce_materiales', 'stock') && fin_column_exists($pdo, 'ecommerce_materiales', 'stock_minimo')) {
    $items_reponer += (int) fin_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_materiales WHERE stock <= stock_minimo", 0);
}

$saldo_real_estimado = $saldo_flujo_mes + $cxc_pedidos + $valor_total_stock - $gastos_pendientes - $cheques_pendientes;

$contabConfig = contabilidad_get_config($pdo);
$contabMoneda = (string)($contabConfig['moneda'] ?? 'ARS');
$contabImpuestosActivos = contabilidad_get_impuestos($pdo, true);
$contabResumenMes = contabilidad_calcular_impuestos($contabImpuestosActivos, $ingresos_mes, $ingresos_mes, 'pedido');
$contabImpuestosMesTotal = (float)($contabResumenMes['total_incluidos'] ?? 0) + (float)($contabResumenMes['total_adicionales'] ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">💹 Estado Financiero Real</h1>
        <p class="text-muted mb-0">Vista consolidada: caja, deudas, cuentas por cobrar e inventario valorizado.</p>
    </div>
    <form method="GET" class="d-flex gap-2 align-items-center">
        <label for="mes" class="form-label mb-0">Mes</label>
        <input type="month" id="mes" name="mes" class="form-control" value="<?= htmlspecialchars($mes) ?>" onchange="this.form.submit()">
    </form>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white h-100">
            <div class="card-body">
                <h6>Saldo de Caja del Mes</h6>
                <h3>$<?= number_format($saldo_flujo_mes, 0, ',', '.') ?></h3>
                <small>Ingresos: $<?= number_format($ingresos_mes, 0, ',', '.') ?> · Egresos: $<?= number_format($egresos_mes, 0, ',', '.') ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h6>Cuentas por Cobrar</h6>
                <h3>$<?= number_format($cxc_pedidos, 0, ',', '.') ?></h3>
                <small>Pedidos pendientes de cobro/confirmación</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-info text-white h-100">
            <div class="card-body">
                <h6>Inventario Valorizado</h6>
                <h3>$<?= number_format($valor_total_stock, 0, ',', '.') ?></h3>
                <small>
                    Productos: $<?= number_format($valor_stock_productos, 0, ',', '.') ?> ·
                    Materiales: $<?= number_format($valor_stock_materiales, 0, ',', '.') ?>
                </small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3 border-info">
    <div class="card-body">
        <div class="row g-3 align-items-center">
            <div class="col-md-4">
                <div class="small text-muted">Productos con stock</div>
                <div class="h5 mb-0"><?= number_format($productos_con_stock, 0, ',', '.') ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Materiales con stock</div>
                <div class="h5 mb-0"><?= number_format($materiales_con_stock, 0, ',', '.') ?></div>
            </div>
            <div class="col-md-4">
                <div class="small text-muted">Criterio de valorización</div>
                <div class="small">
                    <?= htmlspecialchars($detalle_valorizacion_productos) ?><br>
                    <?= htmlspecialchars($detalle_valorizacion_materiales) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card border-warning h-100">
            <div class="card-body">
                <h6 class="text-warning">Gastos Pendientes</h6>
                <h4>$<?= number_format($gastos_pendientes, 0, ',', '.') ?></h4>
                <small class="text-muted">Mes seleccionado</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-danger h-100">
            <div class="card-body">
                <h6 class="text-danger">Cheques Pendientes</h6>
                <h4>$<?= number_format($cheques_pendientes, 0, ',', '.') ?></h4>
                <small class="text-muted">Total a cubrir</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-secondary h-100">
            <div class="card-body">
                <h6 class="text-secondary">Items a Reponer</h6>
                <h4><?= number_format($items_reponer, 0, ',', '.') ?></h4>
                <small class="text-muted">Control de riesgo por stock</small>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Resultado General Estimado</h5>
    </div>
    <div class="card-body">
        <p class="mb-2">
            <strong>Fórmula:</strong>
            Saldo Caja + Cuentas por Cobrar + Inventario − Gastos Pendientes − Cheques Pendientes
        </p>
        <h2 class="mb-0 <?= $saldo_real_estimado >= 0 ? 'text-success' : 'text-danger' ?>">
            $<?= number_format($saldo_real_estimado, 0, ',', '.') ?>
        </h2>
    </div>
</div>

<div class="card mb-3 border-primary">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h5 class="mb-1">📚 Contabilidad fiscal</h5>
            <div class="text-muted small">
                Impuestos activos: <?= number_format(count($contabImpuestosActivos), 0, ',', '.') ?> ·
                Carga estimada del mes: <?= htmlspecialchars($contabMoneda) ?> $<?= number_format($contabImpuestosMesTotal, 0, ',', '.') ?>
            </div>
            <?php if (!empty($contabConfig['condicion_fiscal'])): ?>
                <div class="small text-muted">Condición fiscal: <?= htmlspecialchars((string)$contabConfig['condicion_fiscal']) ?></div>
            <?php endif; ?>
        </div>
        <a href="contabilidad.php" class="btn btn-outline-primary">Abrir módulo de contabilidad</a>
    </div>
</div>

<div class="d-flex flex-wrap gap-2">
    <a href="flujo_caja.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-primary">Ver Flujo de Caja</a>
    <a href="contabilidad.php" class="btn btn-outline-info">Ver Contabilidad</a>
    <a href="gastos/gastos.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-warning">Ver Gastos</a>
    <a href="cheques/cheques.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-danger">Ver Cheques</a>
    <a href="inventario_reporte_reponer.php" class="btn btn-outline-secondary">Ver Reposición</a>
</div>

<?php require 'includes/footer.php'; ?>
