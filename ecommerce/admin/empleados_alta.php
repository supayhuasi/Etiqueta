<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: empleados.php');
    exit;
}

$id = intval($_GET['id']);
$pdo->prepare("UPDATE empleados SET activo = 1 WHERE id = ?")->execute([$id]);
header('Location: empleados.php?success=Empleado reactivado');
exit;
