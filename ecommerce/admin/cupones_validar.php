<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_path = dirname(dirname(__DIR__));
require $base_path . '/config.php';
require $base_path . '/ecommerce/includes/descuentos.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['valido' => false, 'descuento' => 0, 'error' => 'no_auth']);
    exit;
}

$codigo = normalizar_codigo_descuento((string)($_GET['codigo'] ?? ''));
$subtotal = floatval($_GET['subtotal'] ?? 0);

if ($codigo === '' || $subtotal <= 0) {
    echo json_encode(['valido' => false, 'descuento' => 0, 'mensaje' => 'Subtotal inválido']);
    exit;
}

$descuento = obtener_descuento_por_codigo($pdo, $codigo);
if (!$descuento) {
    echo json_encode(['valido' => false, 'descuento' => 0, 'mensaje' => 'Cupón inválido']);
    exit;
}

$validacion = validar_descuento($descuento, $subtotal);
if (!$validacion['valido']) {
    echo json_encode(['valido' => false, 'descuento' => 0, 'mensaje' => $validacion['mensaje']]);
    exit;
}

$monto = calcular_monto_descuento($descuento['tipo'], (float)$descuento['valor'], $subtotal);

echo json_encode([
    'valido' => true,
    'descuento' => round($monto, 2),
    'tipo' => $descuento['tipo'],
    'valor' => (float)$descuento['valor'],
    'mensaje' => $validacion['mensaje']
]);
