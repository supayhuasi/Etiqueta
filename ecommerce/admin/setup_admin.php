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
                stock INT DEFAULT 0,
                mostrar_ecommerce TINYINT(1) DEFAULT 1,
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

    // Verificar columnas para Google Analytics
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'ga_enabled'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna ga_enabled a ecommerce_empresa...\n";
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN ga_enabled TINYINT(1) DEFAULT 0 AFTER redes_sociales");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'ga_measurement_id'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna ga_measurement_id a ecommerce_empresa...\n";
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN ga_measurement_id VARCHAR(50) AFTER ga_enabled");
    }

    // Verificar tabla ecommerce_config
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_config'");
    if ($stmt->rowCount() === 0) {
        echo "Creando tabla ecommerce_config...\n";
        $pdo->exec("
            CREATE TABLE ecommerce_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                lista_precio_id INT,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $pdo->exec("INSERT INTO ecommerce_config (id, lista_precio_id) VALUES (1, NULL)");
    }
    
    // Verificar columnas en ecommerce_productos
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'imagen'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna imagen a ecommerce_productos...\n";
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN imagen VARCHAR(255) AFTER tipo_precio");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'stock'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna stock a ecommerce_productos...\n";
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN stock INT DEFAULT 0 AFTER imagen");
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'mostrar_ecommerce'");
    if ($stmt->rowCount() === 0) {
        echo "Agregando columna mostrar_ecommerce a ecommerce_productos...\n";
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN mostrar_ecommerce TINYINT(1) DEFAULT 1 AFTER stock");
    }
    
    // Verificar tabla ecommerce_mercadopago_config
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_mercadopago_config'");
    if ($stmt->rowCount() === 0) {
        echo "Creando tabla ecommerce_mercadopago_config...\n";
        $pdo->exec("
            CREATE TABLE ecommerce_mercadopago_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                activo TINYINT(1) DEFAULT 0,
                modo ENUM('test', 'produccion') DEFAULT 'test',
                public_key_test VARCHAR(500),
                access_token_test VARCHAR(500),
                public_key_produccion VARCHAR(500),
                access_token_produccion VARCHAR(500),
                notification_url VARCHAR(500),
                descripcion_defecto VARCHAR(255) DEFAULT 'Pago en tienda',
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        // Insertar registro inicial
        $pdo->exec("INSERT INTO ecommerce_mercadopago_config (activo) VALUES (0)");
    }
    
    echo "✓ Todas las tablas están listas\n";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}
?>
