<?php
session_start();

// Obtener config desde 4 niveles arriba
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . '/config.php';

$user = $_POST['usuario'] ?? '';
$pass = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ? AND u.activo = 1");
$stmt->execute([$user]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if ($u && password_verify($pass, $u['password'])) {
    $_SESSION['user'] = [
        'id' => $u['id'],
        'usuario' => $u['usuario'],
        'nombre' => $u['nombre']
    ];
    $_SESSION['rol'] = $u['rol'] ?? 'usuario';
    $_SESSION['rol_id'] = $u['rol_id'];
    // Redirigir al admin del ecommerce después del login
    header("Location: ../index.php");
} else {
    // Redirigir al login del admin con error
    header("Location: login.php?error=1");
}
