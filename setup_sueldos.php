<?php
require 'config.php';

// Crear tablas si no existen

// Tabla de Empleados
$pdo->exec("
    CREATE TABLE IF NOT EXISTS empleados (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        sueldo_base DECIMAL(10, 2) NOT NULL,
        activo TINYINT DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP
    )
");

// Tabla de Conceptos (Bonificaciones y Descuentos)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS conceptos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(255) NOT NULL UNIQUE,
        tipo ENUM('bonificacion', 'descuento') NOT NULL,
        descripcion TEXT,
        activo TINYINT DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Tabla de Conceptos por Empleado
$pdo->exec("
    CREATE TABLE IF NOT EXISTS sueldo_conceptos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        concepto_id INT NOT NULL,
        monto DECIMAL(10, 2) NOT NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (concepto_id) REFERENCES conceptos(id) ON DELETE RESTRICT,
        UNIQUE KEY unique_emp_concept (empleado_id, concepto_id)
    )
");

// Insertar conceptos por defecto si la tabla está vacía
$stmt = $pdo->query("SELECT COUNT(*) FROM conceptos");
if ($stmt->fetchColumn() == 0) {
    $pdo->exec("
        INSERT INTO conceptos (nombre, tipo, descripcion) VALUES
        ('Bonificación', 'bonificacion', 'Bonificación general'),
        ('Bono Rendimiento', 'bonificacion', 'Bono por rendimiento'),
        ('Ausencias', 'descuento', 'Descuento por inasistencias'),
        ('Aporte Jubilación', 'descuento', 'Aporte jubilatorio'),
        ('Aporte Salud', 'descuento', 'Aporte a salud'),
        ('Embargo', 'descuento', 'Embargo judicial')
    ");
}

echo "✓ Base de datos configurada correctamente";
?>
