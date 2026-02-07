<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';
require 'includes/mailer.php';
require 'includes/envio.php';

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
$cantidad_total = 0;
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
    $cantidad_total += (int)$item['cantidad'];
}
$envio_data = calcular_envio($pdo, $subtotal, $cantidad_total);
$envio = $envio_data['costo'];
$envio_mensaje = $envio_data['mensaje'] ?? '';
$total = $subtotal + $envio;

// Procesar compra
$mensaje = '';
$error = '';

// Obtener configuración de Mercado Pago
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
$config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

function enviar_correos_pedido(PDO $pdo, string $numero_pedido, string $email_cliente, string $nombre_cliente, float $total, string $metodo_pago): void {
    $email_cliente = trim($email_cliente);
    $empresa_email = '';
    $empresa_nombre = 'Ecommerce';

    try {
        $stmt = $pdo->query("SELECT nombre, email FROM ecommerce_empresa LIMIT 1");
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($empresa) {
            $empresa_email = $empresa['email'] ?? '';
            $empresa_nombre = $empresa['nombre'] ?? $empresa_nombre;
        }
    } catch (Exception $e) {
    }

    $asunto_cliente = "Pedido {$numero_pedido} recibido";
    $html_cliente = "<p>Hola " . htmlspecialchars($nombre_cliente) . ",</p>"
        . "<p>Recibimos tu pedido <strong>{$numero_pedido}</strong>.</p>"
        . "<p>Total: <strong>$" . number_format($total, 2, ',', '.') . "</strong></p>"
        . "<p>Método de pago: <strong>" . htmlspecialchars($metodo_pago) . "</strong></p>"
        . "<p>Gracias por tu compra.</p>";

    if ($email_cliente !== '' && strpos($email_cliente, 'sin-email-') === false) {
        enviar_email($email_cliente, $asunto_cliente, $html_cliente);
    }

    if (!empty($empresa_email)) {
        $asunto_admin = "Nuevo pedido {$numero_pedido}";
        $html_admin = "<p>Nuevo pedido recibido.</p>"
            . "<p>Número: <strong>{$numero_pedido}</strong></p>"
            . "<p>Cliente: <strong>" . htmlspecialchars($nombre_cliente) . "</strong></p>"
            . "<p>Email: <strong>" . htmlspecialchars($email_cliente) . "</strong></p>"
            . "<p>Total: <strong>$" . number_format($total, 2, ',', '.') . "</strong></p>"
            . "<p>Método de pago: <strong>" . htmlspecialchars($metodo_pago) . "</strong></p>";
        enviar_email($empresa_email, $asunto_admin, $html_admin);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $provincia = $_POST['provincia'] ?? '';
    $localidad = $_POST['localidad'] ?? '';
    $ciudad = $_POST['ciudad'] ?? '';
    $direccion = $_POST['direccion'] ?? '';
    $codigo_postal = $_POST['codigo_postal'] ?? '';
    $responsabilidad_fiscal = $_POST['responsabilidad_fiscal'] ?? '';
    $documento_tipo = $_POST['documento_tipo'] ?? '';
    $documento_numero = $_POST['documento_numero'] ?? '';
    $metodo_pago = $_POST['metodo_pago'] ?? '';
    
    // Validar datos
    if (empty($nombre) || empty($direccion) || empty($provincia) || empty($localidad) || empty($responsabilidad_fiscal) || empty($documento_tipo) || empty($documento_numero)) {
        $error = "Por favor completa todos los campos obligatorios (incluyendo datos fiscales)";
    } else if (empty($carrito)) {
        $error = "El carrito está vacío";
    } else {
        try {
            if ($ciudad === '' && $localidad !== '') {
                $ciudad = $localidad;
            }
            if ($cliente_actual) {
                $cliente_id = $cliente_actual['id'];
                $email = $cliente_actual['email'];
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_clientes 
                    SET nombre = ?, telefono = ?, provincia = ?, localidad = ?, ciudad = ?, direccion = ?, codigo_postal = ?, responsabilidad_fiscal = ?, documento_tipo = ?, documento_numero = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $telefono, $provincia, $localidad, $ciudad, $direccion, $codigo_postal, $responsabilidad_fiscal, $documento_tipo, $documento_numero, $cliente_id]);
            } else {
                // Verificar o crear cliente
                if (empty($email)) {
                    $email = 'sin-email-' . date('YmdHis') . '-' . rand(1000, 9999) . '@tucuroller.local';
                }

                $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE email = ?");
                $stmt->execute([$email]);
                $cliente = $stmt->fetch();
                
                if (!$cliente) {
                    $stmt = $pdo->prepare("
                        INSERT INTO ecommerce_clientes (email, nombre, telefono, provincia, localidad, ciudad, direccion, codigo_postal, responsabilidad_fiscal, documento_tipo, documento_numero)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$email, $nombre, $telefono, $provincia, $localidad, $ciudad, $direccion, $codigo_postal, $responsabilidad_fiscal, $documento_tipo, $documento_numero]);
                    $cliente_id = $pdo->lastInsertId();
                } else {
                    $cliente_id = $cliente['id'];
                    // Actualizar datos del cliente
                    $stmt = $pdo->prepare("
                        UPDATE ecommerce_clientes 
                        SET nombre = ?, telefono = ?, provincia = ?, localidad = ?, ciudad = ?, direccion = ?, codigo_postal = ?, responsabilidad_fiscal = ?, documento_tipo = ?, documento_numero = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $telefono, $provincia, $localidad, $ciudad, $direccion, $codigo_postal, $responsabilidad_fiscal, $documento_tipo, $documento_numero, $cliente_id]);
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

            $estados_validos = [
                'pendiente_pago',
                'esperando_transferencia',
                'esperando_envio',
                'pagado',
                'pago_pendiente',
                'pago_autorizado',
                'pago_en_proceso',
                'pago_rechazado',
                'pago_reembolsado',
                'confirmado',
                'preparando',
                'enviado',
                'entregado',
                'cancelado'
            ];
            if (!in_array($estado_pedido, $estados_validos, true)) {
                $estado_pedido = 'pendiente_pago';
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

            enviar_correos_pedido($pdo, $numero_pedido, $email, $nombre, (float)$total, $metodo_pago);
            
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

                <?php if (!$cliente_actual): ?>
                    <div class="alert alert-info d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <strong>¿Cómo querés continuar?</strong>
                            <div class="text-muted">Podés comprar como invitado o ingresar a tu cuenta.</div>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="#form-checkout" class="btn btn-outline-primary">Comprar como invitado</a>
                            <a href="cliente_login.php" class="btn btn-primary">Ingresar a mi cuenta</a>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="POST" class="mt-4" id="form-checkout">
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
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cliente_actual['email'] ?? '') ?>" <?= $cliente_actual ? 'readonly' : '' ?>>
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

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="responsabilidad_fiscal" class="form-label">Responsabilidad Fiscal *</label>
                                    <select class="form-select" id="responsabilidad_fiscal" name="responsabilidad_fiscal" required>
                                        <option value="">Seleccionar</option>
                                        <option value="Consumidor Final" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'Consumidor Final') ? 'selected' : '' ?>>Consumidor Final</option>
                                        <option value="Monotributista" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'Monotributista') ? 'selected' : '' ?>>Monotributista</option>
                                        <option value="Responsable Inscripto" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'Responsable Inscripto') ? 'selected' : '' ?>>Responsable Inscripto</option>
                                        <option value="Exento" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'Exento') ? 'selected' : '' ?>>Exento</option>
                                        <option value="No Responsable" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'No Responsable') ? 'selected' : '' ?>>No Responsable</option>
                                        <option value="Sujeto No Categorizado" <?= (($cliente_actual['responsabilidad_fiscal'] ?? '') === 'Sujeto No Categorizado') ? 'selected' : '' ?>>Sujeto No Categorizado</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="documento_tipo" class="form-label">Tipo Documento *</label>
                                    <select class="form-select" id="documento_tipo" name="documento_tipo" required>
                                        <option value="">Seleccionar</option>
                                        <option value="DNI" <?= (($cliente_actual['documento_tipo'] ?? '') === 'DNI') ? 'selected' : '' ?>>DNI</option>
                                        <option value="CUIT" <?= (($cliente_actual['documento_tipo'] ?? '') === 'CUIT') ? 'selected' : '' ?>>CUIT</option>
                                    </select>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="documento_numero" class="form-label">Número *</label>
                                    <input type="text" class="form-control" id="documento_numero" name="documento_numero" value="<?= htmlspecialchars($cliente_actual['documento_numero'] ?? '') ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="direccion" class="form-label">Dirección *</label>
                                <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($cliente_actual['direccion'] ?? '') ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="localidad" class="form-label">Localidad *</label>
                                    <input type="text" class="form-control" id="localidad" name="localidad" value="<?= htmlspecialchars($cliente_actual['localidad'] ?? $cliente_actual['ciudad'] ?? '') ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="provincia" class="form-label">Provincia *</label>
                                    <input type="text" class="form-control" id="provincia" name="provincia" value="<?= htmlspecialchars($cliente_actual['provincia'] ?? '') ?>" required>
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
                        <div class="d-flex justify-content-between mb-1">
                            <span>Envío:</span>
                            <strong>$<?= number_format($envio, 2, ',', '.') ?></strong>
                        </div>
                        <?php if (!empty($envio_mensaje)): ?>
                            <div class="mb-3 pb-3" style="border-bottom: 2px solid #dee2e6;">
                                <small class="text-muted"><?= htmlspecialchars($envio_mensaje) ?></small>
                            </div>
                        <?php else: ?>
                            <div class="mb-3 pb-3" style="border-bottom: 2px solid #dee2e6;"></div>
                        <?php endif; ?>
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
