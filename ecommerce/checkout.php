<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';
require 'includes/mailer.php';
require 'includes/envio.php';
require 'includes/descuentos.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cliente_actual = cliente_actual($pdo);

$carrito = $_SESSION['carrito'] ?? [];
$mensaje_descuento = '';
$error_descuento = '';
$skip_checkout = false;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aplicar_codigo'])) {
    $codigo = normalizar_codigo_descuento((string)($_POST['codigo_descuento'] ?? ''));
    if ($codigo === '') {
        $error_descuento = 'Ingresá un código válido.';
    } else {
        $descuento = obtener_descuento_por_codigo($pdo, $codigo);
        if (!$descuento) {
            $error_descuento = 'El código no existe.';
        } else {
            $validacion = validar_descuento($descuento, $subtotal);
            if ($validacion['valido']) {
                $_SESSION['descuento_codigo'] = $codigo;
                $mensaje_descuento = 'Código aplicado correctamente.';
            } else {
                $error_descuento = $validacion['mensaje'];
            }
        }
    }
    $skip_checkout = true;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quitar_codigo'])) {
    unset($_SESSION['descuento_codigo']);
    $mensaje_descuento = 'Código removido.';
    $skip_checkout = true;
}

$descuento_info = aplicar_descuento_actual($pdo, $subtotal);
$descuento_monto = $descuento_info['monto'] ?? 0.0;
$codigo_descuento = $descuento_info['codigo'] ?? '';

$total = max(0, $subtotal + $envio - $descuento_monto);

// Procesar compra
$mensaje = '';
$error = '';

// Obtener configuración de Mercado Pago
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
$config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener métodos de pago activos
$metodos_pago = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_metodos_pago WHERE activo = 1 ORDER BY orden ASC, nombre ASC");
    $metodos_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}

if (empty($metodos_pago)) {
    $metodos_pago = [
        ['codigo' => 'transferencia_bancaria', 'nombre' => 'Transferencia Bancaria', 'tipo' => 'manual', 'instrucciones_html' => ''],
        ['codigo' => 'mercadopago_tarjeta', 'nombre' => 'Tarjeta de Crédito (Mercado Pago)', 'tipo' => 'mercadopago', 'instrucciones_html' => ''],
        ['codigo' => 'efectivo_entrega', 'nombre' => 'Efectivo contra Entrega', 'tipo' => 'manual', 'instrucciones_html' => '']
    ];
}

$metodos_pago_map = [];
foreach ($metodos_pago as $m) {
    $metodos_pago_map[strtolower($m['codigo'])] = $m;
}

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$skip_checkout) {
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
    $factura_a = isset($_POST['factura_a']) ? 1 : 0;
    $envio_mismo = isset($_POST['envio_mismo']) ? 1 : 0;
    $envio_nombre = trim($_POST['envio_nombre'] ?? '');
    $envio_telefono = trim($_POST['envio_telefono'] ?? '');
    $envio_direccion = trim($_POST['envio_direccion'] ?? '');
    $envio_localidad = trim($_POST['envio_localidad'] ?? '');
    $envio_provincia = trim($_POST['envio_provincia'] ?? '');
    $envio_codigo_postal = trim($_POST['envio_codigo_postal'] ?? '');
    $metodo_codigo = $_POST['metodo_pago'] ?? '';
    $metodo_codigo = trim($metodo_codigo);
    $metodo_codigo_key = strtolower($metodo_codigo);
    $metodo_pago = '';
    $metodo_tipo = '';
    $metodo_instrucciones = '';

    if ($metodo_codigo_key !== '' && isset($metodos_pago_map[$metodo_codigo_key])) {
        $metodo_pago = $metodos_pago_map[$metodo_codigo_key]['nombre'] ?? '';
        $metodo_tipo = $metodos_pago_map[$metodo_codigo_key]['tipo'] ?? 'manual';
        $metodo_instrucciones = $metodos_pago_map[$metodo_codigo_key]['instrucciones_html'] ?? '';
    }
    
    // Validar datos
    if (empty($nombre) || empty($direccion) || empty($provincia) || empty($localidad) || empty($responsabilidad_fiscal) || empty($documento_tipo) || empty($documento_numero)) {
        $error = "Por favor completa todos los campos obligatorios (incluyendo datos fiscales)";
    } elseif (!$envio_mismo && (empty($envio_nombre) || empty($envio_direccion) || empty($envio_localidad) || empty($envio_provincia))) {
        $error = "Por favor completa los datos de envío";
    } elseif ($metodo_codigo === '' || empty($metodo_pago)) {
        $error = "Seleccioná un método de pago";
    } else if (empty($carrito)) {
        $error = "El carrito está vacío";
    } elseif ($metodo_tipo === 'mercadopago' && !$config_mp) {
        $error = "Mercado Pago no está disponible";
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
            
            if ($envio_mismo) {
                $envio_nombre = $nombre;
                $envio_telefono = $telefono;
                $envio_direccion = $direccion;
                $envio_localidad = $localidad;
                $envio_provincia = $provincia;
                $envio_codigo_postal = $codigo_postal;
            }

            // Generar número de pedido
            $numero_pedido = "PED-" . date('YmdHis') . "-" . rand(1000, 9999);
            
            // Determinar estado inicial del pedido
            $estado_pedido = 'pendiente_pago';
            if ($metodo_codigo_key === 'transferencia_bancaria') {
                $estado_pedido = 'esperando_transferencia';
            } elseif ($metodo_codigo_key === 'efectivo_entrega') {
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
            
            $descuento_aplicado = aplicar_descuento_actual($pdo, $subtotal);
            $descuento_monto = $descuento_aplicado['monto'] ?? 0.0;
            $codigo_descuento = $descuento_aplicado['codigo'] ?? null;
            $total = max(0, $subtotal + $envio - $descuento_monto);

            // Crear pedido
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_pedidos (numero_pedido, cliente_id, subtotal, envio, descuento_monto, codigo_descuento, total, factura_a, envio_nombre, envio_telefono, envio_direccion, envio_localidad, envio_provincia, envio_codigo_postal, metodo_pago, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $numero_pedido,
                $cliente_id,
                $subtotal,
                $envio,
                $descuento_monto,
                $codigo_descuento,
                $total,
                $factura_a,
                $envio_nombre,
                $envio_telefono ?: null,
                $envio_direccion,
                $envio_localidad,
                $envio_provincia,
                $envio_codigo_postal ?: null,
                $metodo_pago,
                $estado_pedido
            ]);
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

            if (!empty($descuento_aplicado['valido']) && !empty($descuento_aplicado['descuento']['id'])) {
                $stmt = $pdo->prepare("UPDATE ecommerce_descuentos SET usos_usados = usos_usados + 1 WHERE id = ?");
                $stmt->execute([(int)$descuento_aplicado['descuento']['id']]);
            }

            unset($_SESSION['descuento_codigo']);

            enviar_correos_pedido($pdo, $numero_pedido, $email, $nombre, (float)$total, $metodo_pago);
            
            // Si es tarjeta de crédito y Mercado Pago está disponible
            if ($metodo_tipo === 'mercadopago' && $config_mp) {
                // Redirigir a página de pago con Mercado Pago
                $_SESSION['pedido_id'] = $pedido_id;
                $_SESSION['pedido_numero'] = $numero_pedido;
                header("Location: mp_checkout.php?pedido_id=" . $pedido_id);
                exit;
            }

            // Para métodos manuales, mostrar pantalla dedicada con instrucciones
            unset($_SESSION['carrito']);
            $_SESSION['pedido_numero'] = $numero_pedido;
            $_SESSION['pedido_metodo_nombre'] = $metodo_pago;
            $_SESSION['pedido_metodo_instrucciones'] = $metodo_instrucciones;
            $_SESSION['pedido_total'] = $total;
            header("Location: checkout_confirmacion.php");
            exit;
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
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="factura_a" name="factura_a" value="1">
                                <label class="form-check-label" for="factura_a">Necesito Factura A</label>
                            </div>
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
                            <h5>Datos de Envío</h5>
                        </div>
                        <div class="card-body">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="envio_mismo" name="envio_mismo" value="1" checked>
                                <label class="form-check-label" for="envio_mismo">Los datos de facturación son los mismos para el envío</label>
                            </div>

                            <div id="envio-diferente" style="display:none;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="envio_nombre" class="form-label">Nombre completo *</label>
                                        <input type="text" class="form-control" id="envio_nombre" name="envio_nombre">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="envio_telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="envio_telefono" name="envio_telefono">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="envio_direccion" class="form-label">Dirección *</label>
                                    <input type="text" class="form-control" id="envio_direccion" name="envio_direccion">
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="envio_localidad" class="form-label">Localidad *</label>
                                        <input type="text" class="form-control" id="envio_localidad" name="envio_localidad">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="envio_provincia" class="form-label">Provincia *</label>
                                        <input type="text" class="form-control" id="envio_provincia" name="envio_provincia">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="envio_codigo_postal" class="form-label">Código Postal</label>
                                    <input type="text" class="form-control" id="envio_codigo_postal" name="envio_codigo_postal">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header bg-primary text-white">
                            <h5>Método de Pago</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($metodos_pago as $index => $metodo):
                                $codigo = $metodo['codigo'] ?? '';
                                $nombre = $metodo['nombre'] ?? '';
                                $instrucciones = $metodo['instrucciones_html'] ?? '';
                                $instrucciones_b64 = base64_encode($instrucciones);
                                $id = 'metodo_' . $codigo;
                            ?>
                                <div class="form-check mb-2">
                                    <input class="form-check-input metodo-pago-input" type="radio" name="metodo_pago" id="<?= htmlspecialchars($id) ?>" value="<?= htmlspecialchars($codigo) ?>" data-instrucciones="<?= htmlspecialchars($instrucciones_b64) ?>" <?= $index === 0 ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= htmlspecialchars($id) ?>">
                                        <?= htmlspecialchars($nombre) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>

                            <div id="metodo-instrucciones" class="alert alert-light border mt-3" style="display:none;"></div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <a href="carrito.php" class="btn btn-outline-secondary">← Volver al Carrito</a>
                        <button type="submit" class="btn btn-success btn-lg flex-grow-1">Confirmar Compra</button>
                    </div>
                </form>
                <script>
                    (function() {
                        const container = document.getElementById('metodo-instrucciones');
                        const inputs = document.querySelectorAll('.metodo-pago-input');
                        if (!container || inputs.length === 0) return;

                        const update = () => {
                            const selected = document.querySelector('.metodo-pago-input:checked');
                            const data = selected?.getAttribute('data-instrucciones') || '';
                            const html = data ? decodeURIComponent(escape(atob(data))) : '';
                            if (html.trim() !== '') {
                                container.innerHTML = html;
                                container.style.display = 'block';
                            } else {
                                container.innerHTML = '';
                                container.style.display = 'none';
                            }
                        };

                        inputs.forEach(i => i.addEventListener('change', update));
                        update();
                    })();
                </script>
                <script>
                    (function() {
                        const envioMismo = document.getElementById('envio_mismo');
                        const envioBox = document.getElementById('envio-diferente');
                        if (!envioMismo || !envioBox) return;

                        const toggleEnvio = () => {
                            if (envioMismo.checked) {
                                envioBox.style.display = 'none';
                            } else {
                                envioBox.style.display = 'block';
                            }
                        };

                        envioMismo.addEventListener('change', toggleEnvio);
                        toggleEnvio();
                    })();
                </script>
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
                        <?php if (!empty($mensaje_descuento)): ?>
                            <div class="alert alert-success py-2 mb-2"><?= htmlspecialchars($mensaje_descuento) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($error_descuento)): ?>
                            <div class="alert alert-danger py-2 mb-2"><?= htmlspecialchars($error_descuento) ?></div>
                        <?php endif; ?>
                        <form method="POST" class="mb-3">
                            <label class="form-label">Código de descuento</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="codigo_descuento" placeholder="PROMO10" value="<?= htmlspecialchars($codigo_descuento) ?>" <?= $codigo_descuento ? 'readonly' : '' ?>>
                                <?php if ($codigo_descuento): ?>
                                    <button class="btn btn-outline-danger" type="submit" name="quitar_codigo">Quitar</button>
                                <?php else: ?>
                                    <button class="btn btn-outline-primary" type="submit" name="aplicar_codigo">Aplicar</button>
                                <?php endif; ?>
                            </div>
                        </form>
                        <?php if ($descuento_monto > 0): ?>
                            <div class="d-flex justify-content-between mb-3">
                                <span>Descuento:</span>
                                <strong class="text-success">- $<?= number_format($descuento_monto, 2, ',', '.') ?></strong>
                            </div>
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
