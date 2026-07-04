<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';
header('Content-Type: application/json');

/**
 * Procesador de Pagos Mercado Pago
 * Recibe token de tarjeta y crea el pago directamente con la API de Mercado Pago
 */

$log_file = __DIR__ . '/logs/mercadopago_debug.log';
if (!is_dir(__DIR__ . '/logs')) {
    mkdir(__DIR__ . '/logs', 0755, true);
}

function log_debug($message, $data = null) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_message = "[$timestamp] " . $message;
    if ($data) {
        $log_message .= " | " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    $log_message .= "\n";
    error_log($log_message, 3, $log_file);
}

log_debug('=== Nueva solicitud de pago ===', ['method' => $_SERVER['REQUEST_METHOD'], 'post' => $_POST]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    log_debug('Error: Método no permitido', $_SERVER['REQUEST_METHOD']);
    exit;
}

try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
    $config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config_mp) {
        throw new Exception('Mercado Pago no está configurado. Por favor contacte al administrador.');
    }

    $access_token = $config_mp['modo'] === 'test'
        ? $config_mp['access_token_test']
        : $config_mp['access_token_produccion'];

    if (empty($access_token)) {
        throw new Exception('Access Token no configurado para modo: ' . $config_mp['modo']);
    }

    $pedido_id = intval($_POST['pedido_id'] ?? 0);
    if ($pedido_id <= 0) {
        throw new Exception('ID de pedido inválido');
    }

    $stmt = $pdo->prepare('SELECT * FROM ecommerce_pedidos WHERE id = ?');
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    $stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE id = ?');
    $stmt->execute([$pedido['cliente_id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $token = trim($_POST['token'] ?? '');
    $payment_method_id = trim($_POST['payment_method_id'] ?? '');
    $installments = max(1, min(12, intval($_POST['installments'] ?? 1)));
    $doc_type = trim($_POST['docType'] ?? '') ?: 'DNI';
    $doc_number = trim($_POST['docNumber'] ?? '');

    if ($token === '') {
        throw new Exception('Token de tarjeta inválido');
    }

    $payer_email = trim($cliente['email'] ?? '');
    if ($payer_email === '') {
        $payer_email = 'sin-email@tucuroller.local';
    }

    $name = trim($cliente['nombre'] ?? 'Cliente');
    $nameParts = preg_split('/\s+/', $name, 2, PREG_SPLIT_NO_EMPTY);
    $payer_first_name = $nameParts[0] ?? 'Cliente';
    $payer_last_name = $nameParts[1] ?? '';

    $payment_payload = [
        'transaction_amount' => round((float)$pedido['total'], 2),
        'token' => $token,
        'description' => trim($config_mp['descripcion_defecto'] ?? '') ?: ('Pago pedido ' . $pedido['numero_pedido']),
        'installments' => $installments,
        'payment_method_id' => $payment_method_id ?: 'visa',
        'payer' => [
            'email' => $payer_email,
            'first_name' => $payer_first_name,
            'last_name' => $payer_last_name,
            'identification' => [
                'type' => $doc_type,
                'number' => $doc_number !== '' ? $doc_number : '0'
            ]
        ],
        'external_reference' => 'PEDIDO-' . $pedido['numero_pedido']
    ];

    log_debug('Creando pago con Mercado Pago', ['pedido_id' => $pedido_id, 'payload' => $payment_payload]);

    $ch = curl_init('https://api.mercadopago.com/v1/payments');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $access_token
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payment_payload, JSON_UNESCAPED_UNICODE));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    log_debug('Respuesta de Mercado Pago', ['http_code' => $http_code, 'curl_error' => $curl_error, 'response' => $response]);

    if (!empty($curl_error)) {
        throw new Exception('Error de conexión con Mercado Pago: ' . $curl_error);
    }

    if ($http_code !== 201 && $http_code !== 200) {
        $errorData = json_decode($response, true);
        $message = $errorData['message'] ?? 'Error en Mercado Pago';
        throw new Exception('Mercado Pago rechazó el pago: ' . $message);
    }

    $payment = json_decode($response, true);
    if (!$payment || empty($payment['id'])) {
        throw new Exception('Respuesta inválida de Mercado Pago');
    }

    $status = $payment['status'] ?? 'unknown';
    $payment_id = (string)$payment['id'];

    $estado_pedido = 'pendiente_pago';
    switch ($status) {
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

    $stmt = $pdo->prepare('UPDATE ecommerce_pedidos SET estado = ?, mercadopago_payment_id = ?, mercadopago_status = ? WHERE id = ?');
    $stmt->execute([$estado_pedido, $payment_id, $status, $pedido_id]);

    if ($status === 'approved') {
        $cols_pago = $pdo->query('SHOW COLUMNS FROM ecommerce_pedido_pagos')->fetchAll(PDO::FETCH_COLUMN, 0);
        $cols_map = array_flip($cols_pago);
        $existe_pago = false;

        if (isset($cols_map['referencia'])) {
            $stmt_check = $pdo->prepare('SELECT id FROM ecommerce_pedido_pagos WHERE referencia = ? LIMIT 1');
            $stmt_check->execute([$payment_id]);
            $existe_pago = (bool)$stmt_check->fetch(PDO::FETCH_ASSOC);
        }

        if (!$existe_pago) {
            $campos = [];
            $valores = [];

            if (isset($cols_map['pedido_id'])) {
                $campos[] = 'pedido_id';
                $valores[] = $pedido_id;
            }
            if (isset($cols_map['monto'])) {
                $campos[] = 'monto';
                $valores[] = (float)($payment['transaction_amount'] ?? $pedido['total']);
            }
            if (isset($cols_map['metodo'])) {
                $campos[] = 'metodo';
                $valores[] = 'Mercado Pago';
            }
            if (isset($cols_map['referencia'])) {
                $campos[] = 'referencia';
                $valores[] = $payment_id;
            }
            if (isset($cols_map['notas'])) {
                $campos[] = 'notas';
                $valores[] = 'MP:' . $payment_id;
            }
            if (isset($cols_map['creado_por'])) {
                $campos[] = 'creado_por';
                $valores[] = null;
            }
            if (isset($cols_map['fecha_pago'])) {
                $campos[] = 'fecha_pago';
                $valores[] = date('Y-m-d H:i:s');
            }

            if (!empty($campos)) {
                $placeholders = implode(', ', array_fill(0, count($campos), '?'));
                $sql = 'INSERT INTO ecommerce_pedido_pagos (' . implode(', ', $campos) . ') VALUES (' . $placeholders . ')';
                $stmt_pago = $pdo->prepare($sql);
                $stmt_pago->execute($valores);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'status' => $status,
        'payment_id' => $payment_id,
        'pedido_id' => $pedido_id
    ]);

    log_debug('Pago procesado correctamente', ['pedido_id' => $pedido_id, 'status' => $status, 'payment_id' => $payment_id]);
} catch (Exception $e) {
    http_response_code(400);
    $error_msg = $e->getMessage();
    log_debug('ERROR CAPTURADO', ['message' => $error_msg]);
    echo json_encode(['success' => false, 'error' => $error_msg]);
}
