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
                
                $items[] = [
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $cantidad,
                    'ancho' => !empty($item['ancho']) ? floatval($item['ancho']) : null,
                    'alto' => !empty($item['alto']) ? floatval($item['alto']) : null,
                    'precio_unitario' => $precio,
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

function agregarItem() {
    itemIndex++;
    const html = `
        <div class="card mb-3 item-row" id="item_${itemIndex}">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Nombre del Producto *</label>
                        <input type="text" class="form-control" name="items[${itemIndex}][nombre]" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Descripción</label>
                        <input type="text" class="form-control" name="items[${itemIndex}][descripcion]" onchange="calcularTotales()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Ancho</label>
                        <input type="number" class="form-control" name="items[${itemIndex}][ancho]" step="0.01" min="0" onchange="calcularTotales()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Alto</label>
                        <input type="number" class="form-control" name="items[${itemIndex}][alto]" step="0.01" min="0" onchange="calcularTotales()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Cant. *</label>
                        <input type="number" class="form-control item-cantidad" name="items[${itemIndex}][cantidad]" value="1" min="1" required onchange="calcularTotales()">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio Unit. *</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control item-precio" name="items[${itemIndex}][precio]" step="0.01" min="0" required onchange="calcularTotales()">
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-danger mt-2" onclick="eliminarItem(${itemIndex})">🗑️ Eliminar</button>
            </div>
        </div>
    `;
    
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
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
        subtotal += cantidad * precio;
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
