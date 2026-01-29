<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'lista_precio_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN lista_precio_id INT NULL AFTER empresa");
        echo "✓ Columna lista_precio_id agregada en ecommerce_cotizaciones<br>";
    }

    $stmt = $pdo->query("SHOW INDEX FROM ecommerce_cotizaciones WHERE Key_name = 'idx_lista_precio'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD INDEX idx_lista_precio (lista_precio_id)");
    }

    echo "✓ Setup de lista de precios en cotizaciones completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
