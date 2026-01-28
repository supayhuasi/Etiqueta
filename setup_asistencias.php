<?php
/**
 * Setup para Módulo de Asistencias
 * Crea las tablas necesarias para el control de asistencias de empleados
 */

require 'config.php';

try {
    // 1. Tabla de Horarios de Empleados (mantener para compatibilidad)
    $sql = "
    CREATE TABLE IF NOT EXISTS empleados_horarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        hora_entrada TIME NOT NULL,
        hora_salida TIME NOT NULL,
        tolerancia_minutos INT DEFAULT 10,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        UNIQUE KEY unique_empleado_activo (empleado_id, activo)
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla empleados_horarios creada<br>";

    // 1b. Tabla de Horarios por Día de la Semana
    $sql = "
    CREATE TABLE IF NOT EXISTS empleados_horarios_dias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        dia_semana TINYINT NOT NULL COMMENT '0=Domingo, 1=Lunes, ..., 6=Sábado',
        hora_entrada TIME NOT NULL,
        hora_salida TIME NOT NULL,
        tolerancia_minutos INT DEFAULT 10,
        activo TINYINT DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        UNIQUE KEY unique_empleado_dia (empleado_id, dia_semana),
        INDEX idx_empleado (empleado_id)
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla empleados_horarios_dias creada<br>";

    // 2. Tabla de Asistencias
    $sql = "
    CREATE TABLE IF NOT EXISTS asistencias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        fecha DATE NOT NULL,
        hora_entrada TIME,
        hora_salida TIME,
        observaciones TEXT,
        estado ENUM('presente', 'tarde', 'ausente', 'justificado') DEFAULT 'presente',
        creado_por INT,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
        UNIQUE KEY unique_empleado_fecha (empleado_id, fecha)
    )
    ";
    $pdo->exec($sql);
    echo "✓ Tabla asistencias creada<br>";

    echo "<div class='alert alert-success mt-3'>✅ Todas las tablas de asistencias han sido creadas exitosamente</div>";
    echo "<p><a href='asistencias.php' class='btn btn-primary'>Ir a Asistencias</a></p>";

} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
