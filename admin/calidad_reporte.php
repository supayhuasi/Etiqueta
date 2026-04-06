<?php
// Puente de compatibilidad para URL antigua /admin/calidad_reporte.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/calidad_reporte.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
