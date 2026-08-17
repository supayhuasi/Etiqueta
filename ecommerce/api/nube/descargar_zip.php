<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/nube_auth.php';
require_once __DIR__ . '/../../includes/fotos_nube_helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

nube_api_authenticate($pdo);

$root_dir = __DIR__ . '/../../uploads/fotos_nube';

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$seleccion = $input['seleccion'] ?? $_POST['seleccion'] ?? [];

$archivos = [];
foreach ((array)$seleccion as $item) {
    $rel = fotos_nube_normalize_path((string)$item);
    if ($rel === '') {
        continue;
    }

    $full = $root_dir . '/' . $rel;
    if (is_file($full) && fotos_nube_is_within_root($full, $root_dir)) {
        $archivos[] = $full;
    }
}

if (empty($archivos)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No seleccionaste ninguna foto para descargar']);
    exit;
}

$zip_name = 'fotos_nube_' . date('Ymd_His') . '.zip';
$zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('nube_', true) . '.zip';
$zip = new ZipArchive();

if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'No se pudo preparar la descarga en ZIP']);
    exit;
}

foreach ($archivos as $file_path) {
    $relative_in_zip = str_replace($root_dir . '/', '', $file_path);
    $zip->addFile($file_path, $relative_in_zip);
}
$zip->close();

if (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zip_name . '"');
header('Content-Length: ' . filesize($zip_path));
readfile($zip_path);
unlink($zip_path);
