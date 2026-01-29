<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_lista_precio_categorias (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lista_precio_id INT NOT NULL,
            categoria_id INT NOT NULL,
            descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
            activo TINYINT DEFAULT 1,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (lista_precio_id) REFERENCES ecommerce_listas_precios(id) ON DELETE CASCADE,
            FOREIGN KEY (categoria_id) REFERENCES ecommerce_categorias(id) ON DELETE CASCADE,
            UNIQUE KEY unique_lista_categoria (lista_precio_id, categoria_id)
        )
    ");
    echo "✓ Tabla ecommerce_lista_precio_categorias creada<br>";
    echo "✓ Setup de listas de precios por categoría completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
