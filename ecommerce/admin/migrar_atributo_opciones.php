<?php
require '../../config.php';

try {
    echo "Iniciando migración de opciones de atributos con imágenes...\n";
    
    // Crear tabla para opciones de atributos con imágenes
    echo "1. Creando tabla ecommerce_atributo_opciones...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE ecommerce_atributo_opciones (
                id INT PRIMARY KEY AUTO_INCREMENT,
                atributo_id INT NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                imagen VARCHAR(255),
                orden INT DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (atributo_id) REFERENCES ecommerce_producto_atributos(id) ON DELETE CASCADE,
                INDEX (atributo_id)
            )
        ");
        echo "   ✓ Tabla ecommerce_atributo_opciones creada\n";
    } else {
        echo "   ✓ Ya existe tabla ecommerce_atributo_opciones\n";
    }
    
    echo "\n✓ Migración completada exitosamente\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
