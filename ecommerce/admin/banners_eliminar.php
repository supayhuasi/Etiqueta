<?php
require 'includes/header.php';

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM ecommerce_banners WHERE id = ?");
    $stmt->execute([$id]);
    echo "<div class='alert alert-success'>✓ Banner eliminado correctamente</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

header("refresh:1; url=banners.php");
exit;
?>
