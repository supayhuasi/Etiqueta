<?php

// Tabla para registrar pagos de sueldos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS pagos_sueldos (
        id INT PRIMARY KEY AUTO_INCREMENT,
        empleado_id INT NOT NULL,
        mes_pago VARCHAR(7) NOT NULL,
        sueldo_total DECIMAL(10, 2) NOT NULL,
        monto_pagado DECIMAL(10, 2) NOT NULL,
        fecha_pago DATETIME,
        usuario_registra INT NOT NULL,
        observaciones TEXT,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
        FOREIGN KEY (usuario_registra) REFERENCES usuarios(id),
        UNIQUE KEY unique_emp_mes (empleado_id, mes_pago)
    )
");

echo "✓ Tabla de pagos creada correctamente";
?>
