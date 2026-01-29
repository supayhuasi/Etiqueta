<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cliente_actual = cliente_actual($pdo);

$carrito = $_SESSION['carrito'] ?? [];

if (empty($carrito)) {
    header("Location: tienda.php");
    exit;
}

// Calcular totales
$subtotal = 0;
foreach ($carrito as $item) {
    $precio_item = $item['precio'];
    
    // Sumar costos adicionales de atributos
    if (isset($item['atributos']) && is_array($item['atributos'])) {
        foreach ($item['atributos'] as $attr) {
            if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                $precio_item += $attr['costo_adicional'];
            }
        }
    }
    
    $subtotal += $precio_item * $item['cantidad'];
}
$envio = $subtotal > 0 ? 500 : 0;
$total = $subtotal + $envio;

// Procesar compra
$mensaje = '';
$error = '';

// Obtener configuración de Mercado Pago
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
$config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $codigo_postal = $_POST['codigo_postal'] ?? '';
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    
    // Validar datos
    if (empty($email) || empty($nombre) || empty($direccion)) {
        $error = "Por favor completa todos los campos obligatorios";
    } else if (empty($carrito)) {
        $error = "El carrito está vacío";
    } else {
        try {
            if ($cliente_actual) {
                $cliente_id = $cliente_actual['id'];
                $email = $cliente_actual['email'];
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_clientes 
                    SET nombre = ?, telefono = ?, provincia = ?, ciudad = ?, direccion = ?, codigo_postal = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $telefono, $provincia, $ciudad, $direccion, $codigo_postal, $cliente_id]);
            } else {
                // Verificar o crear cliente
                $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE email = ?");
                $stmt->execute([$email]);
                $cliente = $stmt->fetch();
                
                if (!$cliente) {
                    $stmt = $pdo->prepare("
                        INSERT INTO ecommerce_clientes (email, nombre, telefono, provincia, ciudad, direccion, codigo_postal)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$email, $nombre, $telefono, $provincia, $ciudad, $direccion, $codigo_postal]);
                    $cliente_id = $pdo->lastInsertId();
                } else {
                    $cliente_id = $cliente['id'];
                    // Actualizar datos del cliente
                    $stmt = $pdo->prepare("
                        UPDATE ecommerce_clientes 
                        SET nombre = ?, telefono = ?, provincia = ?, ciudad = ?, direccion = ?, codigo_postal = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $telefono, $provincia, $ciudad, $direccion, $codigo_postal, $cliente_id]);
                }
            }
            
            // Generar número de pedido
            $numero_pedido = "PED-" . date('YmdHis') . "-" . rand(1000, 9999);
            
            // Determinar estado inicial del pedido
            $estado_pedido = 'pendiente_pago';
            if ($metodo_pago === 'Transferencia Bancaria') {
                $estado_pedido = 'esperando_transferencia';
            } elseif ($metodo_pago === 'Efectivo contra Entrega') {
                $estado_pedido = 'esperando_envio';
            }
            
            // Crear pedido
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_pedidos (numero_pedido, cliente_id, total, metodo_pago, estado)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$numero_pedido, $cliente_id, $total, $metodo_pago, $estado_pedido]);
            $pedido_id = $pdo->lastInsertId();
            
            // Agregar items al pedido
            foreach ($carrito as $item) {
                $precio_final = $item['precio'];
                $atributos_json = '';
                
                // Sumar costos adicionales de atributos
                if (isset($item['atributos']) && is_array($item['atributos'])) {
                    $atributos_json = json_encode($item['atributos']);
                    foreach ($item['atributos'] as $attr) {
                        if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                            $precio_final += $attr['costo_adicional'];
                        }
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_pedido_items (pedido_id, producto_id, cantidad, precio_unitario, alto_cm, ancho_cm, subtotal, atributos)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $subtotal_item = $precio_final * $item['cantidad'];
                $stmt->execute([
                    $pedido_id, 
                    $item['id'], 
                    $item['cantidad'], 
                    $precio_final, 
                    $item['alto'] > 0 ? $item['alto'] : null,
                    $item['ancho'] > 0 ? $item['ancho'] : null,
                    $subtotal_item,
                    $atributos_json
                ]);
            }
            
            // Si es tarjeta de crédito y Mercado Pago está disponible
            if ($metodo_pago === 'Tarjeta de Crédito' && $config_mp) {
                // Redirigir a página de pago con Mercado Pago
                $_SESSION['pedido_id'] = $pedido_id;
                $_SESSION['pedido_numero'] = $numero_pedido;
                header("Location: mp_checkout.php?pedido_id=" . $pedido_id);
                exit;
            }
            
            // Para otros métodos de pago, limpiar carrito y mostrar confirmación
            unset($_SESSION['carrito']);
            
            $mensaje = "¡Pedido creado exitosamente! Número de pedido: <strong>$numero_pedido</strong>";
        } catch (Exception $e) {
            $error = "Error al procesar el pedido: " . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Checkout</h1>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <h4>✓ Pedido Confirmado</h4>
                    <p><?= $mensaje ?></p>
                    <a href="index.php" class="btn btn-primary">Volver al Inicio</a>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>

                <form method="POST" class="mt-4">
                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5>Datos de Facturación</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="nombre" class="form-label">Nombre Completo *</label>
                                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($cliente_actual['nombre'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente_actual['email'] ?? '') ?>" <?= $cliente_actual ? 'readonly' : '' ?> required>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="telefono" class="form-label">Teléfono</label>
                                    <input type="tel" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cliente_actual['telefono'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="codigo_postal" class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" id="codigo_postal" name="codigo_postal" value="<?= htmlspecialchars($cliente_actual['codigo_postal'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($cliente_actual['direccion'] ?? '') ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="ciudad" class="form-label">Ciudad</label>
                                    <input type="text" class="form-control" id="ciudad" name="ciudad" value="<?= htmlspecialchars($cliente_actual['ciudad'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="provincia" class="form-label">Provincia</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia" value="<?= htmlspecialchars($cliente_actual['provincia'] ?? '') ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5>Método de Pago</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="transferencia" value="Transferencia Bancaria" checked>
                                <label class="form-check-label" for="transferencia">
                                    Transferencia Bancaria
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="tarjeta" value="Tarjeta de Crédito">
                                <label class="form-check-label" for="tarjeta">
                                    Tarjeta de Crédito
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="metodo_pago" id="efectivo" value="Efectivo contra Entrega">
                                <label class="form-check-label" for="efectivo">
                                    Efectivo contra Entrega
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a href="carrito.php" class="btn btn-outline-secondary">← Volver al Carrito</a>
                        <button type="submit" class="btn btn-success btn-lg flex-grow-1">Confirmar Compra</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- Resumen de compra -->
        <div class="col-md-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-secondary text-white">
                    <h5>Resumen del Pedido</h5>
                </div>
                <div class="card-body">
                    <div class="cart-item" style="padding: 0;">
                        <?php foreach ($carrito as $item): 
                            $precio_item = $item['precio'];
                            $costo_atributos = 0;
                            
                            if (isset($item['atributos']) && is_array($item['atributos'])) {
                                foreach ($item['atributos'] as $attr) {
                                    if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                                        $costo_atributos += $attr['costo_adicional'];
                                    }
                                }
                            }
                            $precio_item += $costo_atributos;
                        ?>
                            <div class="mb-3 pb-3" style="border-bottom: 1px solid #dee2e6;">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                    <span><?= $item['cantidad'] ?>x</span>
                                </div>
                                <?php if ($item['alto'] > 0 && $item['ancho'] > 0): ?>
                                    <small class="text-muted"><?= $item['alto'] ?>cm x <?= $item['ancho'] ?>cm</small><br>
                                <?php endif; ?>
                                <?php if (isset($item['atributos']) && is_array($item['atributos']) && count($item['atributos']) > 0): ?>
                                    <small class="text-muted d-block mb-2">
                                        <?php foreach ($item['atributos'] as $attr): ?>
                                            <div><?= htmlspecialchars($attr['nombre']) ?>: <?= htmlspecialchars($attr['valor']) ?>
                                                <?php if ($attr['costo_adicional'] > 0): ?>
                                                    <span class="badge bg-success">+$<?= number_format($attr['costo_adicional'], 2, ',', '.') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </small>
                                <?php endif; ?>
                                <small>$<?= number_format($item['precio'], 2, ',', '.') ?> c/u <?php if ($costo_atributos > 0): ?> <span class="text-success">(+$<?= number_format($costo_atributos, 2, ',', '.') ?> atributos)</span><?php endif; ?></small>
                                <div class="text-end mt-2">
                                    <strong>$<?= number_format($precio_item * $item['cantidad'], 2, ',', '.') ?></strong>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="pt-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong>$<?= number_format($subtotal, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3" style="border-bottom: 2px solid #dee2e6;">
                            <span>Envío:</span>
                            <strong>$<?= number_format($envio, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span style="font-size: 1.1rem;">Total:</span>
                            <h4 class="text-success">$<?= number_format($total, 2, ',', '.') ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
