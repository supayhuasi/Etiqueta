<?php

// Agregar campo de estado a la tabla cheques
try {
    // Verificar si el campo ya existe
    $stmt = $pdo->query("SHOW COLUMNS FROM cheques LIKE 'estado'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE cheques 
            ADD COLUMN estado ENUM('pendiente', 'pagado', 'rechazado', 'aceptado') DEFAULT 'pendiente' AFTER pagado
        ");
        echo "✓ Campo 'estado' agregado a la tabla cheques<br>";
        
        // Migrar datos existentes
        $pdo->exec("UPDATE cheques SET estado = 'pagado' WHERE pagado = 1");
        $pdo->exec("UPDATE cheques SET estado = 'pendiente' WHERE pagado = 0");
        echo "✓ Datos migrados correctamente<br>";
    } else {
        echo "ℹ️ El campo 'estado' ya existe<br>";
    }
    
    echo "<div class='alert alert-success mt-3'>Actualización completada exitosamente</div>";
    echo "<p><a href='cheques.php' class='btn btn-primary'>Ir a Cheques</a></p>";
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
