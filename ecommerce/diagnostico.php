<?php
// Test de Rutas y Configuración
echo "<h1>🔍 Diagnóstico de Sistema</h1>";
echo "<hr>";

// 1. Test de rutas
echo "<h3>1. Verificación de Rutas</h3>";

$test_file = __FILE__;
echo "<p><strong>Archivo actual:</strong> $test_file</p>";

$base_path = dirname(dirname(__FILE__));
echo "<p><strong>Base path:</strong> $base_path</p>";

if (file_exists($base_path . '/config.php')) {
    echo "<p style='color: green;'>✓ config.php encontrado en $base_path/config.php</p>";
} else {
    echo "<p style='color: red;'>✗ config.php NO encontrado en $base_path/config.php</p>";
}

// 2. Test de config
echo "<h3>2. Verificación de Config</h3>";

try {
    require $base_path . '/config.php';
    echo "<p style='color: green;'>✓ Config.php cargado exitosamente</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error al cargar config: " . $e->getMessage() . "</p>";
    exit;
}

// 3. Test de PDO
echo "<h3>3. Verificación de Base de Datos</h3>";

try {
    $result = $pdo->query("SELECT 1 as test");
    echo "<p style='color: green;'>✓ Conexión PDO activa</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error en PDO: " . $e->getMessage() . "</p>";
}

// 4. Test de Tablas
echo "<h3>4. Verificación de Tablas Migradas</h3>";

$tables = [
    'asistencias' => 'Asistencias',
    'pagos_sueldos' => 'Sueldos',
    'cheques' => 'Cheques',
    'gastos' => 'Gastos',
    'empleados' => 'Empleados',
    'empleados_horarios' => 'Horarios de Empleados'
];

foreach ($tables as $table => $name) {
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            // Contar registros
            $count = $pdo->query("SELECT COUNT(*) as cnt FROM $table")->fetch()['cnt'];
            echo "<p style='color: green;'>✓ $name ($table) - $count registros</p>";
        } else {
            echo "<p style='color: orange;'>⚠ $name ($table) no existe (puede necesitar setup)</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: orange;'>⚠ Error al verificar $name: " . $e->getMessage() . "</p>";
    }
}

// 5. Test de Sesión
echo "<h3>5. Verificación de Sesión</h3>";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user'])) {
    echo "<p style='color: green;'>✓ Sesión activa - Usuario: " . htmlspecialchars($_SESSION['user']['usuario']) . "</p>";
    if (isset($_SESSION['rol'])) {
        echo "<p style='color: green;'>✓ Rol: " . htmlspecialchars($_SESSION['rol']) . "</p>";
    }
} else {
    echo "<p style='color: orange;'>⚠ No hay sesión activa (debes estar logueado)</p>";
    echo "<p><a href='" . $base_path . "/auth/login.php'>Login</a></p>";
}

// 6. Test de Archivos de Módulos
echo "<h3>6. Verificación de Archivos Migrados</h3>";

$modules = [
    'asistencias' => ['asistencias.php', 'asistencias_crear.php', 'setup_asistencias.php'],
    'sueldos' => ['sueldos.php', 'plantillas.php', 'setup_sueldos.php'],
    'cheques' => ['cheques.php', 'cheques_crear.php', 'setup_cheques.php'],
    'gastos' => ['gastos.php', 'gastos_crear.php', 'setup_gastos.php']
];

$base_admin = $base_path . '/ecommerce/admin';

foreach ($modules as $module => $files) {
    $path = "$base_admin/$module";
    if (is_dir($path)) {
        echo "<p style='color: green;'>✓ Carpeta $module existe</p>";
        foreach ($files as $file) {
            if (file_exists("$path/$file")) {
                echo "<span style='color: green;'>  ✓ $file</span><br>";
            } else {
                echo "<span style='color: orange;'>  ⚠ $file NO encontrado</span><br>";
            }
        }
    } else {
        echo "<p style='color: red;'>✗ Carpeta $module NO existe</p>";
    }
}

echo "<hr>";
echo "<h3>✅ Diagnóstico Completado</h3>";
echo "<p><a href='../admin/index.php'>Ir al Panel Admin</a></p>";
?>
