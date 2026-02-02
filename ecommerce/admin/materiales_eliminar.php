<?php
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: materiales.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM ecommerce_materiales WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: materiales.php');
    exit;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='materiales.php' class='btn btn-secondary mt-3'>Volver</a>";
}
?>
