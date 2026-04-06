<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/calidad_helper.php';

ensureCalidadSchema($pdo);

if (!isset($can_access) || !$can_access('calidad')) {
    die('Acceso denegado.');
}

$desde = $_GET['desde'] ?? date('Y-m-01');
$hasta = $_GET['hasta'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$desde)) {
    $desde = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$hasta)) {
    $hasta = date('Y-m-d');
}
if ($hasta < $desde) {
    $hasta = $desde;
}

$metricas = obtenerMetricasCalidad($pdo, $desde, $hasta);
$eventos = obtenerEventosCalidad($pdo, $desde, $hasta, 300);
$tasaReclamos = ($metricas['pedidos_entregados'] ?? 0) > 0 ? ((float)$metricas['reclamos'] / (float)$metricas['pedidos_entregados']) * 100 : 0;
$tasaRehechos = ($metricas['pedidos_entregados'] ?? 0) > 0 ? ((float)$metricas['productos_rehechos'] / (float)$metricas['pedidos_entregados']) * 100 : 0;
?>

<style>
    .reporte-calidad {
        max-width: 1180px;
        margin: 0 auto;
    }
    .reporte-header {
        background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
        color: #fff;
        padding: 1.25rem 1.4rem;
        border-radius: 16px;
        margin-bottom: 1.25rem;
    }
    .reporte-card {
        border: 1px solid #e9eef5;
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        height: 100%;
    }
    .mini-kpi {
        border: 1px solid #edf1f7;
        border-radius: 12px;
        padding: 0.9rem;
        background: #fff;
        height: 100%;
    }
    @media print {
        .btn, .no-print, .sidebar, nav, header, .navbar, .menu-section, .menu-header, .collapse, .topbar {
            display: none !important;
        }
        body {
            background: #fff !important;
        }
        .reporte-card, .mini-kpi, .reporte-header {
            box-shadow: none !important;
        }
    }
</style>

<div class="container-fluid mt-4 reporte-calidad">
    <div class="reporte-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 class="mb-1">📄 Reporte de Calidad</h1>
            <div>Período: <strong><?= htmlspecialchars(date('d/m/Y', strtotime((string)$desde))) ?></strong> al <strong><?= htmlspecialchars(date('d/m/Y', strtotime((string)$hasta))) ?></strong></div>
            <small>Generado: <?= htmlspecialchars(date('d/m/Y H:i')) ?></small>
        </div>
        <div class="d-flex gap-2 no-print">
            <a href="calidad.php?desde=<?= urlencode((string)$desde) ?>&hasta=<?= urlencode((string)$hasta) ?>" class="btn btn-light">← Volver</a>
            <button onclick="window.print()" class="btn btn-warning">🖨️ Imprimir</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="mini-kpi"><small class="text-muted d-block">Pedidos entregados</small><h3 class="mb-0"><?= number_format((int)$metricas['pedidos_entregados']) ?></h3></div></div>
        <div class="col-md-6 col-xl-3"><div class="mini-kpi"><small class="text-muted d-block">Reclamos</small><h3 class="mb-0"><?= number_format((int)$metricas['reclamos']) ?></h3><small class="text-muted">Tasa: <?= number_format($tasaReclamos, 1, ',', '.') ?>%</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="mini-kpi"><small class="text-muted d-block">Productos rehechos</small><h3 class="mb-0"><?= number_format((int)$metricas['productos_rehechos']) ?></h3><small class="text-muted">Tasa: <?= number_format($tasaRehechos, 1, ',', '.') ?>%</small></div></div>
        <div class="col-md-6 col-xl-3"><div class="mini-kpi"><small class="text-muted d-block">Demoras</small><h3 class="mb-0"><?= number_format((int)$metricas['demoras_entrega']) ?></h3><small class="text-muted">Promedio: <?= number_format((float)$metricas['demoras_promedio_dias'], 1, ',', '.') ?> días</small></div></div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card reporte-card">
                <div class="card-header bg-light"><strong>Indicadores clave</strong></div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li><strong>Instalaciones sin reclamo:</strong> <?= number_format((float)$metricas['porcentaje_instalaciones_sin_reclamo'], 1, ',', '.') ?>%</li>
                        <li><strong>Instalaciones relevadas:</strong> <?= number_format((int)$metricas['instalaciones_totales']) ?></li>
                        <li><strong>Instalaciones con reclamo:</strong> <?= number_format((int)$metricas['instalaciones_con_reclamo']) ?></li>
                        <li><strong>Satisfacción del cliente:</strong> <?= number_format((float)$metricas['satisfaccion_media'], 1, ',', '.') ?>/<?= number_format((float)$metricas['satisfaccion_escala'], 0, ',', '.') ?> (<?= number_format((float)$metricas['satisfaccion_porcentaje'], 1, ',', '.') ?>%)</li>
                        <li><strong>Respuestas consideradas:</strong> <?= number_format((int)$metricas['satisfaccion_respuestas']) ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card reporte-card">
                <div class="card-header bg-light"><strong>Lectura rápida</strong></div>
                <div class="card-body">
                    <p class="mb-2">Este reporte consolida entregas, reclamos, rehechos, demoras e indicadores de satisfacción para el período seleccionado.</p>
                    <ul class="mb-0">
                        <li>Una <strong>tasa de reclamos</strong> más baja indica menor fricción post-entrega.</li>
                        <li>Los <strong>rehechos</strong> ayudan a medir retrabajo y costo oculto.</li>
                        <li>Las <strong>demoras</strong> muestran riesgo operativo y de experiencia del cliente.</li>
                        <li>El porcentaje de <strong>instalaciones sin reclamo</strong> resume la calidad final del servicio.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="card reporte-card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
            <strong>Detalle de registros</strong>
            <span class="text-muted small"><?= count($eventos) ?> registro(s)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($eventos)): ?>
                <div class="p-4 text-center text-muted">No hay registros de calidad en este período.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Título</th>
                                <th>Cliente / Pedido</th>
                                <th>Detalle</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($eventos as $evento): ?>
                                <tr>
                                    <td><?= !empty($evento['fecha_evento']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$evento['fecha_evento']))) : '-' ?></td>
                                    <td><?= htmlspecialchars(ucfirst((string)($evento['tipo'] ?? ''))) ?></td>
                                    <td><?= htmlspecialchars((string)($evento['titulo'] ?? '')) ?></td>
                                    <td>
                                        <?= htmlspecialchars((string)($evento['cliente_nombre'] ?? '-')) ?>
                                        <?php if (!empty($evento['numero_pedido'])): ?>
                                            <div class="small text-muted">Pedido <?= htmlspecialchars((string)$evento['numero_pedido']) ?></div>
                                        <?php elseif (!empty($evento['pedido_id'])): ?>
                                            <div class="small text-muted">Pedido #<?= (int)$evento['pedido_id'] ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($evento['tipo'] ?? '') === 'rehecho'): ?>
                                            <div><strong>Cantidad:</strong> <?= (int)($evento['cantidad'] ?? 1) ?></div>
                                        <?php endif; ?>
                                        <?php if (($evento['tipo'] ?? '') === 'demora'): ?>
                                            <div><strong>Días:</strong> <?= (int)($evento['dias_demora'] ?? 0) ?></div>
                                        <?php endif; ?>
                                        <?php if (($evento['tipo'] ?? '') === 'satisfaccion' && $evento['puntaje_satisfaccion'] !== null): ?>
                                            <div><strong>Puntaje:</strong> <?= number_format((float)$evento['puntaje_satisfaccion'], 1, ',', '.') ?>/10</div>
                                        <?php endif; ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($evento['descripcion'] ?? 'Sin detalle')) ?></div>
                                    </td>
                                    <td><?= htmlspecialchars(ucfirst((string)($evento['estado'] ?? 'abierto'))) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
