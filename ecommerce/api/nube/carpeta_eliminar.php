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
$folder_to_delete = fotos_nube_normalize_path($input['carpeta'] ?? $_POST['carpeta'] ?? '');

if ($folder_to_delete === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se puede borrar la carpeta raíz']);
    exit;
}

$target_dir = $root_dir . '/' . $folder_to_delete;
if (!is_dir($target_dir) || !fotos_nube_is_within_root($target_dir, $root_dir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Carpeta inválida']);
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($target_dir, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $item) {
    if ($item->isDir()) {
        rmdir($item->getPathname());
    } else {
        unlink($item->getPathname());
    }
}

rmdir($target_dir);

echo json_encode([
    'success' => true,
    'message' => 'Carpeta eliminada',
    'carpeta_padre' => fotos_nube_parent_folder($folder_to_delete),
]);
