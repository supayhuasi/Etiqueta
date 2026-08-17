<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/nube_auth.php';
require_once __DIR__ . '/../../includes/fotos_nube_helpers.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

nube_api_authenticate($pdo);

$root_dir = __DIR__ . '/../../uploads/fotos_nube';
if (!is_dir($root_dir)) {
    mkdir($root_dir, 0755, true);
}

$current_folder = fotos_nube_normalize_path($_GET['folder'] ?? '');
$current_path = $root_dir . ($current_folder !== '' ? '/' . $current_folder : '');

if (!is_dir($current_path)) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Carpeta no encontrada']);
    exit;
}

$folders = [];
$files = [];

$iterator = new DirectoryIterator($current_path);
foreach ($iterator as $item) {
    if ($item->isDot()) {
        continue;
    }

    $name = $item->getFilename();
    if ($item->isDir()) {
        $folders[] = [
            'name' => $name,
            'folder' => ($current_folder !== '' ? $current_folder . '/' : '') . $name,
        ];
    } elseif ($item->isFile() && fotos_nube_is_image($name)) {
        $rel = ($current_folder !== '' ? $current_folder . '/' : '') . $name;
        $url_path = implode('/', array_map('rawurlencode', explode('/', $rel)));
        $files[] = [
            'name' => $name,
            'rel' => $rel,
            'url' => '/ecommerce/uploads/fotos_nube/' . $url_path,
            'tamanio' => $item->getSize(),
        ];
    }
}

usort($folders, static function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});
usort($files, static function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

$breadcrumbs = [['label' => 'Raíz', 'folder' => '']];
$acc = '';
foreach ($current_folder !== '' ? explode('/', $current_folder) : [] as $part) {
    $acc = $acc === '' ? $part : $acc . '/' . $part;
    $breadcrumbs[] = ['label' => $part, 'folder' => $acc];
}

echo json_encode([
    'success' => true,
    'folder' => $current_folder,
    'breadcrumbs' => $breadcrumbs,
    'folders' => $folders,
    'files' => $files,
]);
