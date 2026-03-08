<?php
require 'includes/header.php';

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

$mes = $_GET['mes'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$ingresos_mes = 0.0;
$egresos_mes = 0.0;
$saldo_flujo_mes = 0.0;

if (fin_table_exists($pdo, 'flujo_caja')) {
    $ingresos_mes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM flujo_caja WHERE tipo = 'ingreso' AND DATE_FORMAT(fecha, '%Y-%m') = '" . $mes . "'", 0);
    $egresos_mes = (float) fin_scalar($pdo, "SELECT SUM(monto) FROM flujo_caja WHERE tipo = 'egreso' AND DATE_FORMAT(fecha, '%Y-%m') = '" . $mes . "'", 0);
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
if (fin_table_exists($pdo, 'ecommerce_pedidos')) {
    $cxc_pedidos = (float) fin_scalar($pdo, "
        SELECT SUM(total)
        FROM ecommerce_pedidos
        WHERE estado IN ('pendiente_pago', 'esperando_transferencia', 'pago_pendiente', 'confirmado', 'preparando')
    ", 0);
}

$valor_stock_productos = 0.0;
if (fin_table_exists($pdo, 'ecommerce_productos') && fin_column_exists($pdo, 'ecommerce_productos', 'stock') && fin_column_exists($pdo, 'ecommerce_productos', 'precio_base')) {
    $valor_stock_productos = (float) fin_scalar($pdo, "SELECT SUM(GREATEST(stock,0) * COALESCE(precio_base,0)) FROM ecommerce_productos", 0);
}

$valor_stock_materiales = 0.0;
if (fin_table_exists($pdo, 'ecommerce_materiales') && fin_column_exists($pdo, 'ecommerce_materiales', 'stock')) {
    $costo_material = '0';
    if (fin_column_exists($pdo, 'ecommerce_materiales', 'costo_unitario')) {
        $costo_material = 'COALESCE(costo_unitario,0)';
    } elseif (fin_column_exists($pdo, 'ecommerce_materiales', 'precio')) {
        $costo_material = 'COALESCE(precio,0)';
    } elseif (fin_column_exists($pdo, 'ecommerce_materiales', 'precio_base')) {
        $costo_material = 'COALESCE(precio_base,0)';
    }

    $valor_stock_materiales = (float) fin_scalar($pdo, "SELECT SUM(GREATEST(stock,0) * {$costo_material}) FROM ecommerce_materiales", 0);
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
                <small>Productos + materiales al costo base</small>
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

<div class="d-flex flex-wrap gap-2">
    <a href="flujo_caja.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-primary">Ver Flujo de Caja</a>
    <a href="gastos/gastos.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-warning">Ver Gastos</a>
    <a href="cheques/cheques.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-danger">Ver Cheques</a>
    <a href="inventario_reporte_reponer.php" class="btn btn-outline-secondary">Ver Reposición</a>
</div>

<?php require 'includes/footer.php'; ?>
