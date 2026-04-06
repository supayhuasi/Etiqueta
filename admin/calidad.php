<?php
// Puente de compatibilidad para URL antigua /admin/calidad.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/calidad.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
