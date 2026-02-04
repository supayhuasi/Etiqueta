<?php
// Test de rutas y requires
echo "<h1>Test de Integración - Módulos Migrados</h1>";

try {
    // Probar require de config desde ruta absoluta
    $base_path = dirname(__FILE__);
    require $base_path . '/../config.php';
    echo "<p style='color: green;'>✓ Config.php cargado correctamente</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error al cargar config.php: " . $e->getMessage() . "</p>";
}

// Verificar conexión PDO
try {
    if ($pdo) {
        $stmt = $pdo->query("SELECT 1");
        echo "<p style='color: green;'>✓ Conexión PDO activa</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error en PDO: " . $e->getMessage() . "</p>";
}

// Verificar tablas necesarias
$tables_to_check = [
    'asistencias' => 'Asistencias',
    'pagos_sueldos' => 'Sueldos',
    'cheques' => 'Cheques',
    'gastos' => 'Gastos',
    'empleados' => 'Empleados'
];

echo "<h3>Verificación de Tablas:</h3>";
foreach ($tables_to_check as $table => $name) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "<p style='color: green;'>✓ Tabla $name ($table) existe</p>";
        } else {
            echo "<p style='color: orange;'>⚠ Tabla $name ($table) NO encontrada</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Error al verificar $name: " . $e->getMessage() . "</p>";
    }
}

// Verificar sesión
echo "<h3>Verificación de Sesión:</h3>";
session_start();
if (isset($_SESSION['user'])) {
    echo "<p style='color: green;'>✓ Usuario: " . htmlspecialchars($_SESSION['user']['usuario']) . "</p>";
} else {
    echo "<p style='color: orange;'>⚠ No hay usuario logueado (esperado si accedes directamente)</p>";
}
?>
