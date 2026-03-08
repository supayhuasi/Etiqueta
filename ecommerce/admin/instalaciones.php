<?php
require 'includes/header.php';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$instalacion_desde = $_GET['instalacion_desde'] ?? '';
$instalacion_hasta = $_GET['instalacion_hasta'] ?? '';
$incluir_entregados = !empty($_GET['incluir_entregados']);
$pedidos = [];
$pedidos_por_fecha = [];
$pedidos_sin_fecha = [];
$error_pagina = '';

// Manejar programación de instalaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'programar_instalacion') {
    $orden_ids = $_POST['orden_ids'] ?? [];
    $fecha_instalacion = trim($_POST['fecha_instalacion'] ?? '');
    if (!empty($orden_ids) && $fecha_instalacion) {
        $ids = array_map('intval', $orden_ids);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$fecha_instalacion], $ids);
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET fecha_instalacion = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
        } catch (Throwable $e) {
            // registrar error pero continuar
            error_log('Error programando instalaciones: ' . $e->getMessage());
        }
    }
    // Redirigir para evitar reenvío de formulario
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: instalaciones.php' . ($qs ? ('?' . $qs) : ''));
    exit;
}

try {
    $sql = "
        SELECT op.id AS orden_id, op.pedido_id, op.estado AS estado_produccion, op.fecha_entrega, op.fecha_instalacion,
               p.numero_pedido, p.envio_nombre, p.envio_telefono, p.envio_direccion,
               p.envio_localidad, p.envio_provincia, p.envio_codigo_postal, p.fecha_pedido AS fecha_creacion,
               c.nombre AS cliente_nombre
        FROM ecommerce_ordenes_produccion op
        JOIN ecommerce_pedidos p ON op.pedido_id = p.id
        LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
        WHERE " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'") . "
    ";
    $params = [];
    if ($fecha_desde !== '') {
        $sql .= " AND DATE(p.fecha_pedido) >= ?";
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $sql .= " AND DATE(p.fecha_pedido) <= ?";
        $params[] = $fecha_hasta;
    }
    if ($instalacion_desde !== '') {
        $sql .= " AND op.fecha_instalacion >= ?";
        $params[] = $instalacion_desde;
    }
    if ($instalacion_hasta !== '') {
        $sql .= " AND op.fecha_instalacion <= ?";
        $params[] = $instalacion_hasta;
    }
    $sql .= " ORDER BY op.fecha_instalacion IS NULL, op.fecha_instalacion ASC, p.fecha_pedido DESC, op.pedido_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pedidos as $row) {
        if (!empty($row['fecha_instalacion'])) {
            $pedidos_por_fecha[$row['fecha_instalacion']][] = $row;
        } else {
            $pedidos_sin_fecha[] = $row;
        }
    }

    ksort($pedidos_por_fecha);
} catch (Throwable $e) {
    $error_pagina = $e->getMessage();
}

$qs = http_build_query(array_filter([
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'instalacion_desde' => $instalacion_desde,
    'instalacion_hasta' => $instalacion_hasta,
    'incluir_entregados' => $incluir_entregados ? '1' : null,
]));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Instalaciones</h1>
        <p class="text-muted">Agenda por fecha para planificar qué se instala cada día.</p>
    </div>
    <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Ordenes de Produccion</a>
</div>

<?php if ($error_pagina !== ''): ?>
<div class="alert alert-danger">
    <strong>Error al cargar:</strong> <?= htmlspecialchars($error_pagina) ?>
    <p class="mb-0 mt-2 small">Comprobá que existan las tablas <code>ecommerce_ordenes_produccion</code>, <code>ecommerce_pedidos</code> y <code>ecommerce_clientes</code>.</p>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Pedido desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Pedido hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Instalación desde</label>
                <input type="date" name="instalacion_desde" class="form-control" value="<?= htmlspecialchars($instalacion_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Instalación hasta</label>
                <input type="date" name="instalacion_hasta" class="form-control" value="<?= htmlspecialchars($instalacion_hasta) ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="incluir_entregados" value="1" id="incluir_entregados" <?= $incluir_entregados ? 'checked' : '' ?>>
                    <label class="form-check-label" for="incluir_entregados">Incluir ya entregados</label>
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="instalaciones.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">No hay pedidos con produccion terminada para los filtros elegidos.</div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= count($pedidos) ?> pedido(s) para instalaciones</h5>
            <div class="d-flex gap-2">
                <a href="instalaciones_reporte_direcciones.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por direcciones y cortinas</a>
                <a href="instalaciones_reporte_productos.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por productos y tiempos</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3 border-bottom">
                <form method="POST" id="programar-form" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="programar_instalacion">
                    <div class="col-auto">
                        <label class="form-label mb-0">Fecha instalación</label>
                        <input type="date" name="fecha_instalacion" class="form-control">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Programar instalación para seleccionados</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" id="select-all" class="btn btn-outline-secondary">Seleccionar todo</button>
                        <button type="button" id="clear-all" class="btn btn-outline-secondary">Limpiar</button>
                    </div>
                </form>
            </div>
            <form method="POST" id="ordenes-form">
                <input type="hidden" name="action" value="programar_instalacion">

                <?php foreach ($pedidos_por_fecha as $fecha_instalada => $items_fecha): ?>
                    <div class="border-top p-3 bg-light">
                        <h5 class="mb-0">📅 <?= htmlspecialchars(date('d/m/Y', strtotime($fecha_instalada))) ?> <span class="badge bg-primary ms-2"><?= count($items_fecha) ?></span></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Pedido</th>
                                    <th>Cliente / Envio</th>
                                    <th>Direccion</th>
                                    <th>Localidad</th>
                                    <th>Fecha pedido</th>
                                    <th>Fecha instalación</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_fecha as $row): ?>
                                    <?php
                                    $nombre = trim($row['envio_nombre'] ?? '') ?: $row['cliente_nombre'] ?? 'Sin nombre';
                                    $dir = trim($row['envio_direccion'] ?? '');
                                    $loc = trim($row['envio_localidad'] ?? '') . (!empty($row['envio_provincia']) ? ', ' . $row['envio_provincia'] : '');
                                    $is_programada = !empty($row['fecha_instalacion']);
                                    $is_past = $is_programada && (strtotime($row['fecha_instalacion']) < strtotime(date('Y-m-d')));
                                    $rowClass = $is_programada ? ($is_past ? 'table-warning' : 'table-success') : '';
                                    ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td><input type="checkbox" name="orden_ids[]" value="<?= (int)$row['orden_id'] ?>" class="form-check-input"></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['numero_pedido']) ?></strong>
                                            <?php if ($is_programada): ?>
                                                <span class="badge <?= $is_past ? 'bg-warning text-dark' : 'bg-success' ?> ms-2" title="Instalación: <?= htmlspecialchars($row['fecha_instalacion']) ?>">Programada</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($nombre) ?></td>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= htmlspecialchars($loc) ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['fecha_creacion'])) ?></td>
                                        <td>
                                            <?php if (!empty($row['fecha_entrega'])): ?>
                                                <div class="small text-muted">Entrega: <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_entrega']))) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-primary">Programada: <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_instalacion']))) ?></div>
                                        </td>
                                        <td>
                                            <a href="orden_produccion_detalle.php?pedido_id=<?= (int)$row['pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($pedidos_sin_fecha)): ?>
                    <div class="border-top p-3 bg-white">
                        <h5 class="mb-0">🗂️ Sin fecha de instalación <span class="badge bg-secondary ms-2"><?= count($pedidos_sin_fecha) ?></span></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Pedido</th>
                                    <th>Cliente / Envio</th>
                                    <th>Direccion</th>
                                    <th>Localidad</th>
                                    <th>Fecha pedido</th>
                                    <th>Fecha instalación</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_sin_fecha as $row): ?>
                                    <?php
                                    $nombre = trim($row['envio_nombre'] ?? '') ?: $row['cliente_nombre'] ?? 'Sin nombre';
                                    $dir = trim($row['envio_direccion'] ?? '');
                                    $loc = trim($row['envio_localidad'] ?? '') . (!empty($row['envio_provincia']) ? ', ' . $row['envio_provincia'] : '');
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="orden_ids[]" value="<?= (int)$row['orden_id'] ?>" class="form-check-input"></td>
                                        <td><strong><?= htmlspecialchars($row['numero_pedido']) ?></strong></td>
                                        <td><?= htmlspecialchars($nombre) ?></td>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= htmlspecialchars($loc) ?></td>
                                        <td><?= date('d/m/Y', strtotime($row['fecha_creacion'])) ?></td>
                                        <td>
                                            <?php if (!empty($row['fecha_entrega'])): ?>
                                                <div class="small text-muted">Entrega: <?= htmlspecialchars(date('d/m/Y', strtotime($row['fecha_entrega']))) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted">-</div>
                                        </td>
                                        <td>
                                            <a href="orden_produccion_detalle.php?pedido_id=<?= (int)$row['pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var selectAll = document.getElementById('select-all');
    var clearAll = document.getElementById('clear-all');
    if (selectAll) selectAll.addEventListener('click', function(){
        document.querySelectorAll('input[name="orden_ids[]"]').forEach(function(cb){ cb.checked = true; });
    });
    if (clearAll) clearAll.addEventListener('click', function(){
        document.querySelectorAll('input[name="orden_ids[]"]').forEach(function(cb){ cb.checked = false; });
    });
    // When the top form is submitted, copy selected ids into the hidden POST form if needed
    var progForm = document.getElementById('programar-form');
    var ordenesForm = document.getElementById('ordenes-form');
    if (progForm && ordenesForm) {
        progForm.addEventListener('submit', function(e){
            // Move a copy of selected checkboxes into ordenes-form so server receives orden_ids[]
            var selected = document.querySelectorAll('input[name="orden_ids[]"]:checked');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Seleccioná al menos una orden para programar.');
                return;
            }
            var fecha = progForm.querySelector('input[name="fecha_instalacion"]').value;
            if (!fecha) {
                e.preventDefault();
                alert('Seleccioná la fecha de instalación antes de programar.');
                return;
            }
            // set the date into ordenes-form
            var hiddenDate = ordenesForm.querySelector('input[name="fecha_instalacion"]');
            if (!hiddenDate) {
                hiddenDate = document.createElement('input'); hiddenDate.type='hidden'; hiddenDate.name='fecha_instalacion'; ordenesForm.appendChild(hiddenDate);
            }
            hiddenDate.value = fecha;
            // remove existing hidden ids
            ordenesForm.querySelectorAll('input[name="orden_ids[]"]').forEach(function(n){ n.parentNode.removeChild(n); });
            selected.forEach(function(cb){
                var h = document.createElement('input'); h.type='hidden'; h.name='orden_ids[]'; h.value = cb.value; ordenesForm.appendChild(h);
            });
            // submit the ordenes-form instead
            ordenesForm.submit();
            e.preventDefault();
        });
    }
});
</script>
