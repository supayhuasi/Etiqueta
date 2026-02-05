<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Ingreso</h1>";

echo "<h3>1. Probando config.php</h3>";
require '../../config.php';
echo "✓ Config cargado<br>";

echo "<h3>2. Probando sesión</h3>";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✓ Sesión iniciada<br>";
} else {
    echo "✓ Sesión ya existía<br>";
}

echo "<h3>3. Datos de sesión</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "<h3>4. Usuario ID</h3>";
$usuario_id = $_SESSION['user']['id'] ?? null;
echo "Usuario ID: " . ($usuario_id ?? 'NULL') . "<br>";

echo "<h3>5. Probando query de pedidos</h3>";
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            ep.id,
            ep.numero_pedido
        FROM ecommerce_pedidos ep
        LIMIT 5
    ");
    $stmt->execute();
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "✓ Query ejecutado, " . count($pedidos) . " pedidos encontrados<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>6. Probando includes/header.php</h3>";
try {
    require 'includes/header.php';
    echo "✓ Header cargado<br>";
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "<br>";
}

echo "<h3>TODO OK - Si ves esto, el problema es otro</h3>";
?>
