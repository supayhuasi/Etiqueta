<?php
require '../../config.php';
header('Content-Type: application/json');

/**
 * Webhook de Mercado Pago
 * Recibe notificaciones de cambios en los pagos
 */

// Log de la solicitud recibida
$log_dir = '../../logs/';
if (!is_dir($log_dir)) {
    mkdir($log_dir, 0755, true);
}

$request_body = file_get_contents('php://input');
file_put_contents($log_dir . 'mp_webhook_' . date('Y-m-d') . '.log', 
    date('Y-m-d H:i:s') . ' - ' . $request_body . "\n", FILE_APPEND);

try {
    $data = json_decode($request_body, true);
    
    // Validar que sea una notificación de pago
    if (!isset($data['type']) || $data['type'] !== 'payment') {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'Not a payment notification']);
        exit;
    }
    
    // Obtener datos del pago
    $payment_data = $data['data'] ?? [];
    $payment_id = $payment_data['id'] ?? null;
    
    if (!$payment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing payment ID']);
        exit;
    }
    
    // Obtener configuración de Mercado Pago
    $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
    $config_mp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config_mp) {
        throw new Exception("Mercado Pago not configured");
    }
    
    // Seleccionar token según modo
    $access_token = $config_mp['modo'] === 'test' 
        ? $config_mp['access_token_test'] 
        : $config_mp['access_token_produccion'];
    
    // Obtener detalles del pago desde MP
    $ch = curl_init("https://api.mercadopago.com/v1/payments/$payment_id");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $access_token
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Error getting payment details: " . $response);
    }
    
    $payment = json_decode($response, true);
    
    // Extraer número de pedido de external_reference
    if (!isset($payment['external_reference'])) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'No external reference']);
        exit;
    }
    
    // El formato es 'PEDIDO-numero_pedido'
    $parts = explode('-', $payment['external_reference']);
    if (count($parts) < 2) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'Invalid format']);
        exit;
    }
    
    $numero_pedido = implode('-', array_slice($parts, 1));
    
    // Buscar pedido por número
    $stmt = $pdo->prepare("SELECT id FROM ecommerce_pedidos WHERE numero_pedido = ?");
    $stmt->execute([$numero_pedido]);
    $pedido_row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido_row) {
        http_response_code(200);
        echo json_encode(['status' => 'ignored', 'reason' => 'Pedido not found']);
        exit;
    }
    
    $pedido_id = $pedido_row['id'];
    
    // Mapear estado de MP a estado de pedido
    $status_mp = $payment['status'] ?? null;
    $estado_pedido = 'pendiente_pago';
    
    switch ($status_mp) {
        case 'approved':
            $estado_pedido = 'pagado';
            break;
        case 'pending':
            $estado_pedido = 'pago_pendiente';
            break;
        case 'authorized':
            $estado_pedido = 'pago_autorizado';
            break;
        case 'in_process':
            $estado_pedido = 'pago_en_proceso';
            break;
        case 'rejected':
        case 'cancelled':
            $estado_pedido = 'pago_rechazado';
            break;
        case 'refunded':
            $estado_pedido = 'pago_reembolsado';
            break;
        default:
            $estado_pedido = 'pendiente_pago';
    }
    
    // Actualizar pedido
    $stmt = $pdo->prepare("
        UPDATE ecommerce_pedidos 
        SET estado = ?, mercadopago_payment_id = ?, mercadopago_status = ?
        WHERE id = ?
    ");
    $stmt->execute([
        $estado_pedido,
        $payment_id,
        $status_mp,
        $pedido_id
    ]);
    
    // Log de éxito
    file_put_contents($log_dir . 'mp_webhook_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " - SUCCESS: Pedido $pedido_id actualizado a $estado_pedido\n", FILE_APPEND);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'pedido_id' => $pedido_id,
        'nuevo_estado' => $estado_pedido
    ]);
    
} catch (Exception $e) {
    file_put_contents($log_dir . 'mp_webhook_' . date('Y-m-d') . '.log', 
        date('Y-m-d H:i:s') . " - ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
