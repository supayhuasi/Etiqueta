<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
if ($token === '') {
    header('Location: pedido_publico.php');
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    $stmt = $pdo->query("SELECT * FROM ecommerce_metodos_pago WHERE activo = 1 AND tipo = 'mercadopago' LIMIT 1");
    $mp_habilitado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mp_habilitado) {
        throw new Exception('Mercado Pago no está habilitado');
    }

    $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
    $config_mp = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$config_mp) {
        throw new Exception('Mercado Pago no está configurado');
    }

    $access_token = $config_mp['modo'] === 'test'
        ? $config_mp['access_token_test']
        : $config_mp['access_token_produccion'];

    if (empty($access_token)) {
        throw new Exception('Access Token no configurado');
    }

    $stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ?");
    $stmt->execute([$pedido['cliente_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("
        SELECT pi.*, p.nombre as producto_nombre
        FROM ecommerce_pedido_items pi
        LEFT JOIN ecommerce_productos p ON pi.producto_id = p.id
        WHERE pi.pedido_id = ?
    ");
    $stmt->execute([(int)$pedido['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items_mp = [];
    foreach ($items as $item) {
        $items_mp[] = [
            'title' => $item['producto_nombre'] ?? 'Producto',
            'description' => 'Compra en tienda',
            'quantity' => (int)$item['cantidad'],
            'unit_price' => (float)$item['precio_unitario'],
            'picture_url' => (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/ecommerce/uploads/productos/placeholder.jpg'
        ];
    }

    if (empty($items_mp)) {
        $items_mp[] = [
            'title' => 'Pedido ' . ($pedido['numero_pedido'] ?? ''),
            'description' => 'Compra en tienda',
            'quantity' => 1,
            'unit_price' => (float)$pedido['total']
        ];
    }

    $scheme = (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) ? $_SERVER['HTTP_X_FORWARDED_PROTO'] : ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http'));
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = $scheme . '://' . $host;

    $success_url = $base . '/ecommerce/mp_success.php?pedido_id=' . (int)$pedido['id'];
    $failure_url = $base . '/ecommerce/mp_failure.php?pedido_id=' . (int)$pedido['id'];
    $pending_url = $base . '/ecommerce/mp_pending.php?pedido_id=' . (int)$pedido['id'];

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
        'external_reference' => 'PEDIDO-' . ($pedido['numero_pedido'] ?? ''),
        'notification_url' => $config_mp['notification_url'],
        'description' => $config_mp['descripcion_defecto'],
        'total_amount' => (float)$pedido['total']
    ];

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
        throw new Exception('Error en Mercado Pago');
    }

    $preference_data = json_decode($response, true);
    if (empty($preference_data['init_point'])) {
        throw new Exception('No se obtuvo URL de pago');
    }

    $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET mercadopago_preference_id = ? WHERE id = ?");
    $stmt->execute([$preference_data['id'], (int)$pedido['id']]);

    header('Location: ' . $preference_data['init_point']);
    exit;
} catch (Exception $e) {
    header('Location: pedido_publico.php?token=' . urlencode($token));
    exit;
}
