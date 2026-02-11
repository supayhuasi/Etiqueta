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

// Opciones de color por producto
$colorOptionsByProduct = [];
$tiene_opciones = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'")->rowCount() > 0;
if ($tiene_opciones) {
    $stmt = $pdo->query("
        SELECT p.id AS producto_id, o.id AS opcion_id, o.nombre AS opcion_nombre, o.color
        FROM ecommerce_atributo_opciones o
        JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
        JOIN ecommerce_productos p ON p.id = a.producto_id
        WHERE a.tipo = 'select'
          AND (
                LOWER(a.nombre) LIKE '%color%'
             OR (o.color IS NOT NULL AND o.color <> '')
             OR LOWER(o.nombre) LIKE '%color%'
             OR LOWER(o.nombre) REGEXP '(negro|blanco|rojo|azul|verde|gris|plata|dorado|amarillo|naranja|violeta|fucsia|rosa|celeste|turquesa|marr[oó]n|beige|crema|aqua)'
          )
        ORDER BY p.nombre, o.nombre
    ");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $colorOptionsByProduct[(int)$row['producto_id']][] = $row;
    }
}

// Categorías activas
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Asegurar columna atributos_json en compra_items
$tiene_atributos_col = false;
try {
    $cols_compra_items = $pdo->query("SHOW COLUMNS FROM ecommerce_compra_items")->fetchAll(PDO::FETCH_COLUMN, 0);
    $tiene_atributos_col = in_array('atributos_json', $cols_compra_items, true);
    if (!$tiene_atributos_col) {
        $pdo->exec("ALTER TABLE ecommerce_compra_items ADD COLUMN atributos_json TEXT NULL");
        $tiene_atributos_col = true;
    }
} catch (Exception $e) {
    $tiene_atributos_col = false;
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
                $producto_id = intval($item['producto_id'] ?? 0);
                $nuevo = !empty($item['nuevo']);
                $nombre_nuevo = trim($item['nombre_nuevo'] ?? '');

                if (($producto_id <= 0 && !$nuevo) || empty($item['cantidad']) || empty($item['costo'])) {
                    continue;
                }

                $cantidad = intval($item['cantidad']);
                $costo = floatval($item['costo']);
                $alto = !empty($item['alto']) ? intval($item['alto']) : null;
                $ancho = !empty($item['ancho']) ? intval($item['ancho']) : null;
                $color_opcion_id = intval($item['color_opcion_id'] ?? 0);
                $atributos_sel = [];
                if (!empty($item['atributos']) && is_array($item['atributos'])) {
                    foreach ($item['atributos'] as $attr) {
                        if (!empty($attr['nombre'])) {
                            $atributos_sel[] = [
                                'nombre' => $attr['nombre'],
                                'valor' => $attr['valor'] ?? ''
                            ];
                        }
                    }
                }
                $categoria_id_nuevo = intval($item['categoria_id'] ?? 0);
                $tipo_precio_nuevo = $item['tipo_precio_nuevo'] ?? 'fijo';
                $precio_base_nuevo = floatval($item['precio_base_nuevo'] ?? 0);
                $mostrar_ecommerce = !empty($item['mostrar_ecommerce']) ? 1 : 0;

                if ($producto_id <= 0 && $nuevo) {
                    if (empty($nombre_nuevo) || $categoria_id_nuevo <= 0) {
                        throw new Exception('Complete nombre y categoría del nuevo producto');
                    }

                    $codigo = null;
                    for ($i = 0; $i < 5; $i++) {
                        $codigo = 'AUTO-' . date('YmdHis') . '-' . rand(100, 999);
                        $stmt = $pdo->prepare("SELECT id FROM ecommerce_productos WHERE codigo = ? LIMIT 1");
                        $stmt->execute([$codigo]);
                        if (!$stmt->fetch()) {
                            break;
                        }
                        $codigo = null;
                    }

                    if (!$codigo) {
                        throw new Exception('No se pudo generar código de producto');
                    }

                    $precio_base_insert = $precio_base_nuevo > 0 ? $precio_base_nuevo : $costo;

                    $stmt = $pdo->prepare("
                        INSERT INTO ecommerce_productos (codigo, nombre, descripcion, categoria_id, precio_base, tipo_precio, orden, activo, mostrar_ecommerce)
                        VALUES (?, ?, '', ?, ?, ?, 0, 1, ?)
                    ");
                    $stmt->execute([$codigo, $nombre_nuevo, $categoria_id_nuevo, $precio_base_insert, $tipo_precio_nuevo, $mostrar_ecommerce]);
                    $producto_id = $pdo->lastInsertId();

                    $productosById[$producto_id] = [
                        'id' => $producto_id,
                        'nombre' => $nombre_nuevo,
                        'tipo_precio' => $tipo_precio_nuevo
                    ];
                }

                $tipo_item = $productosById[$producto_id]['tipo_precio'] ?? 'fijo';
                if ($tipo_item === 'variable' && (empty($alto) || empty($ancho))) {
                    throw new Exception('Debe indicar alto y ancho para productos variables');
                }

                $subtotal_item = $cantidad * $costo;

                $items[] = [
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'alto_cm' => $alto,
                    'ancho_cm' => $ancho,
                    'subtotal' => $subtotal_item,
                    'color_opcion_id' => $color_opcion_id,
                    'atributos' => $atributos_sel
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

        if ($tiene_atributos_col) {
            $stmtItem = $pdo->prepare("
                INSERT INTO ecommerce_compra_items (compra_id, producto_id, cantidad, costo_unitario, alto_cm, ancho_cm, subtotal, atributos_json)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
        } else {
            $stmtItem = $pdo->prepare("
                INSERT INTO ecommerce_compra_items (compra_id, producto_id, cantidad, costo_unitario, alto_cm, ancho_cm, subtotal)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
        }

        $stmtMov = $pdo->prepare("
            INSERT INTO ecommerce_inventario_movimientos (producto_id, tipo, cantidad, alto_cm, ancho_cm, referencia)
            VALUES (?, 'compra', ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            if ($tiene_atributos_col) {
                $stmtItem->execute([
                    $compra_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['costo_unitario'],
                    $item['alto_cm'],
                    $item['ancho_cm'],
                    $item['subtotal'],
                    !empty($item['atributos']) ? json_encode($item['atributos']) : null
                ]);
            } else {
                $stmtItem->execute([
                    $compra_id,
                    $item['producto_id'],
                    $item['cantidad'],
                    $item['costo_unitario'],
                    $item['alto_cm'],
                    $item['ancho_cm'],
                    $item['subtotal']
                ]);
            }

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
                if (!empty($item['color_opcion_id'])) {
                    $stmtUpd = $pdo->prepare("UPDATE ecommerce_atributo_opciones SET stock = stock + ? WHERE id = ?");
                    $stmtUpd->execute([$item['cantidad'], $item['color_opcion_id']]);
                } else {
                    $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = stock + ? WHERE id = ?");
                    $stmtUpd->execute([$item['cantidad'], $item['producto_id']]);
                }
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
const categorias = <?= json_encode($categorias) ?>;
const colorOptionsByProduct = <?= json_encode($colorOptionsByProduct) ?>;

function agregarItem() {
    itemIndex++;

    asegurarDatalistProductos();

    let categoriasOptions = '<option value="">-- Seleccionar categoría --</option>';
    categorias.forEach(c => {
        categoriasOptions += `<option value="${c.id}">${c.nombre}</option>`;
    });

    const html = `
        <div class="card mb-3 item-row" id="item_${itemIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Producto *</label>
                        <input type="text" class="form-control item-producto" list="productos-datalist" id="producto_input_${itemIndex}" data-index="${itemIndex}" placeholder="Escriba para buscar..." oninput="cargarProductoDesdeInput(${itemIndex})" required>
                        <input type="hidden" id="producto_id_${itemIndex}" name="items[${itemIndex}][producto_id]">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="nuevo_${itemIndex}" name="items[${itemIndex}][nuevo]" value="1" onchange="toggleNuevoProducto(${itemIndex})">
                            <label class="form-check-label" for="nuevo_${itemIndex}">Nuevo producto</label>
                        </div>
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
                    <div class="col-md-4">
                        <label class="form-label">Color (opcional)</label>
                        <select class="form-select item-color" name="items[${itemIndex}][color_opcion_id]" disabled>
                            <option value="">-- Sin color --</option>
                        </select>
                        <small class="text-muted">Solo para materiales con stock por color</small>
                    </div>
                </div>
                <div class="row mt-2">
                    <div class="col-md-12">
                        <div id="atributos-container-${itemIndex}" style="display:none; padding: 10px; border: 1px dashed #ddd; border-radius: 6px;">
                            <h6 class="mb-2">Atributos</h6>
                            <div id="atributos-list-${itemIndex}" class="row g-2"></div>
                        </div>
                    </div>
                </div>
                <div class="row mt-3 nuevo-producto-fields" id="nuevo_fields_${itemIndex}" style="display:none;">
                    <div class="col-md-4">
                        <label class="form-label">Nombre nuevo *</label>
                        <input type="text" class="form-control" name="items[${itemIndex}][nombre_nuevo]" placeholder="Nombre del producto">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría *</label>
                        <select class="form-select" name="items[${itemIndex}][categoria_id]">
                            ${categoriasOptions}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo de precio</label>
                        <select class="form-select" name="items[${itemIndex}][tipo_precio_nuevo]" onchange="toggleMedidasNuevo(${itemIndex})">
                            <option value="fijo">Fijo</option>
                            <option value="variable">Variable</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio base</label>
                        <input type="number" class="form-control" name="items[${itemIndex}][precio_base_nuevo]" step="0.01" min="0" placeholder="Opcional">
                    </div>
                    <div class="col-md-3 mt-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="items[${itemIndex}][mostrar_ecommerce]" id="mostrar_${itemIndex}" checked>
                            <label class="form-check-label" for="mostrar_${itemIndex}">Mostrar en Ecommerce</label>
                        </div>
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

function asegurarDatalistProductos() {
    let datalist = document.getElementById('productos-datalist');
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'productos-datalist';
        productos.forEach(p => {
            const option = document.createElement('option');
            option.value = p.nombre;
            datalist.appendChild(option);
        });
        document.body.appendChild(datalist);
    }
}

function obtenerProductoPorTexto(texto) {
    const textoNormalizado = String(texto || '').toLowerCase().trim();
    if (!textoNormalizado) return null;
    return productos.find(p => String(p.nombre).toLowerCase() === textoNormalizado) || null;
}

function cargarProductoDesdeInput(index) {
    const input = document.getElementById(`producto_input_${index}`);
    const hidden = document.getElementById(`producto_id_${index}`);
    if (!input || !hidden) return;

    const producto = obtenerProductoPorTexto(input.value);
    if (!producto) {
        hidden.value = '';
        toggleMedidas(index);
        return;
    }

    hidden.value = producto.id;
    toggleMedidas(index);
    cargarAtributosProducto(index);
}

function toggleMedidas(index) {
    const productoId = document.getElementById(`producto_id_${index}`)?.value;
    const producto = productos.find(p => String(p.id) === String(productoId));
    const tipo = producto?.tipo_precio || 'fijo';
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

    actualizarColores(index);
    cargarAtributosProducto(index);
}

function toggleMedidasNuevo(index) {
    const select = document.querySelector(`#item_${index} select[name="items[${index}][tipo_precio_nuevo]"]`);
    const tipo = select?.value || 'fijo';
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

function toggleNuevoProducto(index) {
    const nuevo = document.getElementById(`nuevo_${index}`).checked;
    const input = document.getElementById(`producto_input_${index}`);
    const hidden = document.getElementById(`producto_id_${index}`);
    const fields = document.getElementById(`nuevo_fields_${index}`);

    if (nuevo) {
        if (input) {
            input.value = '';
            input.disabled = true;
        }
        if (hidden) hidden.value = '';
        fields.style.display = 'flex';
        toggleMedidasNuevo(index);
        actualizarColores(index, true);
        limpiarAtributos(index);
    } else {
        if (input) input.disabled = false;
        fields.style.display = 'none';
        toggleMedidas(index);
    }
}

function limpiarAtributos(index) {
    const contenedor = document.getElementById(`atributos-container-${index}`);
    const lista = document.getElementById(`atributos-list-${index}`);
    if (contenedor) contenedor.style.display = 'none';
    if (lista) lista.innerHTML = '';
}

function cargarAtributosProducto(index) {
    const productoId = document.getElementById(`producto_id_${index}`)?.value;
    const contenedor = document.getElementById(`atributos-container-${index}`);
    const lista = document.getElementById(`atributos-list-${index}`);
    if (!contenedor || !lista) return;

    if (!productoId) {
        limpiarAtributos(index);
        return;
    }

    fetch(`productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributos = data?.atributos || [];
            lista.innerHTML = '';
            if (!atributos.length) {
                contenedor.style.display = 'none';
                return;
            }

            atributos.forEach(attr => {
                const fieldName = `items[${index}][atributos][${attr.id}]`;
                let inputHTML = '';

                if (attr.tipo === 'text') {
                    inputHTML = `<input type="text" class="form-control" name="${fieldName}[valor]">`;
                } else if (attr.tipo === 'number') {
                    inputHTML = `<input type="number" class="form-control" name="${fieldName}[valor]" step="0.01">`;
                } else if (attr.tipo === 'color') {
                    inputHTML = `<input type="color" class="form-control form-control-color" name="${fieldName}[valor]" value="#000000">`;
                } else if (attr.tipo === 'select') {
                    const opciones = Array.isArray(attr.opciones) && attr.opciones.length > 0
                        ? attr.opciones.map(o => ({ valor: o.nombre }))
                        : (attr.valores ? attr.valores.split(',').map(v => ({ valor: v.trim() })) : []);
                    inputHTML = `
                        <select class="form-select" name="${fieldName}[valor]">
                            <option value="">Seleccionar...</option>
                            ${opciones.map(o => `<option value="${o.valor}">${o.valor}</option>`).join('')}
                        </select>
                    `;
                }

                if (!inputHTML) return;

                const col = document.createElement('div');
                col.className = 'col-md-4';
                col.innerHTML = `
                    <label class="form-label small">${attr.nombre}</label>
                    ${inputHTML}
                    <input type="hidden" name="${fieldName}[nombre]" value="${attr.nombre}">
                `;
                lista.appendChild(col);
            });

            contenedor.style.display = 'block';
        })
        .catch(() => {
            limpiarAtributos(index);
        });
}

function actualizarColores(index, forzarVacio = false) {
    const productoId = document.getElementById(`producto_id_${index}`)?.value;
    const selectColor = document.querySelector(`#item_${index} .item-color`);
    if (!selectColor) return;

    if (forzarVacio || !productoId) {
        selectColor.innerHTML = '<option value="">-- Sin color --</option>';
        selectColor.disabled = true;
        return;
    }

    const productoIdNum = parseInt(productoId, 10);
    const opciones = colorOptionsByProduct?.[productoIdNum] || [];
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
