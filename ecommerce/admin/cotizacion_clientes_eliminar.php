<?php
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: cotizacion_clientes.php');
    exit;
}

try {
    $stmt = $pdo->prepare("DELETE FROM ecommerce_cotizacion_clientes WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: cotizacion_clientes.php');
    exit;
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<a href='cotizacion_clientes.php' class='btn btn-secondary mt-3'>Volver</a>";
}
?>
