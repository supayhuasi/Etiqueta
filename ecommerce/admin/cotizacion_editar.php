<?php
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);

// Obtener cotización
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
        
        // Validaciones
        if (empty($nombre_cliente)) {
            throw new Exception("Nombre es obligatorio");
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        
        // Procesar items
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
                
                // Procesar atributos si existen
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
                
                // Agregar costo de atributos al precio unitario
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
        $cupon_codigo = trim($_POST['cupon_codigo'] ?? '');
        $cupon_descuento = floatval($_POST['cupon_descuento'] ?? 0);
        if ($cupon_codigo !== '') {
            $hoy = date('Y-m-d');
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_cupones
                WHERE codigo = ? AND activo = 1
                AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
                AND (fecha_fin IS NULL OR fecha_fin >= ?)
                LIMIT 1
            ");
            $stmt->execute([$cupon_codigo, $hoy, $hoy]);
            $cupon = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cupon) {
                throw new Exception('Cupón inválido');
            }
            if ($cupon['tipo'] === 'porcentaje') {
                $cupon_descuento = $subtotal * ((float)$cupon['valor'] / 100);
            } else {
                $cupon_descuento = (float)$cupon['valor'];
            }
            $cupon_descuento = max(0, min($cupon_descuento, $subtotal));
        } else {
            $cupon_descuento = 0;
        }

        $total = $subtotal - $descuento - $cupon_descuento;
        
        // Actualizar cotización
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
        
        header("Location: cotizacion_detalle.php?id=" . $id . "&mensaje=actualizada");
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
    </style>
    <div class="row">
        <!-- Información del Cliente -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">👤 Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nombre_cliente" class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars($cotizacion['nombre_cliente']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cotizacion['email']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cotizacion['telefono']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($cotizacion['direccion']) ?>">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">⚙️ Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="validez_dias" class="form-label">Validez (días)</label>
                        <input type="number" class="form-control" id="validez_dias" name="validez_dias" value="<?= $cotizacion['validez_dias'] ?>" min="1" max="90">
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
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($cotizacion['observaciones']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Items -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Cotización</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm" onclick="actualizarPreciosCotizacion()">🔄 Actualizar precios</button>
                <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
            </div>
        </div>
        <div class="card-body">
            <div id="itemsContainer">
                <!-- Los items se cargan/agregan aquí -->
            </div>
            
            <div class="row mt-4">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <table class="table">
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-end"><span id="subtotal">$0.00</span></td>
                        </tr>
                        <tr>
                            <th>
                                <label for="descuento">Descuento:</label>
                            </th>
                            <td class="text-end">
                                <input type="number" class="form-control form-control-sm text-end" id="descuento" name="descuento" value="<?= $cotizacion['descuento'] ?>" step="0.01" min="0" onchange="calcularTotales()">
                                <small id="descuento_lista_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="cupon_codigo">Cupón:</label>
                            </th>
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
    
    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Cambios</button>
        <a href="cotizacion_detalle.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
let itemIndex = 0;
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

function actualizarCostoAtributo(index, attrId, costoBase, costoOpcion, valorSeleccionado) {
    const inputCosto = document.getElementById(`attr_costo_${attrId}_${index}`);
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

    calcularTotales();
}

function asegurarDatalistProductos() {
    let datalist = document.getElementById('productos-datalist');
    if (!datalist) {
        datalist = document.createElement('datalist');
        datalist.id = 'productos-datalist';
        productos.forEach(p => {
            const option = document.createElement('option');
            option.value = productoLabel(p);
            datalist.appendChild(option);
        });
        document.body.appendChild(datalist);
    }
}

function agregarItem(itemData = null) {
    itemIndex++;
    const item = itemData || {
        nombre: '',
        descripcion: '',
        ancho: '',
        alto: '',
        cantidad: 1,
        precio_unitario: 0
    };
    
    asegurarDatalistProductos();
    
    const html = `
        <div class="card mb-3 item-row" id="item_${itemIndex}">
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Producto del catálogo</label>
                        <input type="text" class="form-control producto-autocomplete" list="productos-datalist" id="producto_input_${itemIndex}" data-index="${itemIndex}" placeholder="Escriba para buscar..." oninput="cargarProductoDesdeInput(${itemIndex})">
                        <input type="hidden" id="producto_id_${itemIndex}" name="items[${itemIndex}][producto_id]">
                        <small class="text-muted">O edita manualmente abajo</small>
                    </div>
                    <div class="col-md-6">
                        <div id="precio-info-${itemIndex}" class="alert alert-info mt-4" style="display:none; padding: 8px; margin: 0;"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control item-nombre" id="nombre_${itemIndex}" name="items[${itemIndex}][nombre]" value="${item.nombre}" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="descripcion_${itemIndex}" name="items[${itemIndex}][descripcion]" value="${item.descripcion || ''}" onchange="calcularTotales()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Ancho (cm)</label>
                        <input type="number" class="form-control item-ancho" id="ancho_${itemIndex}" name="items[${itemIndex}][ancho]" value="${item.ancho || ''}" step="0.01" min="0" onchange="actualizarPrecioItem(${itemIndex})">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Alto (cm)</label>
                        <input type="number" class="form-control item-alto" id="alto_${itemIndex}" name="items[${itemIndex}][alto]" value="${item.alto || ''}" step="0.01" min="0" onchange="actualizarPrecioItem(${itemIndex})">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Cant. *</label>
                        <input type="number" class="form-control item-cantidad" id="cantidad_${itemIndex}" name="items[${itemIndex}][cantidad]" value="${item.cantidad}" min="1" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio Unit. *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control item-precio" id="precio_${itemIndex}" name="items[${itemIndex}][precio]" value="${item.precio_unitario || item.precio_base || ''}" step="0.01" min="0" required onchange="actualizarBasePrecio(${itemIndex})">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Subtotal</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control item-subtotal" id="subtotal_${itemIndex}" readonly>
                        </div>
                    </div>
                </div>
                
                <!-- Atributos del producto -->
                <div id="atributos-container-${itemIndex}" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                    <h6 class="mb-3">🎨 Atributos del Producto</h6>
                    <div id="atributos-list-${itemIndex}"></div>
                </div>
                
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="eliminarItem(${itemIndex})">🗑️ Eliminar</button>
            </div>
        </div>
    `;
    
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    if (item.producto_id) {
        const producto = productos.find(p => String(p.id) === String(item.producto_id));
        if (producto) {
            document.getElementById(`producto_input_${itemIndex}`).value = productoLabel(producto);
            document.getElementById(`producto_id_${itemIndex}`).value = producto.id;
            cargarAtributosProducto(producto.id, itemIndex, item.atributos || []);
        }
    } else if (item.nombre) {
        const producto = productos.find(p => p.nombre.toLowerCase() === String(item.nombre).toLowerCase());
        if (producto) {
            document.getElementById(`producto_input_${itemIndex}`).value = productoLabel(producto);
            document.getElementById(`producto_id_${itemIndex}`).value = producto.id;
            cargarAtributosProducto(producto.id, itemIndex, item.atributos || []);
        }
    }

    const precioInput = document.getElementById(`precio_${itemIndex}`);
    if (precioInput) {
        const base = item.precio_base || item.precio_unitario || '';
        if (base) {
            precioInput.dataset.base = parseFloat(base).toFixed(2);
        }
    }
    calcularTotales();
}

function limpiarProducto(index) {
    document.getElementById(`nombre_${index}`).value = '';
    document.getElementById(`descripcion_${index}`).value = '';
    document.getElementById(`precio_${index}`).value = '';
    document.getElementById(`precio_${index}`).dataset.base = '';
    document.getElementById(`precio-info-${index}`).style.display = 'none';
    document.getElementById(`atributos-container-${index}`).style.display = 'none';
    calcularTotales();
}

function actualizarBasePrecio(index) {
    const precioInput = document.getElementById(`precio_${index}`);
    if (!precioInput) return;
    precioInput.dataset.base = precioInput.value || '';
    calcularTotales();
}

function obtenerProductoPorTexto(texto) {
    const textoNormalizado = texto.toLowerCase();
    return productos.find(p => productoLabel(p).toLowerCase() === textoNormalizado);
}

function cargarProductoDesdeInput(index) {
    const input = document.getElementById(`producto_input_${index}`);
    const texto = input.value.trim();
    const producto = obtenerProductoPorTexto(texto);

    if (!producto) {
        document.getElementById(`producto_id_${index}`).value = '';
        if (texto === '') {
            limpiarProducto(index);
        }
        return;
    }

    document.getElementById(`producto_id_${index}`).value = producto.id;
    aplicarProducto(index, producto);
}

function aplicarProducto(index, producto) {
    // Llenar nombre
    document.getElementById(`nombre_${index}`).value = producto.nombre;

    // Cargar atributos del producto
    cargarAtributosProducto(producto.id, index);

    // Si es precio fijo, establecer precio inmediatamente
    if (producto.tipo_precio === 'fijo') {
        const precioBase = parseFloat(producto.precio_base);
        const precioInput = document.getElementById(`precio_${index}`);
        precioInput.dataset.base = precioBase.toFixed(2);
        precioInput.value = precioBase.toFixed(2);
        document.getElementById(`precio-info-${index}`).innerHTML = '✓ Precio fijo del producto';
        document.getElementById(`precio-info-${index}`).style.display = 'block';
        calcularTotales();
    } else {
        // Es precio variable, necesita medidas
        const precioInput = document.getElementById(`precio_${index}`);
        precioInput.value = '';
        precioInput.dataset.base = '';
        document.getElementById(`precio-info-${index}`).innerHTML = '⚠️ Ingrese ancho y alto para calcular precio';
        document.getElementById(`precio-info-${index}`).style.display = 'block';
    }
}

function cargarAtributosProducto(productoId, index, atributosExistentes = []) {
    const atributosMap = {};
    if (Array.isArray(atributosExistentes)) {
        atributosExistentes.forEach(attr => {
            if (attr && attr.nombre) {
                atributosMap[String(attr.nombre).toLowerCase()] = attr;
            }
        });
    }

    fetch(`productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributosContainer = document.getElementById(`atributos-list-${index}`);
            atributosContainer.innerHTML = '';
            
            if (data.atributos && data.atributos.length > 0) {
                document.getElementById(`atributos-container-${index}`).style.display = 'block';
                
                data.atributos.forEach(attr => {
                    let inputHTML = '';
                    const fieldName = `items[${index}][atributos][${attr.id}]`;
                    const requerido = attr.es_obligatorio ? 'required' : '';
                    
                    if (attr.tipo === 'text') {
                        inputHTML = `<input type="text" class="form-control form-control-sm mb-2" name="${fieldName}[valor]" ${requerido} oninput="actualizarCostoAtributo(${index}, ${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'number') {
                        inputHTML = `<input type="number" class="form-control form-control-sm mb-2" name="${fieldName}[valor]" step="0.01" ${requerido} oninput="actualizarCostoAtributo(${index}, ${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'color') {
                        const inputId = `attr_${attr.id}_${index}`;
                        const previewId = `color_preview_${attr.id}_${index}`;
                        inputHTML = `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="color" class="form-control form-control-color" id="${inputId}" name="${fieldName}[valor]" value="#000000" ${requerido} oninput="actualizarCostoAtributo(${index}, ${attr.id}, ${attr.costo_adicional}, 0, this.value)">
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

                        // Siempre mostrar como botones visuales (como en el ecommerce)
                        const inputId = `attr_${attr.id}_${index}`;
                        inputHTML = `
                            <input type="hidden" id="${inputId}" name="${fieldName}[valor]" ${requerido}>
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                ${opciones.map((o, i) => {
                                    const optId = `opt_${attr.id}_${index}_${i}`;
                                    const hasColor = o.color && /^#[0-9A-Fa-f]{6}$/.test(o.color);
                                    const colorBox = hasColor ? `<div class="rounded" style="width: 80px; height: 80px; background-color: ${o.color}; border: 1px solid #ddd;"></div>` : '';
                                    const imgTag = o.imagen ? `<img src="../../uploads/atributos/${o.imagen}" alt="${o.valor}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block;">` : '';
                                    const placeholder = !hasColor && !o.imagen ? `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 80px; height: 80px;"><small class="text-center text-muted">${o.valor}</small></div>` : '';
                                    const costoBadge = o.costo > 0 ? `<span class="badge bg-success position-absolute" style="top: -8px; right: -8px;">+$${parseFloat(o.costo).toFixed(2)}</span>` : '';
                                    const label = `<small class="d-block text-center mt-1 text-muted">${o.valor}</small>`;
                                    
                                    return `
                                        <div class="position-relative">
                                            <label class="cursor-pointer position-relative" style="cursor: pointer;">
                                                <input type="radio" name="${fieldName}[valor]" value="${o.valor}" class="d-none attr-radio" data-attr-id="${attr.id}" data-index="${index}" data-costo="${o.costo || 0}" ${requerido} onchange="actualizarCostoAtributo(${index}, ${attr.id}, ${attr.costo_adicional}, this.dataset.costo, this.value); marcarOpcionAtributo(this);">
                                                <div class="attr-option position-relative" id="${optId}" style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 4px; transition: all 0.2s ease; background: #fff;">
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
                        <div class="mb-2">
                            <label class="form-label small mb-1">
                                ${attr.nombre}
                                ${attr.costo_adicional > 0 ? `<span class="badge bg-warning text-dark">+$${parseFloat(attr.costo_adicional).toFixed(2)}</span>` : ''}
                            </label>
                            ${inputHTML}
                            <input type="hidden" name="${fieldName}[nombre]" value="${attr.nombre}">
                            <input type="hidden" id="attr_costo_${attr.id}_${index}" name="${fieldName}[costo]" value="0" data-base="${attr.costo_adicional}">
                        </div>
                    `;
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);

                    const existente = atributosMap[String(attr.nombre).toLowerCase()];
                    if (existente && existente.valor !== undefined && existente.valor !== null) {
                        const valorExistente = String(existente.valor);
                        if (attr.tipo === 'text' || attr.tipo === 'number') {
                            const input = atributosContainer.querySelector(`input[name="${fieldName}[valor]"]`);
                            if (input) {
                                input.value = valorExistente;
                                actualizarCostoAtributo(index, attr.id, attr.costo_adicional, 0, valorExistente);
                            }
                        } else if (attr.tipo === 'color') {
                            const input = document.getElementById(`attr_${attr.id}_${index}`);
                            if (input) {
                                input.value = valorExistente || '#000000';
                                actualizarCostoAtributo(index, attr.id, attr.costo_adicional, 0, input.value);
                                const preview = document.getElementById(`color_preview_${attr.id}_${index}`);
                                if (preview) preview.style.backgroundColor = input.value;
                            }
                        } else if (attr.tipo === 'select') {
                            const radio = atributosContainer.querySelector(`input[type="radio"][name="${fieldName}[valor]"][value="${valorExistente}"]`);
                            if (radio) {
                                radio.checked = true;
                                actualizarCostoAtributo(index, attr.id, attr.costo_adicional, radio.dataset.costo, valorExistente);
                                marcarOpcionAtributo(radio);
                            }
                        }
                    }

                    if (attr.tipo === 'color') {
                        const inputId = `attr_${attr.id}_${index}`;
                        const previewId = `color_preview_${attr.id}_${index}`;
                        const colorInput = document.getElementById(inputId);
                        const preview = document.getElementById(previewId);
                        if (colorInput && preview) {
                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                            colorInput.addEventListener('input', updatePreview);
                            updatePreview();
                            actualizarCostoAtributo(index, attr.id, attr.costo_adicional, 0, colorInput.value);
                        }
                    }
                });
            } else {
                document.getElementById(`atributos-container-${index}`).style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando atributos:', error);
            document.getElementById(`atributos-container-${index}`).style.display = 'none';
        });
}

function actualizarPrecioItem(index) {
    const productoId = document.getElementById(`producto_id_${index}`)?.value;
    
    if (!productoId) {
        calcularTotales();
        return;
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    if (producto?.tipo_precio === 'variable') {
        const ancho = parseFloat(document.getElementById(`ancho_${index}`).value || 0);
        const alto = parseFloat(document.getElementById(`alto_${index}`).value || 0);
        
        if (ancho > 0 && alto > 0) {
            // Obtener precio desde la matriz
            fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }

                    const precioBase = parseFloat(data.precio);
                    const precioInput = document.getElementById(`precio_${index}`);
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                    document.getElementById(`precio-info-${index}`).innerHTML = '✓ ' + data.precio_info;
                    document.getElementById(`precio-info-${index}`).style.display = 'block';
                    calcularTotales();
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }
    
    calcularTotales();
}

function eliminarItem(index) {
    document.getElementById('item_' + index).remove();
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

        const precioConAtributos = precioBase + costoAtributos;
        if (precioInput) {
            precioInput.value = precioConAtributos.toFixed(2);
        }
        const subtotalItem = cantidad * precioConAtributos;
        
        // Actualizar subtotal del item
        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
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
                if (info) info.textContent = 'Cupón inválido.';
                if (descuentoInput) descuentoInput.value = '0';
            } else {
                if (descuentoInput) descuentoInput.value = data.descuento || 0;
                if (info) info.textContent = `Descuento aplicado: $${Number(data.descuento || 0).toFixed(2)}`;
            }
            calcularTotales();
        })
        .catch(() => {
            if (info) info.textContent = 'No se pudo validar el cupón.';
        });
}

function marcarOpcionAtributo(radio) {
    if (!radio || !radio.name) return;
    
    // Encontrar el contenedor de opciones del atributo (el padre más cercano con flex wrap)
    const label = radio.closest('label');
    if (!label) return;
    
    const divPadre = label.parentElement;
    if (!divPadre || !divPadre.parentElement) return;
    
    const contenedorOpciones = divPadre.parentElement;
    
    // Desmarcar todas las opciones del mismo atributo
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
    
    // Marcar la opción seleccionada
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
            const info = document.getElementById(`precio-info-${index}`);
            if (info) {
                info.innerHTML = '✓ Precio fijo actualizado';
                info.style.display = 'block';
            }
            return Promise.resolve();
        }

        const ancho = parseFloat(document.getElementById(`ancho_${index}`)?.value || 0);
        const alto = parseFloat(document.getElementById(`alto_${index}`)?.value || 0);
        if (ancho <= 0 || alto <= 0) {
            const info = document.getElementById(`precio-info-${index}`);
            if (info) {
                info.innerHTML = '⚠️ Ingrese ancho y alto para actualizar precio';
                info.style.display = 'block';
            }
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
                const info = document.getElementById(`precio-info-${index}`);
                if (info) {
                    info.innerHTML = '✓ ' + data.precio_info + ' (actualizado)';
                    info.style.display = 'block';
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


// Cargar items existentes al cargar la página
document.addEventListener('DOMContentLoaded', function() {
    if (itemsExistentes.length > 0) {
        itemsExistentes.forEach(item => {
            agregarItem(item);
        });
    } else {
        agregarItem();
    }
    aplicarListaPrecios();
});
</script>

<?php require 'includes/footer.php'; ?>
