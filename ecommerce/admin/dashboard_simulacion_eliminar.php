<?php
require 'includes/header.php';

if (!$can_access('dashboard_principal')) {
    die('Acceso denegado.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

admin_require_csrf_post();

$id = (int)($_POST['id'] ?? 0);
if ($id > 0) {
    $stmt = $pdo->prepare("DELETE FROM dashboard_gastos_simulados WHERE id = ?");
    $stmt->execute([$id]);
}

header('Location: dashboard.php');
exit;
