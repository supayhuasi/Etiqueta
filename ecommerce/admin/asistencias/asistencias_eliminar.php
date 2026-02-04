<?php
require '../../config.php';
require '../includes/header.php';

$id = $_GET['id'] ?? 0;

try {
    $stmt = $pdo->prepare("DELETE FROM asistencias WHERE id = ?");
    $stmt->execute([$id]);
    echo "<div class='alert alert-success'>✓ Asistencia eliminada correctamente</div>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

header("refresh:1; url=asistencias.php");
exit;
?>
