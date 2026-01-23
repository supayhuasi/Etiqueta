<?php
require 'config.php';

try {
    // Validar que el id exista y sea válido
    if (!isset($_GET['id']) || empty($_GET['id'])) {
        throw new Exception("ID de producto no válido");
    }
    
    $id = intval($_GET['id']);
    
    // Iniciar transacción para asegurar consistencia
    $pdo->beginTransaction();
    
    // Eliminar primero del historial_estados (relación secundaria)
    $stmt1 = $pdo->prepare("DELETE FROM historial_estados WHERE producto_id = ?");
    $stmt1->execute([$id]);
    
    // Luego eliminar del productos (relación principal)
    $stmt2 = $pdo->prepare("DELETE FROM productos WHERE id = ?");
    $result = $stmt2->execute([$id]);
    
    // Confirmar la transacción
    $pdo->commit();
    
    // Redirigir después de éxito
    header("Location: index.php?success=1");
    exit();
    
} catch (Exception $e) {
    // Revertir en caso de error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Redirigir con mensaje de error
    header("Location: index.php?error=1");
    exit();
}
