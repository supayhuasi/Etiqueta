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

    // Verificar si el ENUM de estado incluye 'cancelado'
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_ordenes_produccion LIKE 'estado'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($column && strpos($column['Type'], 'cancelado') === false) {
        $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion MODIFY COLUMN estado ENUM('pendiente','en_produccion','terminado','entregado','cancelado') DEFAULT 'pendiente'");
        echo "<br>✓ Estado 'cancelado' agregado al ENUM";
    }
    echo "✓ Tabla ecommerce_ordenes_produccion creada/actualizada";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
