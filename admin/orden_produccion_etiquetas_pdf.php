<?php
$qs = $_SERVER['QUERY_STRING'] ?? '';
$target = '/ecommerce/admin/orden_produccion_etiquetas_pdf.php' . ($qs !== '' ? ('?' . $qs) : '');
header('Location: ' . $target, true, 302);
exit;
