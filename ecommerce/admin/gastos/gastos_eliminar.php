<?php

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

// Obtener datos del gasto
$stmt = $pdo->prepare("SELECT * FROM gastos WHERE id = ?");
$stmt->execute([$id]);
$gasto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gasto) {
    die("Gasto no encontrado");
}

// Eliminar archivo si existe
if (!empty($gasto['archivo']) && file_exists("uploads/gastos/" . $gasto['archivo'])) {
    unlink("uploads/gastos/" . $gasto['archivo']);
}

// Eliminar historial
$stmt = $pdo->prepare("DELETE FROM historial_gastos WHERE gasto_id = ?");
$stmt->execute([$id]);

// Eliminar gasto
$stmt = $pdo->prepare("DELETE FROM gastos WHERE id = ?");
$stmt->execute([$id]);

header("Location: gastos.php");
exit;
?>
