<?php

// Tabla para gestionar cheques emitidos
$pdo->exec("
    CREATE TABLE IF NOT EXISTS cheques (
        id INT PRIMARY KEY AUTO_INCREMENT,
        numero_cheque VARCHAR(50) NOT NULL UNIQUE,
        monto DECIMAL(10, 2) NOT NULL,
        fecha_emision DATE NOT NULL,
        mes_emision VARCHAR(7) NOT NULL,
        banco VARCHAR(100) NOT NULL,
        beneficiario VARCHAR(255) NOT NULL,
        estado ENUM('pendiente', 'pagado', 'rechazado', 'aceptado') DEFAULT 'pendiente',
        pagado TINYINT DEFAULT 0,
        fecha_pago DATE,
        observaciones TEXT,
        usuario_registra INT NOT NULL,
        fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_registra) REFERENCES usuarios(id),
        INDEX idx_mes_emision (mes_emision),
        INDEX idx_estado (estado),
        INDEX idx_fecha_pago (fecha_pago)
    )
");

echo "✓ Tabla de cheques creada correctamente";
?>
