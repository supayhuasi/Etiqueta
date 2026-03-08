<?php
// Puente de compatibilidad para URL antigua /admin/instalaciones.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/instalaciones.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
