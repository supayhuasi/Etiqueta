<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/nube_auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$usuario = nube_api_authenticate($pdo);

$stmt = $pdo->prepare('UPDATE nube_api_tokens SET revocado_en = NOW() WHERE id = ?');
$stmt->execute([$usuario['token_id']]);

echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);
