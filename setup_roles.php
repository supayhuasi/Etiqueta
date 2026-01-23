<?php
require 'config.php';

try {
    // Crear tabla de roles
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS roles (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(50) NOT NULL UNIQUE,
            descripcion VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Agregar columna rol_id a usuarios si no existe
    $pdo->exec("
        ALTER TABLE usuarios 
        ADD COLUMN rol_id INT DEFAULT 2 AFTER password,
        ADD CONSTRAINT fk_usuarios_roles 
        FOREIGN KEY (rol_id) REFERENCES roles(id)
    ");

    // Insertar roles por defecto
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("
            INSERT INTO roles (nombre, descripcion) VALUES
            (1, 'admin', 'Administrador del sistema'),
            (2, 'usuario', 'Usuario regular'),
            (3, 'operario', 'Operario de producción')
        ");
    }

    echo "✓ Tabla de roles creada exitosamente<br>";
    echo "✓ Columna rol_id agregada a usuarios<br>";
    echo "✓ Roles por defecto insertados<br>";
    echo "<br><a href='index.php'>← Volver al inicio</a>";

} catch (PDOException $e) {
    // Si la columna ya existe, ignora el error
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "✓ La tabla ya estaba configurada correctamente<br>";
        echo "<a href='index.php'>← Volver al inicio</a>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
