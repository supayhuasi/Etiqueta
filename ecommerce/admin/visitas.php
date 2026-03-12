<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'instalaciones.php' . ($query ? ('?' . $query) : '');
header('Location: ' . $target, true, 302);
exit;
