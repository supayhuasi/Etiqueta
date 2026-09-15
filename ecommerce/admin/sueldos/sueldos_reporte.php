<?php
require '../includes/header.php';
require_once '../includes/sueldos_helper.php';

$mes = trim((string)($_GET['mes'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$ids_raw = trim((string)($_GET['empleado_ids'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids_raw)), static function ($id) {
    return $id > 0;
})));

$meses_es = [
    1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril',
    5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto',
    9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
];
$mes_partes = explode('-', $mes);
$mes_texto = ($meses_es[(int)($mes_partes[1] ?? 0)] ?? $mes) . ' ' . ($mes_partes[0] ?? '');

$filas = [];
$total_sueldo = 0.0;
$total_pagado = 0.0;
$total_pendiente = 0.0;

if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT e.id, e.nombre, e.email, e.sueldo_base,
               COALESCE(ps.monto_pagado, 0) AS monto_pagado,
               COALESCE(ps.sueldo_total, 0) AS sueldo_total_reg,
               COALESCE(ps.id, 0) AS pago_id,
               COALESCE((
                   SELECT SUM(monto_pagado)
                   FROM pagos_sueldos_parciales
                   WHERE empleado_id = e.id AND mes_pago = ?
               ), 0) AS pagos_parciales
        FROM empleados e
        LEFT JOIN pagos_sueldos ps ON e.id = ps.empleado_id AND ps.mes_pago = ?
        WHERE e.id IN ($placeholders)
        ORDER BY e.nombre ASC
    ");
    $params = array_merge([$mes, $mes], $ids);
    $stmt->execute($params);
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($empleados as $emp) {
        if ((int)$emp['pago_id'] === 0 || (float)$emp['sueldo_total_reg'] <= 0) {
            $sueldo_total = sueldosCalcularTotalMes($pdo, (int)$emp['id'], $mes);
        } else {
            $sueldo_total = (float)$emp['sueldo_total_reg'];
        }
        $pagado = (float)$emp['monto_pagado'] + (float)$emp['pagos_parciales'];
        $pendiente = max(0, $sueldo_total - $pagado);

        $filas[] = [
            'nombre' => $emp['nombre'],
            'email' => $emp['email'],
            'sueldo_total' => $sueldo_total,
            'pagado' => $pagado,
            'pendiente' => $pendiente,
        ];
        $total_sueldo += $sueldo_total;
        $total_pagado += $pagado;
        $total_pendiente += $pendiente;
    }
}
?>
<style>
@media print {
    .btn, .no-print, .sidebar, nav, header, .navbar, .menu-section, .menu-header, .collapse, .topbar {
        display: none !important;
    }
    body { background: #fff !important; }
    .card { box-shadow: none !important; border: 1px solid #ccc !important; }
}
.firma-linea {
    min-width: 140px;
    border-bottom: 1px solid #333;
    height: 18px;
    display: inline-block;
}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="mb-1">Reporte de sueldos pendientes</h1>
        <p class="text-muted mb-0">Mes: <strong><?= htmlspecialchars($mes_texto) ?></strong></p>
        <small class="text-muted">Generado: <?= htmlspecialchars(date('d/m/Y H:i')) ?></small>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="sueldos.php?mes=<?= urlencode($mes) ?>" class="btn btn-outline-secondary">← Volver</a>
        <button type="button" class="btn btn-danger" onclick="window.print()">Imprimir</button>
    </div>
</div>

<?php if (empty($ids)): ?>
    <div class="alert alert-warning">No se recibieron empleados seleccionados.</div>
<?php elseif (empty($filas)): ?>
    <div class="alert alert-info">No hay datos para los empleados seleccionados.</div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Sueldo total</h6>
                    <h4 class="mb-0">$<?= number_format($total_sueldo, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Ya pagado</h6>
                    <h4 class="mb-0 text-success">$<?= number_format($total_pagado, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card bg-light border-danger">
                <div class="card-body">
                    <h6 class="mb-1">Falta pagar</h6>
                    <h4 class="mb-0 text-danger">$<?= number_format($total_pendiente, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th>
                    <th class="text-end">Sueldo total</th>
                    <th class="text-end">Ya pagado</th>
                    <th class="text-end">Falta pagar</th>
                    <th>Firma / recibido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filas as $fila): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars((string)$fila['nombre']) ?></strong>
                            <?php if (!empty($fila['email'])): ?>
                                <div class="small text-muted"><?= htmlspecialchars((string)$fila['email']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">$<?= number_format((float)$fila['sueldo_total'], 2, ',', '.') ?></td>
                        <td class="text-end">$<?= number_format((float)$fila['pagado'], 2, ',', '.') ?></td>
                        <td class="text-end">
                            <strong class="<?= ((float)$fila['pendiente']) > 0 ? 'text-danger' : 'text-success' ?>">
                                $<?= number_format((float)$fila['pendiente'], 2, ',', '.') ?>
                            </strong>
                        </td>
                        <td>
                            <div class="small text-muted">Recibí $ <?= number_format((float)$fila['pendiente'], 2, ',', '.') ?></div>
                            <span class="firma-linea"></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold">
                    <td>Total (<?= count($filas) ?> empleado<?= count($filas) === 1 ? '' : 's' ?>)</td>
                    <td class="text-end">$<?= number_format($total_sueldo, 2, ',', '.') ?></td>
                    <td class="text-end">$<?= number_format($total_pagado, 2, ',', '.') ?></td>
                    <td class="text-end text-danger">$<?= number_format($total_pendiente, 2, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif; ?>

<?php require '../includes/footer.php'; ?>
