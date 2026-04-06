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

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? 'crear_evento');

    try {
        if ($action === 'cambiar_estado') {
            $id = (int)($_POST['id'] ?? 0);
            $nuevoEstado = (string)($_POST['estado'] ?? 'resuelto');
            $estadosValidos = ['abierto', 'resuelto', 'cerrado'];

            if ($id <= 0 || !in_array($nuevoEstado, $estadosValidos, true)) {
                throw new Exception('Registro o estado inválido.');
            }

            $stmt = $pdo->prepare("UPDATE ecommerce_calidad_eventos SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevoEstado, $id]);
            $mensaje = 'Estado de calidad actualizado correctamente.';
        } else {
            $tipo = (string)($_POST['tipo'] ?? 'reclamo');
            $tiposValidos = ['reclamo', 'rehecho', 'demora', 'satisfaccion'];
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $clienteNombre = trim((string)($_POST['cliente_nombre'] ?? ''));
            $pedidoId = !empty($_POST['pedido_id']) ? (int)$_POST['pedido_id'] : null;
            $instalacionTipo = trim((string)($_POST['instalacion_tipo'] ?? ''));
            $instalacionId = !empty($_POST['instalacion_id']) ? (int)$_POST['instalacion_id'] : null;
            $cantidad = max(1, (int)($_POST['cantidad'] ?? 1));
            $diasDemora = max(0, (int)($_POST['dias_demora'] ?? 0));
            $puntajeSatisfaccion = isset($_POST['puntaje_satisfaccion']) && $_POST['puntaje_satisfaccion'] !== '' ? (float)$_POST['puntaje_satisfaccion'] : null;
            $fechaEvento = (string)($_POST['fecha_evento'] ?? date('Y-m-d'));
            $estado = (string)($_POST['estado_registro'] ?? 'abierto');

            if (!in_array($tipo, $tiposValidos, true)) {
                throw new Exception('Tipo de registro inválido.');
            }
            if ($titulo === '') {
                throw new Exception('El título es obligatorio.');
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEvento)) {
                throw new Exception('La fecha del evento no es válida.');
            }
            if (!in_array($estado, ['abierto', 'resuelto', 'cerrado'], true)) {
                $estado = 'abierto';
            }
            if ($tipo !== 'demora') {
                $diasDemora = 0;
            }
            if ($tipo !== 'satisfaccion') {
                $puntajeSatisfaccion = null;
            }
            if ($puntajeSatisfaccion !== null && ($puntajeSatisfaccion < 0 || $puntajeSatisfaccion > 10)) {
                throw new Exception('La satisfacción debe estar entre 0 y 10.');
            }
            if (!in_array($instalacionTipo, ['', 'orden', 'manual'], true)) {
                $instalacionTipo = '';
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_calidad_eventos
                (tipo, titulo, descripcion, pedido_id, instalacion_tipo, instalacion_id, cliente_nombre, cantidad, dias_demora, puntaje_satisfaccion, fecha_evento, estado, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $tipo,
                $titulo,
                $descripcion !== '' ? $descripcion : null,
                $pedidoId,
                $instalacionTipo !== '' ? $instalacionTipo : null,
                $instalacionId,
                $clienteNombre !== '' ? $clienteNombre : null,
                $cantidad,
                $diasDemora,
                $puntajeSatisfaccion,
                $fechaEvento,
                $estado,
                $_SESSION['user']['id'] ?? null,
            ]);

            $mensaje = 'Registro de calidad guardado correctamente.';
        }
    } catch (Throwable $e) {
        $error = 'Error: ' . $e->getMessage();
    }
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
$serieMensual = ['labels' => [], 'series' => []];

try {
    $metricas = obtenerMetricasCalidad($pdo, $desde, $hasta);
    $eventos = obtenerEventosCalidad($pdo, $desde, $hasta, 60);
    $serieMensual = obtenerSerieCalidadMensual($pdo, 6);
} catch (Throwable $e) {
    if ($setupError === '') {
        $setupError = 'No se pudieron cargar las métricas de calidad: ' . $e->getMessage();
    }
}
?>

<style>
    .quality-card {
        border: 1px solid #e9eef5;
        border-radius: 14px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        height: 100%;
    }
    .quality-kpi {
        border-radius: 14px;
        padding: 1rem;
        border: 1px solid #eef2f7;
        background: #fff;
        height: 100%;
    }
    .quality-kpi .icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }
</style>

<div class="container-fluid mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">🏅 Calidad</h1>
            <p class="text-muted mb-0">Seguimiento de entregas, reclamos, rehechos, demoras e indicadores de satisfacción.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="calidad_reporte.php?desde=<?= urlencode((string)$desde) ?>&hasta=<?= urlencode((string)$hasta) ?>" class="btn btn-primary" target="_blank">🖨️ Reporte</a>
            <a href="instalaciones.php" class="btn btn-outline-secondary">Instalaciones</a>
            <a href="encuestas.php" class="btn btn-outline-secondary">Encuestas</a>
        </div>
    </div>

    <?php if ($mensaje !== ''): ?>
        <div class="alert alert-success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($setupError !== ''): ?>
        <div class="alert alert-warning" role="alert">
            <?= htmlspecialchars($setupError) ?>
            <div class="mt-2">
                <a href="setup_calidad.php" class="btn btn-sm btn-outline-dark">Inicializar módulo</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card quality-card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="desde" class="form-label">Desde</label>
                    <input type="date" class="form-control" id="desde" name="desde" value="<?= htmlspecialchars((string)$desde) ?>">
                </div>
                <div class="col-md-3">
                    <label for="hasta" class="form-label">Hasta</label>
                    <input type="date" class="form-control" id="hasta" name="hasta" value="<?= htmlspecialchars((string)$hasta) ?>">
                </div>
                <div class="col-md-6 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Aplicar filtro</button>
                    <a href="calidad.php" class="btn btn-outline-secondary">Mes actual</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Pedidos entregados</small>
                        <h3 class="mb-0"><?= number_format((int)$metricas['pedidos_entregados']) ?></h3>
                    </div>
                    <span class="icon bg-success-subtle text-success"><i class="bi bi-truck"></i></span>
                </div>
                <small class="text-muted">Pedidos con estado <code>entregado</code></small>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Reclamos</small>
                        <h3 class="mb-0"><?= number_format((int)$metricas['reclamos']) ?></h3>
                    </div>
                    <span class="icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-octagon"></i></span>
                </div>
                <small class="text-muted">Resueltos: <?= number_format((int)$metricas['reclamos_resueltos']) ?></small>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Productos rehechos</small>
                        <h3 class="mb-0"><?= number_format((int)$metricas['productos_rehechos']) ?></h3>
                    </div>
                    <span class="icon bg-warning-subtle text-warning"><i class="bi bi-arrow-repeat"></i></span>
                </div>
                <small class="text-muted">Unidades registradas</small>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Demoras de entrega</small>
                        <h3 class="mb-0"><?= number_format((int)$metricas['demoras_entrega']) ?></h3>
                    </div>
                    <span class="icon bg-info-subtle text-info"><i class="bi bi-clock-history"></i></span>
                </div>
                <small class="text-muted">Promedio: <?= number_format((float)$metricas['demoras_promedio_dias'], 1, ',', '.') ?> días</small>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Instalaciones sin reclamo</small>
                        <h3 class="mb-0"><?= number_format((float)$metricas['porcentaje_instalaciones_sin_reclamo'], 1, ',', '.') ?>%</h3>
                    </div>
                    <span class="icon bg-primary-subtle text-primary"><i class="bi bi-shield-check"></i></span>
                </div>
                <small class="text-muted"><?= max(0, (int)$metricas['instalaciones_totales'] - (int)$metricas['instalaciones_con_reclamo']) ?> de <?= number_format((int)$metricas['instalaciones_totales']) ?> instalaciones</small>
                <div class="progress mt-2" style="height: 7px;">
                    <div class="progress-bar bg-primary" style="width: <?= max(0, min(100, (float)$metricas['porcentaje_instalaciones_sin_reclamo'])) ?>%"></div>
                </div>
            </div>
        </div>

        <div class="col-md-6 col-xl-2">
            <div class="quality-kpi">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <small class="text-muted d-block">Satisfacción del cliente</small>
                        <h3 class="mb-0"><?= number_format((float)$metricas['satisfaccion_media'], 1, ',', '.') ?>/<?= number_format((float)$metricas['satisfaccion_escala'], 0, ',', '.') ?></h3>
                    </div>
                    <span class="icon bg-secondary-subtle text-secondary"><i class="bi bi-emoji-smile"></i></span>
                </div>
                <small class="text-muted"><?= number_format((float)$metricas['satisfaccion_porcentaje'], 1, ',', '.') ?>% · <?= number_format((int)$metricas['satisfaccion_respuestas']) ?> respuesta(s)</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card quality-card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">📈 Evolución mensual</h5>
                    <small class="text-muted">Últimos 6 meses de entregas e incidencias</small>
                </div>
                <div class="card-body">
                    <canvas id="chartCalidadMensual" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card quality-card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">🎯 Indicadores</h5>
                    <small class="text-muted">Satisfacción y calidad de instalación</small>
                </div>
                <div class="card-body">
                    <canvas id="chartIndicadoresCalidad" height="220"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card quality-card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">➕ Registrar novedad de calidad</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="crear_evento">
                        <div class="col-md-6">
                            <label class="form-label">Tipo</label>
                            <select name="tipo" class="form-select" required>
                                <option value="reclamo">Reclamo</option>
                                <option value="rehecho">Producto rehecho</option>
                                <option value="demora">Demora de entrega</option>
                                <option value="satisfaccion">Satisfacción manual</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha</label>
                            <input type="date" name="fecha_evento" class="form-control" value="<?= htmlspecialchars(date('Y-m-d')) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Título</label>
                            <input type="text" name="titulo" class="form-control" placeholder="Ej: Reclamo por color / Reposición de pieza" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Cliente</label>
                            <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre del cliente">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pedido ID</label>
                            <input type="number" name="pedido_id" class="form-control" min="1" placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="cantidad" class="form-control" min="1" value="1">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tipo instalación</label>
                            <select name="instalacion_tipo" class="form-select">
                                <option value="">Sin vincular</option>
                                <option value="orden">Orden</option>
                                <option value="manual">Manual</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ID instalación</label>
                            <input type="number" name="instalacion_id" class="form-control" min="1" placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Días de demora</label>
                            <input type="number" name="dias_demora" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Satisfacción (0 a 10)</label>
                            <input type="number" name="puntaje_satisfaccion" class="form-control" min="0" max="10" step="0.1" placeholder="Opcional">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select name="estado_registro" class="form-select">
                                <option value="abierto">Abierto</option>
                                <option value="resuelto">Resuelto</option>
                                <option value="cerrado">Cerrado</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="descripcion" class="form-control" rows="4" placeholder="Detalle del reclamo, rehecho o seguimiento"></textarea>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Guardar registro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card quality-card">
                <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">📋 Registros del período</h5>
                    <small class="text-muted">Usá esta lista para dar seguimiento a reclamos y rehechos.</small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($eventos)): ?>
                        <div class="p-4 text-center text-muted">No hay registros de calidad para el rango seleccionado.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Título</th>
                                        <th>Cliente / Pedido</th>
                                        <th>Detalle</th>
                                        <th>Estado</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($eventos as $evento): ?>
                                        <?php
                                            $tipo = (string)($evento['tipo'] ?? '');
                                            $badge = 'secondary';
                                            if ($tipo === 'reclamo') $badge = 'danger';
                                            if ($tipo === 'rehecho') $badge = 'warning text-dark';
                                            if ($tipo === 'demora') $badge = 'info text-dark';
                                            if ($tipo === 'satisfaccion') $badge = 'success';
                                            $estadoActual = (string)($evento['estado'] ?? 'abierto');
                                        ?>
                                        <tr>
                                            <td><?= !empty($evento['fecha_evento']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$evento['fecha_evento']))) : '-' ?></td>
                                            <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars(ucfirst($tipo)) ?></span></td>
                                            <td>
                                                <strong><?= htmlspecialchars((string)($evento['titulo'] ?? '')) ?></strong>
                                                <?php if (!empty($evento['instalacion_id'])): ?>
                                                    <div class="small text-muted">Instalación <?= htmlspecialchars((string)($evento['instalacion_tipo'] ?? '')) ?> #<?= (int)$evento['instalacion_id'] ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars((string)($evento['cliente_nombre'] ?? '-')) ?>
                                                <?php if (!empty($evento['numero_pedido'])): ?>
                                                    <div class="small text-muted">Pedido <?= htmlspecialchars((string)$evento['numero_pedido']) ?></div>
                                                <?php elseif (!empty($evento['pedido_id'])): ?>
                                                    <div class="small text-muted">Pedido #<?= (int)$evento['pedido_id'] ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($tipo === 'rehecho'): ?>
                                                    <div><strong>Cantidad:</strong> <?= (int)($evento['cantidad'] ?? 1) ?></div>
                                                <?php endif; ?>
                                                <?php if ($tipo === 'demora'): ?>
                                                    <div><strong>Demora:</strong> <?= (int)($evento['dias_demora'] ?? 0) ?> día(s)</div>
                                                <?php endif; ?>
                                                <?php if ($tipo === 'satisfaccion' && $evento['puntaje_satisfaccion'] !== null): ?>
                                                    <div><strong>Puntaje:</strong> <?= number_format((float)$evento['puntaje_satisfaccion'], 1, ',', '.') ?>/10</div>
                                                <?php endif; ?>
                                                <div class="small text-muted"><?= htmlspecialchars((string)($evento['descripcion'] ?? 'Sin detalle')) ?></div>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $estadoActual === 'abierto' ? 'danger' : ($estadoActual === 'resuelto' ? 'success' : 'secondary') ?>">
                                                    <?= htmlspecialchars(ucfirst($estadoActual)) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-flex gap-1 flex-wrap">
                                                    <input type="hidden" name="action" value="cambiar_estado">
                                                    <input type="hidden" name="id" value="<?= (int)$evento['id'] ?>">
                                                    <?php if ($estadoActual !== 'resuelto'): ?>
                                                        <button type="submit" name="estado" value="resuelto" class="btn btn-sm btn-outline-success">Resolver</button>
                                                    <?php endif; ?>
                                                    <?php if ($estadoActual !== 'cerrado'): ?>
                                                        <button type="submit" name="estado" value="cerrado" class="btn btn-sm btn-outline-secondary">Cerrar</button>
                                                    <?php endif; ?>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const calidadSeries = <?= json_encode($serieMensual, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

if (typeof Chart !== 'undefined') {
    const ctxMensual = document.getElementById('chartCalidadMensual');
    if (ctxMensual) {
        new Chart(ctxMensual, {
            type: 'bar',
            data: {
                labels: calidadSeries.labels || [],
                datasets: [
                    {
                        label: 'Pedidos entregados',
                        data: (calidadSeries.series && calidadSeries.series.pedidos_entregados) || [],
                        backgroundColor: 'rgba(25, 135, 84, 0.65)',
                        borderColor: 'rgba(25, 135, 84, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Reclamos',
                        data: (calidadSeries.series && calidadSeries.series.reclamos) || [],
                        backgroundColor: 'rgba(220, 53, 69, 0.65)',
                        borderColor: 'rgba(220, 53, 69, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Rehechos',
                        data: (calidadSeries.series && calidadSeries.series.rehechos) || [],
                        backgroundColor: 'rgba(255, 193, 7, 0.65)',
                        borderColor: 'rgba(255, 193, 7, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Demoras',
                        data: (calidadSeries.series && calidadSeries.series.demoras) || [],
                        backgroundColor: 'rgba(13, 202, 240, 0.65)',
                        borderColor: 'rgba(13, 202, 240, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 }
                    }
                }
            }
        });
    }

    const ctxIndicadores = document.getElementById('chartIndicadoresCalidad');
    if (ctxIndicadores) {
        new Chart(ctxIndicadores, {
            type: 'line',
            data: {
                labels: calidadSeries.labels || [],
                datasets: [
                    {
                        label: '% instalaciones sin reclamo',
                        data: (calidadSeries.series && calidadSeries.series.instalaciones_sin_reclamo_pct) || [],
                        borderColor: 'rgba(13, 110, 253, 1)',
                        backgroundColor: 'rgba(13, 110, 253, 0.15)',
                        fill: true,
                        tension: 0.25
                    },
                    {
                        label: '% satisfacción cliente',
                        data: (calidadSeries.series && calidadSeries.series.satisfaccion_pct) || [],
                        borderColor: 'rgba(108, 117, 125, 1)',
                        backgroundColor: 'rgba(108, 117, 125, 0.15)',
                        fill: true,
                        tension: 0.25
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
}
</script>

<?php require 'includes/footer.php'; ?>
