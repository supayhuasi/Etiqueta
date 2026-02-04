<?php

// Crear tabla tipos_gastos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS tipos_gastos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(100) NOT NULL UNIQUE,
        descripcion TEXT,
        color VARCHAR(20),
        activo BOOLEAN DEFAULT 1,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Crear tabla estados_gastos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS estados_gastos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        nombre VARCHAR(50) NOT NULL UNIQUE,
        descripcion TEXT,
        color VARCHAR(20),
        activo BOOLEAN DEFAULT 1
    )
");

// Crear tabla gastos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS gastos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        numero_gasto VARCHAR(20) NOT NULL UNIQUE,
        fecha DATE NOT NULL,
        tipo_gasto_id INT,
        estado_gasto_id INT,
        descripcion TEXT,
        monto DECIMAL(10,2) NOT NULL,
        beneficiario VARCHAR(100),
        observaciones TEXT,
        archivo VARCHAR(255),
        usuario_registra INT NOT NULL,
        usuario_aprueba INT,
        fecha_aprobacion DATETIME,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (tipo_gasto_id) REFERENCES tipos_gastos(id),
        FOREIGN KEY (estado_gasto_id) REFERENCES estados_gastos(id),
        FOREIGN KEY (usuario_registra) REFERENCES usuarios(id),
        FOREIGN KEY (usuario_aprueba) REFERENCES usuarios(id),
        INDEX idx_fecha (fecha),
        INDEX idx_estado (estado_gasto_id),
        INDEX idx_tipo (tipo_gasto_id)
    )
");

// Crear tabla historial_gastos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS historial_gastos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        gasto_id INT NOT NULL,
        estado_anterior_id INT,
        estado_nuevo_id INT,
        usuario_id INT NOT NULL,
        observaciones TEXT,
        fecha_cambio DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (gasto_id) REFERENCES gastos(id) ON DELETE CASCADE,
        FOREIGN KEY (estado_anterior_id) REFERENCES estados_gastos(id),
        FOREIGN KEY (estado_nuevo_id) REFERENCES estados_gastos(id),
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        INDEX idx_gasto (gasto_id),
        INDEX idx_fecha (fecha_cambio)
    )
");

// Insertar tipos de gastos predeterminados
$tipos = [
    ['Servicios', 'Servicios profesionales', '#007BFF'],
    ['Insumos', 'Materiales e insumos', '#28A745'],
    ['Transporte', 'Gastos de transporte', '#FFC107'],
    ['Mantenimiento', 'Mantenimiento y reparaciones', '#DC3545'],
    ['Utilidades', 'Servicios de utilidades', '#6F42C1']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO tipos_gastos (nombre, descripcion, color) VALUES (?, ?, ?)");
foreach ($tipos as $tipo) {
    $stmt->execute($tipo);
}

// Insertar estados predeterminados
$estados = [
    ['Pendiente', 'Gasto pendiente de aprobación', '#FFC107'],
    ['Aprobado', 'Gasto aprobado', '#17A2B8'],
    ['Pagado', 'Gasto pagado', '#28A745'],
    ['Cancelado', 'Gasto cancelado', '#6C757D'],
    ['Rechazado', 'Gasto rechazado', '#DC3545']
];

$stmt = $pdo->prepare("INSERT IGNORE INTO estados_gastos (nombre, descripcion, color) VALUES (?, ?, ?)");
foreach ($estados as $estado) {
    $stmt->execute($estado);
}

echo "✓ Tablas de gastos creadas correctamente\n";
echo "✓ Tipos de gastos insertados\n";
echo "✓ Estados de gastos insertados\n";
?>
