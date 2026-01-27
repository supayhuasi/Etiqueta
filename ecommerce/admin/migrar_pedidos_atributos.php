<?php
require '../../config.php';

try {
    echo "Iniciando migración de pedidos - agregar columna atributos...\n";
    
    // Agregar columna atributos a ecommerce_pedido_items
    echo "1. Agregando columna atributos a ecommerce_pedido_items...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_pedido_items LIKE 'atributos'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            ALTER TABLE ecommerce_pedido_items 
            ADD COLUMN atributos LONGTEXT DEFAULT NULL AFTER ancho_cm
        ");
        echo "   ✓ Columna atributos agregada\n";
    } else {
        echo "   ✓ Ya existe columna atributos\n";
    }
    
    echo "\n✓ Migración completada exitosamente\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
