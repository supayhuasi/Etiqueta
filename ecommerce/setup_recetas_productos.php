<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_producto_recetas_productos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            material_producto_id INT NOT NULL,
            tipo_calculo ENUM('fijo','por_area','por_ancho','por_alto') NOT NULL DEFAULT 'fijo',
            factor DECIMAL(10,4) NOT NULL DEFAULT 0,
            merma_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
            notas VARCHAR(255) NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
            FOREIGN KEY (material_producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            UNIQUE KEY uniq_producto_material (producto_id, material_producto_id),
            INDEX idx_producto (producto_id)
        )
    ");

    echo "✓ Tabla ecommerce_producto_recetas_productos creada/actualizada";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
