<?php
require 'config.php';

try {
    // Actualizar el usuario admin para que tenga rol_id = 1
    $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = 1 WHERE usuario = 'admin'");
    $stmt->execute();
    
    echo "<div style='padding: 20px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "<h3 style='color: #155724;'>✓ Actualización completada</h3>";
    echo "<p style='color: #155724;'>El usuario <strong>admin</strong> ahora tiene rol de <strong>administrador</strong></p>";
    echo "<p style='color: #155724;'><strong>Próximo paso:</strong> Cierra sesión e inicia sesión nuevamente para actualizar la sesión</p>";
    echo "<a href='auth/logout.php' style='display: inline-block; padding: 10px 20px; background: #155724; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px;'>Cerrar Sesión</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3 style='color: #721c24;'>✗ Error</h3>";
    echo "<p style='color: #721c24;'>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>
