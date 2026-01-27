<?php
require '../config.php';

/**
 * Generador de Matriz de Precios para Cortinas y Toldos
 * Crea una matriz de precios cada 10cm desde 10 hasta 300cm
 */

$producto_id = $_GET['id'] ?? 0;
$precio_base = $_GET['base'] ?? 100; // Precio base por cm²

if ($producto_id <= 0) {
    echo "Error: Debe especificar un producto_id";
    exit;
}

try {
    // Primero, limpiar matriz anterior si existe
    $stmt = $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE producto_id = ?");
    $stmt->execute([$producto_id]);
    
    // Generar matriz
    $total_insertados = 0;
    for ($alto = 10; $alto <= 300; $alto += 10) {
        for ($ancho = 10; $ancho <= 300; $ancho += 10) {
            // Cálculo del precio: precio_base * (alto * ancho / 10000)
            $precio = $precio_base * ($alto * $ancho / 10000);
            
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$producto_id, $alto, $ancho, round($precio, 2), 100]);
            $total_insertados++;
        }
    }
    
    echo "✓ Matriz de precios generada exitosamente<br>";
    echo "Total de combinaciones: $total_insertados<br>";
    echo "Rango: 10cm a 300cm cada 10cm<br>";
    echo "Precio base usado: \$$precio_base por cm²<br>";
    echo "<a href='../producto.php?id=$producto_id'>Ver producto</a>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
