<?php
/**
 * Script de migración: Renombrar columna 'empresa' a 'direccion'
 * Ejecutar una sola vez para actualizar la estructura de las tablas
 */

require 'includes/header.php';

echo "<h2>Migración: Cambiar 'empresa' por 'dirección'</h2>";

try {
    // 1. Tabla ecommerce_cotizaciones
    echo "<p>1. Actualizando tabla ecommerce_cotizaciones...</p>";
    
    try {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones CHANGE COLUMN empresa direccion VARCHAR(255)");
        echo "<p style='color: green;'>✓ Columna 'empresa' renombrada a 'direccion' en ecommerce_cotizaciones</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Unknown column") !== false) {
            echo "<p style='color: blue;'>ℹ Columna 'direccion' ya existe en ecommerce_cotizaciones</p>";
        } else {
            throw $e;
        }
    }

    // 2. Tabla ecommerce_cotizacion_clientes
    echo "<p>2. Actualizando tabla ecommerce_cotizacion_clientes...</p>";
    
    try {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes CHANGE COLUMN empresa direccion VARCHAR(255)");
        echo "<p style='color: green;'>✓ Columna 'empresa' renombrada a 'direccion' en ecommerce_cotizacion_clientes</p>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), "Unknown column") !== false) {
            echo "<p style='color: blue;'>ℹ Columna 'direccion' ya existe en ecommerce_cotizacion_clientes</p>";
        } else {
            throw $e;
        }
    }

    echo "<hr>";
    echo "<h3 style='color: green;'>✓ Migración completada exitosamente</h3>";
    echo "<p><a href='cotizaciones.php'>← Volver a Cotizaciones</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

require 'includes/footer.php';
?>
