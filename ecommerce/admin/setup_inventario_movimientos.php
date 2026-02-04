<?php
require 'includes/header.php';

try {
    // Crear tabla de movimientos de inventario
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_inventario_movimientos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tipo_item VARCHAR(20) NOT NULL COMMENT 'producto o material',
            item_id INT NOT NULL,
            tipo_movimiento VARCHAR(20) NOT NULL COMMENT 'entrada, salida, ajuste',
            cantidad DECIMAL(10,2) NOT NULL,
            stock_anterior DECIMAL(10,2),
            stock_nuevo DECIMAL(10,2),
            referencia VARCHAR(255),
            pedido_id INT NULL,
            orden_produccion_id INT NULL,
            usuario_id INT,
            fecha_movimiento DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item (tipo_item, item_id),
            INDEX idx_fecha (fecha_movimiento)
        )
    ");
    
    echo "<div class='alert alert-success'>✓ Tabla ecommerce_inventario_movimientos creada correctamente</div>";
    echo "<p><a href='index.php'>← Volver al Dashboard</a></p>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

require 'includes/footer.php';
?>
