<?php
require '../../config.php';

try {
    echo "Iniciando migración de productos v2...\n";
    
    // 1. Agregar parent_id a categorias para subcategorías
    echo "1. Agregando soporte para subcategorías...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_categorias LIKE 'parent_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE ecommerce_categorias 
            ADD COLUMN parent_id INT DEFAULT NULL,
            ADD FOREIGN KEY (parent_id) REFERENCES ecommerce_categorias(id) ON DELETE SET NULL
        ");
        echo "   ✓ Columna parent_id agregada\n";
    } else {
        echo "   ✓ Ya existe parent_id\n";
    }
    
    // 2. Crear tabla para múltiples imágenes de productos
    echo "2. Creando tabla ecommerce_producto_imagenes...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_producto_imagenes'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE ecommerce_producto_imagenes (
                id INT PRIMARY KEY AUTO_INCREMENT,
                producto_id INT NOT NULL,
                imagen VARCHAR(255) NOT NULL,
                orden INT DEFAULT 0,
                es_principal TINYINT(1) DEFAULT 0,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
                INDEX (producto_id)
            )
        ");
        echo "   ✓ Tabla ecommerce_producto_imagenes creada\n";
    } else {
        echo "   ✓ Ya existe tabla ecommerce_producto_imagenes\n";
    }
    
    // 3. Crear tabla para atributos de productos
    echo "3. Creando tabla ecommerce_producto_atributos...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_producto_atributos'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE ecommerce_producto_atributos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                producto_id INT NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                tipo ENUM('text', 'number', 'select') DEFAULT 'text',
                valores TEXT,
                costo_adicional DECIMAL(10, 2) DEFAULT 0,
                es_obligatorio TINYINT(1) DEFAULT 0,
                orden INT DEFAULT 0,
                FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
                INDEX (producto_id)
            )
        ");
        echo "   ✓ Tabla ecommerce_producto_atributos creada\n";
    } else {
        echo "   ✓ Ya existe tabla ecommerce_producto_atributos\n";
        // Agregar columna costo_adicional si no existe
        $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_producto_atributos LIKE 'costo_adicional'");
        if ($stmt->rowCount() === 0) {
            $pdo->exec("ALTER TABLE ecommerce_producto_atributos ADD COLUMN costo_adicional DECIMAL(10, 2) DEFAULT 0 AFTER valores");
            echo "   ✓ Columna costo_adicional agregada\n";
        }
    }
    
    // 4. Migrar imagen principal a tabla de imágenes
    echo "4. Migrando imágenes principales...\n";
    $productos = $pdo->query("SELECT id, imagen FROM ecommerce_productos WHERE imagen IS NOT NULL AND imagen != ''")->fetchAll(PDO::FETCH_ASSOC);
    
    $migrados = 0;
    foreach ($productos as $prod) {
        // Verificar si ya fue migrada
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_producto_imagenes WHERE producto_id = ? AND imagen = ?");
        $stmt->execute([$prod['id'], $prod['imagen']]);
        
        if ($stmt->rowCount() === 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_producto_imagenes (producto_id, imagen, orden, es_principal)
                VALUES (?, ?, 0, 1)
            ");
            $stmt->execute([$prod['id'], $prod['imagen']]);
            $migrados++;
        }
    }
    echo "   ✓ {$migrados} imágenes migradas\n";
    
    echo "\n✓ Migración completada exitosamente\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
