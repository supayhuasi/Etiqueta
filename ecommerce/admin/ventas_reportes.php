<?php
// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'includes/header.php';

// Verificar que $pdo existe
if (!isset($pdo)) {
    die('Error: No hay conexión a la base de datos');
}

$periodo = $_GET['periodo'] ?? 'mes';
$year = intval($_GET['year'] ?? date('Y'));
$month = intval($_GET['month'] ?? date('n'));
$quarter = intval($_GET['quarter'] ?? ceil(date('n') / 3));

if ($periodo !== 'trimestre') {
    $periodo = 'mes';
}

if ($year < 2000 || $year > 2100) {
    $year = (int)date('Y');
}

if ($periodo === 'mes') {
    if ($month < 1 || $month > 12) {
        $month = (int)date('n');
    }
    $start = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $end = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);
    $label_periodo = $start->format('m/Y');
} else {
    if ($quarter < 1 || $quarter > 4) {
        $quarter = (int)ceil(date('n') / 3);
    }
    $startMonth = ($quarter - 1) * 3 + 1;
    $endMonth = $startMonth + 2;
    $start = new DateTime(sprintf('%04d-%02d-01', $year, $startMonth));
    $end = (clone $start)->modify('+' . ($endMonth - $startMonth) . ' months')->modify('last day of this month')->setTime(23, 59, 59);
    $label_periodo = 'T' . $quarter . ' ' . $year;
}

$startStr = $start->format('Y-m-d H:i:s');
$endStr = $end->format('Y-m-d H:i:s');

// Verificar qué columna de fecha existe en ecommerce_pedidos
try {
    $columnas = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos")->fetchAll(PDO::FETCH_ASSOC);
    $columnas_nombres = array_column($columnas, 'Field');
    
    // Determinar qué columna usar para las fechas
    if (in_array('fecha_pedido', $columnas_nombres)) {
        $fecha_columna = 'fecha_pedido';
    } elseif (in_array('fecha_creacion', $columnas_nombres)) {
        $fecha_columna = 'fecha_creacion';
    } elseif (in_array('fecha', $columnas_nombres)) {
        $fecha_columna = 'fecha';
    } elseif (in_array('created_at', $columnas_nombres)) {
        $fecha_columna = 'created_at';
    } else {
        die('Error: No se encontró columna de fecha en ecommerce_pedidos. Columnas disponibles: ' . implode(', ', $columnas_nombres));
    }
} catch (Exception $e) {
    die('Error al verificar estructura de tabla: ' . $e->getMessage());
}

try {
    // Total vendido: TODOS los pedidos excepto cancelados
    // Esto incluye: pendiente_pago, esperando_transferencia, esperando_envio, pagado,
    // confirmado, preparando, enviado, entregado, y otros pendientes de actualización
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total_vendido FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado != 'cancelado'");
    $stmt->execute([$startStr, $endStr]);
    $total_vendido = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_vendido'];
} catch (Exception $e) {
    die('Error en consulta de ventas: ' . $e->getMessage());
}

// Total cobrado: SOLO pedidos con estado 'pagado'
$total_cobrado = 0.0;
try {
    $tabla_pagos = $pdo->query("SHOW TABLES LIKE 'ecommerce_pedido_pagos'")->rowCount() > 0;
    if ($tabla_pagos) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(pp.monto),0) as total_cobrado
            FROM ecommerce_pedido_pagos pp
            INNER JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
            WHERE p.$fecha_columna BETWEEN ? AND ? AND pp.estado = 'completado'
        ");
        $stmt->execute([$startStr, $endStr]);
        $total_cobrado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_cobrado'];
    } else {
        // Alternativa: si no existe tabla de pagos, contar como cobrado solo los con estado 'pagado'
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total_cobrado FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado = 'pagado'");
        $stmt->execute([$startStr, $endStr]);
        $total_cobrado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_cobrado'];
    }
} catch (Exception $e) {
    // Si hay error intentar fallback: contar estado='pagado'
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total_cobrado FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado = 'pagado'");
        $stmt->execute([$startStr, $endStr]);
        $total_cobrado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_cobrado'];
    } catch (Exception $e2) {
        $total_cobrado = 0.0;
    }
}

$porcentaje_cobrado = $total_vendido > 0 ? ($total_cobrado / $total_vendido) * 100 : 0;

// Pendiente de entrega (monto) - usa tabla ecommerce_ordenes_produccion que tiene los estados correctos
$pendiente_entrega = 0.0;
try {
    // Estados pendientes de entrega: pendiente, en_produccion, terminado (sin entregar aún)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(p.total),0) as pendiente_entrega
        FROM ecommerce_pedidos p
        INNER JOIN ecommerce_ordenes_produccion op ON p.id = op.pedido_id
        WHERE p.$fecha_columna BETWEEN ? AND ? AND op.estado IN ('pendiente','en_produccion','terminado')
    ");
    $stmt->execute([$startStr, $endStr]);
    $pendiente_entrega = (float)$stmt->fetch(PDO::FETCH_ASSOC)['pendiente_entrega'];
} catch (Exception $e) {
    // Si falla (tabla no existe), usar fallback: pendientes en ecommerce_pedidos
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as pendiente_entrega FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado IN ('esperando_envio','preparando','confirmado')");
        $stmt->execute([$startStr, $endStr]);
        $pendiente_entrega = (float)$stmt->fetch(PDO::FETCH_ASSOC)['pendiente_entrega'];
    } catch (Exception $e2) {
        $pendiente_entrega = 0.0;
    }
}

$pendiente_cobro = max(0, $total_vendido - $total_cobrado);
if ($pendiente_cobro < 0) {
    $pendiente_cobro = 0; // Asegurar que nunca sea negativo
}

$gastos_total_anio = 0.0;
$gastos_total_periodo = 0.0;
$evolucion_ventas = [];
$evolucion_ventas_anterior = [];
$evolucion_gastos = [];
$evolucion_gastos_anterior = [];
$evolucion_pedidos = [];
$labels_meses = [];

$gastos_fecha_col = 'fecha';
try {
    $gastos_cols = $pdo->query("SHOW COLUMNS FROM gastos")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (in_array('fecha', $gastos_cols, true)) {
        $gastos_fecha_col = 'fecha';
    } elseif (in_array('fecha_gasto', $gastos_cols, true)) {
        $gastos_fecha_col = 'fecha_gasto';
    } elseif (in_array('created_at', $gastos_cols, true)) {
        $gastos_fecha_col = 'created_at';
    } else {
        $gastos_fecha_col = 'fecha';
    }
} catch (Exception $e) {
    $gastos_fecha_col = 'fecha';
}

for ($mes_num = 1; $mes_num <= 12; $mes_num++) {
    $mes_start = new DateTime(sprintf('%04d-%02d-01', $year, $mes_num));
    $mes_end = (clone $mes_start)->modify('last day of this month')->setTime(23, 59, 59);
    $mes_start_str = $mes_start->format('Y-m-d H:i:s');
    $mes_end_str = $mes_end->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as ventas_mes FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado != 'cancelado'");
    $stmt->execute([$mes_start_str, $mes_end_str]);
    $ventas_mes = (float)$stmt->fetch(PDO::FETCH_ASSOC)['ventas_mes'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as pedidos_mes FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado != 'cancelado'");
    $stmt->execute([$mes_start_str, $mes_end_str]);
    $pedidos_mes = (int)$stmt->fetch(PDO::FETCH_ASSOC)['pedidos_mes'];

    $gastos_mes = 0.0;
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as gastos_mes FROM gastos WHERE $gastos_fecha_col BETWEEN ? AND ?");
        $stmt->execute([$mes_start_str, $mes_end_str]);
        $gastos_mes = (float)$stmt->fetch(PDO::FETCH_ASSOC)['gastos_mes'];
    } catch (Exception $e) {
        $gastos_mes = 0.0;
    }

    $labels_meses[] = $mes_start->format('M');
    $evolucion_ventas[] = $ventas_mes;
    $evolucion_gastos[] = $gastos_mes;
    $evolucion_pedidos[] = $pedidos_mes;
    $gastos_total_anio += $gastos_mes;
}

$gastos_total_periodo = 0.0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as gastos_periodo FROM gastos WHERE $gastos_fecha_col BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $gastos_total_periodo = (float)$stmt->fetch(PDO::FETCH_ASSOC)['gastos_periodo'];
} catch (Exception $e) {
    $gastos_total_periodo = 0.0;
}

$total_pedidos_anio = array_sum($evolucion_pedidos);
$utilidad_neta = $total_vendido - $gastos_total_periodo;
$margen_pct = $total_vendido > 0 ? (($utilidad_neta / $total_vendido) * 100) : 0;

$prev_year = $year - 1;
$ventas_prev_anio = 0.0;
$gastos_prev_anio = 0.0;
$pedidos_prev_anio = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as ventas_prev FROM ecommerce_pedidos WHERE YEAR($fecha_columna) = ? AND estado != 'cancelado'");
    $stmt->execute([$prev_year]);
    $ventas_prev_anio = (float)$stmt->fetch(PDO::FETCH_ASSOC)['ventas_prev'];
} catch (Exception $e) { $ventas_prev_anio = 0.0; }

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as gastos_prev FROM gastos WHERE YEAR($gastos_fecha_col) = ?");
    $stmt->execute([$prev_year]);
    $gastos_prev_anio = (float)$stmt->fetch(PDO::FETCH_ASSOC)['gastos_prev'];
} catch (Exception $e) { $gastos_prev_anio = 0.0; }

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as pedidos_prev FROM ecommerce_pedidos WHERE YEAR($fecha_columna) = ? AND estado != 'cancelado'");
    $stmt->execute([$prev_year]);
    $pedidos_prev_anio = (int)$stmt->fetch(PDO::FETCH_ASSOC)['pedidos_prev'];
} catch (Exception $e) { $pedidos_prev_anio = 0; }

$variacion_ventas = $ventas_prev_anio > 0 ? (($total_vendido - $ventas_prev_anio) / $ventas_prev_anio) * 100 : 0;
$variacion_gastos = $gastos_prev_anio > 0 ? (($gastos_total_anio - $gastos_prev_anio) / $gastos_prev_anio) * 100 : 0;
$variacion_pedidos = $pedidos_prev_anio > 0 ? (($total_pedidos_anio - $pedidos_prev_anio) / $pedidos_prev_anio) * 100 : 0;
$ticket_promedio = $total_pedidos_anio > 0 ? ($total_vendido / $total_pedidos_anio) : 0;

for ($mes_num = 1; $mes_num <= 12; $mes_num++) {
    $mes_start = new DateTime(sprintf('%04d-%02d-01', $prev_year, $mes_num));
    $mes_end = (clone $mes_start)->modify('last day of this month')->setTime(23, 59, 59);
    $mes_start_str = $mes_start->format('Y-m-d H:i:s');
    $mes_end_str = $mes_end->format('Y-m-d H:i:s');

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as ventas_mes FROM ecommerce_pedidos WHERE $fecha_columna BETWEEN ? AND ? AND estado != 'cancelado'");
    $stmt->execute([$mes_start_str, $mes_end_str]);
    $evolucion_ventas_anterior[] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['ventas_mes'];

    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as gastos_mes FROM gastos WHERE $gastos_fecha_col BETWEEN ? AND ?");
        $stmt->execute([$mes_start_str, $mes_end_str]);
        $evolucion_gastos_anterior[] = (float)$stmt->fetch(PDO::FETCH_ASSOC)['gastos_mes'];
    } catch (Exception $e) { $evolucion_gastos_anterior[] = 0.0; }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📊 Reporte de Ventas</h1>
        <p class="text-muted">Resumen por período de ventas, cobranzas y entregas</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-select" onchange="this.form.submit()">
                    <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Mes</option>
                    <option value="trimestre" <?= $periodo === 'trimestre' ? 'selected' : '' ?>>Trimestre</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Año</label>
                <input type="number" class="form-control" name="year" value="<?= $year ?>" min="2000" max="2100">
            </div>
            <div class="col-md-3">
                <label class="form-label">Mes</label>
                <select name="month" class="form-select" <?= $periodo === 'trimestre' ? 'disabled' : '' ?>>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= str_pad($m, 2, '0', STR_PAD_LEFT) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Trimestre</label>
                <select name="quarter" class="form-select" <?= $periodo === 'mes' ? 'disabled' : '' ?>>
                    <?php for ($q = 1; $q <= 4; $q++): ?>
                        <option value="<?= $q ?>" <?= $q === $quarter ? 'selected' : '' ?>>T<?= $q ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h6>Ventas (<?= htmlspecialchars($label_periodo) ?>)</h6>
                <h3>$<?= number_format($total_vendido, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h6>Cobrado</h6>
                <h3>$<?= number_format($total_cobrado, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h6>Pendiente Cobro</h6>
                <h3>$<?= number_format($pendiente_cobro, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h6>Falta Entregar</h6>
                <h3>$<?= number_format($pendiente_entrega, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h6>Pedidos del año</h6>
                <h3><?= number_format($total_pedidos_anio, 0, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h6>Gastos del año</h6>
                <h3>$<?= number_format($gastos_total_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body text-center">
                <h6>Gastos del período</h6>
                <h3>$<?= number_format($gastos_total_periodo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h6>Utilidad neta</h6>
                <h3>$<?= number_format($utilidad_neta, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-success">Margen %</h6>
                <h3><?= number_format($margen_pct, 2, ',', '.') ?>%</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-primary">Var. ventas YoY</h6>
                <h3><?= number_format($variacion_ventas, 2, ',', '.') ?>%</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-danger">Var. gastos YoY</h6>
                <h3><?= number_format($variacion_gastos, 2, ',', '.') ?>%</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-info">Ticket promedio</h6>
                <h3>$<?= number_format($ticket_promedio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Ventas vs Cobros</div>
            <div class="card-body">
                <canvas id="chartVentasCobros" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">Porcentaje Cobrado</div>
            <div class="card-body">
                <canvas id="chartCobrado" height="140"></canvas>
                <p class="text-center mt-2 mb-0"><strong><?= number_format($porcentaje_cobrado, 2, ',', '.') ?>%</strong> cobrado</p>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-8 mb-4">
        <div class="card">
            <div class="card-header">Evolución mensual de ventas y gastos</div>
            <div class="card-body">
                <canvas id="chartEvolucionMensual" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-4">
        <div class="card">
            <div class="card-header">Pedidos por mes</div>
            <div class="card-body">
                <canvas id="chartPedidosMensuales" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ventas = <?= json_encode($total_vendido) ?>;
const cobros = <?= json_encode($total_cobrado) ?>;
const pendienteEntrega = <?= json_encode($pendiente_entrega) ?>;
const pendienteCobro = <?= json_encode($pendiente_cobro) ?>;
const labelsMeses = <?= json_encode($labels_meses) ?>;
const ventasMensuales = <?= json_encode($evolucion_ventas) ?>;
const ventasAnteriores = <?= json_encode($evolucion_ventas_anterior) ?>;
const gastosMensuales = <?= json_encode($evolucion_gastos) ?>;
const gastosAnteriores = <?= json_encode($evolucion_gastos_anterior) ?>;
const pedidosMensuales = <?= json_encode($evolucion_pedidos) ?>;

new Chart(document.getElementById('chartVentasCobros'), {
    type: 'bar',
    data: {
        labels: ['Ventas', 'Cobrado', 'Pend. Cobro', 'Pend. Entrega'],
        datasets: [{
            label: 'Importe',
            data: [ventas, cobros, pendienteCobro, pendienteEntrega],
            backgroundColor: ['#0d6efd','#198754','#ffc107','#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true }
        }
    }
});

new Chart(document.getElementById('chartCobrado'), {
    type: 'doughnut',
    data: {
        labels: ['Cobrado', 'Pendiente'],
        datasets: [{
            data: [cobros, Math.max(0, ventas - cobros)],
            backgroundColor: ['#198754','#dc3545']
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById('chartEvolucionMensual'), {
    type: 'line',
    data: {
        labels: labelsMeses,
        datasets: [
            {
                label: 'Ventas ' + <?= json_encode($year) ?>,
                data: ventasMensuales,
                borderColor: '#0d6efd',
                backgroundColor: 'rgba(13,110,253,0.15)',
                tension: 0.35,
                fill: false
            },
            {
                label: 'Ventas ' + <?= json_encode($prev_year) ?>,
                data: ventasAnteriores,
                borderColor: '#6c757d',
                backgroundColor: 'rgba(108,117,125,0.08)',
                tension: 0.35,
                fill: false,
                borderDash: [6, 6]
            },
            {
                label: 'Gastos ' + <?= json_encode($year) ?>,
                data: gastosMensuales,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220,53,69,0.15)',
                tension: 0.35,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: false }
        }
    }
});

new Chart(document.getElementById('chartPedidosMensuales'), {
    type: 'line',
    data: {
        labels: labelsMeses,
        datasets: [{
            label: 'Pedidos',
            data: pedidosMensuales,
            borderColor: '#198754',
            backgroundColor: 'rgba(25,135,84,0.15)',
            tension: 0.35,
            fill: false
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, precision: 0 }
        }
    }
});
</script>

<?php require 'includes/footer.php'; ?>
