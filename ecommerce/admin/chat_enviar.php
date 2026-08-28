<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/chat_helper.php';

if (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

if (!admin_validate_csrf($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Solicitud inválida (CSRF).']);
    exit;
}

$usuario_id = (int)($_SESSION['user']['id'] ?? 0);
if ($usuario_id <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

$mensaje = trim((string)($_POST['mensaje'] ?? ''));
if ($mensaje === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'El mensaje no puede estar vacío']);
    exit;
}

try {
    chat_asegurar_tablas($pdo);
    chat_actualizar_actividad($pdo, $usuario_id);
    $mensaje_creado = chat_enviar_mensaje($pdo, $usuario_id, $mensaje);
    echo json_encode(['ok' => true, 'mensaje' => $mensaje_creado]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('chat_enviar error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al enviar el mensaje']);
}
