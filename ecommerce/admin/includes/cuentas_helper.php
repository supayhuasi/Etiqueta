<?php

// admin_table_exists/admin_column_exists normalmente ya están definidas por includes/header.php,
// pero algunos endpoints (ej. gastos_api.php) incluyen este helper sin pasar por header.php primero.
if (!function_exists('admin_table_exists')) {
    function admin_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_column_exists')) {
    function admin_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ensureCuentasSchema')) {
    function ensureCuentasSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS cuentas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nombre VARCHAR(150) NOT NULL,
                tipo VARCHAR(50) NOT NULL DEFAULT 'Operativa',
                descripcion TEXT NULL,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                orden_visual INT NOT NULL DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_nombre (nombre),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            if (admin_table_exists($pdo, 'flujo_caja') && !admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $pdo->exec("ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL AFTER id_referencia");
                $pdo->exec("ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id)");
            }

            if (admin_table_exists($pdo, 'gastos') && !admin_column_exists($pdo, 'gastos', 'cuenta_id')) {
                $pdo->exec("ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL");
            }

            // Cuenta por defecto para movimientos históricos / sin cuenta asignada
            $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
            $stmt->execute();
            $defaultId = $stmt->fetchColumn();
            if (!$defaultId) {
                $pdo->prepare("
                    INSERT INTO cuentas (nombre, tipo, descripcion, activo)
                    VALUES ('Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1)
                ")->execute();
                $defaultId = $pdo->lastInsertId();
            }

            if (admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $stmt = $pdo->prepare("UPDATE flujo_caja SET cuenta_id = ? WHERE cuenta_id IS NULL");
                $stmt->execute([$defaultId]);
            }

            // FK: se agrega solo después de garantizar que no hay cuenta_id huérfano (backfill de arriba)
            if (admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id')) {
                $stmtFk = $pdo->prepare("
                    SELECT COUNT(*) FROM information_schema.table_constraints
                    WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND constraint_name = 'fk_flujo_caja_cuenta'
                ");
                $stmtFk->execute();
                if ((int)$stmtFk->fetchColumn() === 0) {
                    try {
                        $pdo->exec("ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)");
                    } catch (Throwable $e) {
                        // No bloquear la carga de la página si la FK no se puede agregar todavía (p.ej. datos huérfanos residuales)
                        error_log('cuentas_helper: no se pudo agregar la FK fk_flujo_caja_cuenta: ' . $e->getMessage());
                    }
                }
            }
        } catch (Throwable $e) {
            // No bloquear la carga de la página si falla algún paso de la migración defensiva
            error_log('cuentas_helper: ensureCuentasSchema falló: ' . $e->getMessage());
        }
    }
}

if (!function_exists('cuentas_get_default_id')) {
    function cuentas_get_default_id(PDO $pdo): int
    {
        try {
            $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
            $stmt->execute();
            $id = $stmt->fetchColumn();
            if ($id) {
                return (int)$id;
            }
            ensureCuentasSchema($pdo);
            $stmt->execute();
            return (int)($stmt->fetchColumn() ?: 0);
        } catch (Throwable $e) {
            error_log('cuentas_helper: cuentas_get_default_id falló: ' . $e->getMessage());
            return 0;
        }
    }
}

if (!function_exists('cuentas_listar')) {
    function cuentas_listar(PDO $pdo, bool $soloActivas = true): array
    {
        try {
            $sql = "SELECT * FROM cuentas";
            if ($soloActivas) {
                $sql .= " WHERE activo = 1";
            }
            $sql .= " ORDER BY orden_visual ASC, nombre ASC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('cuentas_helper: cuentas_listar falló: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('cuentas_get')) {
    function cuentas_get(PDO $pdo, int $id): ?array
    {
        try {
            $stmt = $pdo->prepare("SELECT * FROM cuentas WHERE id = ?");
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('cuentas_saldo_movimientos_sql')) {
    // Fórmula compartida: replica el criterio de flujo_caja.php donde 'Pago de Sueldo'
    // siempre se contabiliza como egreso, aunque el tipo guardado sea 'ingreso'.
    function cuentas_saldo_movimientos_sql(): string
    {
        return "SUM(
            CASE
                WHEN tipo = 'ingreso' AND categoria <> 'Pago de Sueldo' THEN monto
                WHEN tipo = 'egreso' OR categoria = 'Pago de Sueldo' THEN -monto
                ELSE 0
            END
        )";
    }
}

if (!function_exists('cuentas_saldo_inicial_mes')) {
    function cuentas_saldo_inicial_mes(PDO $pdo, int $cuentaId, string $mesYm): float
    {
        try {
            $primerDia = $mesYm . '-01';
            $sql = "SELECT COALESCE(" . cuentas_saldo_movimientos_sql() . ", 0)
                    FROM flujo_caja WHERE cuenta_id = ? AND fecha < ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cuentaId, $primerDia]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('cuentas_saldo_total')) {
    function cuentas_saldo_total(PDO $pdo, int $cuentaId): float
    {
        try {
            $sql = "SELECT COALESCE(" . cuentas_saldo_movimientos_sql() . ", 0)
                    FROM flujo_caja WHERE cuenta_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$cuentaId]);
            return (float)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0.0;
        }
    }
}

if (!function_exists('cuentas_saldo_periodo')) {
    function cuentas_saldo_periodo(PDO $pdo, int $cuentaId, string $fechaInicio, string $fechaFin): array
    {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    COALESCE(SUM(CASE WHEN tipo = 'ingreso' AND categoria <> 'Pago de Sueldo' THEN monto ELSE 0 END), 0) AS ingresos,
                    COALESCE(SUM(CASE WHEN tipo = 'egreso' OR categoria = 'Pago de Sueldo' THEN monto ELSE 0 END), 0) AS egresos
                FROM flujo_caja
                WHERE cuenta_id = ? AND fecha BETWEEN ? AND ?
            ");
            $stmt->execute([$cuentaId, $fechaInicio, $fechaFin]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['ingresos' => 0, 'egresos' => 0];
            $ingresos = (float)$row['ingresos'];
            $egresos = (float)$row['egresos'];
            return [
                'ingresos' => $ingresos,
                'egresos' => $egresos,
                'saldo' => $ingresos - $egresos,
            ];
        } catch (Throwable $e) {
            return ['ingresos' => 0.0, 'egresos' => 0.0, 'saldo' => 0.0];
        }
    }
}
