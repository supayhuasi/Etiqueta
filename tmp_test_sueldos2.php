<?php
session_start();
$_GET['nombre'] = 'Ana Dominguez';
// no mes param: debería tomar el actual
require __DIR__ . '/ecommerce/api/sueldos_faltantes.php';
