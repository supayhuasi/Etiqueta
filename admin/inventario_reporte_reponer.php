<?php
// Puente de compatibilidad para URL antigua /admin/inventario_reporte_reponer.php
$target_file = dirname(__DIR__) . '/ecommerce/admin/inventario_reporte_reponer.php';

if (!is_file($target_file)) {
	http_response_code(404);
	echo 'No se encontró el módulo de inventario (reporte reponer).';
	exit;
}

require $target_file;
