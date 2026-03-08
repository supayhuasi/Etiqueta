<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/api/asistencias_empleado.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $target, true, 302);
exit;
