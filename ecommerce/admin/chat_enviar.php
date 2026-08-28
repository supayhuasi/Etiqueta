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

$conversacion_id = (int)($_POST['conversacion_id'] ?? 0);
$mensaje = trim((string)($_POST['mensaje'] ?? ''));
$tiene_adjunto = isset($_FILES['adjunto']) && ($_FILES['adjunto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

if ($conversacion_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'Conversación inválida']);
    exit;
}
if ($mensaje === '' && !$tiene_adjunto) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => 'El mensaje no puede estar vacío']);
    exit;
}

try {
    chat_asegurar_tablas($pdo);
    chat_actualizar_actividad($pdo, $usuario_id);

    if (!chat_usuario_tiene_acceso($pdo, $conversacion_id, $usuario_id)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'msg' => 'No tenés acceso a esta conversación']);
        exit;
    }

    $adjunto = $tiene_adjunto ? chat_guardar_adjunto($_FILES['adjunto']) : null;

    $mensaje_creado = chat_enviar_mensaje($pdo, $conversacion_id, $usuario_id, $mensaje, $adjunto);
    $mensaje_creado = chat_agregar_url_adjuntos([$mensaje_creado], $admin_url)[0];
    $mensaje_creado['leido'] = false;

    echo json_encode(['ok' => true, 'mensaje' => $mensaje_creado]);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('chat_enviar error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al enviar el mensaje']);
}
