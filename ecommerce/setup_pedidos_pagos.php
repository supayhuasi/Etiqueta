<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_pedido_pagos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            monto DECIMAL(10,2) NOT NULL,
            metodo VARCHAR(100) NOT NULL,
            referencia VARCHAR(150) NULL,
            notas TEXT NULL,
            fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
            creado_por INT NULL,
            FOREIGN KEY (pedido_id) REFERENCES ecommerce_pedidos(id) ON DELETE CASCADE,
            INDEX idx_pedido (pedido_id),
            INDEX idx_fecha (fecha_pago)
        )
    ");
    echo "✓ Tabla ecommerce_pedido_pagos creada/actualizada";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
