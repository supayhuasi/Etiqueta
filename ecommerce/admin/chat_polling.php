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

    $conversacion_id = (int)($_GET['conversacion_id'] ?? 0);
    $desde = (int)($_GET['desde'] ?? 0);

    $mensajes = null;
    if ($conversacion_id > 0) {
        if (!chat_usuario_tiene_acceso($pdo, $conversacion_id, $usuario_id)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'No tenés acceso a esta conversación']);
            exit;
        }

        $mensajes_raw = chat_obtener_mensajes($pdo, $conversacion_id, $desde, 50);

        $ultimo_id = $desde;
        foreach ($mensajes_raw as $m) {
            $ultimo_id = max($ultimo_id, (int)$m['id']);
        }
        // Marcar como leído ANTES de listar conversaciones, para que el contador de
        // no leídos de esta conversación ya refleje que se acaba de ver.
        chat_marcar_leido($pdo, $conversacion_id, $usuario_id, $ultimo_id);

        $lecturas = chat_obtener_lecturas($pdo, $conversacion_id);
        $mensajes = chat_marcar_estado_lectura($mensajes_raw, $lecturas);
        $mensajes = chat_agregar_url_adjuntos($mensajes, $admin_url);
    }

    $respuesta = [
        'ok' => true,
        'conectados' => chat_usuarios_conectados($pdo, $usuario_id, 3),
        'usuarios' => chat_usuarios_activos($pdo, $usuario_id),
        'conversaciones' => chat_listar_conversaciones($pdo, $usuario_id),
    ];

    if ($mensajes !== null) {
        $respuesta['mensajes'] = $mensajes;
        $respuesta['conversacion_id'] = $conversacion_id;
    }

    echo json_encode($respuesta);
} catch (Throwable $e) {
    error_log('chat_polling error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al obtener el chat']);
}
