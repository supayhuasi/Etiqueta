<?php
// Puente de compatibilidad para URL antigua /admin/crm.php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/crm.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
