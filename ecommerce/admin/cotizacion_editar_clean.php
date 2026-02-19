<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';

$id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("Cotización no encontrada");
}

$items = json_decode($cotizacion['items'], true) ?? [];
$mensaje = '';
$error = '';

// Tabla de cupones
$pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_cupones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('porcentaje','monto') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Columnas de cupón en cotizaciones
$cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('cupon_codigo', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_codigo VARCHAR(50) NULL");
}
if (!in_array('cupon_descuento', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_descuento DECIMAL(10,2) NULL");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre_cliente = $_POST['nombre_cliente'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $validez_dias = intval($_POST['validez_dias'] ?? 15);
        $lista_precio_id = !empty($_POST['lista_precio_id']) ? intval($_POST['lista_precio_id']) : null;

        if (empty($nombre_cliente)) {
            throw new Exception("Nombre es obligatorio");
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }

        $items_nuevos = [];
        $subtotal = 0;

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['nombre']) || empty($item['cantidad']) || empty($item['precio'])) {
                    continue;
                }

                $cantidad = intval($item['cantidad']);
                $precio = floatval($item['precio']);
                $total_item = $cantidad * $precio;

                $atributos = [];
                $costo_atributos_total = 0;
                if (!empty($item['atributos']) && is_array($item['atributos'])) {
                    foreach ($item['atributos'] as $attr) {
                        if (!empty($attr['nombre'])) {
                            $costo = floatval($attr['costo'] ?? 0);
                            $atributos[] = [
                                'nombre' => $attr['nombre'],
                                'valor' => $attr['valor'] ?? '',
                                'costo_adicional' => $costo
                            ];
                            $costo_atributos_total += $costo;
                        }
                    }
                }

                $precio_total_unitario = $precio + $costo_atributos_total;
                $total_item = $cantidad * $precio_total_unitario;

                $items_nuevos[] = [
                    'producto_id' => !empty($item['producto_id']) ? intval($item['producto_id']) : null,
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $cantidad,
                    'ancho' => !empty($item['ancho']) ? floatval($item['ancho']) : null,
                    'alto' => !empty($item['alto']) ? floatval($item['alto']) : null,
                    'precio_base' => $precio,
                    'atributos' => $atributos,
                    'precio_unitario' => $precio_total_unitario,
                    'precio_total' => $total_item
                ];

                $subtotal += $total_item;
            }
        }

        if (empty($items_nuevos)) {
            throw new Exception("Debe agregar al menos un item");
        }

        $descuento = floatval($_POST['descuento'] ?? 0);
        $cupon_codigo = normalizar_codigo_descuento((string)($_POST['cupon_codigo'] ?? ''));
        $cupon_descuento = floatval($_POST['cupon_descuento'] ?? 0);
        if ($cupon_codigo !== '') {
            $descuento_row = obtener_descuento_por_codigo($pdo, $cupon_codigo);
            if (!$descuento_row) {
                throw new Exception('Cupón inválido');
            }
            $validacion = validar_descuento($descuento_row, $subtotal);
            if (!$validacion['valido']) {
                throw new Exception($validacion['mensaje']);
            }
            $cupon_descuento = calcular_monto_descuento($descuento_row['tipo'], (float)$descuento_row['valor'], $subtotal);
        } else {
            $cupon_descuento = 0;
        }

        $total = $subtotal - $descuento - $cupon_descuento;

        $stmt = $pdo->prepare("
            UPDATE ecommerce_cotizaciones
            SET nombre_cliente = ?, email = ?, telefono = ?, direccion = ?, lista_precio_id = ?, items = ?,
                subtotal = ?, descuento = ?, cupon_codigo = ?, cupon_descuento = ?, total = ?, observaciones = ?, validez_dias = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $nombre_cliente,
            $email,
            $telefono,
            $direccion,
            $lista_precio_id,
            json_encode($items_nuevos),
            $subtotal,
            $descuento,
            $cupon_codigo ?: null,
            $cupon_descuento,
            $total,
            $observaciones,
            $validez_dias,
            $id
        ]);

        header("Location: cotizacion_detalle.php?id=" . $id);
        exit;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener productos activos para el selector
$stmt = $pdo->query("
    SELECT id, nombre, tipo_precio, precio_base, categoria_id
    FROM ecommerce_productos
    WHERE activo = 1
    ORDER BY nombre
");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listas de precios activas
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_listas_precios WHERE activo = 1 ORDER BY nombre");
$listas_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT lista_precio_id, producto_id, precio_nuevo, descuento_porcentaje FROM ecommerce_lista_precio_items WHERE activo = 1");
$lista_items_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_items_map = [];
foreach ($lista_items_rows as $row) {
    $lista_items_map[$row['lista_precio_id']][$row['producto_id']] = [
        'precio_nuevo' => (float)$row['precio_nuevo'],
        'descuento_porcentaje' => (float)$row['descuento_porcentaje']
    ];
}

$stmt = $pdo->query("SELECT lista_precio_id, categoria_id, descuento_porcentaje FROM ecommerce_lista_precio_categorias WHERE activo = 1");
$lista_cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_cat_map = [];
foreach ($lista_cat_rows as $row) {
    $lista_cat_map[$row['lista_precio_id']][$row['categoria_id']] = (float)$row['descuento_porcentaje'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>✏️ Editar Cotización <?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></h1>
    </div>
    <a href="cotizacion_detalle.php?id=<?= $id ?>" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="formCotizacion">
    <style>
        .attr-option-item {
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }
        .attr-option-item.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,.2);
            background: #e7f1ff;
        }
        .item-resumen-title {
            font-weight: 600;
            font-size: 1rem;
        }
        .item-resumen-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .item-resumen-attrs .badge {
            font-weight: 500;
        }
    </style>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">👤 Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nombre_cliente" class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars($cotizacion['nombre_cliente'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cotizacion['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cotizacion['telefono'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($cotizacion['direccion'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">⚙️ Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="validez_dias" class="form-label">Validez (días)</label>
                        <input type="number" class="form-control" id="validez_dias" name="validez_dias" value="<?= (int)$cotizacion['validez_dias'] ?>" min="1" max="90">
                        <small class="text-muted">Días de validez del presupuesto</small>
                    </div>
                    <div class="mb-3">
                        <label for="lista_precio_id" class="form-label">Lista de Precios</label>
                        <select class="form-select" id="lista_precio_id" name="lista_precio_id" onchange="aplicarListaPrecios()">
                            <option value="">-- Sin lista --</option>
                            <?php foreach ($listas_precios as $lista): ?>
                                <option value="<?= $lista['id'] ?>" <?= (int)($cotizacion['lista_precio_id'] ?? 0) === (int)$lista['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lista['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Aplica descuentos por producto o categoría</small>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($cotizacion['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Cotización</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm" onclick="actualizarPreciosCotizacion()">🔄 Actualizar precios</button>
                <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
            </div>
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
                        <tr>
                            <th><label for="descuento">Descuento:</label></th>
                            <td class="text-end">
                                <input type="number" class="form-control form-control-sm text-end" id="descuento" name="descuento" value="<?= (float)($cotizacion['descuento'] ?? 0) ?>" step="0.01" min="0" onchange="calcularTotales()">
                                <small id="descuento_lista_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cupon_codigo">Cupón:</label></th>
                            <td class="text-end">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control text-end" id="cupon_codigo" name="cupon_codigo" value="<?= htmlspecialchars($cotizacion['cupon_codigo'] ?? '') ?>" placeholder="Código">
                                    <button class="btn btn-outline-secondary" type="button" onclick="aplicarCupon()">Aplicar</button>
                                </div>
                                <input type="hidden" id="cupon_descuento" name="cupon_descuento" value="<?= (float)($cotizacion['cupon_descuento'] ?? 0) ?>">
                                <small id="cupon_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <th>TOTAL:</th>
                            <th class="text-end"><span id="total">$0.00</span></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal-backdrop.show {
            opacity: 0.4;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        .modal-content {
            border-radius: 12px;
        }
    </style>

    <!-- Modal Agregar/Editar Item -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header px-4 py-3">
                    <h5 class="modal-title" id="itemModalLabel">Agregar item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="itemModalForm" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Producto del catálogo</label>
                                <input type="text" class="form-control" list="productos-datalist" id="producto_input_modal" placeholder="Escriba para buscar..." oninput="cargarProductoDesdeModalInput()">
                                <input type="hidden" id="producto_id_modal">
                                <small class="text-muted">O completá manualmente los campos.</small>
                            </div>
                            <div class="col-md-5">
                                <div id="precio-info-modal" class="alert alert-info mt-4" style="display:none; padding: 8px; margin: 0;"></div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="nombre_modal" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Descripción</label>
                                <input type="text" class="form-control" id="descripcion_modal">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ancho (cm)</label>
                                <input type="number" class="form-control" id="ancho_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Alto (cm)</label>
                                <input type="number" class="form-control" id="alto_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Cant. *</label>
                                <input type="number" class="form-control" id="cantidad_modal" value="1" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Precio Unit. *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_modal" step="0.01" min="0" required onchange="actualizarBasePrecioModal()">
                                </div>
                                <small class="text-muted">Se guarda como precio base, sin atributos.</small>
                            </div>
                        </div>

                        <div id="atributos-container-modal" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <h6 class="mb-3">🎨 Atributos del Producto</h6>
                            <div id="atributos-list-modal"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarItemBtn">Guardar item</button>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Cambios</button>
        <a href="cotizacion_detalle.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
let itemIndex = 0;
let modalEditIndex = null;
const itemsExistentes = <?= json_encode($items) ?>;
const productos = <?= json_encode($productos) ?>;
const listaItems = <?= json_encode($lista_items_map) ?>;
const listaCategorias = <?= json_encode($lista_cat_map) ?>;

function productoLabel(p) {
    const precioLabel = p.tipo_precio === 'variable'
        ? '(Precio variable)'
        : '($' + parseFloat(p.precio_base).toFixed(2) + ')';
    return `${p.nombre} ${precioLabel}`;
}

function obtenerListaSeleccionada() {
    const select = document.getElementById('lista_precio_id');
    return select ? parseInt(select.value || '0', 10) : 0;
}

function calcularPrecioConLista(productoId, precioBase) {
    const listaId = obtenerListaSeleccionada();
    if (!listaId) {
        return precioBase;
    }

    const item = listaItems?.[listaId]?.[productoId];
    if (item) {
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        const descItem = parseFloat(item.descuento_porcentaje || 0);
        if (precioNuevo > 0) {
            return precioNuevo;
        }
        if (descItem > 0) {
            return precioBase * (1 - descItem / 100);
        }
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    const categoriaId = producto?.categoria_id;
    const descCat = listaCategorias?.[listaId]?.[categoriaId] ?? 0;
    if (descCat > 0) {
        return precioBase * (1 - descCat / 100);
    }

    return precioBase;
}

function asegurarDatalistProductos() {
    let datalist = document.getElementById('productos-datalist');
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'productos-datalist';
        const listaProductos = Array.isArray(productos) ? productos : [];
        listaProductos.forEach(p => {
            const option = document.createElement('option');
            option.value = productoLabel(p);
            datalist.appendChild(option);
        });
        document.body.appendChild(datalist);
    }
}

function agregarItem(itemData = null) {
    if (!itemData) {
        abrirModalItem();
        return;
    }

    itemIndex++;
    const normalizado = normalizarItemData(itemData);
    const html = renderItemResumen(itemIndex, normalizado);
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    calcularTotales();
}

function normalizarItemData(item) {
    const costoAtributosItem = Array.isArray(item.atributos)
        ? item.atributos.reduce((sum, attr) => {
            const costo = parseFloat(attr?.costo_adicional ?? attr?.costo ?? 0) || 0;
            return sum + costo;
        }, 0)
        : 0;

    let precioBaseCalculado = null;
    if (item.precio_base !== undefined && item.precio_base !== null && String(item.precio_base) !== '') {
        precioBaseCalculado = parseFloat(item.precio_base);
    } else if (item.precio_unitario !== undefined && item.precio_unitario !== null && String(item.precio_unitario) !== '') {
        precioBaseCalculado = parseFloat(item.precio_unitario) - costoAtributosItem;
    } else if (item.precio !== undefined && item.precio !== null && String(item.precio) !== '') {
        precioBaseCalculado = parseFloat(item.precio);
    }

    if (!isFinite(precioBaseCalculado) || precioBaseCalculado < 0) {
        precioBaseCalculado = 0;
    }

    return {
        producto_id: item.producto_id || '',
        nombre: item.nombre || '',
        descripcion: item.descripcion || '',
        ancho: item.ancho || '',
        alto: item.alto || '',
        cantidad: item.cantidad || 1,
        precio: precioBaseCalculado.toFixed(2),
        atributos: Array.isArray(item.atributos) ? item.atributos.map(a => ({
            id: a.id,
            nombre: a.nombre,
            valor: a.valor,
            costo: parseFloat(a.costo_adicional ?? a.costo ?? 0) || 0
        })) : []
    };
}

function abrirModalItem(editIndex = null) {
    modalEditIndex = editIndex;
    asegurarDatalistProductos();
    resetearModalItem();

    if (editIndex) {
        const itemData = obtenerItemDesdeDOM(editIndex);
        if (itemData) {
            document.getElementById('itemModalLabel').textContent = 'Editar item';
            document.getElementById('producto_input_modal').value = itemData.productoLabel || '';
            document.getElementById('producto_id_modal').value = itemData.producto_id || '';
            document.getElementById('nombre_modal').value = itemData.nombre || '';
            document.getElementById('descripcion_modal').value = itemData.descripcion || '';
            document.getElementById('ancho_modal').value = itemData.ancho || '';
            document.getElementById('alto_modal').value = itemData.alto || '';
            document.getElementById('cantidad_modal').value = itemData.cantidad || 1;
            const precioInput = document.getElementById('precio_modal');
            precioInput.value = itemData.precio || '';
            precioInput.dataset.base = itemData.precio || '';
            if (itemData.producto_id) {
                cargarAtributosProductoModal(itemData.producto_id, itemData.atributos || []);
            }
        }
    } else {
        document.getElementById('itemModalLabel').textContent = 'Agregar item';
    }

    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
    if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
        return;
    }
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}

function resetearModalItem() {
    const form = document.getElementById('itemModalForm');
    if (form) form.reset();
    const productoIdInput = document.getElementById('producto_id_modal');
    if (productoIdInput) productoIdInput.value = '';
    const precioInput = document.getElementById('precio_modal');
    if (precioInput) {
        precioInput.value = '';
        precioInput.dataset.base = '';
    }
    const info = document.getElementById('precio-info-modal');
    if (info) info.style.display = 'none';
    const attrsContainer = document.getElementById('atributos-list-modal');
    if (attrsContainer) attrsContainer.innerHTML = '';
    const attrsWrapper = document.getElementById('atributos-container-modal');
    if (attrsWrapper) attrsWrapper.style.display = 'none';
}

function obtenerProductoPorTexto(texto) {
    const textoNormalizado = texto.toLowerCase();
    return productos.find(p => productoLabel(p).toLowerCase() === textoNormalizado);
}

function cargarProductoDesdeModalInput() {
    const input = document.getElementById('producto_input_modal');
    const texto = input.value.trim();
    const producto = obtenerProductoPorTexto(texto);

    if (!producto) {
        document.getElementById('producto_id_modal').value = '';
        if (texto === '') {
            resetearModalItem();
        }
        return;
    }

    document.getElementById('producto_id_modal').value = producto.id;
    aplicarProductoModal(producto);
}

function aplicarProductoModal(producto) {
    document.getElementById('nombre_modal').value = producto.nombre;
    cargarAtributosProductoModal(producto.id);

    const precioInput = document.getElementById('precio_modal');
    const info = document.getElementById('precio-info-modal');

    if (producto.tipo_precio === 'fijo') {
        const precioBase = parseFloat(producto.precio_base || 0);
        precioInput.dataset.base = precioBase.toFixed(2);
        precioInput.value = precioBase.toFixed(2);
        if (info) {
            info.innerHTML = '✓ Precio fijo del producto';
            info.style.display = 'block';
        }
    } else {
        precioInput.value = '';
        precioInput.dataset.base = '';
        if (info) {
            info.innerHTML = '⚠️ Ingrese ancho y alto para calcular precio';
            info.style.display = 'block';
        }
    }
}

function cargarAtributosProductoModal(productoId, valoresExistentes = []) {
    fetch(`productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributosContainer = document.getElementById('atributos-list-modal');
            atributosContainer.innerHTML = '';

            if (data.atributos && data.atributos.length > 0) {
                document.getElementById('atributos-container-modal').style.display = 'block';

                data.atributos.forEach(attr => {
                    const requerido = attr.es_obligatorio ? 'required' : '';
                    let inputHTML = '';

                    if (attr.tipo === 'text') {
                        inputHTML = `<input type="text" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'number') {
                        inputHTML = `<input type="number" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" step="0.01" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'color') {
                        const inputId = `modal_attr_${attr.id}`;
                        const previewId = `modal_color_preview_${attr.id}`;
                        inputHTML = `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="color" class="form-control form-control-color" id="${inputId}" data-attr-valor="${attr.id}" value="#000000" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">
                                <div class="border rounded" id="${previewId}" style="width: 28px; height: 28px; background-color: #000000;"></div>
                            </div>
                        `;
                    } else if (attr.tipo === 'select') {
                        const opciones = Array.isArray(attr.opciones) && attr.opciones.length > 0
                            ? attr.opciones.map(o => ({
                                valor: o.nombre,
                                color: o.color,
                                imagen: o.imagen,
                                costo: o.costo_adicional
                            }))
                            : (attr.valores ? attr.valores.split(',').map(v => ({ valor: v.trim() })) : []);

                        inputHTML = `
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                ${opciones.map((o, i) => {
                                    const hasColor = o.color && /^#[0-9A-Fa-f]{6}$/.test(o.color);
                                    const colorBox = hasColor ? `<div class="rounded" style="width: 80px; height: 80px; background-color: ${o.color}; border: 1px solid #ddd;"></div>` : '';
                                    const imgTag = o.imagen ? `<img src="../uploads/atributos/${o.imagen}" alt="${o.valor}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block;">` : '';
                                    const placeholder = !hasColor && !o.imagen ? `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 80px; height: 80px;"><small class="text-center text-muted">${o.valor}</small></div>` : '';
                                    const costoBadge = o.costo > 0 ? `<span class="badge bg-success position-absolute" style="top: -8px; right: -8px;">+$${parseFloat(o.costo).toFixed(2)}</span>` : '';
                                    const label = `<small class="d-block text-center mt-1 text-muted">${o.valor}</small>`;
                                    return `
                                        <div class="position-relative">
                                            <label class="cursor-pointer position-relative" style="cursor: pointer;">
                                                <input type="radio" name="modal_attr_${attr.id}" value="${o.valor}" class="d-none attr-radio" data-attr-id="${attr.id}" data-costo="${o.costo || 0}" ${requerido} onchange="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, this.dataset.costo, this.value); marcarOpcionAtributo(this);">
                                                <div class="attr-option position-relative" style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 4px; transition: all 0.2s ease; background: #fff;">
                                                    ${colorBox || imgTag || placeholder}
                                                    ${costoBadge}
                                                    ${label}
                                                </div>
                                            </label>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        `;
                    }

                    const attrHTML = `
                        <div class="mb-2 modal-attr-item" data-attr-id="${attr.id}" data-attr-nombre="${attr.nombre}" data-required="${attr.es_obligatorio ? 1 : 0}">
                            <label class="form-label small mb-1">
                                ${attr.nombre}
                                ${attr.costo_adicional > 0 ? `<span class="badge bg-warning text-dark">+$${parseFloat(attr.costo_adicional).toFixed(2)}</span>` : ''}
                            </label>
                            ${inputHTML}
                            <input type="hidden" id="modal_attr_costo_${attr.id}" value="0" data-base="${attr.costo_adicional}">
                        </div>
                    `;
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);

                    if (attr.tipo === 'color') {
                        const colorInput = document.getElementById(`modal_attr_${attr.id}`);
                        const preview = document.getElementById(`modal_color_preview_${attr.id}`);
                        if (colorInput && preview) {
                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                            colorInput.addEventListener('input', updatePreview);
                            updatePreview();
                        }
                    }
                });

                if (Array.isArray(valoresExistentes) && valoresExistentes.length > 0) {
                    valoresExistentes.forEach(v => {
                        const wrapper = document.querySelector(`.modal-attr-item[data-attr-id="${v.id}"]`);
                        if (!wrapper) return;
                        const radio = wrapper.querySelector(`input[type="radio"][value="${v.valor}"]`);
                        if (radio) {
                            radio.checked = true;
                            marcarOpcionAtributo(radio);
                        }
                        const input = wrapper.querySelector(`[data-attr-valor="${v.id}"]`);
                        if (input) {
                            input.value = v.valor || '';
                        }
                        const baseInput = document.getElementById(`modal_attr_costo_${v.id}`);
                        const baseCosto = baseInput ? baseInput.dataset.base : 0;
                        actualizarCostoAtributoModal(v.id, baseCosto || 0, v.costo || 0, v.valor);
                    });
                }
            } else {
                document.getElementById('atributos-container-modal').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando atributos:', error);
            document.getElementById('atributos-container-modal').style.display = 'none';
        });
}

function actualizarCostoAtributoModal(attrId, costoBase, costoOpcion, valorSeleccionado) {
    const inputCosto = document.getElementById(`modal_attr_costo_${attrId}`);
    if (!inputCosto) return;

    const base = parseFloat(costoBase || 0);
    const opcion = parseFloat(costoOpcion || 0);
    const tieneValor = valorSeleccionado !== undefined && valorSeleccionado !== null && String(valorSeleccionado).trim() !== '';

    if (!tieneValor) {
        inputCosto.value = '0';
    } else if (opcion > 0) {
        inputCosto.value = opcion.toFixed(2);
    } else if (base > 0) {
        inputCosto.value = base.toFixed(2);
    } else {
        inputCosto.value = '0';
    }
}

function actualizarPrecioItemModal() {
    const productoId = document.getElementById('producto_id_modal')?.value;
    if (!productoId) return;

    const producto = productos.find(p => String(p.id) === String(productoId));
    if (producto?.tipo_precio === 'variable') {
        const ancho = parseFloat(document.getElementById('ancho_modal').value || 0);
        const alto = parseFloat(document.getElementById('alto_modal').value || 0);

        if (ancho > 0 && alto > 0) {
            fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    const precioBase = parseFloat(data.precio || 0);
                    const precioInput = document.getElementById('precio_modal');
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                    const info = document.getElementById('precio-info-modal');
                    if (info) {
                        info.innerHTML = '✓ ' + data.precio_info;
                        info.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }
}

function actualizarBasePrecioModal() {
    const precioInput = document.getElementById('precio_modal');
    if (!precioInput) return;
    precioInput.dataset.base = precioInput.value || '';
}

function obtenerItemDesdeDOM(index) {
    const row = document.getElementById(`item_${index}`);
    if (!row) return null;
    const getVal = (id) => document.getElementById(id)?.value || '';
    const productoId = getVal(`producto_id_${index}`);
    const producto = productos.find(p => String(p.id) === String(productoId));
    const atributos = [];

    row.querySelectorAll(`input[name^="items[${index}][atributos]"][name$="[nombre]"]`).forEach(input => {
        const match = input.name.match(/atributos\]\[(\d+)\]\[nombre\]/);
        if (!match) return;
        const attrId = match[1];
        const valorInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][valor]"]`);
        const costoInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][costo]"]`);
        const valor = valorInput?.value || '';
        const costo = parseFloat(costoInput?.value || 0) || 0;
        atributos.push({ id: attrId, nombre: input.value, valor, costo });
    });

    return {
        producto_id: productoId,
        productoLabel: producto ? productoLabel(producto) : '',
        nombre: getVal(`nombre_${index}`),
        descripcion: getVal(`descripcion_${index}`),
        ancho: getVal(`ancho_${index}`),
        alto: getVal(`alto_${index}`),
        cantidad: getVal(`cantidad_${index}`),
        precio: getVal(`precio_${index}`),
        atributos
    };
}

function obtenerAtributosDesdeModal() {
    const atributos = [];
    let faltan = false;

    document.querySelectorAll('#atributos-list-modal .modal-attr-item').forEach(wrapper => {
        const attrId = wrapper.dataset.attrId;
        const nombre = wrapper.dataset.attrNombre || '';
        const requerido = wrapper.dataset.required === '1';
        let valor = '';

        const radio = wrapper.querySelector('input[type="radio"]:checked');
        if (radio) {
            valor = radio.value;
        } else {
            const input = wrapper.querySelector(`[data-attr-valor="${attrId}"]`);
            if (input) valor = input.value;
        }

        if (requerido && !valor) {
            faltan = true;
        }

        const costoInput = document.getElementById(`modal_attr_costo_${attrId}`);
        const costo = parseFloat(costoInput?.value || 0) || 0;

        if (valor) {
            atributos.push({ id: attrId, nombre, valor, costo });
        }
    });

    if (faltan) {
        alert('Completa los atributos obligatorios antes de guardar.');
        return null;
    }

    return atributos;
}

function renderItemResumen(index, itemData) {
    const atributos = itemData.atributos || [];
    const atributosResumen = atributos.length
        ? atributos.map(a => `<span class="badge bg-light text-dark me-1">${a.nombre}: ${a.valor}${a.costo > 0 ? ` (+$${parseFloat(a.costo).toFixed(2)})` : ''}</span>`).join('')
        : '<span class="text-muted">Sin atributos</span>';

    const dimensiones = (itemData.ancho && itemData.alto)
        ? `${itemData.ancho} x ${itemData.alto} cm`
        : '—';

    const html = `
        <div class="card mb-3 item-row" id="item_${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <div class="item-resumen-title">${itemData.nombre || 'Producto sin nombre'}</div>
                        ${itemData.descripcion ? `<div class="item-resumen-meta">${itemData.descripcion}</div>` : ''}
                        <div class="item-resumen-meta">Cantidad: <strong>${itemData.cantidad}</strong> · Medidas: <strong>${dimensiones}</strong></div>
                        <div class="item-resumen-meta">Precio base: <strong>$${parseFloat(itemData.precio || 0).toFixed(2)}</strong></div>
                        <div class="item-resumen-attrs mt-2">${atributosResumen}</div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-primary-subtle text-primary border" style="font-size: 0.95rem;">
                            Subtotal: $<span class="item-subtotal-text" id="item_subtotal_text_${index}">0.00</span>
                        </div>
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalItem(${index})">✏️ Editar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem(${index})">🗑️ Eliminar</button>
                        </div>
                    </div>
                </div>

                <input type="hidden" class="item-nombre" id="nombre_${index}" name="items[${index}][nombre]" value="${itemData.nombre || ''}">
                <input type="hidden" id="descripcion_${index}" name="items[${index}][descripcion]" value="${itemData.descripcion || ''}">
                <input type="hidden" class="item-ancho" id="ancho_${index}" name="items[${index}][ancho]" value="${itemData.ancho || ''}">
                <input type="hidden" class="item-alto" id="alto_${index}" name="items[${index}][alto]" value="${itemData.alto || ''}">
                <input type="hidden" class="item-cantidad" id="cantidad_${index}" name="items[${index}][cantidad]" value="${itemData.cantidad || 1}">
                <input type="hidden" class="item-precio" id="precio_${index}" name="items[${index}][precio]" value="${itemData.precio || 0}" data-base="${itemData.precio || 0}">
                <input type="hidden" id="producto_id_${index}" name="items[${index}][producto_id]" value="${itemData.producto_id || ''}">
                <input type="text" class="form-control item-subtotal" id="subtotal_${index}" readonly style="display:none;">
                <div id="precio-info-${index}" style="display:none;"></div>
                ${atributos.map(a => `
                    <input type="hidden" name="items[${index}][atributos][${a.id}][nombre]" value="${a.nombre}">
                    <input type="hidden" name="items[${index}][atributos][${a.id}][valor]" value="${a.valor}">
                    <input type="hidden" name="items[${index}][atributos][${a.id}][costo]" value="${parseFloat(a.costo || 0).toFixed(2)}">
                `).join('')}
            </div>
        </div>
    `;

    return html;
}

function guardarItemDesdeModal() {
    const form = document.getElementById('itemModalForm');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const atributos = obtenerAtributosDesdeModal();
    if (atributos === null) return;

    const cantidadValue = parseInt(document.getElementById('cantidad_modal').value || '0', 10);
    const precioRaw = String(document.getElementById('precio_modal').value || '').replace(',', '.');
    const precioValue = parseFloat(precioRaw);

    if (!isFinite(cantidadValue) || cantidadValue <= 0) {
        alert('Ingresá una cantidad válida.');
        return;
    }
    if (!isFinite(precioValue) || precioValue <= 0) {
        alert('Ingresá un precio válido.');
        return;
    }

    const itemData = {
        producto_id: document.getElementById('producto_id_modal').value || '',
        nombre: document.getElementById('nombre_modal').value.trim(),
        descripcion: document.getElementById('descripcion_modal').value.trim(),
        ancho: document.getElementById('ancho_modal').value,
        alto: document.getElementById('alto_modal').value,
        cantidad: cantidadValue,
        precio: precioValue.toFixed(2),
        atributos
    };

    if (!itemData.nombre || !itemData.cantidad || !itemData.precio) {
        alert('Completa los campos obligatorios del item.');
        return;
    }

    try {
        let index = modalEditIndex;
        const itemsContainer = document.getElementById('itemsContainer');
        if (!itemsContainer) {
            alert('No se encontró el contenedor de items.');
            return;
        }
        if (!index) {
            itemIndex++;
            index = itemIndex;
            const html = renderItemResumen(index, itemData);
            itemsContainer.insertAdjacentHTML('beforeend', html);
        } else {
            const html = renderItemResumen(index, itemData);
            const row = document.getElementById(`item_${index}`);
            if (row) {
                row.outerHTML = html;
            }
        }
    } catch (err) {
        console.error('Error guardando item:', err);
        alert('No se pudo guardar el item.');
        return;
    }

    try {
        calcularTotales();
    } catch (err) {
        console.error('Error calculando totales:', err);
    }
    modalEditIndex = null;
    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
    if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
        if (modal) modal.hide();
        return;
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
}

function eliminarItem(index) {
    document.getElementById('item_' + index)?.remove();
    calcularTotales();
}

function calcularTotales() {
    let subtotal = 0;
    let descuentoListaTotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const precioInput = row.querySelector('.item-precio');
        if (precioInput && (precioInput.dataset.base === undefined || precioInput.dataset.base === '')) {
            precioInput.dataset.base = precioInput.value || '';
        }
        const precioBase = parseFloat(precioInput?.dataset.base || 0) || parseFloat(precioInput?.value || 0);
        let costoAtributos = 0;
        row.querySelectorAll('input[name*="[atributos]"][name$="[costo]"]').forEach(input => {
            costoAtributos += parseFloat(input.value || 0);
        });

    const subtotalItem = cantidad * (precioBase + costoAtributos);

        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        const subtotalText = row.querySelector('.item-subtotal-text');
        if (subtotalText) {
            subtotalText.textContent = subtotalItem.toFixed(2);
        }

        subtotal += subtotalItem;

        const productoId = row.querySelector('input[type="hidden"][id^="producto_id_"]')?.value;
        if (productoId) {
            const precioLista = calcularPrecioConLista(productoId, precioBase);
            const descUnit = Math.max(0, precioBase - precioLista);
            descuentoListaTotal += descUnit * cantidad;
        }
    });

    const listaId = obtenerListaSeleccionada();
    const descuentoInput = document.getElementById('descuento');
    const descuentoInfo = document.getElementById('descuento_lista_info');

    if (listaId && descuentoInput) {
        descuentoInput.value = descuentoListaTotal.toFixed(2);
        if (descuentoInfo) {
            descuentoInfo.textContent = `Base: $${subtotal.toFixed(2)} | Descuento lista: $${descuentoListaTotal.toFixed(2)}`;
        }
    } else if (descuentoInfo) {
        descuentoInfo.textContent = '';
    }

    const descuento = parseFloat(descuentoInput?.value || 0);
    const descuentoCupon = parseFloat(document.getElementById('cupon_descuento')?.value || 0);
    const total = subtotal - descuento - descuentoCupon;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

function aplicarCupon() {
    const codigo = document.getElementById('cupon_codigo')?.value?.trim() || '';
    const info = document.getElementById('cupon_info');
    const descuentoInput = document.getElementById('cupon_descuento');
    const subtotalText = document.getElementById('subtotal')?.textContent || '$0';
    const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;

    if (!codigo) {
        if (info) info.textContent = 'Ingresá un cupón.';
        if (descuentoInput) descuentoInput.value = '0';
        calcularTotales();
        return;
    }

    fetch(`cupones_validar.php?codigo=${encodeURIComponent(codigo)}&subtotal=${subtotal}`)
        .then(r => r.json())
        .then(data => {
            if (!data.valido) {
                if (info) info.textContent = data.mensaje || 'Cupón inválido.';
                if (descuentoInput) descuentoInput.value = '0';
            } else {
                if (descuentoInput) descuentoInput.value = data.descuento || 0;
                if (info) info.textContent = data.mensaje
                    ? `${data.mensaje} (-$${Number(data.descuento || 0).toFixed(2)})`
                    : `Descuento aplicado: $${Number(data.descuento || 0).toFixed(2)}`;
            }
            calcularTotales();
        })
        .catch(() => {
            if (info) info.textContent = 'No se pudo validar el cupón.';
        });
}

function marcarOpcionAtributo(radio) {
    if (!radio || !radio.name) return;
    const label = radio.closest('label');
    if (!label) return;
    const divPadre = label.parentElement;
    if (!divPadre || !divPadre.parentElement) return;
    const contenedorOpciones = divPadre.parentElement;

    contenedorOpciones.querySelectorAll('input[type="radio"][name="' + radio.name + '"]').forEach(r => {
        const l = r.closest('label');
        if (l) {
            const divOpcion = l.querySelector('.attr-option');
            if (divOpcion) {
                divOpcion.style.borderColor = '#ddd';
                divOpcion.style.boxShadow = 'none';
                divOpcion.style.background = '#fff';
            }
        }
    });

    if (radio.checked) {
        const divOpcion = label.querySelector('.attr-option');
        if (divOpcion) {
            divOpcion.style.borderColor = '#0d6efd';
            divOpcion.style.boxShadow = '0 0 0 2px rgba(13,110,253,.2)';
            divOpcion.style.background = '#e7f1ff';
        }
    }
}

function aplicarListaPrecios() {
    calcularTotales();
}

function obtenerIndexDesdeRow(row) {
    const id = row?.id || '';
    const match = id.match(/item_(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

function actualizarPreciosCotizacion() {
    const filas = Array.from(document.querySelectorAll('.item-row'));
    if (filas.length === 0) {
        return;
    }

    const tareas = filas.map(row => {
        const index = obtenerIndexDesdeRow(row);
        if (!index) return Promise.resolve();

        const productoId = document.getElementById(`producto_id_${index}`)?.value;
        if (!productoId) return Promise.resolve();

        const producto = productos.find(p => String(p.id) === String(productoId));
        if (!producto) return Promise.resolve();

        if (producto.tipo_precio === 'fijo') {
            const precioBase = parseFloat(producto.precio_base || 0);
            const precioInput = document.getElementById(`precio_${index}`);
            if (precioInput) {
                precioInput.dataset.base = precioBase.toFixed(2);
                precioInput.value = precioBase.toFixed(2);
            }
            return Promise.resolve();
        }

        const ancho = parseFloat(document.getElementById(`ancho_${index}`)?.value || 0);
        const alto = parseFloat(document.getElementById(`alto_${index}`)?.value || 0);
        if (ancho <= 0 || alto <= 0) {
            return Promise.resolve();
        }

        return fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    return;
                }
                const precioBase = parseFloat(data.precio || 0);
                const precioInput = document.getElementById(`precio_${index}`);
                if (precioInput) {
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                }
            })
            .catch(() => {});
    });

    Promise.all(tareas).then(() => {
        calcularTotales();
    });
}

document.addEventListener('change', function(e) {
    const radio = e.target;
    if (radio && radio.matches('input[type="radio"].attr-radio')) {
        marcarOpcionAtributo(radio);
    }
});

document.addEventListener('DOMContentLoaded', function() {
    if (itemsExistentes.length > 0) {
        itemsExistentes.forEach(item => {
            agregarItem(item);
        });
    }
    aplicarListaPrecios();
    const btnGuardar = document.getElementById('guardarItemBtn');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarItemDesdeModal);
    }
    const formModal = document.getElementById('itemModalForm');
    if (formModal) {
        formModal.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarItemDesdeModal();
        });
    }
});
</script>

<?php require 'includes/footer.php'; ?>
