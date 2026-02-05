<?php
require '../../config.php';

// Tabla de Flujo de Caja
$pdo->exec("
    CREATE TABLE IF NOT EXISTS flujo_caja (
        id INT PRIMARY KEY AUTO_INCREMENT,
        fecha DATE NOT NULL,
        tipo ENUM('ingreso', 'egreso') NOT NULL,
        categoria VARCHAR(100) NOT NULL,
        descripcion TEXT,
        monto DECIMAL(10, 2) NOT NULL,
        referencia VARCHAR(255),
        id_referencia INT,
        usuario_id INT,
        observaciones TEXT,
        comprobante VARCHAR(255),
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
        INDEX idx_fecha (fecha),
        INDEX idx_tipo (tipo),
        INDEX idx_categoria (categoria),
        INDEX idx_referencia (id_referencia)
    )
");

// Tabla para pagos parciales de sueldos (reemplaza la anterior)
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pagos_sueldos_parciales (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        mes_pago VARCHAR(7) NOT NULL,
        sueldo_total DECIMAL(10, 2) NOT NULL,
        sueldo_pendiente DECIMAL(10, 2) NOT NULL,
        monto_pagado DECIMAL(10, 2) NOT NULL,
        fecha_pago DATE NOT NULL,
        usuario_registra INT NOT NULL,
        observaciones TEXT,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_registra) REFERENCES usuarios(id),
        INDEX idx_empleado (empleado_id),
        INDEX idx_mes (mes_pago),
        INDEX idx_fecha (fecha_pago)
    )
");

// Tabla de resumen mensual
$pdo->exec("
    CREATE TABLE IF NOT EXISTS flujo_caja_resumen (
        id INT PRIMARY KEY AUTO_INCREMENT,
        año_mes VARCHAR(7) NOT NULL,
        total_ingresos DECIMAL(10, 2) DEFAULT 0,
        total_egresos DECIMAL(10, 2) DEFAULT 0,
        saldo DECIMAL(10, 2) DEFAULT 0,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_mes (año_mes)
    )
");

echo "✓ Tablas de flujo de caja creadas correctamente";
?>
