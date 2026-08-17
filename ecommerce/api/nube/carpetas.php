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

echo json_encode([
    'success' => true,
    'carpetas' => fotos_nube_list_folders($root_dir),
]);
