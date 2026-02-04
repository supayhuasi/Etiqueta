<?php
// Redirección a la nueva ubicación en ecommerce/admin
$module = basename(__FILE__, '.php');
$mapping = [
    'sueldos' => 'sueldos/sueldos.php',
    'cheques' => 'cheques/cheques.php',
    'gastos' => 'gastos/gastos.php',
    'asistencias' => 'asistencias/asistencias.php',
    'plantillas' => 'sueldos/plantillas.php'
];
$target = $mapping[$module] ?? 'index.php';
header("Location: ecommerce/admin/" . $target);
exit;
?>
