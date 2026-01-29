<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'stock'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN stock INT DEFAULT 0 AFTER imagen");
        echo "✓ Columna stock agregada en ecommerce_productos<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'mostrar_ecommerce'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN mostrar_ecommerce TINYINT(1) DEFAULT 1 AFTER stock");
        echo "✓ Columna mostrar_ecommerce agregada en ecommerce_productos<br>";
    }

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_proveedores (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            telefono VARCHAR(50),
            direccion VARCHAR(255),
            cuit VARCHAR(50),
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "✓ Tabla ecommerce_proveedores creada<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_compras (
            id INT PRIMARY KEY AUTO_INCREMENT,
            numero_compra VARCHAR(50) NOT NULL UNIQUE,
            proveedor_id INT NOT NULL,
            fecha_compra DATE NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL,
            total DECIMAL(10,2) NOT NULL,
            observaciones TEXT,
            creado_por INT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (proveedor_id) REFERENCES ecommerce_proveedores(id) ON DELETE RESTRICT,
            INDEX idx_proveedor (proveedor_id),
            INDEX idx_fecha (fecha_compra)
        )
    ");
    echo "✓ Tabla ecommerce_compras creada<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_compra_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            compra_id INT NOT NULL,
            producto_id INT NOT NULL,
            cantidad INT NOT NULL,
            costo_unitario DECIMAL(10,2) NOT NULL,
            alto_cm INT,
            ancho_cm INT,
            subtotal DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (compra_id) REFERENCES ecommerce_compras(id) ON DELETE CASCADE,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            INDEX idx_compra (compra_id)
        )
    ");
    echo "✓ Tabla ecommerce_compra_items creada<br>";

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_inventario_movimientos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            tipo ENUM('compra','ajuste','venta') NOT NULL,
            cantidad INT NOT NULL,
            alto_cm INT,
            ancho_cm INT,
            referencia VARCHAR(100),
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE RESTRICT,
            INDEX idx_producto (producto_id)
        )
    ");
    echo "✓ Tabla ecommerce_inventario_movimientos creada<br>";

    echo "✓ Setup de compras completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
