<?php
require 'includes/header.php';

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

// Total vendido
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total_vendido FROM ecommerce_pedidos WHERE fecha_creacion BETWEEN ? AND ?");
$stmt->execute([$startStr, $endStr]);
$total_vendido = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_vendido'];

// Total cobrado
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(pp.monto),0) as total_cobrado
    FROM ecommerce_pedido_pagos pp
    INNER JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
    WHERE p.fecha_creacion BETWEEN ? AND ?
");
$stmt->execute([$startStr, $endStr]);
$total_cobrado = (float)$stmt->fetch(PDO::FETCH_ASSOC)['total_cobrado'];

$porcentaje_cobrado = $total_vendido > 0 ? ($total_cobrado / $total_vendido) * 100 : 0;

// Pendiente de entrega (monto)
$estados_entregados = ['entregado'];
$placeholders = implode(',', array_fill(0, count($estados_entregados), '?'));
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as pendiente_entrega FROM ecommerce_pedidos WHERE fecha_creacion BETWEEN ? AND ? AND estado NOT IN ($placeholders)");
$params = array_merge([$startStr, $endStr], $estados_entregados);
$stmt->execute($params);
$pendiente_entrega = (float)$stmt->fetch(PDO::FETCH_ASSOC)['pendiente_entrega'];

$pendiente_cobro = max(0, $total_vendido - $total_cobrado);
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

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ventas = <?= json_encode($total_vendido) ?>;
const cobros = <?= json_encode($total_cobrado) ?>;
const pendienteEntrega = <?= json_encode($pendiente_entrega) ?>;
const pendienteCobro = <?= json_encode($pendiente_cobro) ?>;

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
</script>

<?php require 'includes/footer.php'; ?>
