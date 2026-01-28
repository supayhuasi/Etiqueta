<?php
require 'config.php';
header('Content-Type: application/json');

/**
 * Procesador de Pagos Mercado Pago
 * Recibe datos del checkout y crea la preferencia de pago
 */

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

try {
    // Obtener configuración de Mercado Pago
    $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
    $config_mp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config_mp) {
        throw new Exception("Mercado Pago no está configurado");
    }
    
    // Seleccionar token según modo
    $access_token = $config_mp['modo'] === 'test' 
        ? $config_mp['access_token_test'] 
        : $config_mp['access_token_produccion'];
    
    if (empty($access_token)) {
        throw new Exception("Access Token no configurado");
    }
    
    // Obtener datos del formulario
    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    
    if ($pedido_id <= 0) {
        throw new Exception("ID de pedido inválido");
    }
    
    // Obtener pedido con sus items
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$pedido) {
        throw new Exception("Pedido no encontrado");
    }
    
    // Obtener cliente
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ?");
    $stmt->execute([$pedido['cliente_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener items del pedido
    $stmt = $pdo->prepare("
        SELECT pi.*, p.nombre as producto_nombre
        FROM ecommerce_pedido_items pi
        LEFT JOIN ecommerce_productos p ON pi.producto_id = p.id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([$pedido_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Construir array de items para Mercado Pago
    $items_mp = [];
    foreach ($items as $item) {
        $items_mp[] = [
            'title' => $item['producto_nombre'] ?? 'Producto',
            'description' => 'Compra en tienda',
            'quantity' => $item['cantidad'],
            'unit_price' => (float)$item['precio_unitario'],
            'picture_url' => (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/uploads/productos/placeholder.jpg'
        ];
    }
    
    // URL de retorno
    $success_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/mp_success.php?pedido_id=' . $pedido_id;
    $failure_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . $_SERVER['HTTP_HOST'] . '/ecommerce/mp_failure.php?pedido_id=' . $pedido_id;
    $pending_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/ecommerce/mp_pending.php?pedido_id=' . $pedido_id;
    
    // Construir preferencia de pago
    $preference = [
        'items' => $items_mp,
        'payer' => [
            'name' => $cliente['nombre'] ?? 'Cliente',
            'email' => $cliente['email'] ?? '',
            'phone' => [
                'area_code' => '54',
                'number' => str_replace([' ', '-', '(', ')'], '', $cliente['telefono'] ?? '')
            ],
            'address' => [
                'street_name' => $cliente['direccion'] ?? '',
                'street_number' => 1,
                'zip_code' => $cliente['codigo_postal'] ?? ''
            ]
        ],
        'back_urls' => [
            'success' => $success_url,
            'failure' => $failure_url,
            'pending' => $pending_url
        ],
        'auto_return' => 'approved',
        'external_reference' => 'PEDIDO-' . $pedido['numero_pedido'],
        'notification_url' => $config_mp['notification_url'],
        'description' => $config_mp['descripcion_defecto'],
        'total_amount' => (float)$pedido['total']
    ];
    
    // Hacer request a API de Mercado Pago
    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($preference));
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 201) {
        throw new Exception("Error en Mercado Pago: " . $response);
    }
    
    $preference_data = json_decode($response, true);
    
    if (!isset($preference_data['init_point'])) {
        throw new Exception("No se obtuvo URL de pago");
    }
    
    // Guardar ID de preferencia en el pedido
    $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET mercadopago_preference_id = ? WHERE id = ?");
    $stmt->execute([$preference_data['id'], $pedido_id]);
    
    // Retornar URL de pago
    echo json_encode([
        'success' => true,
        'url' => $preference_data['init_point'],
        'preference_id' => $preference_data['id']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
