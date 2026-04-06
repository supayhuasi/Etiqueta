<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/compras_workflow.php';
ensureComprasWorkflowSchema($pdo);

$compra_id = intval($_GET['id'] ?? 0);
$mensaje = $_GET['mensaje'] ?? '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT * FROM ecommerce_compras WHERE id = ? FOR UPDATE");
        $stmt->execute([$compra_id]);
        $compraLock = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$compraLock) {
            throw new Exception('Compra no encontrada');
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'aprobar') {
            if (($compraLock['estado'] ?? 'orden_pendiente') !== 'aprobada') {
                $stmt = $pdo->prepare("UPDATE ecommerce_compras SET estado = 'aprobada', fecha_aprobacion = COALESCE(fecha_aprobacion, NOW()) WHERE id = ?");
                $stmt->execute([$compra_id]);
            }

            $pdo->commit();
            header('Location: compras_detalle.php?id=' . $compra_id . '&mensaje=aprobada');
            exit;
        }

        if ($action === 'recepcionar') {
            $stmt = $pdo->prepare("SELECT ci.*, pr.nombre as producto_nombre, pr.tipo_precio FROM ecommerce_compra_items ci LEFT JOIN ecommerce_productos pr ON ci.producto_id = pr.id WHERE ci.compra_id = ? ORDER BY ci.id");
            $stmt->execute([$compra_id]);
            $itemsLock = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $cantidadesRecibidas = $_POST['cantidad_recibida'] ?? [];
            $totalPedido = 0;
            $totalRecibido = 0;
            $huboCambios = false;

            foreach ($itemsLock as $item) {
                $cantidadPedida = (int)($item['cantidad'] ?? 0);
                $cantidadActual = (int)($item['cantidad_recibida'] ?? 0);
                $cantidadNueva = array_key_exists($item['id'], $cantidadesRecibidas)
                    ? (int)$cantidadesRecibidas[$item['id']]
                    : $cantidadActual;

                if ($cantidadNueva < $cantidadActual) {
                    $cantidadNueva = $cantidadActual;
                }
                if ($cantidadNueva < 0) {
                    $cantidadNueva = 0;
                }
                if ($cantidadNueva > $cantidadPedida) {
                    $cantidadNueva = $cantidadPedida;
                }

                $delta = $cantidadNueva - $cantidadActual;
                if ($delta > 0) {
                    aplicarStockCompraDelta($pdo, $item, $delta, (string)($compraLock['numero_compra'] ?? 'COMPRA'));
                    $stmtUpd = $pdo->prepare("UPDATE ecommerce_compra_items SET cantidad_recibida = ? WHERE id = ?");
                    $stmtUpd->execute([$cantidadNueva, $item['id']]);
                    $huboCambios = true;
                }

                $totalPedido += $cantidadPedida;
                $totalRecibido += $cantidadNueva;
            }

            if (($compraLock['estado'] ?? 'orden_pendiente') === 'orden_pendiente' && ($huboCambios || !empty($_POST['marcar_aprobada']))) {
                $compraLock['estado'] = 'aprobada';
            }

            $recepcionEstado = 'pendiente';
            $fechaRecepcion = null;
            if ($totalRecibido > 0 && $totalRecibido < $totalPedido) {
                $recepcionEstado = 'parcial';
            } elseif ($totalPedido > 0 && $totalRecibido >= $totalPedido) {
                $recepcionEstado = 'total';
                $fechaRecepcion = date('Y-m-d H:i:s');
            }

            $estadoFinal = ($compraLock['estado'] ?? 'orden_pendiente') === 'orden_pendiente' ? 'aprobada' : ($compraLock['estado'] ?? 'aprobada');
            $stmt = $pdo->prepare("UPDATE ecommerce_compras SET estado = ?, recepcion_estado = ?, stock_actualizado = ?, fecha_aprobacion = COALESCE(fecha_aprobacion, NOW()), fecha_recepcion = ? WHERE id = ?");
            $stmt->execute([$estadoFinal, $recepcionEstado, $totalRecibido > 0 ? 1 : 0, $fechaRecepcion, $compra_id]);

            $pdo->commit();
            header('Location: compras_detalle.php?id=' . $compra_id . '&mensaje=recepcion_actualizada');
            exit;
        }

        $pdo->rollBack();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT c.*, p.nombre as proveedor_nombre, p.email as proveedor_email, p.telefono as proveedor_telefono
    FROM ecommerce_compras c
    LEFT JOIN ecommerce_proveedores p ON c.proveedor_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die('Compra no encontrada');
}

$stmt = $pdo->prepare("
    SELECT ci.*, pr.nombre as producto_nombre, pr.tipo_precio
    FROM ecommerce_compra_items ci
    LEFT JOIN ecommerce_productos pr ON ci.producto_id = pr.id
    WHERE ci.compra_id = ?
    ORDER BY ci.id
");
$stmt->execute([$compra_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tiene_atributos_col = false;
try {
    $cols_compra_items = $pdo->query("SHOW COLUMNS FROM ecommerce_compra_items")->fetchAll(PDO::FETCH_COLUMN, 0);
    $tiene_atributos_col = in_array('atributos_json', $cols_compra_items, true);
} catch (Exception $e) {
    $tiene_atributos_col = false;
}

$estadoMeta = compraEstadoMeta($compra['estado'] ?? 'orden_pendiente');
$recepcionMeta = compraRecepcionMeta($compra['recepcion_estado'] ?? 'pendiente');
$totalPedido = 0;
$totalRecibido = 0;
foreach ($items as $item) {
    $totalPedido += (int)($item['cantidad'] ?? 0);
    $totalRecibido += (int)($item['cantidad_recibida'] ?? 0);
}

$mensajeTexto = '';
if ($mensaje === 'orden_creada') {
    $mensajeTexto = 'La orden de compra se creó correctamente.';
} elseif ($mensaje === 'aprobada') {
    $mensajeTexto = 'La orden fue aprobada y ya figura como compra.';
} elseif ($mensaje === 'recepcion_actualizada') {
    $mensajeTexto = 'La recepción se actualizó y el stock se ajustó por las cantidades recibidas.';
} elseif ($mensaje === 'editada') {
    $mensajeTexto = 'El registro se actualizó correctamente.';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1>🧾 <?= htmlspecialchars((($compra['estado'] ?? 'orden_pendiente') === 'orden_pendiente' ? 'Orden de Compra ' : 'Compra ') . (string)($compra['numero_compra'] ?? '')) ?></h1>
        <p class="text-muted mb-0">Flujo: orden → aprobación → recepción con ajuste real de stock</p>
    </div>
    <div class="d-flex gap-2">
        <a href="compras.php" class="btn btn-secondary">← Volver</a>
        <?php if (($compra['estado'] ?? 'orden_pendiente') === 'orden_pendiente'): ?>
            <form method="POST" class="d-inline">
                <input type="hidden" name="action" value="aprobar">
                <button type="submit" class="btn btn-success">✅ Aprobar compra</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($mensajeTexto): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensajeTexto) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏭 Proveedor</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars((string)($compra['proveedor_nombre'] ?? 'N/A')) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars((string)($compra['proveedor_email'] ?? '-')) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars((string)($compra['proveedor_telefono'] ?? '-')) ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">📋 Estado y Totales</h5>
            </div>
            <div class="card-body">
                <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime((string)$compra['fecha_compra'])) ?></p>
                <p><strong>Estado:</strong> <span class="badge bg-<?= $estadoMeta['class'] ?>"><?= htmlspecialchars($estadoMeta['label']) ?></span></p>
                <p><strong>Recepción:</strong> <span class="badge bg-<?= $recepcionMeta['class'] ?>"><?= htmlspecialchars($recepcionMeta['label']) ?></span></p>
                <p><strong>Unidades pedidas:</strong> <?= (int)$totalPedido ?></p>
                <p><strong>Unidades recibidas:</strong> <?= (int)$totalRecibido ?></p>
                <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format((float)($compra['total'] ?? 0), 2) ?></span></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($compra['observaciones'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">📝 Observaciones</div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars((string)$compra['observaciones'])) ?>
        </div>
    </div>
<?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="recepcionar">
    <div class="card">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">📦 Items y recepción</h5>
            <small>La cantidad recibida es acumulada; solo el incremento ajusta stock.</small>
        </div>
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Medidas</th>
                        <?php if ($tiene_atributos_col): ?>
                            <th>Atributos</th>
                        <?php endif; ?>
                        <th class="text-center">Pedida</th>
                        <th class="text-center">Recibida</th>
                        <th class="text-center">Pendiente</th>
                        <th class="text-end">Costo Unit.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php $pendiente = max(0, (int)$item['cantidad'] - (int)($item['cantidad_recibida'] ?? 0)); ?>
                        <tr>
                            <td><strong><?= htmlspecialchars((string)($item['producto_nombre'] ?? 'Producto eliminado')) ?></strong></td>
                            <td>
                                <?php if (!empty($item['alto_cm']) && !empty($item['ancho_cm'])): ?>
                                    <?= (int)$item['ancho_cm'] ?>cm × <?= (int)$item['alto_cm'] ?>cm
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <?php if ($tiene_atributos_col): ?>
                                <td>
                                    <?php $attrs = !empty($item['atributos_json']) ? (json_decode((string)$item['atributos_json'], true) ?: []) : []; ?>
                                    <?php if (!empty($attrs)): ?>
                                        <small>
                                            <?php foreach ($attrs as $attr): ?>
                                                <div><?= htmlspecialchars((string)($attr['nombre'] ?? '')) ?>: <?= htmlspecialchars((string)($attr['valor'] ?? '')) ?></div>
                                            <?php endforeach; ?>
                                        </small>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="text-center"><?= (int)$item['cantidad'] ?></td>
                            <td class="text-center" style="min-width: 120px;">
                                <?php if (($compra['recepcion_estado'] ?? 'pendiente') !== 'total'): ?>
                                    <input type="number" class="form-control form-control-sm text-center" name="cantidad_recibida[<?= (int)$item['id'] ?>]" value="<?= (int)($item['cantidad_recibida'] ?? 0) ?>" min="<?= (int)($item['cantidad_recibida'] ?? 0) ?>" max="<?= (int)$item['cantidad'] ?>">
                                <?php else: ?>
                                    <strong><?= (int)($item['cantidad_recibida'] ?? 0) ?></strong>
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $pendiente ?></td>
                            <td class="text-end">$<?= number_format((float)($item['costo_unitario'] ?? 0), 2) ?></td>
                            <td class="text-end"><strong>$<?= number_format((float)($item['subtotal'] ?? 0), 2) ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (($compra['recepcion_estado'] ?? 'pendiente') !== 'total'): ?>
            <div class="card-body border-top bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <strong>Estado actual:</strong>
                    <span class="badge bg-<?= $recepcionMeta['class'] ?>"><?= htmlspecialchars($recepcionMeta['label']) ?></span>
                </div>
                <button type="submit" class="btn btn-primary">📥 Guardar recepción</button>
            </div>
        <?php endif; ?>
    </div>
</form>

<?php require 'includes/footer.php'; ?>
