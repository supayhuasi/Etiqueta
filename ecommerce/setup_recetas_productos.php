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
            con_condicion TINYINT(1) DEFAULT 0,
            condicion_tipo ENUM('ancho','alto','area','atributo') NULL,
            condicion_operador ENUM('igual','mayor','mayor_igual','menor','menor_igual','diferente') NULL,
            condicion_valor VARCHAR(100) NULL,
            condicion_atributo_id INT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
            FOREIGN KEY (material_producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            FOREIGN KEY (condicion_atributo_id) REFERENCES ecommerce_producto_atributos(id) ON DELETE SET NULL,
            UNIQUE KEY uniq_producto_material (producto_id, material_producto_id),
            INDEX idx_producto (producto_id)
        )
    ");

    echo "✓ Tabla ecommerce_producto_recetas_productos creada/actualizada";
    
    // Agregar columnas si no existen
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_producto_recetas_productos LIKE 'con_condicion'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ecommerce_producto_recetas_productos ADD COLUMN con_condicion TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE ecommerce_producto_recetas_productos ADD COLUMN condicion_tipo ENUM('ancho','alto','area','atributo') NULL");
            $pdo->exec("ALTER TABLE ecommerce_producto_recetas_productos ADD COLUMN condicion_operador ENUM('igual','mayor','mayor_igual','menor','menor_igual','diferente') NULL");
            $pdo->exec("ALTER TABLE ecommerce_producto_recetas_productos ADD COLUMN condicion_valor VARCHAR(100) NULL");
            $pdo->exec("ALTER TABLE ecommerce_producto_recetas_productos ADD COLUMN condicion_atributo_id INT NULL");
            echo "<br>✓ Columnas de condición agregadas";
        }
    } catch (Exception $e) {
        echo "<br>ℹ Columnas de condición ya existen";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
