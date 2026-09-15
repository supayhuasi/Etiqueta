<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/presupuesto_helper.php';

$mes = trim((string)($_GET['mes'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$caja = (float)str_replace(',', '.', (string)($_GET['caja'] ?? '0'));
$cobrosSel = presupuestoParseParesMonto((string)($_GET['cobros'] ?? ''));
$sueldosSel = presupuestoParseParesMonto((string)($_GET['sueldos'] ?? ''));
$gastosSel = presupuestoParseParesMonto((string)($_GET['gastos'] ?? ''));
$otrosSel = presupuestoParseOtrosPagos((string)($_GET['otros'] ?? ''));

$clientes = presupuestoClientesConSaldo($pdo);
$sueldos = presupuestoSueldosPendientes($pdo, $mes);
$gastos = presupuestoGastosAprobadosPendientes($pdo);
$clientesPorId = [];
foreach ($clientes as $c) {
    $clientesPorId[(int)$c['id']] = $c;
}
$sueldosPorId = [];
foreach ($sueldos as $s) {
    $sueldosPorId[(int)$s['id']] = $s;
}
$gastosPorId = [];
foreach ($gastos as $g) {
    $gastosPorId[(int)$g['id']] = $g;
}

$filasCobro = [];
$totalCobros = 0.0;
foreach ($cobrosSel as $id => $monto) {
    if (!isset($clientesPorId[$id])) {
        continue;
    }
    $saldo = (float)$clientesPorId[$id]['saldo'];
    $monto = min(max($monto, 0), $saldo);
    if ($monto <= 0) {
        continue;
    }
    $filasCobro[] = [
        'nombre' => $clientesPorId[$id]['nombre'],
        'saldo' => $saldo,
        'monto' => $monto,
    ];
    $totalCobros += $monto;
}

$filasSueldo = [];
$totalSueldos = 0.0;
foreach ($sueldosSel as $id => $monto) {
    if (!isset($sueldosPorId[$id])) {
        continue;
    }
    $pendiente = (float)$sueldosPorId[$id]['pendiente'];
    $monto = min(max($monto, 0), $pendiente);
    if ($monto <= 0) {
        continue;
    }
    $filasSueldo[] = [
        'nombre' => $sueldosPorId[$id]['nombre'],
        'pendiente' => $pendiente,
        'monto' => $monto,
    ];
    $totalSueldos += $monto;
}

$filasGasto = [];
$totalGastos = 0.0;
foreach ($gastosSel as $id => $monto) {
    if (!isset($gastosPorId[$id])) {
        continue;
    }
    $pendiente = (float)$gastosPorId[$id]['monto'];
    $monto = min(max($monto, 0), $pendiente);
    if ($monto <= 0) {
        continue;
    }
    $label = trim((string)($gastosPorId[$id]['descripcion'] ?: ($gastosPorId[$id]['tipo_nombre'] ?? 'Gasto')));
    if (!empty($gastosPorId[$id]['numero_gasto'])) {
        $label = $gastosPorId[$id]['numero_gasto'] . ' — ' . $label;
    }
    $filasGasto[] = [
        'nombre' => $label,
        'pendiente' => $pendiente,
        'monto' => $monto,
    ];
    $totalGastos += $monto;
}

$totalOtros = 0.0;
foreach ($otrosSel as $otro) {
    $totalOtros += (float)$otro['monto'];
}

$totalPagos = $totalSueldos + $totalGastos + $totalOtros;
$queda = $caja + $totalCobros - $totalPagos;

$meses_es = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
];
$mes_partes = explode('-', $mes);
$mes_texto = ($meses_es[(int)($mes_partes[1] ?? 0)] ?? $mes) . ' ' . ($mes_partes[0] ?? '');
?>
<style>
@media print {
    .btn, .no-print, .sidebar, nav, header, .navbar, .menu-section, .menu-header, .collapse, .topbar {
        display: none !important;
    }
    body { background: #fff !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="mb-1">Estrategia de caja</h1>
        <p class="text-muted mb-0">Mes de sueldos: <strong><?= htmlspecialchars($mes_texto) ?></strong></p>
        <small class="text-muted">Generado: <?= htmlspecialchars(date('d/m/Y H:i')) ?></small>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="presupuesto_estrategia.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-secondary">← Volver</a>
        <button type="button" class="btn btn-danger" onclick="window.print()">Imprimir</button>
    </div>
</div>

<?php if (empty($filasCobro) && empty($filasSueldo) && empty($filasGasto) && empty($otrosSel)): ?>
    <div class="alert alert-warning">No hay cobros ni pagos para mostrar en esta estrategia.</div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-md-3">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Caja inicial</h6>
                    <h4 class="mb-0">$<?= number_format($caja, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3 mt-md-0">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">A cobrar</h6>
                    <h4 class="mb-0 text-success">$<?= number_format($totalCobros, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3 mt-md-0">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">A pagar</h6>
                    <h4 class="mb-0 text-danger">$<?= number_format($totalPagos, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-3 mt-3 mt-md-0">
            <div class="card bg-light border-<?= $queda >= 0 ? 'success' : 'danger' ?>">
                <div class="card-body">
                    <h6 class="mb-1">Queda</h6>
                    <h4 class="mb-0 <?= $queda >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($queda, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($filasCobro)): ?>
        <h5 class="mt-4">Cobros</h5>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Cliente</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-end">Se cobra</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filasCobro as $fila): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)$fila['nombre']) ?></strong></td>
                            <td class="text-end">$<?= number_format((float)$fila['saldo'], 2, ',', '.') ?></td>
                            <td class="text-end text-success fw-semibold">$<?= number_format((float)$fila['monto'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total cobros</td>
                        <td></td>
                        <td class="text-end text-success">$<?= number_format($totalCobros, 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!empty($filasSueldo) || !empty($filasGasto) || !empty($otrosSel)): ?>
        <h5 class="mt-4">Pagos</h5>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Concepto</th>
                        <th class="text-end">Pendiente</th>
                        <th class="text-end">Se paga</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filasSueldo as $fila): ?>
                        <tr>
                            <td>Sueldo — <?= htmlspecialchars((string)$fila['nombre']) ?></td>
                            <td class="text-end">$<?= number_format((float)$fila['pendiente'], 2, ',', '.') ?></td>
                            <td class="text-end text-danger fw-semibold">$<?= number_format((float)$fila['monto'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($filasGasto as $fila): ?>
                        <tr>
                            <td>Gasto — <?= htmlspecialchars((string)$fila['nombre']) ?></td>
                            <td class="text-end">$<?= number_format((float)$fila['pendiente'], 2, ',', '.') ?></td>
                            <td class="text-end text-danger fw-semibold">$<?= number_format((float)$fila['monto'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($otrosSel as $otro): ?>
                        <tr>
                            <td><?= htmlspecialchars((string)$otro['descripcion']) ?></td>
                            <td class="text-end text-muted">—</td>
                            <td class="text-end text-danger fw-semibold">$<?= number_format((float)$otro['monto'], 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light fw-bold">
                        <td>Total pagos</td>
                        <td></td>
                        <td class="text-end text-danger">$<?= number_format($totalPagos, 2, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
