<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/api/asistencias_usuario.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $target, true, 302);
exit;
