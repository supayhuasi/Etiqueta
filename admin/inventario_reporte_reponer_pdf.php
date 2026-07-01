<?php
// Puente de compatibilidad para URL antigua /admin/inventario_reporte_reponer_pdf.php
$target_file = dirname(__DIR__) . '/ecommerce/admin/inventario_reporte_reponer_pdf.php';

if (!is_file($target_file)) {
    http_response_code(404);
    echo 'No se encontró el módulo de inventario (reporte reponer PDF).';
    exit;
}

require $target_file;
