<?php
session_start();
require 'config.php';

echo "<h2>Debug de Rol y Sesión</h2>";

echo "<h3>Sesión actual:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

if (isset($_SESSION['user']['id'])) {
    echo "<h3>Datos en Base de Datos:</h3>";
    $stmt = $pdo->prepare("SELECT u.id, u.usuario, u.rol_id, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.id = ?");
    $stmt->execute([$_SESSION['user']['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($user);
    echo "</pre>";
}

echo "<h3>Roles disponibles:</h3>";
$stmt = $pdo->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($roles);
echo "</pre>";

echo "<hr>";
echo "<a href='index.php'>← Volver al inicio</a>";
?>
