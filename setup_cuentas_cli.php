<?php
/**
 * Script CLI para crear el sistema de cuentas
 * Ejecutar: php setup_cuentas_cli.php
 */

require './config.php';

echo "═════════════════════════════════════════════════════════════════\n";
echo "  SETUP SISTEMA DE CUENTAS (CLI)\n";
echo "═════════════════════════════════════════════════════════════════\n\n";

// Función helper
function paso($numero, $descripcion, $ok, $detalle = '') {
    $status = $ok ? '✅' : '❌';
    echo "[$numero] $status $descripcion\n";
    if ($detalle) {
        echo "    → $detalle\n";
    }
    echo "\n";
    return $ok;
}

$pasos_ok = 0;
$pasos_total = 0;

// 1. Verificar usuario MySQL
$pasos_total++;
try {
    $stmt = $pdo->query("SELECT USER()");
    $user = $stmt->fetchColumn();
    if (paso(1, "Usuario MySQL", true, "Conectado como: $user")) {
        $pasos_ok++;
    }
} catch (Throwable $e) {
    paso(1, "Usuario MySQL", false, $e->getMessage());
}

// 2. Crear tabla cuentas
$pasos_total++;
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
    if (paso(2, "Crear tabla cuentas", true, "Tabla creada/verificada")) {
        $pasos_ok++;
    }
} catch (Throwable $e) {
    paso(2, "Crear tabla cuentas", false, $e->getMessage());
}

// 3. Agregar columna flujo_caja.cuenta_id
$pasos_total++;
try {
    // Verificar si la tabla existe
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['flujo_caja']);
    if ($stmt->fetchColumn()) {
        // Verificar si la columna existe
        $stmt = $pdo->prepare("SHOW COLUMNS FROM flujo_caja LIKE ?");
        $stmt->execute(['cuenta_id']);
        if (!$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL");
            paso(3, "Agregar flujo_caja.cuenta_id", true, "Columna agregada");
        } else {
            paso(3, "Agregar flujo_caja.cuenta_id", true, "Columna ya existe");
        }
        $pasos_ok++;
    } else {
        paso(3, "Agregar flujo_caja.cuenta_id", false, "Tabla flujo_caja no existe");
    }
} catch (Throwable $e) {
    paso(3, "Agregar flujo_caja.cuenta_id", false, $e->getMessage());
}

// 4. Agregar índice a flujo_caja.cuenta_id
$pasos_total++;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.statistics
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND index_name = 'idx_cuenta_id'
    ");
    $stmt->execute();
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE flujo_caja ADD INDEX idx_cuenta_id (cuenta_id)");
        paso(4, "Crear índice flujo_caja.idx_cuenta_id", true, "Índice creado");
    } else {
        paso(4, "Crear índice flujo_caja.idx_cuenta_id", true, "Índice ya existe");
    }
    $pasos_ok++;
} catch (Throwable $e) {
    paso(4, "Crear índice flujo_caja.idx_cuenta_id", false, $e->getMessage());
}

// 5. Agregar FK a flujo_caja
$pasos_total++;
try {
    $stmtFk = $pdo->prepare("
        SELECT COUNT(*) FROM information_schema.table_constraints
        WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND constraint_name = 'fk_flujo_caja_cuenta'
    ");
    $stmtFk->execute();
    if ((int)$stmtFk->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE flujo_caja ADD CONSTRAINT fk_flujo_caja_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas(id)");
        paso(5, "Crear FK flujo_caja.fk_flujo_caja_cuenta", true, "FK creada");
    } else {
        paso(5, "Crear FK flujo_caja.fk_flujo_caja_cuenta", true, "FK ya existe");
    }
    $pasos_ok++;
} catch (Throwable $e) {
    paso(5, "Crear FK flujo_caja.fk_flujo_caja_cuenta", false, $e->getMessage());
}

// 6. Agregar columna gastos.cuenta_id
$pasos_total++;
try {
    $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
    $stmt->execute(['gastos']);
    if ($stmt->fetchColumn()) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM gastos LIKE ?");
        $stmt->execute(['cuenta_id']);
        if (!$stmt->fetchColumn()) {
            $pdo->exec("ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL");
            paso(6, "Agregar gastos.cuenta_id", true, "Columna agregada");
        } else {
            paso(6, "Agregar gastos.cuenta_id", true, "Columna ya existe");
        }
        $pasos_ok++;
    } else {
        paso(6, "Agregar gastos.cuenta_id", false, "Tabla gastos no existe");
    }
} catch (Throwable $e) {
    paso(6, "Agregar gastos.cuenta_id", false, $e->getMessage());
}

// 7. Crear cuenta por defecto
$pasos_total++;
try {
    $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? LIMIT 1");
    $stmt->execute(['Caja / Operativa']);
    $id = $stmt->fetchColumn();
    
    if (!$id) {
        $pdo->prepare("
            INSERT INTO cuentas (nombre, tipo, descripcion, activo)
            VALUES (?, ?, ?, ?)
        ")->execute([
            'Caja / Operativa',
            'Operativa',
            'Cuenta por defecto para movimientos históricos y sin cuenta asignada',
            1
        ]);
        $newId = $pdo->lastInsertId();
        paso(7, "Crear cuenta por defecto", true, "Creada (id: $newId)");
    } else {
        paso(7, "Crear cuenta por defecto", true, "Ya existe (id: $id)");
    }
    $pasos_ok++;
} catch (Throwable $e) {
    paso(7, "Crear cuenta por defecto", false, $e->getMessage());
}

// 8. Backfill flujo_caja
$pasos_total++;
try {
    $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? LIMIT 1");
    $stmt->execute(['Caja / Operativa']);
    $defaultId = $stmt->fetchColumn();
    
    if ($defaultId) {
        $stmt = $pdo->prepare("UPDATE flujo_caja SET cuenta_id = ? WHERE cuenta_id IS NULL");
        $stmt->execute([$defaultId]);
        $affected = $stmt->rowCount();
        paso(8, "Backfill flujo_caja.cuenta_id", true, "Registros actualizados: $affected");
    } else {
        paso(8, "Backfill flujo_caja.cuenta_id", false, "Cuenta por defecto no encontrada");
    }
    $pasos_ok++;
} catch (Throwable $e) {
    paso(8, "Backfill flujo_caja.cuenta_id", false, $e->getMessage());
}

// 9. Backfill gastos
$pasos_total++;
try {
    $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? LIMIT 1");
    $stmt->execute(['Caja / Operativa']);
    $defaultId = $stmt->fetchColumn();
    
    if ($defaultId) {
        $stmt = $pdo->prepare("UPDATE gastos SET cuenta_id = ? WHERE cuenta_id IS NULL");
        $stmt->execute([$defaultId]);
        $affected = $stmt->rowCount();
        paso(9, "Backfill gastos.cuenta_id", true, "Registros actualizados: $affected");
    } else {
        paso(9, "Backfill gastos.cuenta_id", false, "Cuenta por defecto no encontrada");
    }
    $pasos_ok++;
} catch (Throwable $e) {
    paso(9, "Backfill gastos.cuenta_id", false, $e->getMessage());
}

// Resumen final
echo "═════════════════════════════════════════════════════════════════\n";
echo "  RESUMEN FINAL\n";
echo "═════════════════════════════════════════════════════════════════\n\n";

$porcentaje = round($pasos_ok / $pasos_total * 100);
echo "Pasos completados: $pasos_ok / $pasos_total ($porcentaje%)\n\n";

if ($pasos_ok === $pasos_total) {
    echo "✅ SETUP COMPLETADO EXITOSAMENTE\n\n";
    echo "El sistema de cuentas está listo para usar:\n";
    echo "  • Tabla cuentas creada\n";
    echo "  • Columnas agregadas a flujo_caja y gastos\n";
    echo "  • Cuenta por defecto creada\n";
    echo "  • Datos históricos vinculados\n\n";
} else {
    echo "⚠️  SETUP COMPLETADO CON ERRORES\n\n";
    echo "Se completaron $pasos_ok de $pasos_total pasos.\n";
    echo "Revisa los errores arriba para identificar qué falló.\n\n";
}

echo "═════════════════════════════════════════════════════════════════\n";
?>
