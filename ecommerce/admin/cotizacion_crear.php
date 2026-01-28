<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre_cliente = $_POST['nombre_cliente'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $empresa = $_POST['empresa'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $validez_dias = intval($_POST['validez_dias'] ?? 15);
        
        // Validaciones
        if (empty($nombre_cliente) || empty($email)) {
            throw new Exception("Nombre y email son obligatorios");
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        
        // Procesar items
        $items = [];
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
                
                $items[] = [
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
        
        if (empty($items)) {
            throw new Exception("Debe agregar al menos un item");
        }
        
        $descuento = floatval($_POST['descuento'] ?? 0);
        $total = $subtotal - $descuento;
        
        // Generar número de cotización
        $año = date('Y');
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM ecommerce_cotizaciones");
        $max_id = $stmt->fetch()['max_id'] ?? 0;
        $numero_cotizacion = 'COT-' . $año . '-' . str_pad($max_id + 1, 5, '0', STR_PAD_LEFT);
        
        // Guardar cotización
        $stmt = $pdo->prepare("
            INSERT INTO ecommerce_cotizaciones 
            (numero_cotizacion, nombre_cliente, email, telefono, empresa, items, subtotal, descuento, total, observaciones, validez_dias, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $numero_cotizacion,
            $nombre_cliente,
            $email,
            $telefono,
            $empresa,
            json_encode($items),
            $subtotal,
            $descuento,
            $total,
            $observaciones,
            $validez_dias,
            $_SESSION['user']['id']
        ]);
        
        $cotizacion_id = $pdo->lastInsertId();
        
        header("Location: cotizacion_detalle.php?id=" . $cotizacion_id . "&mensaje=creada");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener productos activos para el selector
$stmt = $pdo->query("
    SELECT id, nombre, tipo_precio, precio_base 
    FROM ecommerce_productos 
    WHERE activo = 1 
    ORDER BY nombre
");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>➕ Nueva Cotización</h1>
        <p class="text-muted">Crear una cotización/presupuesto para un cliente</p>
    </div>
    <a href="cotizaciones.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="formCotizacion">
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
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono">
                    </div>
                    <div class="mb-3">
                        <label for="empresa" class="form-label">Empresa</label>
                        <input type="text" class="form-control" id="empresa" name="empresa">
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
                        <input type="number" class="form-control" id="validez_dias" name="validez_dias" value="15" min="1" max="90">
                        <small class="text-muted">Días de validez del presupuesto</small>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" placeholder="Notas internas, condiciones especiales, etc."></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Items -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Cotización</h5>
            <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
        </div>
        <div class="card-body">
            <div id="itemsContainer">
                <!-- Los items se agregan dinámicamente aquí -->
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
                                <input type="number" class="form-control form-control-sm text-end" id="descuento" name="descuento" value="0" step="0.01" min="0" onchange="calcularTotales()">
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
        <button type="submit" class="btn btn-primary btn-lg">💾 Crear Cotización</button>
        <a href="cotizaciones.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
let itemIndex = 0;
const productos = <?= json_encode($productos) ?>;

function agregarItem() {
    itemIndex++;
    
    // Crear opciones del selector de productos
    let productosOptions = '<option value="">-- Seleccionar producto --</option>';
    productos.forEach(p => {
        productosOptions += `<option value="${p.id}" data-tipo="${p.tipo_precio}" data-precio="${p.precio_base}">${p.nombre} ${p.tipo_precio === 'variable' ? '(Precio variable)' : '($' + parseFloat(p.precio_base).toFixed(2) + ')'}</option>`;
    });
    
    const html = `
        <div class="card mb-3 item-row" id="item_${itemIndex}">
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-6">
                        <label class="form-label">Producto del catálogo</label>
                        <select class="form-select producto-select" data-index="${itemIndex}" onchange="cargarProducto(${itemIndex})">
                            ${productosOptions}
                        </select>
                        <small class="text-muted">O ingresa manualmente abajo</small>
                    </div>
                    <div class="col-md-6">
                        <div id="precio-info-${itemIndex}" class="alert alert-info mt-4" style="display:none; padding: 8px; margin: 0;"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3">
                        <label class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control item-nombre" id="nombre_${itemIndex}" name="items[${itemIndex}][nombre]" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" id="descripcion_${itemIndex}" name="items[${itemIndex}][descripcion]" onchange="calcularTotales()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Ancho (cm)</label>
                        <input type="number" class="form-control item-ancho" id="ancho_${itemIndex}" name="items[${itemIndex}][ancho]" step="0.01" min="0" onchange="actualizarPrecioItem(${itemIndex})">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Alto (cm)</label>
                        <input type="number" class="form-control item-alto" id="alto_${itemIndex}" name="items[${itemIndex}][alto]" step="0.01" min="0" onchange="actualizarPrecioItem(${itemIndex})">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Cant. *</label>
                        <input type="number" class="form-control item-cantidad" id="cantidad_${itemIndex}" name="items[${itemIndex}][cantidad]" value="1" min="1" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio Unit. *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control item-precio" id="precio_${itemIndex}" name="items[${itemIndex}][precio]" step="0.01" min="0" required onchange="calcularTotales()">
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
    calcularTotales();
}

function cargarProducto(index) {
    const select = document.querySelector(`[data-index="${index}"]`);
    const productoId = select.value;
    
    if (!productoId) {
        // Limpiar campos
        document.getElementById(`nombre_${index}`).value = '';
        document.getElementById(`descripcion_${index}`).value = '';
        document.getElementById(`precio_${index}`).value = '';
        document.getElementById(`precio-info-${index}`).style.display = 'none';
        document.getElementById(`atributos-container-${index}`).style.display = 'none';
        calcularTotales();
        return;
    }
    
    const option = select.selectedOptions[0];
    const tipoProducto = option.dataset.tipo;
    const precioBase = parseFloat(option.dataset.precio);
    const nombreProducto = option.text.split('(')[0].trim();
    
    // Llenar nombre
    document.getElementById(`nombre_${index}`).value = nombreProducto;
    
    // Cargar atributos del producto
    cargarAtributosProducto(productoId, index);
    
    // Si es precio fijo, establecer precio inmediatamente
    if (tipoProducto === 'fijo') {
        document.getElementById(`precio_${index}`).value = precioBase.toFixed(2);
        document.getElementById(`precio-info-${index}`).innerHTML = '✓ Precio fijo del producto';
        document.getElementById(`precio-info-${index}`).style.display = 'block';
        calcularTotales();
    } else {
        // Es precio variable, necesita medidas
        document.getElementById(`precio_${index}`).value = '';
        document.getElementById(`precio-info-${index}`).innerHTML = '⚠️ Ingrese ancho y alto para calcular precio';
        document.getElementById(`precio-info-${index}`).style.display = 'block';
    }
}

function cargarAtributosProducto(productoId, index) {
    fetch(`../../ecommerce/admin/productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributosContainer = document.getElementById(`atributos-list-${index}`);
            atributosContainer.innerHTML = '';
            
            if (data.atributos && data.atributos.length > 0) {
                document.getElementById(`atributos-container-${index}`).style.display = 'block';
                
                data.atributos.forEach(attr => {
                    let inputHTML = '';
                    const fieldName = `items[${index}][atributos][${attr.id}]`;
                    
                    if (attr.tipo === 'text') {
                        inputHTML = `<input type="text" class="form-control form-control-sm mb-2" name="${fieldName}[valor]" onchange="calcularTotales()">`;
                    } else if (attr.tipo === 'number') {
                        inputHTML = `<input type="number" class="form-control form-control-sm mb-2" name="${fieldName}[valor]" step="0.01" onchange="calcularTotales()">`;
                    } else if (attr.tipo === 'select') {
                        const opciones = attr.valores ? attr.valores.split(',') : [];
                        inputHTML = `<select class="form-select form-select-sm mb-2" name="${fieldName}[valor]" onchange="calcularTotales()">
                            <option value="">-- Seleccionar --</option>
                            ${opciones.map(o => `<option value="${o.trim()}">${o.trim()}</option>`).join('')}
                        </select>`;
                    }
                    
                    const attrHTML = `
                        <div class="mb-2">
                            <label class="form-label small mb-1">
                                ${attr.nombre}
                                ${attr.costo_adicional > 0 ? `<span class="badge bg-warning text-dark">+$${parseFloat(attr.costo_adicional).toFixed(2)}</span>` : ''}
                            </label>
                            ${inputHTML}
                            <input type="hidden" name="${fieldName}[nombre]" value="${attr.nombre}">
                            <input type="hidden" name="${fieldName}[costo]" value="${attr.costo_adicional}">
                        </div>
                    `;
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);
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
    const select = document.querySelector(`[data-index="${index}"]`);
    const productoId = select?.value;
    
    if (!productoId) {
        calcularTotales();
        return;
    }
    
    const option = select.selectedOptions[0];
    const tipoProducto = option.dataset.tipo;
    
    if (tipoProducto === 'variable') {
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
                    
                    document.getElementById(`precio_${index}`).value = data.precio.toFixed(2);
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
    
    document.querySelectorAll('.item-row').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const precio = parseFloat(row.querySelector('.item-precio')?.value || 0);
        const subtotalItem = cantidad * precio;
        
        // Actualizar subtotal del item
        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        
        subtotal += subtotalItem;
    });
    
    const descuento = parseFloat(document.getElementById('descuento').value || 0);
    const total = subtotal - descuento;
    
    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

// Agregar primer item al cargar
document.addEventListener('DOMContentLoaded', function() {
    agregarItem();
});
</script>

<?php require 'includes/footer.php'; ?>
