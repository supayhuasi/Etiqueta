<?php
require '../../config.php';

try {
    echo "Verificando tablas del ecommerce...\n";
    
    // Verificar tabla ecommerce_productos
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_productos'");
    if ($stmt->rowCount() === 0) {
        echo "Creando tabla ecommerce_productos...\n";
        $pdo->exec("
            CREATE TABLE ecommerce_productos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                codigo VARCHAR(50) UNIQUE NOT NULL,
                nombre VARCHAR(255) NOT NULL,
                descripcion TEXT,
                categoria_id INT NOT NULL,
                precio_base DECIMAL(10, 2) NOT NULL,
                tipo_precio ENUM('fijo', 'variable') DEFAULT 'fijo',
                imagen VARCHAR(255),
                orden INT DEFAULT 0,
                activo TINYINT(1) DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (categoria_id) REFERENCES ecommerce_categorias(id)
            )
        ");
    }
    
    // Verificar tabla ecommerce_matriz_precios
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_matriz_precios'");
    if ($stmt->rowCount() === 0) {
        echo "Creando tabla ecommerce_matriz_precios...\n";
        $pdo->exec("
            CREATE TABLE ecommerce_matriz_precios (
                id INT PRIMARY KEY AUTO_INCREMENT,
                producto_id INT NOT NULL,
                alto_cm INT NOT NULL,
                ancho_cm INT NOT NULL,
                precio DECIMAL(10, 2) NOT NULL,
                stock INT DEFAULT 0,
                UNIQUE KEY (producto_id, alto_cm, ancho_cm),
                FOREIGN KEY (producto_id) REFERENCES ecommerce_productos(id) ON DELETE CASCADE
            )
        ");
    }
    
    // Verificar tabla ecommerce_empresa
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_empresa'");
    if ($stmt->rowCount() === 0) {
        echo "Creando tabla ecommerce_empresa...\n";
        $pdo->exec("
            CREATE TABLE ecommerce_empresa (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nombre VARCHAR(255) NOT NULL,
                descripcion TEXT,
                logo VARCHAR(255),
                email VARCHAR(100),
                telefono VARCHAR(20),
                direccion VARCHAR(255),
                ciudad VARCHAR(100),
                provincia VARCHAR(100),
                pais VARCHAR(100),
                horario_atencion VARCHAR(255),
                about_us TEXT,
                terminos_condiciones TEXT,
                politica_privacidad TEXT,
                redes_sociales JSON,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        // Insertar registro inicial
        $pdo->exec("INSERT INTO ecommerce_empresa (nombre, email) VALUES ('Mi Empresa', 'info@empresa.com')");
    }
    
    // Verificar columnas en ecommerce_productos
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'imagen'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna imagen a ecommerce_productos...\n";
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN imagen VARCHAR(255) AFTER tipo_precio");
    }
    
    echo "✓ Todas las tablas están listas\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
