<?php
require '../../config.php';

try {
    echo "Iniciando creación de módulo de cotizaciones...<br><br>";
    
    // Tabla de cotizaciones
    echo "1. Creando tabla ecommerce_cotizaciones...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_cotizaciones (
            id INT PRIMARY KEY AUTO_INCREMENT,
            numero_cotizacion VARCHAR(20) UNIQUE NOT NULL,
            nombre_cliente VARCHAR(200) NOT NULL,
            email VARCHAR(200) NOT NULL,
            telefono VARCHAR(50),
            empresa VARCHAR(200),
            items JSON NOT NULL,
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            descuento DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2) NOT NULL,
            observaciones TEXT,
            estado ENUM('pendiente', 'enviada', 'aceptada', 'rechazada', 'convertida') DEFAULT 'pendiente',
            validez_dias INT DEFAULT 15,
            fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            fecha_envio DATETIME,
            fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            creado_por INT,
            INDEX idx_numero (numero_cotizacion),
            INDEX idx_estado (estado),
            INDEX idx_email (email),
            INDEX idx_fecha (fecha_creacion)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Tabla ecommerce_cotizaciones creada<br>";
    
    // Tabla de items de cotización (opcional, ya que usamos JSON pero puede ser útil)
    echo "2. Creando tabla ecommerce_cotizacion_items...<br>";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_cotizacion_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            cotizacion_id INT NOT NULL,
            producto_id INT,
            nombre_producto VARCHAR(255) NOT NULL,
            descripcion TEXT,
            cantidad INT NOT NULL DEFAULT 1,
            ancho DECIMAL(10,2),
            alto DECIMAL(10,2),
            atributos JSON,
            precio_unitario DECIMAL(10,2) NOT NULL,
            precio_total DECIMAL(10,2) NOT NULL,
            FOREIGN KEY (cotizacion_id) REFERENCES ecommerce_cotizaciones(id) ON DELETE CASCADE,
            FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE SET NULL,
            INDEX idx_cotizacion (cotizacion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "   ✓ Tabla ecommerce_cotizacion_items creada<br>";
    
    echo "<br>✅ Módulo de cotizaciones creado exitosamente<br>";
    echo "<br><a href='cotizaciones.php' class='btn btn-primary'>Ir a Cotizaciones</a>";
    echo " <a href='index.php' class='btn btn-secondary'>Volver al Dashboard</a>";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
