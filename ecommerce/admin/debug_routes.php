<?php
// Archivo de debug - agrégalo temporalmente para ver qué está pasando

// Temporalmente, redirige a este archivo desde el header para ver los valores
if ($_GET['debug'] ?? false) {
    session_start();
    
    $base_path = dirname(dirname(dirname(dirname(__FILE__))));
    require $base_path . '/config.php';
    
    $current_dir = substr(str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(dirname($_SERVER['PHP_SELF']))), 1);
    $depth = substr_count($current_dir, '/');
    $relative_root = str_repeat('../', $depth);
    
    $php_self = $_SERVER['PHP_SELF'];
    $admin_path = '/ecommerce/admin';
    $admin_depth = substr_count($admin_path, '/');
    $current_depth = substr_count(dirname($php_self), '/');
    $relative_to_admin = str_repeat('../', max(0, $current_depth - $admin_depth));
    
    echo "<h1>🔍 Debug de Rutas</h1>";
    echo "<pre>";
    echo "\$_SERVER['PHP_SELF'] = " . $_SERVER['PHP_SELF'] . "\n";
    echo "\$_SERVER['DOCUMENT_ROOT'] = " . $_SERVER['DOCUMENT_ROOT'] . "\n";
    echo "dirname(\$_SERVER['PHP_SELF']) = " . dirname($_SERVER['PHP_SELF']) . "\n";
    echo "\n";
    echo "\$current_dir = '$current_dir'\n";
    echo "\$depth = $depth\n";
    echo "\$relative_root = '$relative_root'\n";
    echo "\n";
    echo "\$admin_path = '$admin_path'\n";
    echo "\$admin_depth = $admin_depth (slashes en admin_path)\n";
    echo "\$current_depth = $current_depth (slashes en dirname)\n";
    echo "\$relative_to_admin = '$relative_to_admin'\n";
    echo "Cálculo: " . ($current_depth - $admin_depth) . " (current_depth - admin_depth)\n";
    echo "\n";
    echo "Si haces clic en un enlace con href='<?= \$relative_to_admin ?>asistencias/asistencias.php':\n";
    echo "La URL será: " . dirname($_SERVER['PHP_SELF']) . "/$relative_to_admin" . "asistencias/asistencias.php\n";
    echo "</pre>";
    
    echo "<hr>";
    echo "<p><a href='#' onclick='history.back()'>Volver</a></p>";
    exit;
}
?>
