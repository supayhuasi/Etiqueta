<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Productos activos
$stmt = $pdo->query("SELECT id, nombre, tipo_precio FROM ecommerce_productos WHERE activo = 1 ORDER BY nombre");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$productosById = [];
foreach ($productos as $p) {
    $productosById[$p['id']] = $p;
}

// Opciones de color por producto
$colorOptionsByProduct = [];
$tiene_opciones = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'")->rowCount() > 0;
if ($tiene_opciones) {
    $stmt = $pdo->query("
        SELECT p.id AS producto_id, o.id AS opcion_id, o.nombre AS opcion_nombre, o.color
        FROM ecommerce_atributo_opciones o
        JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
        JOIN ecommerce_productos p ON p.id = a.producto_id
        WHERE a.tipo = 'select' AND LOWER(a.nombre) LIKE '%color%'
        ORDER BY p.nombre, o.nombre
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $colorOptionsByProduct[(int)$row['producto_id']][] = $row;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $producto_id = intval($_POST['producto_id'] ?? 0);
        $tipo = $_POST['tipo'] ?? 'ajuste';
        $cantidad = intval($_POST['cantidad'] ?? 0);
        $alto = !empty($_POST['alto']) ? intval($_POST['alto']) : null;
        $ancho = !empty($_POST['ancho']) ? intval($_POST['ancho']) : null;
        $nota = trim($_POST['nota'] ?? '');
        $color_opcion_id = intval($_POST['color_opcion_id'] ?? 0);

        if ($producto_id <= 0) {
            throw new Exception('Seleccione un producto');
        }
        if ($cantidad === 0) {
            throw new Exception('La cantidad no puede ser 0');
        }

        $producto = $productosById[$producto_id] ?? null;
        if (!$producto) {
            throw new Exception('Producto no válido');
        }

        $pdo->beginTransaction();

        if ($producto['tipo_precio'] === 'variable') {
            if (!$alto || !$ancho) {
                throw new Exception('Debe indicar alto y ancho para productos variables');
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM ecommerce_matriz_precios WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?");
            $stmtCheck->execute([$producto_id, $alto, $ancho]);
            $matriz = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($matriz) {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_matriz_precios SET stock = stock + ? WHERE id = ?");
                $stmtUpd->execute([$cantidad, $matriz['id']]);
            } else {
                $stmtIns = $pdo->prepare("INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock) VALUES (?, ?, ?, 0, ?)");
                $stmtIns->execute([$producto_id, $alto, $ancho, $cantidad]);
            }
        } else {
            if (!empty($color_opcion_id)) {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_atributo_opciones SET stock = stock + ? WHERE id = ?");
                $stmtUpd->execute([$cantidad, $color_opcion_id]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = stock + ? WHERE id = ?");
                $stmtUpd->execute([$cantidad, $producto_id]);
            }
        }

        $stmtMov = $pdo->prepare("
            INSERT INTO ecommerce_inventario_movimientos (producto_id, tipo, cantidad, alto_cm, ancho_cm, referencia)
            VALUES (?, 'ajuste', ?, ?, ?, ?)
        ");
        $stmtMov->execute([$producto_id, $cantidad, $alto, $ancho, $nota]);

        $pdo->commit();
        $mensaje = '✓ Ajuste registrado correctamente';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Movimientos recientes
$stmt = $pdo->query("
    SELECT m.*, p.nombre as producto_nombre
    FROM ecommerce_inventario_movimientos m
    LEFT JOIN ecommerce_productos p ON m.producto_id = p.id
    WHERE m.tipo = 'ajuste'
    ORDER BY m.fecha_creacion DESC
    LIMIT 50
");
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>⚙️ Ajustes de Inventario</h1>
        <p class="text-muted">Sumá o restá stock manualmente</p>
    </div>
    <a href="compras.php" class="btn btn-secondary">← Volver a Compras</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Registrar ajuste</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Producto *</label>
                <input type="text" class="form-control" id="producto_input" list="productos_datalist" placeholder="Escriba para buscar..." required>
                <datalist id="productos_datalist">
                    <?php foreach ($productos as $prod): ?>
                        <option value="<?= htmlspecialchars($prod['nombre']) ?>" data-id="<?= $prod['id'] ?>" data-tipo="<?= $prod['tipo_precio'] ?>"></option>
                    <?php endforeach; ?>
                </datalist>
                <input type="hidden" name="producto_id" id="producto_id" required>
            </div>
            <div class="col-md-2">
                <label class="form-label">Cantidad *</label>
                <input type="number" class="form-control" name="cantidad" required>
                <small class="text-muted">Positivo suma / negativo resta</small>
            </div>
            <div class="col-md-2">
                <label class="form-label">Alto (cm)</label>
                <input type="number" class="form-control" name="alto" id="alto" disabled>
            </div>
            <div class="col-md-2">
                <label class="form-label">Ancho (cm)</label>
                <input type="number" class="form-control" name="ancho" id="ancho" disabled>
            </div>
            <div class="col-md-2">
                <label class="form-label">Color (opcional)</label>
                <select class="form-select" name="color_opcion_id" id="color_opcion_id" disabled>
                    <option value="">-- Sin color --</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Nota</label>
                <input type="text" class="form-control" name="nota" placeholder="Motivo">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Guardar ajuste</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Movimientos recientes</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Fecha</th>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th>Cantidad</th>
                    <th>Nota</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($movimientos)): ?>
                    <tr><td colspan="5" class="text-center text-muted">Sin movimientos</td></tr>
                <?php else: ?>
                    <?php foreach ($movimientos as $mov): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($mov['fecha_creacion'])) ?></td>
                            <td><?= htmlspecialchars($mov['producto_nombre'] ?? 'Producto eliminado') ?></td>
                            <td>
                                <?php if ($mov['alto_cm'] && $mov['ancho_cm']): ?>
                                    <?= $mov['ancho_cm'] ?>cm × <?= $mov['alto_cm'] ?>cm
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?= (int)$mov['cantidad'] ?></td>
                            <td><?= htmlspecialchars($mov['referencia'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const colorOptionsByProduct = <?= json_encode($colorOptionsByProduct) ?>;

function toggleMedidas() {
    const tipo = document.getElementById('producto_id').dataset.tipo || 'fijo';
    const alto = document.getElementById('alto');
    const ancho = document.getElementById('ancho');
    const color = document.getElementById('color_opcion_id');

    if (tipo === 'variable') {
        alto.disabled = false;
        ancho.disabled = false;
    } else {
        alto.value = '';
        ancho.value = '';
        alto.disabled = true;
        ancho.disabled = true;
    }

    actualizarColores();
}

function actualizarColores() {
    const productoId = document.getElementById('producto_id').value;
    const selectColor = document.getElementById('color_opcion_id');
    if (!selectColor) return;

    if (!productoId) {
        selectColor.innerHTML = '<option value="">-- Sin color --</option>';
        selectColor.disabled = true;
        return;
    }

    const opciones = colorOptionsByProduct?.[parseInt(productoId, 10)] || [];
    if (opciones.length === 0) {
        selectColor.innerHTML = '<option value="">-- Sin color --</option>';
        selectColor.disabled = true;
        return;
    }

    let optionsHtml = '<option value="">-- Sin color --</option>';
    opciones.forEach(op => {
        const label = op.opcion_nombre || 'Color';
        optionsHtml += `<option value="${op.opcion_id}">${label}</option>`;
    });
    selectColor.innerHTML = optionsHtml;
    selectColor.disabled = false;
}

function normalizarTexto(texto) {
    return (texto || '')
        .toString()
        .trim()
        .toLowerCase()
        .replace(/\s+/g, ' ');
}

function syncProductoSeleccionado() {
    const inputEl = document.getElementById('producto_input');
    const input = inputEl.value;
    const inputNorm = normalizarTexto(input);
    const datalist = document.getElementById('productos_datalist');
    const hidden = document.getElementById('producto_id');

    let option = Array.from(datalist.options).find(o => o.value === input);
    if (!option && inputNorm) {
        option = Array.from(datalist.options).find(o => normalizarTexto(o.value) === inputNorm) || null;
    }

    if (!option && inputNorm) {
        const startMatches = Array.from(datalist.options).filter(o => normalizarTexto(o.value).startsWith(inputNorm));
        if (startMatches.length === 1) {
            option = startMatches[0];
            inputEl.value = option.value;
        }
    }

    if (!option && /^\d+$/.test(inputNorm)) {
        const idMatch = Array.from(datalist.options).find(o => String(o.getAttribute('data-id') || '') === inputNorm) || null;
        if (idMatch) {
            option = idMatch;
            inputEl.value = option.value;
        }
    }

    if (option) {
        hidden.value = option.getAttribute('data-id') || '';
        hidden.dataset.tipo = option.getAttribute('data-tipo') || 'fijo';
    } else {
        hidden.value = '';
        hidden.dataset.tipo = 'fijo';
    }
    toggleMedidas();
}

const productoInput = document.getElementById('producto_input');
productoInput.addEventListener('input', syncProductoSeleccionado);
productoInput.addEventListener('change', syncProductoSeleccionado);
productoInput.addEventListener('blur', syncProductoSeleccionado);

document.querySelector('form').addEventListener('submit', function(e) {
    syncProductoSeleccionado();
    if (!document.getElementById('producto_id').value) {
        e.preventDefault();
        alert('Seleccione un producto');
        productoInput.focus();
    }
});
</script>

<?php require 'includes/footer.php'; ?>
