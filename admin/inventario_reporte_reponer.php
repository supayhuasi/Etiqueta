<?php
// Puente de compatibilidad para URL antigua /admin/inventario_reporte_reponer.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/inventario_reporte_reponer.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
