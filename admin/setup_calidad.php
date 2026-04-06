<?php
// Puente de compatibilidad para URL antigua /admin/setup_calidad.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/setup_calidad.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
