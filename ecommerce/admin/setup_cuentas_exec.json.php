<?php
/**
 * API JSON para ejecutar el setup de cuentas
 */

require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad
$es_admin = isset($_SESSION['user']) && ($_SESSION['rol'] === 'admin' || $_SESSION['user'] === 'admin');
$token_valido = (isset($_GET['token']) && $_GET['token'] === md5('cuentas_setup_' . date('Y-m-d')));

if (!$es_admin && !$token_valido) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$pasos = [];
$pasos_ok = 0;

function agregar_paso($numero, $descripcion, $ok, $detalle = '') {
    global $pasos, $pasos_ok;
    $pasos[] = [
        'numero' => $numero,
        'descripcion' => $descripcion,
        'ok' => (bool)$ok,
        'detalle' => $detalle
    ];
    if ($ok) $pasos_ok++;
}

try {
    // 1. Verificar usuario MySQL
    try {
        $stmt = $pdo->query("SELECT USER()");
        $user = $stmt->fetchColumn();
        agregar_paso(1, "Usuario MySQL", true, "Conectado como: $user");
    } catch (Throwable $e) {
        agregar_paso(1, "Usuario MySQL", false, substr($e->getMessage(), 0, 100));
    }

    // 2. Crear tabla cuentas
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
        agregar_paso(2, "Crear tabla cuentas", true, "Tabla creada/verificada");
    } catch (Throwable $e) {
        agregar_paso(2, "Crear tabla cuentas", false, substr($e->getMessage(), 0, 100));
    }

    // 3. Agregar columna flujo_caja.cuenta_id
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(['flujo_caja']);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `flujo_caja` LIKE ?");
            $stmt->execute(['cuenta_id']);
            if (!$stmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `flujo_caja` ADD COLUMN `cuenta_id` INT NULL");
                agregar_paso(3, "Agregar flujo_caja.cuenta_id", true, "Columna agregada");
            } else {
                agregar_paso(3, "Agregar flujo_caja.cuenta_id", true, "Columna ya existe");
            }
        } else {
            agregar_paso(3, "Agregar flujo_caja.cuenta_id", false, "Tabla flujo_caja no existe");
        }
    } catch (Throwable $e) {
        agregar_paso(3, "Agregar flujo_caja.cuenta_id", false, substr($e->getMessage(), 0, 100));
    }

    // 4. Agregar índice flujo_caja
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.statistics
            WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND index_name = 'idx_cuenta_id'
        ");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `flujo_caja` ADD INDEX `idx_cuenta_id` (`cuenta_id`)");
            agregar_paso(4, "Crear índice flujo_caja", true, "Índice creado");
        } else {
            agregar_paso(4, "Crear índice flujo_caja", true, "Índice ya existe");
        }
    } catch (Throwable $e) {
        agregar_paso(4, "Crear índice flujo_caja", false, substr($e->getMessage(), 0, 100));
    }

    // 5. Crear FK flujo_caja
    try {
        $stmtFk = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.table_constraints
            WHERE table_schema = DATABASE() AND table_name = 'flujo_caja' AND constraint_name = 'fk_flujo_caja_cuenta'
        ");
        $stmtFk->execute();
        if ((int)$stmtFk->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE `flujo_caja` ADD CONSTRAINT `fk_flujo_caja_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas`(`id`)");
            agregar_paso(5, "Crear FK flujo_caja", true, "FK creada");
        } else {
            agregar_paso(5, "Crear FK flujo_caja", true, "FK ya existe");
        }
    } catch (Throwable $e) {
        agregar_paso(5, "Crear FK flujo_caja", false, substr($e->getMessage(), 0, 100));
    }

    // 6. Agregar columna gastos.cuenta_id
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute(['gastos']);
        if ($stmt->fetchColumn()) {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `gastos` LIKE ?");
            $stmt->execute(['cuenta_id']);
            if (!$stmt->fetchColumn()) {
                $pdo->exec("ALTER TABLE `gastos` ADD COLUMN `cuenta_id` INT NULL");
                agregar_paso(6, "Agregar gastos.cuenta_id", true, "Columna agregada");
            } else {
                agregar_paso(6, "Agregar gastos.cuenta_id", true, "Columna ya existe");
            }
        } else {
            agregar_paso(6, "Agregar gastos.cuenta_id", false, "Tabla gastos no existe");
        }
    } catch (Throwable $e) {
        agregar_paso(6, "Agregar gastos.cuenta_id", false, substr($e->getMessage(), 0, 100));
    }

    // 7. Crear cuenta por defecto
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
            agregar_paso(7, "Crear cuenta por defecto", true, "Creada (id: $newId)");
        } else {
            agregar_paso(7, "Crear cuenta por defecto", true, "Ya existe (id: $id)");
        }
    } catch (Throwable $e) {
        agregar_paso(7, "Crear cuenta por defecto", false, substr($e->getMessage(), 0, 100));
    }

    // 8. Backfill flujo_caja
    try {
        $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? LIMIT 1");
        $stmt->execute(['Caja / Operativa']);
        $defaultId = $stmt->fetchColumn();
        
        if ($defaultId) {
            $stmt = $pdo->prepare("UPDATE flujo_caja SET cuenta_id = ? WHERE cuenta_id IS NULL");
            $stmt->execute([$defaultId]);
            $affected = $stmt->rowCount();
            agregar_paso(8, "Backfill flujo_caja", true, "Actualizados: $affected registros");
        } else {
            agregar_paso(8, "Backfill flujo_caja", false, "Cuenta por defecto no encontrada");
        }
    } catch (Throwable $e) {
        agregar_paso(8, "Backfill flujo_caja", false, substr($e->getMessage(), 0, 100));
    }

    // 9. Backfill gastos
    try {
        $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? LIMIT 1");
        $stmt->execute(['Caja / Operativa']);
        $defaultId = $stmt->fetchColumn();
        
        if ($defaultId) {
            $stmt = $pdo->prepare("UPDATE gastos SET cuenta_id = ? WHERE cuenta_id IS NULL");
            $stmt->execute([$defaultId]);
            $affected = $stmt->rowCount();
            agregar_paso(9, "Backfill gastos", true, "Actualizados: $affected registros");
        } else {
            agregar_paso(9, "Backfill gastos", false, "Cuenta por defecto no encontrada");
        }
    } catch (Throwable $e) {
        agregar_paso(9, "Backfill gastos", false, substr($e->getMessage(), 0, 100));
    }

} catch (Throwable $e) {
    agregar_paso(0, "Error crítico", false, $e->getMessage());
}

$pasos_total = count($pasos);
$porcentaje = $pasos_total > 0 ? round($pasos_ok / $pasos_total * 100) : 0;

echo json_encode([
    'pasos' => $pasos,
    'pasos_ok' => $pasos_ok,
    'pasos_total' => $pasos_total,
    'porcentaje' => $porcentaje,
    'completado' => $pasos_ok === $pasos_total
], JSON_UNESCAPED_UNICODE);
?>
