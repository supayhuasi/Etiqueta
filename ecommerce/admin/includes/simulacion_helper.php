<?php

if (!function_exists('ensureSimulacionSchema')) {
    function ensureSimulacionSchema(PDO $pdo): void
    {
        static $ensured = false;
        if ($ensured) {
            return;
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS dashboard_gastos_simulados (
            id INT AUTO_INCREMENT PRIMARY KEY,
            descripcion VARCHAR(255) NOT NULL,
            monto DECIMAL(12,2) NOT NULL,
            fecha DATE NOT NULL,
            recurrente_mensual TINYINT(1) NOT NULL DEFAULT 0,
            recurrente_hasta DATE NULL,
            usuario_id INT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_fecha (fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $ensured = true;
    }
}
