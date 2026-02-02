<?php
require 'config.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ecommerce_cotizacion_clientes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NULL,
            telefono VARCHAR(50) NULL,
            empresa VARCHAR(255) NULL,
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

    echo "✓ Setup de clientes para cotizaciones completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
