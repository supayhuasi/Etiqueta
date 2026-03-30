<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_cotizacion_clientes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            telefono VARCHAR(50) NULL,
            direccion VARCHAR(255) NULL,
            empresa VARCHAR(255) NULL,
            cuit VARCHAR(20) NULL,
            factura_a TINYINT(1) NOT NULL DEFAULT 0,
            es_empresa TINYINT(1) NOT NULL DEFAULT 0,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_email (email),
            INDEX idx_nombre (nombre)
        )
    ");

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'cliente_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cliente_id INT NULL AFTER empresa");
        echo "✓ Columna cliente_id agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW INDEX FROM ecommerce_cotizaciones WHERE Key_name = 'idx_cliente' ");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD INDEX idx_cliente (cliente_id)");
        echo "✓ Índice idx_cliente agregado<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'direccion'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono");
        echo "✓ Columna direccion agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'cuit'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit VARCHAR(20) NULL AFTER direccion");
        echo "✓ Columna cuit agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'factura_a'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
        echo "✓ Columna factura_a agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'es_empresa'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
        echo "✓ Columna es_empresa agregada en ecommerce_cotizacion_clientes<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'cuit'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER telefono");
        echo "✓ Columna cuit agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'factura_a'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
        echo "✓ Columna factura_a agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'es_empresa'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
        echo "✓ Columna es_empresa agregada en ecommerce_cotizaciones<br>";
    }

    echo "✓ Setup de clientes para cotizaciones completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
