<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_materiales (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            unidad VARCHAR(50) NOT NULL,
            costo DECIMAL(10,2) NULL,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_producto_recetas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            material_id INT NOT NULL,
            tipo_calculo ENUM('fijo','por_area','por_ancho','por_alto') NOT NULL DEFAULT 'fijo',
            factor DECIMAL(10,4) NOT NULL DEFAULT 0,
            merma_pct DECIMAL(5,2) NOT NULL DEFAULT 0,
            notas VARCHAR(255) NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
            FOREIGN KEY (material_id) REFERENCES ecommerce_materiales(id) ON DELETE RESTRICT,
            UNIQUE KEY uniq_producto_material (producto_id, material_id),
            INDEX idx_producto (producto_id)
        )
    ");

    echo "✓ Tablas de materiales y recetas creadas/actualizadas";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
