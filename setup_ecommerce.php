<?php
// Wrapper para ejecutar el setup del ecommerce desde la raíz
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

chdir(__DIR__ . '/ecommerce');
require __DIR__ . '/ecommerce/setup_ecommerce.php';
