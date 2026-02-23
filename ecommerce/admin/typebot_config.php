<?php
// Bridge loader: carga la configuración del administrador principal
// Esto permite acceder a /ecommerce/admin/typebot_config.php si el sitio sirve desde /ecommerce
$main = __DIR__ . '/../../admin/typebot_config.php';
if (file_exists($main)) {
    require $main;
    exit;
}
// Fallback: simple message
http_response_code(404);
echo "Archivo de configuración no encontrado en la ubicación esperada.";
