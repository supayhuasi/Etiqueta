<?php
session_start();
$_SESSION['user'] = ['id' => 1, 'nombre' => 'Test'];
$_SESSION['rol'] = 'admin';
$_GET['pedido_id'] = 300;
$_SERVER['SCRIPT_NAME'] = '/ecommerce/admin/pedidos_detalle.php';
$_SERVER['PHP_SELF'] = '/ecommerce/admin/pedidos_detalle.php';
$_SERVER['HTTP_HOST'] = 'tucuroller.com.ar';
$_SERVER['HTTPS'] = 'on';
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/config.php';
require __DIR__ . '/ecommerce/admin/includes/header.php';
include __DIR__ . '/ecommerce/admin/pedidos_detalle.php';
