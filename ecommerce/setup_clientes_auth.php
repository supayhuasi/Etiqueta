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

    echo "✓ Setup de clientes/auth completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
