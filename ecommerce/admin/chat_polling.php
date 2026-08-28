<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/chat_helper.php';

// header.php ya generó el HTML del panel (navbar, sidebar, etc.) dentro del
// buffer de salida abierto al inicio. Lo descartamos para responder solo JSON.
if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

$usuario_id = (int)($_SESSION['user']['id'] ?? 0);
if ($usuario_id <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

try {
    chat_asegurar_tablas($pdo);
    chat_actualizar_actividad($pdo, $usuario_id);

    $desde = (int)($_GET['desde'] ?? 0);
    $mensajes = chat_obtener_mensajes($pdo, $desde, 50);
    $conectados = chat_usuarios_conectados($pdo, $usuario_id, 3);

    echo json_encode([
        'ok' => true,
        'mensajes' => $mensajes,
        'conectados' => $conectados,
    ]);
} catch (Throwable $e) {
    error_log('chat_polling error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al obtener el chat']);
}
