<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

require 'config.php';

$id = $_GET['id'] ?? 0;

if (!$id) {
    die('ID inválido');
}

try {
    $pdo->beginTransaction();

    // Obtener estado actual
    $stmt = $pdo->prepare("SELECT estado_id FROM productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        throw new Exception('Producto no encontrado');
    }

    // Cambiar estado a STOCK (5)
    $stmt = $pdo->prepare("
        UPDATE productos 
        SET estado_id = 5 
        WHERE id = ?
    ");
    $stmt->execute([$id]);

    // Guardar historial
    $stmt = $pdo->prepare("
        INSERT INTO historial_estados (producto_id, estado_id, fecha)
        VALUES (?, 5, NOW())
    ");
    $stmt->execute([$id]);

    $pdo->commit();

    header("Location: index.php?stock=ok");
    exit;

} catch (Exception $e) {
    $pdo->rollBack();
    die("Error: " . $e->getMessage());
}
