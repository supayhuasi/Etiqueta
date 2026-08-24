<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($can_access) || !$can_access('seguros')) {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM seguros_permisos WHERE id = ?");
$stmt->execute([$id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registro) {
    die("Registro no encontrado");
}

$upload_dir = __DIR__ . '/uploads/';
if (!empty($registro['archivo']) && file_exists($upload_dir . $registro['archivo'])) {
    unlink($upload_dir . $registro['archivo']);
}

$stmt = $pdo->prepare("DELETE FROM seguros_permisos WHERE id = ?");
$stmt->execute([$id]);

header("Location: seguros.php");
exit;
