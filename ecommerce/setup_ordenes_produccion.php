<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_ordenes_produccion (
            id INT PRIMARY KEY AUTO_INCREMENT,
            pedido_id INT NOT NULL,
            estado ENUM('pendiente','en_produccion','terminado','entregado') DEFAULT 'pendiente',
            notas TEXT NULL,
            fecha_entrega DATE NULL,
            materiales_descontados TINYINT DEFAULT 0,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (pedido_id) REFERENCES ecommerce_pedidos(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_pedido (pedido_id),
            INDEX idx_estado (estado)
        )
    ");
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_ordenes_produccion LIKE 'fecha_entrega'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN fecha_entrega DATE NULL AFTER notas");
        echo "<br>✓ Columna fecha_entrega agregada";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_ordenes_produccion LIKE 'materiales_descontados'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN materiales_descontados TINYINT DEFAULT 0 AFTER fecha_entrega");
        echo "<br>✓ Columna materiales_descontados agregada";
    }
    echo "✓ Tabla ecommerce_ordenes_produccion creada/actualizada";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
