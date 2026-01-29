<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Proveedores activos
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_proveedores WHERE activo = 1 ORDER BY nombre");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos activos
$stmt = $pdo->query("SELECT id, nombre, tipo_precio FROM ecommerce_productos WHERE activo = 1 ORDER BY nombre");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productosById = [];
foreach ($productos as $p) {
    $productosById[$p['id']] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $proveedor_id = intval($_POST['proveedor_id'] ?? 0);
        $fecha_compra = $_POST['fecha_compra'] ?? date('Y-m-d');
        $observaciones = $_POST['observaciones'] ?? '';

        if ($proveedor_id <= 0) {
            throw new Exception('Seleccione un proveedor');
        }

        $items = [];
        $subtotal = 0;

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['producto_id']) || empty($item['cantidad']) || empty($item['costo'])) {
                    continue;
                }

                $producto_id = intval($item['producto_id']);
                $cantidad = intval($item['cantidad']);
                $costo = floatval($item['costo']);
                $alto = !empty($item['alto']) ? intval($item['alto']) : null;
                $ancho = !empty($item['ancho']) ? intval($item['ancho']) : null;
                $subtotal_item = $cantidad * $costo;

                $items[] = [
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'alto_cm' => $alto,
                    'ancho_cm' => $ancho,
                    'subtotal' => $subtotal_item
                ];

                $subtotal += $subtotal_item;
            }
        }

        if (empty($items)) {
            throw new Exception('Debe agregar al menos un item');
        }

        $total = $subtotal;

        $pdo->beginTransaction();

        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM ecommerce_compras");
        $max_id = $stmt->fetch()['max_id'] ?? 0;
        $numero_compra = 'COMP-' . date('Y') . '-' . str_pad($max_id + 1, 5, '0', STR_PAD_LEFT);

        $stmt = $pdo->prepare("
            INSERT INTO ecommerce_compras (numero_compra, proveedor_id, fecha_compra, subtotal, total, observaciones, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $numero_compra,
            $proveedor_id,
            $fecha_compra,
            $subtotal,
            $total,
            $observaciones,
            $_SESSION['user']['id'] ?? null
        ]);

        $compra_id = $pdo->lastInsertId();

        $stmtItem = $pdo->prepare("
            INSERT INTO ecommerce_compra_items (compra_id, producto_id, cantidad, costo_unitario, alto_cm, ancho_cm, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmtMov = $pdo->prepare("
            INSERT INTO ecommerce_inventario_movimientos (producto_id, tipo, cantidad, alto_cm, ancho_cm, referencia)
            VALUES (?, 'compra', ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $stmtItem->execute([
                $compra_id,
                $item['producto_id'],
                $item['cantidad'],
                $item['costo_unitario'],
                $item['alto_cm'],
                $item['ancho_cm'],
                $item['subtotal']
            ]);

            $producto = $productosById[$item['producto_id']] ?? null;
            if ($producto && $producto['tipo_precio'] === 'variable' && $item['alto_cm'] && $item['ancho_cm']) {
                $stmtCheck = $pdo->prepare("SELECT id FROM ecommerce_matriz_precios WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?");
                $stmtCheck->execute([$item['producto_id'], $item['alto_cm'], $item['ancho_cm']]);
                $matriz = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                if ($matriz) {
                    $stmtUpd = $pdo->prepare("UPDATE ecommerce_matriz_precios SET stock = stock + ? WHERE id = ?");
                    $stmtUpd->execute([$item['cantidad'], $matriz['id']]);
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock) VALUES (?, ?, ?, 0, ?)");
                    $stmtIns->execute([$item['producto_id'], $item['alto_cm'], $item['ancho_cm'], $item['cantidad']]);
                }
            } else {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = stock + ? WHERE id = ?");
                $stmtUpd->execute([$item['cantidad'], $item['producto_id']]);
            }

            $stmtMov->execute([
                $item['producto_id'],
                $item['cantidad'],
                $item['alto_cm'],
                $item['ancho_cm'],
                $numero_compra
            ]);
        }

        $pdo->commit();

        header("Location: compras_detalle.php?id=" . $compra_id . "&mensaje=creada");
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧾 Nueva Compra</h1>
        <p class="text-muted">Registrar compras para actualizar inventario</p>
    </div>
    <a href="compras.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="formCompra">
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🏭 Proveedor</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Proveedor *</label>
                        <select class="form-select" name="proveedor_id" required>
                            <option value="">-- Seleccionar --</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?= $prov['id'] ?>"><?= htmlspecialchars($prov['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Fecha de compra</label>
                        <input type="date" class="form-control" name="fecha_compra" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">📝 Observaciones</h5>
                </div>
                <div class="card-body">
                    <textarea class="form-control" name="observaciones" rows="5" placeholder="Notas internas..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Compra</h5>
            <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
        </div>
        <div class="card-body">
            <div id="itemsContainer"></div>

            <div class="row mt-4">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <table class="table">
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-end"><span id="subtotal">$0.00</span></td>
                        </tr>
                        <tr class="table-primary">
                            <th>Total:</th>
                            <th class="text-end"><span id="total">$0.00</span></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 Registrar Compra</button>
        <a href="compras.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
let itemIndex = 0;
const productos = <?= json_encode($productos) ?>;

function agregarItem() {
    itemIndex++;

    let productosOptions = '<option value="">-- Seleccionar producto --</option>';
    productos.forEach(p => {
        productosOptions += `<option value="${p.id}" data-tipo="${p.tipo_precio}">${p.nombre}</option>`;
    });

    const html = `
        <div class="card mb-3 item-row" id="item_${itemIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Producto *</label>
                        <select class="form-select item-producto" data-index="${itemIndex}" onchange="toggleMedidas(${itemIndex});" name="items[${itemIndex}][producto_id]" required>
                            ${productosOptions}
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cantidad *</label>
                        <input type="number" class="form-control item-cantidad" name="items[${itemIndex}][cantidad]" value="1" min="1" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Costo Unit. *</label>
                        <input type="number" class="form-control item-costo" name="items[${itemIndex}][costo]" step="0.01" min="0" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Alto (cm)</label>
                        <input type="number" class="form-control item-alto" name="items[${itemIndex}][alto]" step="1" min="0" disabled onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Ancho (cm)</label>
                        <input type="number" class="form-control item-ancho" name="items[${itemIndex}][ancho]" step="1" min="0" disabled onchange="calcularTotales()">
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-3">
                        <label class="form-label">Subtotal</label>
                        <input type="text" class="form-control item-subtotal" readonly>
                    </div>
                    <div class="col-md-9 text-end">
                        <button type="button" class="btn btn-sm btn-danger" onclick="eliminarItem(${itemIndex})">🗑️ Eliminar</button>
                    </div>
                </div>
            </div>
        </div>
    `;

    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    calcularTotales();
}

function toggleMedidas(index) {
    const select = document.querySelector(`#item_${index} .item-producto`);
    const option = select.selectedOptions[0];
    const tipo = option?.dataset?.tipo || 'fijo';
    const alto = document.querySelector(`#item_${index} .item-alto`);
    const ancho = document.querySelector(`#item_${index} .item-ancho`);

    if (tipo === 'variable') {
        alto.disabled = false;
        ancho.disabled = false;
    } else {
        alto.value = '';
        ancho.value = '';
        alto.disabled = true;
        ancho.disabled = true;
    }
}

function eliminarItem(index) {
    document.getElementById('item_' + index).remove();
    calcularTotales();
}

function calcularTotales() {
    let subtotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const costo = parseFloat(row.querySelector('.item-costo')?.value || 0);
        const subtotalItem = cantidad * costo;
        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        subtotal += subtotalItem;
    });

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '$' + subtotal.toFixed(2);
}

document.addEventListener('DOMContentLoaded', function() {
    agregarItem();
});
</script>

<?php require 'includes/footer.php'; ?>
