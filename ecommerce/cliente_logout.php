<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';
require 'includes/cliente_auth.php';
require 'includes/mailer.php';

$cliente = null;
if (!empty($_SESSION['cliente_id'])) {
    $cliente = cliente_actual($pdo);
}

$carrito = $_SESSION['carrito'] ?? [];
if ($cliente && !empty($carrito)) {
    $ultimo_envio = (int)($_SESSION['carrito_abandonado_enviado'] ?? 0);
    if ($ultimo_envio === 0 || (time() - $ultimo_envio) > 86400) {
        if (enviar_email_carrito_abandonado($cliente, $carrito)) {
            $_SESSION['carrito_abandonado_enviado'] = time();
        }
    }
}

unset($_SESSION['cliente_id'], $_SESSION['cliente_nombre']);

header('Location: index.php');
exit;
?>
