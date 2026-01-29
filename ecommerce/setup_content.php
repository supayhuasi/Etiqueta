<?php
/**
 * Setup para Gestión de Contenido
 * Crea las tablas para slideshow, clientes y listas de precios
 */

require 'config.php';

try {
    // 1. Tabla para Slideshow
    $sql = "
    CREATE TABLE IF NOT EXISTS ecommerce_slideshow (
        id INT PRIMARY KEY AUTO_INCREMENT,
        titulo VARCHAR(255),
        descripcion TEXT,
        imagen_url VARCHAR(500),
        enlace VARCHAR(500),
        orden INT DEFAULT 0,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla ecommerce_slideshow creada<br>";

    // 2. Tabla para Clientes/Logos
    $sql = "
    CREATE TABLE IF NOT EXISTS ecommerce_clientes_logos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        logo_url VARCHAR(500),
        enlace VARCHAR(500),
        orden INT DEFAULT 0,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla ecommerce_clientes_logos creada<br>";

    // 3. Tabla para Listas de Precios
    $sql = "
    CREATE TABLE IF NOT EXISTS ecommerce_listas_precios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL UNIQUE,
        descripcion TEXT,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla ecommerce_listas_precios creada<br>";

    // 4. Tabla para Precios por Lista y Producto
    $sql = "
    CREATE TABLE IF NOT EXISTS ecommerce_lista_precio_items (
        id INT PRIMARY KEY AUTO_INCREMENT,
        lista_precio_id INT NOT NULL,
        producto_id INT NOT NULL,
        precio_nuevo DECIMAL(10,2) NOT NULL,
        descuento_porcentaje DECIMAL(5,2) DEFAULT 0,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (lista_precio_id) REFERENCES ecommerce_listas_precios(id) ON DELETE CASCADE,
        FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE,
        UNIQUE KEY unique_lista_producto (lista_precio_id, producto_id)
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla ecommerce_lista_precio_items creada<br>";

    // 5. Tabla para descuentos por categoría en listas de precios
    $sql = "
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
    ";
    $pdo->exec($sql);
    echo "✓ Tabla ecommerce_lista_precio_categorias creada<br>";

    echo "<div class='alert alert-success mt-3'>Todas las tablas de contenido han sido creadas exitosamente</div>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
