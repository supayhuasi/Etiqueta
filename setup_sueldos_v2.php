<?php
require 'config.php';

try {
    // Tabla de plantillas de conceptos
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plantillas_conceptos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(255) NOT NULL UNIQUE,
            descripcion TEXT,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    // Tabla de conceptos en plantilla (relación many-to-many)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS plantilla_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            plantilla_id INT NOT NULL,
            concepto_id INT NOT NULL,
            formula VARCHAR(255),
            valor_fijo DECIMAL(10, 2),
            es_porcentaje TINYINT DEFAULT 0,
            orden INT,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (plantilla_id) REFERENCES plantillas_conceptos(id) ON DELETE CASCADE,
            FOREIGN KEY (concepto_id) REFERENCES conceptos(id) ON DELETE RESTRICT,
            UNIQUE KEY unique_plantilla_concepto (plantilla_id, concepto_id)
        )
    ");

    // Actualizar tabla sueldo_conceptos para incluir mes y fórmula
    $columns = $pdo->query("SHOW COLUMNS FROM sueldo_conceptos")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('mes', $columns)) {
        $pdo->exec("ALTER TABLE sueldo_conceptos ADD COLUMN mes VARCHAR(7)");
    }
    
    if (!in_array('formula', $columns)) {
        $pdo->exec("ALTER TABLE sueldo_conceptos ADD COLUMN formula VARCHAR(255)");
    }
    
    if (!in_array('es_porcentaje', $columns)) {
        $pdo->exec("ALTER TABLE sueldo_conceptos ADD COLUMN es_porcentaje TINYINT DEFAULT 0");
    }
    
    if (!in_array('fecha_actualizacion', $columns)) {
        $pdo->exec("ALTER TABLE sueldo_conceptos ADD COLUMN fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP");
    }

    // Tabla para asignar plantilla a empleado
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS empleado_plantilla (
            id INT PRIMARY KEY AUTO_INCREMENT,
            empleado_id INT NOT NULL,
            plantilla_id INT NOT NULL,
            fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
            FOREIGN KEY (plantilla_id) REFERENCES plantillas_conceptos(id) ON DELETE CASCADE,
            UNIQUE KEY unique_emp_plantilla (empleado_id, plantilla_id)
        )
    ");

    echo "✓ Tablas creadas/actualizadas exitosamente<br>";
    echo "✓ Soporte para fórmulas agregado<br>";
    echo "✓ Sistema de plantillas configurado<br>";
    echo "<br><a href='sueldos.php'>← Ir a Sueldos</a>";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "✓ Las tablas ya estaban configuradas<br>";
        echo "<a href='sueldos.php'>← Ir a Sueldos</a>";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?>
