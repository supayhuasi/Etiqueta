<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/crm_configuracion.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
