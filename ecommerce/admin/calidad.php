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

if (!function_exists('calidad_formatear_atributos_pedido')) {
    function calidad_formatear_atributos_pedido(PDO $pdo, $atributosRaw): string
    {
        $atributosRaw = trim((string)$atributosRaw);
        if ($atributosRaw === '') {
            return '';
        }

        $atributos = json_decode($atributosRaw, true);
        if (!is_array($atributos)) {
            return $atributosRaw;
        }

        $partes = [];
        $opcionesCache = [];
        $puedeBuscarOpciones = calidad_table_exists($pdo, 'ecommerce_atributo_opciones');

        foreach ($atributos as $attr) {
            if (!is_array($attr)) {
                continue;
            }

            $nombre = trim((string)($attr['nombre'] ?? ''));
            $valor = trim((string)($attr['valor'] ?? ''));

            if ($valor !== '' && ctype_digit($valor) && $puedeBuscarOpciones) {
                $opcionId = (int)$valor;
                if ($opcionId > 0) {
                    if (!array_key_exists($opcionId, $opcionesCache)) {
                        try {
                            $stmtOpcion = $pdo->prepare("SELECT nombre FROM ecommerce_atributo_opciones WHERE id = ? LIMIT 1");
                            $stmtOpcion->execute([$opcionId]);
                            $opcionesCache[$opcionId] = (string)($stmtOpcion->fetchColumn() ?: '');
                        } catch (Throwable $e) {
                            $opcionesCache[$opcionId] = '';
                        }
                    }

                    if ($opcionesCache[$opcionId] !== '') {
                        $valor = $opcionesCache[$opcionId];
                    }
                }
            }

            if ($nombre === '' && $valor === '') {
                continue;
            }

            $parte = $nombre !== '' ? ($nombre . ': ' . $valor) : $valor;
            $costoAdicional = isset($attr['costo_adicional']) ? (float)$attr['costo_adicional'] : 0.0;
            if ($costoAdicional > 0) {
                $parte .= ' (+$' . number_format($costoAdicional, 0, ',', '.') . ')';
            }

            $partes[] = trim($parte, ': ');
        }

        return implode(' · ', $partes);
    }
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
$pedidoCalidadId = max(0, (int)($_GET['pedido_id'] ?? ($_POST['pedido_id'] ?? 0)));
$pedidoCalidad = null;
$pedidoCalidadItems = [];
$inspeccionPedido = null;
$inspeccionItemsMap = [];

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
        } elseif ($action === 'guardar_inspeccion_pedido') {
            $pedidoId = (int)($_POST['pedido_id'] ?? 0);
            $clienteNombre = trim((string)($_POST['cliente_nombre'] ?? ''));
            $estadoCalidad = (string)($_POST['estado_calidad'] ?? 'pendiente');
            $pruebaAprobada = (int)($_POST['prueba_aprobada'] ?? 0) === 1 ? 1 : 0;
            $detalleRevision = trim((string)($_POST['detalle_revision'] ?? ''));
            $observaciones = trim((string)($_POST['observaciones'] ?? ''));
            $fechaRevisionInput = trim((string)($_POST['fecha_revision'] ?? ''));

            if ($pedidoId <= 0) {
                throw new Exception('Pedido inválido para el control de calidad.');
            }
            if (!in_array($estadoCalidad, ['pendiente', 'aprobado', 'observado', 'rechazado'], true)) {
                $estadoCalidad = 'pendiente';
            }

            $fechaRevision = date('Y-m-d H:i:s');
            if ($fechaRevisionInput !== '') {
                $fechaRevisionNormalizada = str_replace('T', ' ', $fechaRevisionInput);
                $tsRevision = strtotime($fechaRevisionNormalizada);
                if ($tsRevision !== false) {
                    $fechaRevision = date('Y-m-d H:i:s', $tsRevision);
                }
            }

            $itemEstados = $_POST['item_estado'] ?? [];
            $itemObservaciones = $_POST['item_observacion'] ?? [];
            $itemNombres = $_POST['item_nombre'] ?? [];
            $itemCantidades = $_POST['item_cantidad'] ?? [];
            $itemsRevision = [];

            if (is_array($itemNombres)) {
                foreach ($itemNombres as $itemId => $itemNombre) {
                    $itemId = (int)$itemId;
                    $estadoItem = (string)($itemEstados[$itemId] ?? 'ok');
                    if (!in_array($estadoItem, ['ok', 'observado', 'rechazado', 'no_terminada'], true)) {
                        $estadoItem = 'ok';
                    }

                    $itemsRevision[] = [
                        'item_id' => $itemId,
                        'producto' => trim((string)$itemNombre),
                        'cantidad' => (string)($itemCantidades[$itemId] ?? ''),
                        'estado' => $estadoItem,
                        'observacion' => trim((string)($itemObservaciones[$itemId] ?? '')),
                    ];
                }
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_calidad_inspecciones
                (pedido_id, cliente_nombre, estado_calidad, prueba_aprobada, detalle_revision, observaciones, items_json, revisado_por, fecha_revision)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    cliente_nombre = VALUES(cliente_nombre),
                    estado_calidad = VALUES(estado_calidad),
                    prueba_aprobada = VALUES(prueba_aprobada),
                    detalle_revision = VALUES(detalle_revision),
                    observaciones = VALUES(observaciones),
                    items_json = VALUES(items_json),
                    revisado_por = VALUES(revisado_por),
                    fecha_revision = VALUES(fecha_revision)");
            $stmt->execute([
                $pedidoId,
                $clienteNombre !== '' ? $clienteNombre : null,
                $estadoCalidad,
                $pruebaAprobada,
                $detalleRevision !== '' ? $detalleRevision : null,
                $observaciones !== '' ? $observaciones : null,
                !empty($itemsRevision) ? json_encode($itemsRevision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                $_SESSION['user']['id'] ?? null,
                $fechaRevision,
            ]);

            if (($estadoCalidad === 'observado' || $estadoCalidad === 'rechazado' || $pruebaAprobada === 0) && ($detalleRevision !== '' || $observaciones !== '')) {
                $tituloEvento = 'Control de calidad pedido #' . $pedidoId;
                $descripcionEvento = trim($detalleRevision . "\n" . $observaciones);
                $estadoEvento = $estadoCalidad === 'aprobado' ? 'resuelto' : 'abierto';
                $fechaEvento = date('Y-m-d', strtotime($fechaRevision));

                $stmtExiste = $pdo->prepare("SELECT id FROM ecommerce_calidad_eventos WHERE pedido_id = ? AND tipo = 'reclamo' AND titulo = ? LIMIT 1");
                $stmtExiste->execute([$pedidoId, $tituloEvento]);
                $eventoExistenteId = (int)$stmtExiste->fetchColumn();

                if ($eventoExistenteId > 0) {
                    $stmtEvento = $pdo->prepare("UPDATE ecommerce_calidad_eventos SET descripcion = ?, cliente_nombre = ?, fecha_evento = ?, estado = ? WHERE id = ?");
                    $stmtEvento->execute([
                        $descripcionEvento !== '' ? $descripcionEvento : null,
                        $clienteNombre !== '' ? $clienteNombre : null,
                        $fechaEvento,
                        $estadoEvento,
                        $eventoExistenteId,
                    ]);
                } else {
                    $stmtEvento = $pdo->prepare("INSERT INTO ecommerce_calidad_eventos (tipo, titulo, descripcion, pedido_id, cliente_nombre, cantidad, fecha_evento, estado, creado_por) VALUES ('reclamo', ?, ?, ?, ?, 1, ?, ?, ?)");
                    $stmtEvento->execute([
                        $tituloEvento,
                        $descripcionEvento !== '' ? $descripcionEvento : null,
                        $pedidoId,
                        $clienteNombre !== '' ? $clienteNombre : null,
                        $fechaEvento,
                        $estadoEvento,
                        $_SESSION['user']['id'] ?? null,
                    ]);
                }
            }

            $pedidoCalidadId = $pedidoId;
            $mensaje = 'Control de calidad del pedido guardado correctamente.';
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

if ($pedidoCalidadId > 0) {
    try {
        $stmtPedidoCalidad = $pdo->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email FROM ecommerce_pedidos p LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id WHERE p.id = ? LIMIT 1");
        $stmtPedidoCalidad->execute([$pedidoCalidadId]);
        $pedidoCalidad = $stmtPedidoCalidad->fetch(PDO::FETCH_ASSOC) ?: null;

        if ($pedidoCalidad) {
            if (calidad_table_exists($pdo, 'ecommerce_pedido_items')) {
                $stmtItemsCalidad = $pdo->prepare("SELECT pi.id, pi.cantidad, pi.ancho_cm, pi.alto_cm, pi.atributos, COALESCE(pr.nombre, 'Producto') AS producto_nombre FROM ecommerce_pedido_items pi LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id WHERE pi.pedido_id = ? ORDER BY pi.id ASC");
                $stmtItemsCalidad->execute([$pedidoCalidadId]);
                $pedidoCalidadItems = $stmtItemsCalidad->fetchAll(PDO::FETCH_ASSOC) ?: [];

                foreach ($pedidoCalidadItems as &$pedidoCalidadItem) {
                    $pedidoCalidadItem['atributos_legibles'] = calidad_formatear_atributos_pedido($pdo, $pedidoCalidadItem['atributos'] ?? '');
                }
                unset($pedidoCalidadItem);
            }

            $inspeccionPedido = obtenerInspeccionCalidadPedido($pdo, $pedidoCalidadId);
            if (!empty($inspeccionPedido['items_revision']) && is_array($inspeccionPedido['items_revision'])) {
                foreach ($inspeccionPedido['items_revision'] as $itemRevision) {
                    $inspeccionItemsMap[(int)($itemRevision['item_id'] ?? 0)] = $itemRevision;
                }
            }
        } elseif ($error === '') {
            $error = 'No se encontró el pedido seleccionado para calidad.';
        }
    } catch (Throwable $e) {
        if ($error === '') {
            $error = 'No se pudo cargar el pedido para control de calidad: ' . $e->getMessage();
        }
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

    <?php if ($pedidoCalidad): ?>
        <div class="card quality-card mb-4 border-primary">
            <div class="card-header bg-primary-subtle d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">🔎 Control de calidad del pedido <?= htmlspecialchars((string)($pedidoCalidad['numero_pedido'] ?? ('#' . (int)$pedidoCalidadId))) ?></h5>
                    <small class="text-muted">Revisá el detalle del pedido, marcá si pasó la prueba y dejá el informe listo para imprimir.</small>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="pedidos.php" class="btn btn-sm btn-outline-secondary">Volver a pedidos</a>
                    <a href="calidad_reporte_pedido.php?pedido_id=<?= (int)$pedidoCalidad['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">📄 Reporte PDF</a>
                    <?php if ($inspeccionPedido): ?>
                        <a href="calidad_inspeccion_pdf.php?pedido_id=<?= (int)$pedidoCalidad['id'] ?>" class="btn btn-sm btn-primary" target="_blank">🖨️ PDF calidad</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-3"><div class="small text-muted">Cliente</div><div class="fw-semibold"><?= htmlspecialchars((string)($pedidoCalidad['cliente_nombre'] ?? 'Sin cliente')) ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">Estado pedido</div><div class="fw-semibold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', (string)($pedidoCalidad['estado'] ?? '-')))) ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">Fecha</div><div class="fw-semibold"><?= !empty($pedidoCalidad['fecha_pedido']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$pedidoCalidad['fecha_pedido']))) : '-' ?></div></div>
                    <div class="col-md-3"><div class="small text-muted">Total</div><div class="fw-semibold">$<?= number_format((float)($pedidoCalidad['total'] ?? 0), 2, ',', '.') ?></div></div>
                </div>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_inspeccion_pedido">
                    <input type="hidden" name="pedido_id" value="<?= (int)$pedidoCalidad['id'] ?>">
                    <input type="hidden" name="cliente_nombre" value="<?= htmlspecialchars((string)($pedidoCalidad['cliente_nombre'] ?? '')) ?>">

                    <div class="col-md-4">
                        <label class="form-label">Estado del control</label>
                        <select name="estado_calidad" class="form-select">
                            <?php $estadoControlActual = (string)($inspeccionPedido['estado_calidad'] ?? 'pendiente'); ?>
                            <option value="pendiente" <?= $estadoControlActual === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                            <option value="aprobado" <?= $estadoControlActual === 'aprobado' ? 'selected' : '' ?>>Aprobado</option>
                            <option value="observado" <?= $estadoControlActual === 'observado' ? 'selected' : '' ?>>Observado</option>
                            <option value="rechazado" <?= $estadoControlActual === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">¿Pasó la prueba de calidad?</label>
                        <?php $pasoPrueba = (int)($inspeccionPedido['prueba_aprobada'] ?? 0); ?>
                        <select name="prueba_aprobada" class="form-select">
                            <option value="1" <?= $pasoPrueba === 1 ? 'selected' : '' ?>>Sí</option>
                            <option value="0" <?= $pasoPrueba !== 1 ? 'selected' : '' ?>>No</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Fecha de revisión</label>
                        <input type="datetime-local" name="fecha_revision" class="form-control" value="<?= htmlspecialchars(!empty($inspeccionPedido['fecha_revision']) ? date('Y-m-d\TH:i', strtotime((string)$inspeccionPedido['fecha_revision'])) : date('Y-m-d\TH:i')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Detalle detectado en el pedido</label>
                        <textarea name="detalle_revision" class="form-control" rows="3" placeholder="Ej: control de medidas, terminación, embalaje, herrajes, limpieza final"><?= htmlspecialchars((string)($inspeccionPedido['detalle_revision'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones finales</label>
                        <textarea name="observaciones" class="form-control" rows="3" placeholder="Conclusión del control de calidad y acciones a tomar"><?= htmlspecialchars((string)($inspeccionPedido['observaciones'] ?? '')) ?></textarea>
                    </div>

                    <div class="col-12">
                        <div class="border rounded-3 p-3 bg-light-subtle">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                <h6 class="mb-0">Detalle del pedido a revisar</h6>
                                <small class="text-muted">Marcá cada ítem como OK, observado, rechazado o no terminada.</small>
                            </div>
                            <?php if (empty($pedidoCalidadItems)): ?>
                                <div class="text-muted">No se encontraron ítems cargados para este pedido.</div>
                            <?php else: ?>
                                <div class="row g-3">
                                    <?php foreach ($pedidoCalidadItems as $itemCalidad): ?>
                                        <?php
                                            $itemIdCalidad = (int)($itemCalidad['id'] ?? 0);
                                            $itemRevision = $inspeccionItemsMap[$itemIdCalidad] ?? [];
                                            $medidasCalidad = '';
                                            if (!empty($itemCalidad['ancho_cm']) || !empty($itemCalidad['alto_cm'])) {
                                                $medidasCalidad = ($itemCalidad['ancho_cm'] !== null && $itemCalidad['ancho_cm'] !== '' ? $itemCalidad['ancho_cm'] : '-') . 'x' . ($itemCalidad['alto_cm'] !== null && $itemCalidad['alto_cm'] !== '' ? $itemCalidad['alto_cm'] : '-') . ' cm';
                                            }
                                        ?>
                                        <div class="col-12">
                                            <div class="border rounded p-3 bg-white">
                                                <div class="row g-2 align-items-end">
                                                    <div class="col-lg-5">
                                                        <div class="fw-semibold"><?= htmlspecialchars((string)($itemCalidad['producto_nombre'] ?? 'Producto')) ?></div>
                                                        <div class="small text-muted">Cantidad: <?= htmlspecialchars((string)($itemCalidad['cantidad'] ?? '1')) ?><?= $medidasCalidad !== '' ? ' · ' . htmlspecialchars($medidasCalidad) : '' ?></div>
                                                        <?php if (!empty($itemCalidad['atributos_legibles'])): ?>
                                                            <div class="small text-muted">Atributos: <?= htmlspecialchars((string)$itemCalidad['atributos_legibles']) ?></div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="col-lg-3">
                                                        <label class="form-label">Resultado ítem</label>
                                                        <select name="item_estado[<?= $itemIdCalidad ?>]" class="form-select">
                                                            <?php $estadoItemActual = (string)($itemRevision['estado'] ?? 'ok'); ?>
                                                            <option value="ok" <?= $estadoItemActual === 'ok' ? 'selected' : '' ?>>OK</option>
                                                            <option value="observado" <?= $estadoItemActual === 'observado' ? 'selected' : '' ?>>Observado</option>
                                                            <option value="rechazado" <?= $estadoItemActual === 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                                                            <option value="no_terminada" <?= $estadoItemActual === 'no_terminada' ? 'selected' : '' ?>>No terminada</option>
                                                        </select>
                                                    </div>
                                                    <div class="col-lg-4">
                                                        <label class="form-label">Observación del ítem</label>
                                                        <input type="text" name="item_observacion[<?= $itemIdCalidad ?>]" class="form-control" value="<?= htmlspecialchars((string)($itemRevision['observacion'] ?? '')) ?>" placeholder="Detalle puntual si hubo problema">
                                                    </div>
                                                </div>
                                                <input type="hidden" name="item_nombre[<?= $itemIdCalidad ?>]" value="<?= htmlspecialchars((string)($itemCalidad['producto_nombre'] ?? 'Producto')) ?>">
                                                <input type="hidden" name="item_cantidad[<?= $itemIdCalidad ?>]" value="<?= htmlspecialchars((string)($itemCalidad['cantidad'] ?? '1')) ?>">
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">Guardar control de calidad</button>
                        <a href="calidad_reporte_pedido.php?pedido_id=<?= (int)$pedidoCalidad['id'] ?>" class="btn btn-outline-primary" target="_blank">Reporte PDF</a>
                        <?php if ($inspeccionPedido): ?>
                            <a href="calidad_inspeccion_pdf.php?pedido_id=<?= (int)$pedidoCalidad['id'] ?>" class="btn btn-outline-dark" target="_blank">Imprimir informe PDF</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
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
                            <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre del cliente" value="<?= htmlspecialchars((string)($pedidoCalidad['cliente_nombre'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Pedido ID</label>
                            <input type="number" name="pedido_id" class="form-control" min="1" placeholder="Opcional" value="<?= $pedidoCalidad ? (int)$pedidoCalidad['id'] : '' ?>">
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
