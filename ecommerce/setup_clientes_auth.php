<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'password_hash'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN password_hash VARCHAR(255) NULL AFTER email");
        echo "✓ Columna password_hash agregada<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'activo'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN activo TINYINT(1) DEFAULT 1 AFTER codigo_postal");
        echo "✓ Columna activo agregada<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'google_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN google_id VARCHAR(255) AFTER email");
        echo "✓ Columna google_id agregada<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_clientes LIKE 'auth_provider'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD COLUMN auth_provider VARCHAR(50) AFTER google_id");
        echo "✓ Columna auth_provider agregada<br>";
    }

    $idx = $pdo->query("SHOW INDEX FROM ecommerce_clientes WHERE Key_name = 'idx_google_id'");
    if ($idx->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_clientes ADD INDEX idx_google_id (google_id)");
        echo "✓ Índice idx_google_id agregado<br>";
    }

    echo "✓ Setup de clientes/auth completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
