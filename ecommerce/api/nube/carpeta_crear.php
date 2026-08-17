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
if (!is_dir($root_dir)) {
    mkdir($root_dir, 0755, true);
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$folder = fotos_nube_normalize_path($input['folder'] ?? $_POST['folder'] ?? '');
$nombre = fotos_nube_normalize_name($input['carpeta_nombre'] ?? $_POST['carpeta_nombre'] ?? '');

if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Ingresá un nombre válido para la carpeta']);
    exit;
}

$parent_dir = $root_dir . ($folder !== '' ? '/' . $folder : '');
if (!is_dir($parent_dir) || !fotos_nube_is_within_root($parent_dir, $root_dir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Carpeta destino inválida']);
    exit;
}

$target_dir = $parent_dir . '/' . $nombre;
if (is_dir($target_dir)) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Ya existe una carpeta con ese nombre']);
    exit;
}

if (!mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo crear la carpeta']);
    exit;
}

echo json_encode(['success' => true, 'message' => 'Carpeta creada correctamente']);
