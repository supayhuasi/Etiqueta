<?php
// Test de rutas - Simular estar en diferentes ubicaciones

echo "<h1>🧪 Test de Cálculo de Rutas</h1>";
echo "<hr>";

// Simulamos REQUEST_URI para cada ubicación
$test_cases = [
    '/ecommerce/admin/index.php' => 'Admin Index',
    '/ecommerce/admin/sueldos/sueldos.php' => 'Sueldos',
    '/ecommerce/admin/asistencias/asistencias.php' => 'Asistencias',
    '/ecommerce/admin/cheques/cheques.php' => 'Cheques',
    '/ecommerce/admin/gastos/gastos.php' => 'Gastos',
];

foreach ($test_cases as $php_self => $name) {
    // Simulamos PHP_SELF
    $admin_path = '/ecommerce/admin';
    $admin_depth = substr_count($admin_path, '/');
    $current_depth = substr_count(dirname($php_self), '/');
    $relative_to_admin = str_repeat('../', max(0, $current_depth - $admin_depth));
    
    echo "<h3>$name</h3>";
    echo "<p><strong>PHP_SELF:</strong> $php_self</p>";
    echo "<p><strong>dirname:</strong> " . dirname($php_self) . "</p>";
    echo "<p><strong>Slashes en dirname:</strong> " . substr_count(dirname($php_self), '/') . "</p>";
    echo "<p><strong>Slashes en admin_path:</strong> " . $admin_depth . "</p>";
    echo "<p><strong>Diferencia:</strong> " . ($current_depth - $admin_depth) . "</p>";
    echo "<p><strong>\$relative_to_admin:</strong> <code>'$relative_to_admin'</code></p>";
    
    // Mostrar cómo quedaría un enlace
    echo "<p><strong>Ejemplo de enlace:</strong></p>";
    echo "<pre>&lt;a href=\"${relative_to_admin}asistencias/asistencias.php\"&gt;\n";
    echo "Resolución: " . dirname($php_self) . "/" . $relative_to_admin . "asistencias/asistencias.php\n";
    echo "&lt;/a&gt;</pre>";
    
    echo "<hr>";
}

echo "<h2>✅ Verificación de Lógica</h2>";
echo "<p>Desde sueldos, para ir a asistencias:</p>";
echo "<pre>";
echo "Ubicación: /ecommerce/admin/sueldos/sueldos.php\n";
echo "dirname = /ecommerce/admin/sueldos (3 slashes)\n";
echo "admin_path = /ecommerce/admin (2 slashes)\n";
echo "Diferencia = 3 - 2 = 1\n";
echo "\$relative_to_admin = '../'\n";
echo "\n";
echo "Enlace: href='../asistencias/asistencias.php'\n";
echo "Resolución: /ecommerce/admin/sueldos/ + ../asistencias/asistencias.php\n";
echo "           = /ecommerce/admin/asistencias/asistencias.php ✓\n";
echo "</pre>";
?>
