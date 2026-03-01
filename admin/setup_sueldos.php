<?php
// Forzar visualización de errores para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
echo '<pre style="color:blue">DEBUG: Inicio de script</pre>';

define('SECURITY_CHECK', true);
echo '<pre style="color:blue">DEBUG: Antes de require config.php</pre>';
require_once __DIR__ . '/../config.php';
echo '<pre style="color:blue">DEBUG: Conexión a base de datos OK</pre>';

try {
    echo '<pre style="color:blue">DEBUG: Entrando a bloque try</pre>';
    // Crear tablas si no existen
    // Tabla de Empleados
    echo '<pre style="color:blue">DEBUG: Antes de crear tabla empleados</pre>';
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empleados (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            documento VARCHAR(20),
            tipo_documento ENUM('DNI', 'CUIT', 'Pasaporte') DEFAULT 'DNI',
            telefono VARCHAR(20),
            direccion VARCHAR(255),
            ciudad VARCHAR(100),
            provincia VARCHAR(100),
            codigo_postal VARCHAR(10),
            puesto VARCHAR(100),
            departamento VARCHAR(100),
            fecha_ingreso DATE,
            sueldo_base DECIMAL(10, 2) NOT NULL,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP
        )
    ");

    echo '<pre style="color:blue">DEBUG: Antes de verificar columnas empleados</pre>';
    $col = $pdo->query("SHOW COLUMNS FROM empleados LIKE 'documento'");
    if ($col->rowCount() === 0) {
        $pdo->exec("ALTER TABLE empleados ADD COLUMN documento VARCHAR(20) AFTER email");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN tipo_documento ENUM('DNI', 'CUIT', 'Pasaporte') DEFAULT 'DNI' AFTER documento");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN telefono VARCHAR(20) AFTER tipo_documento");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN direccion VARCHAR(255) AFTER telefono");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN ciudad VARCHAR(100) AFTER direccion");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN provincia VARCHAR(100) AFTER ciudad");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN codigo_postal VARCHAR(10) AFTER provincia");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN puesto VARCHAR(100) AFTER codigo_postal");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN departamento VARCHAR(100) AFTER puesto");
        $pdo->exec("ALTER TABLE empleados ADD COLUMN fecha_ingreso DATE AFTER departamento");
    }

    echo '<pre style="color:blue">DEBUG: Antes de crear tabla conceptos</pre>';
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

    echo '<pre style="color:blue">DEBUG: Antes de crear tabla sueldo_conceptos</pre>';
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

    echo '<pre style="color:blue">DEBUG: Antes de crear tabla sueldo_base_mensual</pre>';
    $pdo->exec("\
        CREATE TABLE IF NOT EXISTS sueldo_base_mensual (\
            id INT PRIMARY KEY AUTO_INCREMENT,\
            empleado_id INT NOT NULL,\
            mes VARCHAR(7) NOT NULL,\
            sueldo_base DECIMAL(10,2) NOT NULL,\
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,\
            UNIQUE KEY unique_emp_mes (empleado_id, mes),\
            FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE\
        )\
    ");

    echo '<pre style="color:blue">DEBUG: Antes de insertar conceptos por defecto</pre>';
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

    echo '<pre style="color:green">✓ Base de datos configurada correctamente</pre>';
} catch (Throwable $e) {
    // Mostrar error para debug temporalmente
    echo '<pre style="color:red">ERROR: ' . $e->getMessage() . '</pre>';
    echo '<pre style="color:red">TRACE: ' . $e->getTraceAsString() . '</pre>';
    exit;
}
