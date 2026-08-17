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

$folder = fotos_nube_normalize_path($_POST['folder'] ?? '');
$current_path = $root_dir . ($folder !== '' ? '/' . $folder : '');

if (!is_dir($current_path) || !fotos_nube_is_within_root($current_path, $root_dir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Carpeta destino inválida']);
    exit;
}

if (empty($_FILES['fotos']['name']) || !is_array($_FILES['fotos']['name'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se seleccionaron fotos para subir']);
    exit;
}

$uploaded = 0;
$subidos = [];
foreach ($_FILES['fotos']['name'] as $index => $name) {
    if ($name === '') {
        continue;
    }

    $tmp = $_FILES['fotos']['tmp_name'][$index] ?? '';
    $error_code = $_FILES['fotos']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

    if ($error_code !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || !fotos_nube_is_image($name)) {
        continue;
    }

    $safe_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($name));
    if ($safe_name === '') {
        continue;
    }

    $destino = $current_path . '/' . $safe_name;
    if (move_uploaded_file($tmp, $destino)) {
        $uploaded++;
        $subidos[] = ($folder !== '' ? $folder . '/' : '') . $safe_name;
    }
}

if ($uploaded === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se pudo subir ninguna foto válida']);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => "Se subieron {$uploaded} foto(s) correctamente",
    'archivos' => $subidos,
]);
