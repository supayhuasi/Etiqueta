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
$seleccion = $input['seleccion'] ?? $_POST['seleccion'] ?? [];
$destino = fotos_nube_normalize_path($input['destino_carpeta'] ?? $_POST['destino_carpeta'] ?? '');
$destino_dir = $root_dir . ($destino !== '' ? '/' . $destino : '');

if ($destino === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Elegí una carpeta destino válida']);
    exit;
}

if (!is_dir($destino_dir) || !fotos_nube_is_within_root($destino_dir, $root_dir)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La carpeta destino no existe o es inválida']);
    exit;
}

if (empty($seleccion) || !is_array($seleccion)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No seleccionaste ninguna foto para mover']);
    exit;
}

$moved = 0;
foreach ($seleccion as $item) {
    $rel = fotos_nube_normalize_path((string)$item);
    if ($rel === '') {
        continue;
    }

    $source = $root_dir . '/' . $rel;
    if (!is_file($source) || !fotos_nube_is_within_root($source, $root_dir)) {
        continue;
    }

    $target = $destino_dir . '/' . basename($source);
    if (file_exists($target)) {
        $target = $destino_dir . '/' . time() . '_' . basename($source);
    }

    if (rename($source, $target)) {
        $moved++;
    }
}

if ($moved === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se pudo mover ninguna foto']);
    exit;
}

echo json_encode(['success' => true, 'message' => "Se movieron {$moved} foto(s) correctamente"]);
