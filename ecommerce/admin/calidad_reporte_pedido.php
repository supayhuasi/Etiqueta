<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/calidad_helper.php';

if (!isset($can_access) || !$can_access('calidad')) {
    die('Acceso denegado.');
}

$setupError = '';
try {
    ensureCalidadSchema($pdo);
} catch (Throwable $e) {
    $setupError = 'No se pudo inicializar el módulo de calidad: ' . $e->getMessage();
}

$pedidoId = max(0, (int)($_GET['pedido_id'] ?? 0));
if ($pedidoId <= 0) {
    die('Pedido inválido.');
}

$pedido = null;
$inspeccion = null;
$pedidoItems = [];
$error = '';

try {
    $stmtPedido = $pdo->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email FROM ecommerce_pedidos p LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id WHERE p.id = ? LIMIT 1");
    $stmtPedido->execute([$pedidoId]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$pedido) {
        throw new Exception('No se encontró el pedido solicitado.');
    }

    $inspeccion = obtenerInspeccionCalidadPedido($pdo, $pedidoId);

    if (calidad_table_exists($pdo, 'ecommerce_pedido_items')) {
        $stmtItems = $pdo->prepare("SELECT pi.id, pi.cantidad, pi.ancho_cm, pi.alto_cm, pi.atributos, COALESCE(pr.nombre, 'Producto') AS producto_nombre FROM ecommerce_pedido_items pi LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id WHERE pi.pedido_id = ? ORDER BY pi.id ASC");
        $stmtItems->execute([$pedidoId]);
        $pedidoItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($pedidoItems as &$item) {
            $item['atributos_legibles'] = calidad_formatear_atributos_pedido($pdo, $item['atributos'] ?? '');
        }
        unset($item);
    }
} catch (Throwable $e) {
    $error = 'No se pudo generar el reporte del pedido: ' . $e->getMessage();
}

$revisionMap = [];
$conteoResultados = ['ok' => 0, 'observado' => 0, 'rechazado' => 0];
if (!empty($inspeccion['items_revision']) && is_array($inspeccion['items_revision'])) {
    foreach ($inspeccion['items_revision'] as $itemRevision) {
        $itemId = (int)($itemRevision['item_id'] ?? 0);
        $revisionMap[$itemId] = $itemRevision;
        $estadoItem = strtolower(trim((string)($itemRevision['estado'] ?? 'ok')));
        if (isset($conteoResultados[$estadoItem])) {
            $conteoResultados[$estadoItem]++;
        }
    }
}

$estadoCalidad = strtolower(trim((string)($inspeccion['estado_calidad'] ?? 'pendiente')));
$estadoClass = 'secondary';
$estadoTexto = 'Pendiente';
if ($estadoCalidad === 'aprobado') {
    $estadoClass = 'success';
    $estadoTexto = 'Aprobado';
} elseif ($estadoCalidad === 'observado') {
    $estadoClass = 'warning text-dark';
    $estadoTexto = 'Observado';
} elseif ($estadoCalidad === 'rechazado') {
    $estadoClass = 'danger';
    $estadoTexto = 'Rechazado';
}

$pasoPrueba = (int)($inspeccion['prueba_aprobada'] ?? 0) === 1;
$fechaRevision = !empty($inspeccion['fecha_revision']) ? date('d/m/Y H:i', strtotime((string)$inspeccion['fecha_revision'])) : '-';
?>

<style>
    .pedido-reporte-wrap {
        max-width: 1180px;
        margin: 0 auto;
    }
    .pedido-reporte-header {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        color: #fff;
        padding: 1.2rem 1.4rem;
        border-radius: 16px;
        margin-bottom: 1.2rem;
    }
    .pedido-reporte-card {
        border: 1px solid #e9eef5;
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        background: #fff;
    }
    .pedido-kpi {
        border: 1px solid #edf1f7;
        border-radius: 12px;
        padding: .95rem;
        background: #fbfcff;
        height: 100%;
    }
    .pedido-kpi h3 {
        margin: 0;
        font-size: 1.5rem;
    }
    .reporte-badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .7rem;
        border-radius: 999px;
        font-weight: 700;
        font-size: .9rem;
    }
    .reporte-badge.success { background: #e8f7ee; color: #146c43; }
    .reporte-badge.warning { background: #fff3cd; color: #997404; }
    .reporte-badge.danger { background: #f8d7da; color: #b02a37; }
    .reporte-badge.secondary { background: #eef1f4; color: #495057; }
    .item-estado-pill {
        display: inline-block;
        border-radius: 999px;
        padding: .2rem .55rem;
        font-size: .78rem;
        font-weight: 700;
    }
    .item-estado-pill.ok { background: #e8f7ee; color: #146c43; }
    .item-estado-pill.observado { background: #fff3cd; color: #997404; }
    .item-estado-pill.rechazado { background: #f8d7da; color: #b02a37; }
    .item-estado-pill.pendiente { background: #eef1f4; color: #495057; }
    @media print {
        .btn, .no-print, .sidebar, nav, header, .navbar, .menu-section, .menu-header, .collapse, .topbar, .top-navbar {
            display: none !important;
        }
        body { background: #fff !important; }
        .pedido-reporte-card { box-shadow: none !important; }
    }
</style>

<div class="container-fluid mt-4 pedido-reporte-wrap">
    <div class="pedido-reporte-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 class="mb-1">📄 Reporte de calidad por pedido</h1>
            <div>
                Pedido: <strong><?= htmlspecialchars((string)($pedido['numero_pedido'] ?? ('#' . $pedidoId))) ?></strong>
                <?php if ($pedido): ?>
                    · Cliente: <strong><?= htmlspecialchars((string)($pedido['cliente_nombre'] ?? 'Sin cliente')) ?></strong>
                <?php endif; ?>
            </div>
            <small>Generado: <?= htmlspecialchars(date('d/m/Y H:i')) ?></small>
            <?php if ($setupError !== ''): ?>
                <div class="mt-2 alert alert-warning mb-0 py-2 px-3 text-dark"><?= htmlspecialchars($setupError) ?></div>
            <?php endif; ?>
        </div>
        <div class="d-flex gap-2 no-print flex-wrap">
            <a href="calidad.php?pedido_id=<?= (int)$pedidoId ?>" class="btn btn-light">← Volver a calidad</a>
            <?php if ($inspeccion): ?>
                <a href="calidad_inspeccion_pdf.php?pedido_id=<?= (int)$pedidoId ?>" class="btn btn-outline-light" target="_blank">PDF</a>
            <?php endif; ?>
            <button onclick="window.print()" class="btn btn-warning">🖨️ Imprimir</button>
        </div>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
        <?php if (!$inspeccion): ?>
            <div class="alert alert-warning">
                Todavía no hay un control de calidad guardado para este pedido. El reporte igualmente muestra el detalle actual del pedido para revisión.
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-md-6 col-xl-3">
                <div class="pedido-kpi">
                    <small class="text-muted d-block">Estado calidad</small>
                    <div class="reporte-badge <?= htmlspecialchars(explode(' ', $estadoClass)[0]) ?> mt-2"><?= htmlspecialchars($estadoTexto) ?></div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="pedido-kpi">
                    <small class="text-muted d-block">Prueba final</small>
                    <h3><?= $pasoPrueba ? '✅ Sí' : '⏳ Pendiente' ?></h3>
                    <small class="text-muted">Fecha revisión: <?= htmlspecialchars($fechaRevision) ?></small>
                </div>
            </div>
            <div class="col-md-6 col-xl-2">
                <div class="pedido-kpi">
                    <small class="text-muted d-block">Ítems OK</small>
                    <h3 class="text-success"><?= number_format((int)$conteoResultados['ok']) ?></h3>
                </div>
            </div>
            <div class="col-md-6 col-xl-2">
                <div class="pedido-kpi">
                    <small class="text-muted d-block">Observados</small>
                    <h3 class="text-warning"><?= number_format((int)$conteoResultados['observado']) ?></h3>
                </div>
            </div>
            <div class="col-md-6 col-xl-2">
                <div class="pedido-kpi">
                    <small class="text-muted d-block">Rechazados</small>
                    <h3 class="text-danger"><?= number_format((int)$conteoResultados['rechazado']) ?></h3>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="pedido-reporte-card h-100">
                    <div class="card-header bg-light"><strong>Datos del pedido</strong></div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>Número:</strong> <?= htmlspecialchars((string)($pedido['numero_pedido'] ?? ('#' . $pedidoId))) ?></li>
                            <li><strong>Cliente:</strong> <?= htmlspecialchars((string)($pedido['cliente_nombre'] ?? 'Sin cliente')) ?></li>
                            <li><strong>Fecha pedido:</strong> <?= !empty($pedido['fecha_pedido']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$pedido['fecha_pedido']))) : '-' ?></li>
                            <li><strong>Estado pedido:</strong> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($pedido['estado'] ?? '-')))) ?></li>
                            <li><strong>Total:</strong> $<?= number_format((float)($pedido['total'] ?? 0), 2, ',', '.') ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="pedido-reporte-card h-100">
                    <div class="card-header bg-light"><strong>Resultado del control</strong></div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Detalle detectado:</strong></p>
                        <div class="text-muted mb-3"><?= nl2br(htmlspecialchars((string)($inspeccion['detalle_revision'] ?? 'Sin detalle general cargado todavía.'))) ?></div>
                        <p class="mb-2"><strong>Observaciones finales:</strong></p>
                        <div class="text-muted mb-0"><?= nl2br(htmlspecialchars((string)($inspeccion['observaciones'] ?? 'Sin observaciones finales todavía.'))) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="pedido-reporte-card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <strong>Detalle de lo cargado en calidad</strong>
                <span class="text-muted small"><?= count($pedidoItems) ?> ítem(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($pedidoItems)): ?>
                    <div class="p-4 text-center text-muted">No hay ítems cargados para este pedido.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Medidas</th>
                                    <th>Atributos</th>
                                    <th>Resultado</th>
                                    <th>Observación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidoItems as $item): ?>
                                    <?php
                                        $itemId = (int)($item['id'] ?? 0);
                                        $revision = $revisionMap[$itemId] ?? [];
                                        $estadoItem = strtolower(trim((string)($revision['estado'] ?? 'pendiente')));
                                        if (!in_array($estadoItem, ['ok', 'observado', 'rechazado'], true)) {
                                            $estadoItem = 'pendiente';
                                        }
                                        $medidas = '';
                                        if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
                                            $medidas = ($item['ancho_cm'] !== null && $item['ancho_cm'] !== '' ? $item['ancho_cm'] : '-') . 'x' . ($item['alto_cm'] !== null && $item['alto_cm'] !== '' ? $item['alto_cm'] : '-') . ' cm';
                                        }
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars((string)($item['producto_nombre'] ?? 'Producto')) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars((string)($item['cantidad'] ?? '1')) ?></td>
                                        <td><?= htmlspecialchars($medidas !== '' ? $medidas : '-') ?></td>
                                        <td><?= htmlspecialchars((string)($item['atributos_legibles'] ?? '-')) ?></td>
                                        <td><span class="item-estado-pill <?= htmlspecialchars($estadoItem) ?>"><?= htmlspecialchars(ucfirst($estadoItem)) ?></span></td>
                                        <td><?= htmlspecialchars((string)($revision['observacion'] ?? '-')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
