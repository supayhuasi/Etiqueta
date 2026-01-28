<?php
require 'includes/header.php';

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM ecommerce_listas_precios WHERE id = ?");
    $stmt->execute([$id]);
    echo "<div class='alert alert-success'>✓ Lista de precios eliminada correctamente</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

header("refresh:1; url=listas_precios.php");
exit;
?>
