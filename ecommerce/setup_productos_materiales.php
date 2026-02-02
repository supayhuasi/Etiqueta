<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'es_material'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN es_material TINYINT(1) DEFAULT 0 AFTER mostrar_ecommerce");
        echo "✓ Columna es_material agregada en ecommerce_productos";
    } else {
        echo "✓ Columna es_material ya existe";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos LIKE 'usa_receta'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_productos ADD COLUMN usa_receta TINYINT(1) DEFAULT 0 AFTER es_material");
        echo "<br>✓ Columna usa_receta agregada en ecommerce_productos";
    } else {
        echo "<br>✓ Columna usa_receta ya existe";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
