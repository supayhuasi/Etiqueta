<?php
// Wrapper para ejecutar el setup del ecommerce desde la raíz
chdir(__DIR__ . '/ecommerce');
require __DIR__ . '/ecommerce/setup_ecommerce.php';
