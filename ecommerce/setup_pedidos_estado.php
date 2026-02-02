<?php
require 'config.php';

try {
    $pdo->exec("
        ALTER TABLE ecommerce_pedidos
        MODIFY COLUMN estado ENUM(
            'pendiente_pago',
            'esperando_transferencia',
            'esperando_envio',
            'pagado',
            'pago_pendiente',
            'pago_autorizado',
            'pago_en_proceso',
            'pago_rechazado',
            'pago_reembolsado',
            'confirmado',
            'preparando',
            'enviado',
            'entregado',
            'cancelado'
        ) DEFAULT 'pendiente_pago'
    ");
    echo "✓ Columna estado de ecommerce_pedidos actualizada";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
