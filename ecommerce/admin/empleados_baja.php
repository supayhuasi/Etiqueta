<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<div class="container mt-4"><div class="alert alert-danger">Acceso solo permitido para administradores.</div></div>';
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: empleados.php');
    exit;
}

$id = intval($_GET['id']);
$pdo->prepare("UPDATE empleados SET activo = 0 WHERE id = ?")->execute([$id]);
header('Location: empleados.php?success=Empleado dado de baja');
exit;
