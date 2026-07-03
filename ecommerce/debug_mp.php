<?php
/**
 * Script de diagnóstico para problemas de Mercado Pago
 * Accede a: https://tucuroller.com.ar/ecommerce/debug_mp.php
 */

header('Content-Type: text/html; charset=utf-8');

echo "<h1>🔍 Diagnóstico Mercado Pago</h1>";
echo "<hr>";

// 1. Verificar si config.php se carga correctamente
echo "<h2>1. Cargando configuración...</h2>";
try {
    require 'config.php';
    echo "✅ config.php cargado correctamente<br>";
} catch (Exception $e) {
    echo "❌ Error al cargar config.php: " . $e->getMessage() . "<br>";
    exit;
}

// 2. Verificar conexión a BD
echo "<h2>2. Verificando conexión a BD...</h2>";
try {
    $test = $pdo->query("SELECT 1");
    echo "✅ Conexión a BD OK<br>";
} catch (Exception $e) {
    echo "❌ Error de conexión: " . $e->getMessage() . "<br>";
    exit;
}

// 3. Verificar tabla mercadopago_config
echo "<h2>3. Verificando tabla ecommerce_mercadopago_config...</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
    $config_mp = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$config_mp) {
        echo "❌ No hay configuración de Mercado Pago activa en la BD<br>";
        echo "<strong>SOLUCIÓN:</strong> Debes agregar una configuración en la tabla ecommerce_mercadopago_config<br>";
    } else {
        echo "✅ Configuración encontrada<br>";
        echo "<pre>";
        echo "ID: " . $config_mp['id'] . "\n";
        echo "Modo: " . $config_mp['modo'] . "\n";
        echo "Nombre: " . $config_mp['nombre'] . "\n";
        echo "Access Token (Test): " . (empty($config_mp['access_token_test']) ? "❌ VACÍO" : "✅ Configurado") . "\n";
        echo "Access Token (Producción): " . (empty($config_mp['access_token_produccion']) ? "❌ VACÍO" : "✅ Configurado") . "\n";
        echo "Notification URL: " . $config_mp['notification_url'] . "\n";
        echo "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Error al consultar tabla: " . $e->getMessage() . "<br>";
    echo "<strong>SOLUCIÓN:</strong> La tabla no existe. Necesitas ejecutar el SQL de configuración<br>";
}

// 4. Verificar tabla pedidos
echo "<h2>4. Verificando tabla ecommerce_pedidos...</h2>";
try {
    $cols = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (in_array('mercadopago_preference_id', $cols)) {
        echo "✅ Columna mercadopago_preference_id existe<br>";
    } else {
        echo "⚠️ Columna mercadopago_preference_id NO existe<br>";
        echo "SQL para agregar: ALTER TABLE ecommerce_pedidos ADD COLUMN mercadopago_preference_id VARCHAR(255) NULL;";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

// 5. Verificar si procesar_pago_mp.php se puede ejecutar
echo "<h2>5. Testeando procesar_pago_mp.php...</h2>";
if (!file_exists('procesar_pago_mp.php')) {
    echo "❌ Archivo procesar_pago_mp.php no existe<br>";
} else {
    echo "✅ Archivo procesar_pago_mp.php existe<br>";
    echo "📝 Para probar, necesitas hacer un POST con pedido_id<br>";
}

echo "<hr>";
echo "<p><strong>Si todos lo pasos están OK (✅), el problema está en la configuración de Mercado Pago o en credenciales inválidas.</strong></p>";
?>
