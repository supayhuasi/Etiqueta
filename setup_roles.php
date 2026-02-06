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

    // Insertar roles por defecto si no existen (idempotente)
    $roles_default = [
        ['admin', 'Administrador del sistema'],
        ['usuario', 'Usuario regular'],
        ['operario', 'Operario de producción'],
        ['ventas', 'Usuario de ventas']
    ];
    $stmt = $pdo->prepare("INSERT IGNORE INTO roles (nombre, descripcion) VALUES (?, ?)");
    foreach ($roles_default as $role) {
        $stmt->execute($role);
    }
    echo "✓ Roles creados/actualizados<br>";

    // Intentar agregar columna rol_id a usuarios si no existe
    $stmt = $pdo->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol_id'");
    if ($stmt->rowCount() == 0) {
        // Si la columna no existe, agregarla
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN rol_id INT");
        echo "✓ Columna rol_id agregada<br>";
    }

    // Asignar rol por defecto (usuario) a los usuarios sin rol
    $pdo->exec("UPDATE usuarios SET rol_id = 2 WHERE rol_id IS NULL");
    echo "✓ Roles por defecto asignados<br>";

    // Agregar la foreign key si no existe
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol_id' AND REFERENCED_TABLE_NAME = 'roles'
    ");
    
    if ($stmt->rowCount() == 0) {
        $pdo->exec("
            ALTER TABLE usuarios 
            ADD CONSTRAINT fk_usuarios_roles 
            FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE SET NULL
        ");
        echo "✓ Foreign key creada<br>";
    }

    echo "<br>✓ Configuración completada exitosamente<br>";
    echo "<a href='index.php'>← Volver al inicio</a>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
