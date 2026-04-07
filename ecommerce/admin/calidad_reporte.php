<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/calidad_helper.php';

$setupError = '';
try {
    ensureCalidadSchema($pdo);
} catch (Throwable $e) {
    $setupError = 'No se pudo inicializar el módulo de calidad: ' . $e->getMessage();
}

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

$metricas = [
    'pedidos_entregados' => 0,
    'reclamos' => 0,
    'reclamos_resueltos' => 0,
    'productos_rehechos' => 0,
    'demoras_entrega' => 0,
    'demoras_promedio_dias' => 0,
    'instalaciones_totales' => 0,
    'instalaciones_con_reclamo' => 0,
    'porcentaje_instalaciones_sin_reclamo' => 0,
    'satisfaccion_media' => 0,
    'satisfaccion_porcentaje' => 0,
    'satisfaccion_respuestas' => 0,
    'satisfaccion_escala' => 5,
];
$eventos = [];

try {
    $metricas = obtenerMetricasCalidad($pdo, $desde, $hasta);
    $eventos = obtenerEventosCalidad($pdo, $desde, $hasta, 300);
} catch (Throwable $e) {
    if ($setupError === '') {
        $setupError = 'No se pudieron cargar las métricas de calidad: ' . $e->getMessage();
    }
}

$tasaReclamos = ($metricas['pedidos_entregados'] ?? 0) > 0 ? ((float)$metricas['reclamos'] / (float)$metricas['pedidos_entregados']) * 100 : 0;
$tasaRehechos = ($metricas['pedidos_entregados'] ?? 0) > 0 ? ((float)$metricas['productos_rehechos'] / (float)$metricas['pedidos_entregados']) * 100 : 0;
$reclamosResueltos = (int)($metricas['reclamos_resueltos'] ?? 0);
$reclamosTotales = (int)($metricas['reclamos'] ?? 0);
$reclamosPendientes = max(0, $reclamosTotales - $reclamosResueltos);
$resolucionReclamos = $reclamosTotales > 0 ? ($reclamosResueltos / $reclamosTotales) * 100 : 100;
$eventosAbiertos = 0;
$resumenTipos = [
    'reclamo' => 0,
    'rehecho' => 0,
    'demora' => 0,
    'satisfaccion' => 0,
];

foreach ($eventos as $evento) {
    $tipoEvento = (string)($evento['tipo'] ?? '');
    if (isset($resumenTipos[$tipoEvento])) {
        $resumenTipos[$tipoEvento]++;
    }
    if ((string)($evento['estado'] ?? 'abierto') === 'abierto') {
        $eventosAbiertos++;
    }
}

$scoreRiesgo = 0;
if ($tasaReclamos >= 8) {
    $scoreRiesgo += 2;
} elseif ($tasaReclamos >= 4) {
    $scoreRiesgo++;
}
if ((float)($metricas['demoras_promedio_dias'] ?? 0) >= 4 || (int)($metricas['demoras_entrega'] ?? 0) >= 5) {
    $scoreRiesgo += 2;
} elseif ((float)($metricas['demoras_promedio_dias'] ?? 0) >= 2 || (int)($metricas['demoras_entrega'] ?? 0) >= 2) {
    $scoreRiesgo++;
}
if ((float)($metricas['satisfaccion_porcentaje'] ?? 0) > 0 && (float)($metricas['satisfaccion_porcentaje'] ?? 0) < 70) {
    $scoreRiesgo += 2;
} elseif ((float)($metricas['satisfaccion_porcentaje'] ?? 0) > 0 && (float)($metricas['satisfaccion_porcentaje'] ?? 0) < 85) {
    $scoreRiesgo++;
}
if ($reclamosPendientes >= 4 || $eventosAbiertos >= 6) {
    $scoreRiesgo += 2;
} elseif ($reclamosPendientes >= 1 || $eventosAbiertos >= 3) {
    $scoreRiesgo++;
}

$estadoGeneral = [
    'label' => 'Saludable',
    'class' => 'ok',
    'icon' => '✅',
    'descripcion' => 'El período muestra niveles controlados de incidencias y buena estabilidad operativa.',
];
if ($scoreRiesgo >= 5) {
    $estadoGeneral = [
        'label' => 'Atención inmediata',
        'class' => 'critical',
        'icon' => '🔴',
        'descripcion' => 'Se detectan señales de riesgo que conviene atacar rápido para evitar más impacto en clientes.',
    ];
} elseif ($scoreRiesgo >= 3) {
    $estadoGeneral = [
        'label' => 'En seguimiento',
        'class' => 'warning',
        'icon' => '🟡',
        'descripcion' => 'Hay desvíos moderados; conviene priorizar resolución y seguimiento esta semana.',
    ];
}

$hallazgos = [];
$hallazgos[] = $reclamosPendientes > 0
    ? 'Quedan ' . number_format($reclamosPendientes) . ' reclamo(s) pendiente(s) de cierre.'
    : 'No hay reclamos pendientes para el período analizado.';
$hallazgos[] = (int)($metricas['demoras_entrega'] ?? 0) > 0
    ? 'Se registraron ' . number_format((int)$metricas['demoras_entrega']) . ' demora(s), con un promedio de ' . number_format((float)($metricas['demoras_promedio_dias'] ?? 0), 1, ',', '.') . ' día(s).'
    : 'No se registraron demoras de entrega relevantes.';
$hallazgos[] = (float)($metricas['satisfaccion_porcentaje'] ?? 0) > 0
    ? 'La satisfacción se ubica en ' . number_format((float)$metricas['satisfaccion_porcentaje'], 1, ',', '.') . '% con ' . number_format((int)($metricas['satisfaccion_respuestas'] ?? 0)) . ' respuesta(s).'
    : 'Todavía no hay respuestas suficientes de satisfacción para medir la experiencia.';

$accionesSugeridas = [];
if ($reclamosPendientes > 0) {
    $accionesSugeridas[] = 'Cerrar primero los reclamos abiertos y dejar responsable + fecha de resolución.';
}
if ((int)($metricas['demoras_entrega'] ?? 0) > 0) {
    $accionesSugeridas[] = 'Revisar cuellos de botella en producción/instalación para bajar demoras.';
}
if ((float)($metricas['satisfaccion_porcentaje'] ?? 0) > 0 && (float)($metricas['satisfaccion_porcentaje'] ?? 0) < 85) {
    $accionesSugeridas[] = 'Contactar clientes con menor satisfacción para recuperar experiencia y detectar causas raíz.';
}
if (empty($accionesSugeridas)) {
    $accionesSugeridas[] = 'Mantener el estándar actual y seguir monitoreando reclamos, rehechos y satisfacción.';
}
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
    .reporte-summary {
        display: grid;
        grid-template-columns: 1.2fr .8fr;
        gap: 1rem;
        margin-bottom: 1.25rem;
    }
    .summary-box {
        border: 1px solid #e9eef5;
        border-radius: 14px;
        background: #fff;
        padding: 1rem 1.1rem;
        height: 100%;
    }
    .summary-status {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        font-weight: 700;
        border-radius: 999px;
        padding: .35rem .7rem;
        margin-bottom: .7rem;
    }
    .summary-status.ok {
        background: #e8f7ee;
        color: #146c43;
    }
    .summary-status.warning {
        background: #fff3cd;
        color: #997404;
    }
    .summary-status.critical {
        background: #f8d7da;
        color: #b02a37;
    }
    .summary-list,
    .summary-actions {
        margin: 0;
        padding-left: 1.1rem;
    }
    .summary-list li,
    .summary-actions li {
        margin-bottom: .45rem;
    }
    .priority-item {
        border: 1px solid #edf1f7;
        border-radius: 12px;
        padding: .75rem .85rem;
        background: #fafcff;
        margin-bottom: .65rem;
    }
    .priority-item:last-child {
        margin-bottom: 0;
    }
    .priority-value {
        font-size: 1.2rem;
        font-weight: 700;
        color: #0b5ed7;
    }
    .type-pill {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: .2rem .55rem;
        font-size: .78rem;
        font-weight: 600;
        background: #eef4ff;
        color: #285192;
    }
    @media (max-width: 991px) {
        .reporte-summary {
            grid-template-columns: 1fr;
        }
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
            <?php if ($setupError !== ''): ?>
                <div class="mt-2 alert alert-warning mb-0 py-2 px-3"><?= htmlspecialchars($setupError) ?></div>
            <?php endif; ?>
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

    <div class="reporte-summary">
        <div class="summary-box">
            <div class="summary-status <?= htmlspecialchars($estadoGeneral['class']) ?>"><?= $estadoGeneral['icon'] ?> <?= htmlspecialchars($estadoGeneral['label']) ?></div>
            <h5 class="mb-2">Resumen ejecutivo</h5>
            <p class="text-muted mb-3"><?= htmlspecialchars($estadoGeneral['descripcion']) ?></p>
            <ul class="summary-list">
                <?php foreach ($hallazgos as $hallazgo): ?>
                    <li><?= htmlspecialchars($hallazgo) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div class="summary-box">
            <h5 class="mb-3">Prioridades del período</h5>
            <div class="priority-item">
                <div class="text-muted small">Reclamos resueltos</div>
                <div class="priority-value"><?= number_format($resolucionReclamos, 1, ',', '.') ?>%</div>
                <div class="small text-muted"><?= number_format($reclamosResueltos) ?> de <?= number_format($reclamosTotales) ?> reclamo(s)</div>
            </div>
            <div class="priority-item">
                <div class="text-muted small">Registros abiertos</div>
                <div class="priority-value"><?= number_format($eventosAbiertos) ?></div>
                <div class="small text-muted">Pendientes de seguimiento o cierre</div>
            </div>
            <div class="priority-item">
                <div class="text-muted small">Acción sugerida</div>
                <ul class="summary-actions mb-0">
                    <?php foreach ($accionesSugeridas as $accionSugerida): ?>
                        <li><?= htmlspecialchars($accionSugerida) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
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
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>Distribución del período</strong>
                    <span class="type-pill"><?= number_format(count($eventos)) ?> evento(s)</span>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="type-pill">Reclamos: <?= number_format((int)$resumenTipos['reclamo']) ?></span>
                        <span class="type-pill">Rehechos: <?= number_format((int)$resumenTipos['rehecho']) ?></span>
                        <span class="type-pill">Demoras: <?= number_format((int)$resumenTipos['demora']) ?></span>
                        <span class="type-pill">Satisfacción: <?= number_format((int)$resumenTipos['satisfaccion']) ?></span>
                    </div>
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
