<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'config.php';
require 'includes/cliente_auth.php';
require 'includes/mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$carrito = $_SESSION['carrito'] ?? [];
if (empty($carrito) || empty($_SESSION['cliente_id'])) {
    http_response_code(204);
    exit;
}

$cliente = cliente_actual($pdo);
if (!$cliente) {
    http_response_code(204);
    exit;
}

$ultimo_envio = (int)($_SESSION['carrito_abandonado_enviado'] ?? 0);
if ($ultimo_envio !== 0 && (time() - $ultimo_envio) <= 86400) {
    http_response_code(204);
    exit;
}

if (enviar_email_carrito_abandonado($cliente, $carrito)) {
    $_SESSION['carrito_abandonado_enviado'] = time();
}

http_response_code(204);
exit;
