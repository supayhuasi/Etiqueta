<?php

function nube_api_authenticate(PDO $pdo): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($header === '' && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }

    $token = '';
    if (preg_match('/^Bearer\s+(.+)$/i', trim($header), $matches)) {
        $token = trim($matches[1]);
    }

    if ($token === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Falta el token de autenticación']);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT u.id, u.usuario, u.nombre, t.id AS token_id
         FROM nube_api_tokens t
         JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token = ? AND t.revocado_en IS NULL AND u.activo = 1'
    );
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token inválido o revocado']);
        exit;
    }

    $update = $pdo->prepare('UPDATE nube_api_tokens SET ultimo_uso = NOW() WHERE id = ?');
    $update->execute([$usuario['token_id']]);

    return $usuario;
}
