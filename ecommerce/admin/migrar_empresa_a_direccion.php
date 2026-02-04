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
    
    // Verificar si existe la columna empresa
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'empresa'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones CHANGE COLUMN empresa direccion VARCHAR(255)");
        echo "<p style='color: green;'>✓ Columna 'empresa' renombrada a 'direccion' en ecommerce_cotizaciones</p>";
    } else {
        // Verificar si ya existe como direccion
        $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones LIKE 'direccion'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: blue;'>ℹ Columna 'direccion' ya existe en ecommerce_cotizaciones</p>";
        } else {
            // Agregar la columna si no existe ninguna
            $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN direccion VARCHAR(255) AFTER telefono");
            echo "<p style='color: green;'>✓ Columna 'direccion' agregada a ecommerce_cotizaciones</p>";
        }
    }

    // 2. Tabla ecommerce_cotizacion_clientes
    echo "<p>2. Actualizando tabla ecommerce_cotizacion_clientes...</p>";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'empresa'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes CHANGE COLUMN empresa direccion VARCHAR(255)");
        echo "<p style='color: green;'>✓ Columna 'empresa' renombrada a 'direccion' en ecommerce_cotizacion_clientes</p>";
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes LIKE 'direccion'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: blue;'>ℹ Columna 'direccion' ya existe en ecommerce_cotizacion_clientes</p>";
        } else {
            $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) AFTER telefono");
            echo "<p style='color: green;'>✓ Columna 'direccion' agregada a ecommerce_cotizacion_clientes</p>";
        }
    }

    echo "<hr>";
    echo "<h3 style='color: green;'>✓ Migración completada exitosamente</h3>";
    echo "<p><a href='cotizaciones.php'>← Volver a Cotizaciones</a></p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
