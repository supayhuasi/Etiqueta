<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/nube_auth.php';
require_once __DIR__ . '/../../includes/fotos_nube_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

nube_api_authenticate($pdo);

$root_dir = __DIR__ . '/../../uploads/fotos_nube';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$rel_file = fotos_nube_normalize_path($input['archivo'] ?? $_POST['archivo'] ?? '');
$target_file = $root_dir . ($rel_file !== '' ? '/' . $rel_file : '');

if ($rel_file === '' || !is_file($target_file) || !fotos_nube_is_within_root($target_file, $root_dir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Archivo inválido']);
    exit;
}

unlink($target_file);

echo json_encode(['success' => true, 'message' => 'Archivo eliminado']);
