<?php
require 'config.php';

// Obtener el ID del usuario admin (generalmente el primero)
$stmt = $pdo->query("SELECT id, usuario FROM usuarios LIMIT 1");
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    // Asignar rol admin (id=1) al primer usuario
    $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = 1 WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    echo "<div class='container mt-4'>";
    echo "<div class='alert alert-success'>";
    echo "<h4>✓ Configuración completada</h4>";
    echo "<p>Usuario: <strong>" . htmlspecialchars($user['usuario']) . "</strong></p>";
    echo "<p>Rol asignado: <strong>admin</strong></p>";
    echo "<p>Por favor, <a href='auth/logout.php'>cierra sesión</a> e <a href='auth/login.php'>inicia sesión nuevamente</a></p>";
    echo "</div>";
    echo "</div>";
} else {
    echo "<div class='alert alert-danger'>No hay usuarios en el sistema</div>";
}
?>
