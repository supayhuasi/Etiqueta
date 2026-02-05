<?php
session_start();

echo "<h1>Debug de Sesión</h1>";
echo "<pre>";
echo "=== SESIÓN COMPLETA ===\n";
print_r($_SESSION);
echo "\n";

echo "=== VERIFICACIONES ===\n";
echo "¿Existe \$_SESSION['user']? " . (isset($_SESSION['user']) ? 'SÍ' : 'NO') . "\n";
echo "¿Existe \$_SESSION['user']['id']? " . (isset($_SESSION['user']['id']) ? 'SÍ' : 'NO') . "\n";
echo "Valor de \$_SESSION['user']['id']: " . ($_SESSION['user']['id'] ?? 'NULL') . "\n";
echo "¿Existe \$_SESSION['user_id']? " . (isset($_SESSION['user_id']) ? 'SÍ' : 'NO') . "\n";

echo "\n=== PRUEBA DE OBTENCIÓN ===\n";
$usuario_id = $_SESSION['user']['id'] ?? null;
echo "Variable \$usuario_id = " . ($usuario_id ?? 'NULL') . "\n";

echo "</pre>";
?>
