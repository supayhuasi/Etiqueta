<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/compras_workflow.php';
ensureComprasWorkflowSchema($pdo);

$compra_id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_compras WHERE id = ?");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die('Compra no encontrada');
}

if (($compra['recepcion_estado'] ?? 'pendiente') !== 'pendiente') {
    $error = 'No se puede editar una compra con recepción parcial o total. Ajustá la recepción desde el detalle.';
}

$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_proveedores WHERE activo = 1 ORDER BY nombre");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, nombre, tipo_precio FROM ecommerce_productos WHERE activo = 1 ORDER BY nombre");
$stmt->execute();
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productosById = [];
foreach ($productos as $p) {
    $productosById[(int)$p['id']] = $p;
}

$tiene_atributos_col = false;
try {
    $cols_compra_items = $pdo->query("SHOW COLUMNS FROM ecommerce_compra_items")->fetchAll(PDO::FETCH_COLUMN, 0);
    $tiene_atributos_col = in_array('atributos_json', $cols_compra_items, true);
} catch (Exception $e) {
    $tiene_atributos_col = false;
}

function ajustar_stock_compra($pdo, $item, $signo, $tieneMatriz)
{
    $productoId = (int)$item['producto_id'];
    $cantidad = (int)$item['cantidad'];
    $alto = !empty($item['alto_cm']) ? (int)$item['alto_cm'] : null;
    $ancho = !empty($item['ancho_cm']) ? (int)$item['ancho_cm'] : null;
    $tipoPrecio = $item['tipo_precio'] ?? 'fijo';
    $delta = $cantidad * $signo;

    if ($tipoPrecio === 'variable' && $alto && $ancho && $tieneMatriz) {
        $stmtCheck = $pdo->prepare("SELECT id FROM ecommerce_matriz_precios WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?");
        $stmtCheck->execute([$productoId, $alto, $ancho]);
        $matriz = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($matriz) {
            $stmtUpd = $pdo->prepare("UPDATE ecommerce_matriz_precios SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
            $stmtUpd->execute([$delta, $matriz['id']]);
        } elseif ($delta > 0) {
            $stmtIns = $pdo->prepare("INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock) VALUES (?, ?, ?, 0, ?)");
            $stmtIns->execute([$productoId, $alto, $ancho, $delta]);
        }
    } else {
        $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
        $stmtUpd->execute([$delta, $productoId]);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    try {
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
        $fecha_compra = $_POST['fecha_compra'] ?? '';
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($proveedor_id <= 0) {
            throw new Exception('Seleccione un proveedor');
        }

        if (empty($fecha_compra)) {
            throw new Exception('Seleccione una fecha de compra');
        }

        $nuevosItems = [];
        $subtotal = 0;
        if (!empty($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $i => $item) {
                $producto_id = (int)($item['producto_id'] ?? 0);
                $cantidad = (int)($item['cantidad'] ?? 0);
                $costo = (float)($item['costo'] ?? 0);
                $alto = isset($item['alto']) && $item['alto'] !== '' ? (int)$item['alto'] : null;
                $ancho = isset($item['ancho']) && $item['ancho'] !== '' ? (int)$item['ancho'] : null;
                $atributos = $item['atributos_json'] ?? null;
                $eliminar = !empty($item['eliminar']);

                if ($eliminar) {
                    continue;
                }

                if ($producto_id <= 0 || $cantidad <= 0 || $costo < 0) {
                    throw new Exception('Revise los items: producto, cantidad y costo son obligatorios.');
                }

                $tipo_item = $productosById[$producto_id]['tipo_precio'] ?? 'fijo';
                if ($tipo_item === 'variable' && (!$alto || !$ancho)) {
                    throw new Exception('Los productos variables deben tener alto y ancho.');
                }

                $subtotal_item = $cantidad * $costo;
                $subtotal += $subtotal_item;

                $nuevosItems[] = [
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'alto_cm' => $alto,
                    'ancho_cm' => $ancho,
                    'subtotal' => $subtotal_item,
                    'atributos_json' => $atributos,
                    'tipo_precio' => $tipo_item
                ];
            }
        }

        if (empty($nuevosItems)) {
            throw new Exception('La compra debe tener al menos un item.');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM ecommerce_compra_items WHERE compra_id = ?");
        $stmt->execute([$compra_id]);

        $total = $subtotal;
        $stmt = $pdo->prepare("UPDATE ecommerce_compras SET proveedor_id = ?, fecha_compra = ?, subtotal = ?, total = ?, observaciones = ? WHERE id = ?");
        $stmt->execute([$proveedor_id, $fecha_compra, $subtotal, $total, $observaciones, $compra_id]);

        if ($tiene_atributos_col) {
            $stmtItem = $pdo->prepare("INSERT INTO ecommerce_compra_items (compra_id, producto_id, cantidad, costo_unitario, alto_cm, ancho_cm, subtotal, atributos_json) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        } else {
            $stmtItem = $pdo->prepare("INSERT INTO ecommerce_compra_items (compra_id, producto_id, cantidad, costo_unitario, alto_cm, ancho_cm, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?)");
        }

        foreach ($nuevosItems as $ni) {
            if ($tiene_atributos_col) {
                $stmtItem->execute([
                    $compra_id,
                    $ni['producto_id'],
                    $ni['cantidad'],
                    $ni['costo_unitario'],
                    $ni['alto_cm'],
                    $ni['ancho_cm'],
                    $ni['subtotal'],
                    $ni['atributos_json']
                ]);
            } else {
                $stmtItem->execute([
                    $compra_id,
                    $ni['producto_id'],
                    $ni['cantidad'],
                    $ni['costo_unitario'],
                    $ni['alto_cm'],
                    $ni['ancho_cm'],
                    $ni['subtotal']
                ]);
            }

        }

        $pdo->commit();

        header('Location: compras_detalle.php?id=' . $compra_id . '&mensaje=editada');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("SELECT ci.*, pr.nombre AS producto_nombre, pr.tipo_precio FROM ecommerce_compra_items ci LEFT JOIN ecommerce_productos pr ON pr.id = ci.producto_id WHERE ci.compra_id = ? ORDER BY ci.id");
$stmt->execute([$compra_id]);
$itemsCompra = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>✏️ Editar Compra <?= htmlspecialchars($compra['numero_compra']) ?></h1>
        <p class="text-muted">Editar datos generales e items</p>
    </div>
    <a href="compras.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label for="proveedor_id" class="form-label">Proveedor *</label>
                <select class="form-select" id="proveedor_id" name="proveedor_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= (int)$prov['id'] ?>" <?= (int)$compra['proveedor_id'] === (int)$prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="fecha_compra" class="form-label">Fecha de compra *</label>
                <input
                    type="date"
                    class="form-control"
                    id="fecha_compra"
                    name="fecha_compra"
                    value="<?= htmlspecialchars($compra['fecha_compra']) ?>"
                    required
                >
            </div>

            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($compra['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
                <h5 class="mb-2">Items de la compra</h5>
                <?php if (empty($itemsCompra)): ?>
                    <div class="alert alert-warning">Esta compra no tiene items cargados.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th style="width:120px;">Cantidad</th>
                                    <th style="width:150px;">Costo unit.</th>
                                    <th style="width:110px;">Alto</th>
                                    <th style="width:110px;">Ancho</th>
                                    <th style="width:120px;">Eliminar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itemsCompra as $i => $item): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong>
                                            <input type="hidden" name="items[<?= $i ?>][producto_id]" value="<?= (int)$item['producto_id'] ?>">
                                            <?php if ($tiene_atributos_col): ?>
                                                <input type="hidden" name="items[<?= $i ?>][atributos_json]" value="<?= htmlspecialchars($item['atributos_json'] ?? '') ?>">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <input type="number" min="1" class="form-control" name="items[<?= $i ?>][cantidad]" value="<?= (int)$item['cantidad'] ?>" required>
                                        </td>
                                        <td>
                                            <input type="number" min="0" step="0.01" class="form-control" name="items[<?= $i ?>][costo]" value="<?= htmlspecialchars((string)$item['costo_unitario']) ?>" required>
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                min="0"
                                                class="form-control"
                                                name="items[<?= $i ?>][alto]"
                                                value="<?= !empty($item['alto_cm']) ? (int)$item['alto_cm'] : '' ?>"
                                                <?= ($item['tipo_precio'] ?? 'fijo') === 'variable' ? '' : 'disabled' ?>
                                            >
                                        </td>
                                        <td>
                                            <input
                                                type="number"
                                                min="0"
                                                class="form-control"
                                                name="items[<?= $i ?>][ancho]"
                                                value="<?= !empty($item['ancho_cm']) ? (int)$item['ancho_cm'] : '' ?>"
                                                <?= ($item['tipo_precio'] ?? 'fijo') === 'variable' ? '' : 'disabled' ?>
                                            >
                                        </td>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input" name="items[<?= $i ?>][eliminar]" value="1" title="Quitar item">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <small class="text-muted">Marca "Eliminar" para quitar items de la compra.</small>
                <?php endif; ?>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="compras_detalle.php?id=<?= $compra_id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
