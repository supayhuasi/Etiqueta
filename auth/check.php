<?php
// REDIRECT PARA COMPATIBILIDAD - El auth se movió a /ecommerce/admin/auth/
// Redirigir al nuevo check
header("Location: ../ecommerce/admin/auth/check.php");
exit;


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
    header("Location: ../index.php");
} else {
    header("Location: login.php?error=1");
}
