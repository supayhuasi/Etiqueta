<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$token = trim($_POST['token'] ?? '');
if ($token === '') {
    header('Location: pedido_publico.php');
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT * FROM ecommerce_pedidos WHERE public_token = ? LIMIT 1');
    $stmt->execute([$token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        throw new Exception('Pedido no encontrado');
    }

    $stmt = $pdo->query("SELECT * FROM ecommerce_metodos_pago WHERE activo = 1 AND tipo = 'mercadopago' LIMIT 1");
    $mp_habilitado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mp_habilitado) {
        throw new Exception('Mercado Pago no está habilitado');
    }

    header('Location: mp_checkout.php?token=' . urlencode($token));
    exit;
} catch (Exception $e) {
    header('Location: pedido_publico.php?token=' . urlencode($token));
    exit;
}
