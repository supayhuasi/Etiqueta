<?php
session_start();
require_once '../config.php';

$user = $_POST['usuario'] ?? '';
$pass = $_POST['password'] ?? '';

$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ? AND activo = 1");
$stmt->execute([$user]);
$u = $stmt->fetch();

if ($u && password_verify($pass, $u['password'])) {
    $_SESSION['user'] = [
        'id' => $u['id'],
        'usuario' => $u['usuario'],
        'nombre' => $u['nombre']
    ];
    header("Location: ../index.php");
} else {
    header("Location: login.php?error=1");
}
