<?php
/**
 * Script de diagnóstico para el problema de creación de tabla cuentas
 * Intenta ejecutar cada paso de forma individual para identificar dónde falla
 */

require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Permitir acceso directo solo en desarrollo o con autenticación
if (!isset($_SESSION['user']) && !isset($_GET['force'])) {
    die("Acceso denegado. Agregá ?force=1 si sos admin.");
}

require 'includes/header.php';

$diagnostico = [];

// Helper de utilidad
function admin_table_exists_local(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function admin_column_exists_local(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

// 1. Verificar usuario MySQL y permisos básicos
$diagnostico['usuario_mysql'] = [
    'nombre' => 'Usuario MySQL conectado',
    'ok' => false,
    'valor' => '?'
];
try {
    $stmt = $pdo->query("SELECT USER()");
    $user = $stmt->fetchColumn();
    $diagnostico['usuario_mysql']['valor'] = $user;
    $diagnostico['usuario_mysql']['ok'] = true;
} catch (Throwable $e) {
    $diagnostico['usuario_mysql']['error'] = $e->getMessage();
}

// 2. Verificar si tabla cuentas existe
$diagnostico['tabla_cuentas'] = [
    'nombre' => 'Tabla cuentas existe',
    'ok' => admin_table_exists_local($pdo, 'cuentas'),
    'valor' => admin_table_exists_local($pdo, 'cuentas') ? 'SÍ' : 'NO'
];

// 3. Intentar crear tabla cuentas
$diagnostico['crear_tabla'] = [
    'nombre' => 'Intento de CREATE TABLE cuentas',
    'ok' => false,
    'valor' => '-'
];
if (!admin_table_exists_local($pdo, 'cuentas')) {
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
        $diagnostico['crear_tabla']['ok'] = true;
        $diagnostico['crear_tabla']['valor'] = 'Éxito';
    } catch (Throwable $e) {
        $diagnostico['crear_tabla']['ok'] = false;
        $diagnostico['crear_tabla']['error'] = $e->getMessage();
        $diagnostico['crear_tabla']['valor'] = 'Error: ' . substr($e->getMessage(), 0, 100);
    }
} else {
    $diagnostico['crear_tabla']['ok'] = true;
    $diagnostico['crear_tabla']['valor'] = 'Tabla ya existe';
}

// 4. Verificar columna flujo_caja.cuenta_id
$diagnostico['flujo_caja_existe'] = [
    'nombre' => 'Tabla flujo_caja existe',
    'ok' => admin_table_exists_local($pdo, 'flujo_caja'),
    'valor' => admin_table_exists_local($pdo, 'flujo_caja') ? 'SÍ' : 'NO'
];

$diagnostico['flujo_caja_columna'] = [
    'nombre' => 'Columna flujo_caja.cuenta_id existe',
    'ok' => admin_table_exists_local($pdo, 'flujo_caja') && admin_column_exists_local($pdo, 'flujo_caja', 'cuenta_id'),
    'valor' => (admin_table_exists_local($pdo, 'flujo_caja') && admin_column_exists_local($pdo, 'flujo_caja', 'cuenta_id')) ? 'SÍ' : 'NO'
];

// 5. Intentar agregar columna a flujo_caja
$diagnostico['agregar_flujo_caja_columna'] = [
    'nombre' => 'Intento de ALTER TABLE flujo_caja ADD COLUMN cuenta_id',
    'ok' => false,
    'valor' => '-'
];
if (admin_table_exists_local($pdo, 'flujo_caja') && !admin_column_exists_local($pdo, 'flujo_caja', 'cuenta_id')) {
    try {
        $pdo->exec("ALTER TABLE flujo_caja ADD COLUMN cuenta_id INT NULL");
        $diagnostico['agregar_flujo_caja_columna']['ok'] = true;
        $diagnostico['agregar_flujo_caja_columna']['valor'] = 'Éxito';
    } catch (Throwable $e) {
        $diagnostico['agregar_flujo_caja_columna']['ok'] = false;
        $diagnostico['agregar_flujo_caja_columna']['error'] = $e->getMessage();
        $diagnostico['agregar_flujo_caja_columna']['valor'] = 'Error: ' . substr($e->getMessage(), 0, 100);
    }
} else {
    $diagnostico['agregar_flujo_caja_columna']['valor'] = 'Ya existe o tabla no encontrada';
}

// 6. Verificar columna gastos.cuenta_id
$diagnostico['gastos_existe'] = [
    'nombre' => 'Tabla gastos existe',
    'ok' => admin_table_exists_local($pdo, 'gastos'),
    'valor' => admin_table_exists_local($pdo, 'gastos') ? 'SÍ' : 'NO'
];

$diagnostico['gastos_columna'] = [
    'nombre' => 'Columna gastos.cuenta_id existe',
    'ok' => admin_table_exists_local($pdo, 'gastos') && admin_column_exists_local($pdo, 'gastos', 'cuenta_id'),
    'valor' => (admin_table_exists_local($pdo, 'gastos') && admin_column_exists_local($pdo, 'gastos', 'cuenta_id')) ? 'SÍ' : 'NO'
];

// 7. Intentar agregar columna a gastos
$diagnostico['agregar_gastos_columna'] = [
    'nombre' => 'Intento de ALTER TABLE gastos ADD COLUMN cuenta_id',
    'ok' => false,
    'valor' => '-'
];
if (admin_table_exists_local($pdo, 'gastos') && !admin_column_exists_local($pdo, 'gastos', 'cuenta_id')) {
    try {
        $pdo->exec("ALTER TABLE gastos ADD COLUMN cuenta_id INT NULL");
        $diagnostico['agregar_gastos_columna']['ok'] = true;
        $diagnostico['agregar_gastos_columna']['valor'] = 'Éxito';
    } catch (Throwable $e) {
        $diagnostico['agregar_gastos_columna']['ok'] = false;
        $diagnostico['agregar_gastos_columna']['error'] = $e->getMessage();
        $diagnostico['agregar_gastos_columna']['valor'] = 'Error: ' . substr($e->getMessage(), 0, 100);
    }
} else {
    $diagnostico['agregar_gastos_columna']['valor'] = 'Ya existe o tabla no encontrada';
}

// 8. Verificar cuenta por defecto
$diagnostico['cuenta_defecto'] = [
    'nombre' => 'Cuenta "Caja / Operativa" existe',
    'ok' => false,
    'valor' => 'NO'
];
if (admin_table_exists_local($pdo, 'cuentas')) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if ($id) {
            $diagnostico['cuenta_defecto']['ok'] = true;
            $diagnostico['cuenta_defecto']['valor'] = "SÍ (id: $id)";
        }
    } catch (Throwable $e) {
        $diagnostico['cuenta_defecto']['error'] = $e->getMessage();
    }
}

// 9. Intentar crear cuenta por defecto
$diagnostico['crear_cuenta_defecto'] = [
    'nombre' => 'Intento de INSERT "Caja / Operativa"',
    'ok' => false,
    'valor' => '-'
];
if (admin_table_exists_local($pdo, 'cuentas')) {
    try {
        $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = 'Caja / Operativa' LIMIT 1");
        $stmt->execute();
        $id = $stmt->fetchColumn();
        if (!$id) {
            $pdo->prepare("
                INSERT INTO cuentas (nombre, tipo, descripcion, activo)
                VALUES ('Caja / Operativa', 'Operativa', 'Cuenta por defecto para movimientos históricos y sin cuenta asignada', 1)
            ")->execute();
            $newId = $pdo->lastInsertId();
            $diagnostico['crear_cuenta_defecto']['ok'] = true;
            $diagnostico['crear_cuenta_defecto']['valor'] = "Creada (id: $newId)";
        } else {
            $diagnostico['crear_cuenta_defecto']['ok'] = true;
            $diagnostico['crear_cuenta_defecto']['valor'] = "Ya existe (id: $id)";
        }
    } catch (Throwable $e) {
        $diagnostico['crear_cuenta_defecto']['ok'] = false;
        $diagnostico['crear_cuenta_defecto']['error'] = $e->getMessage();
        $diagnostico['crear_cuenta_defecto']['valor'] = 'Error: ' . substr($e->getMessage(), 0, 100);
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico Cuentas</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
    <style>
        .ok { background-color: #d4edda; }
        .no-ok { background-color: #f8d7da; }
        .neutral { background-color: #e2e3e5; }
        .paso { margin-bottom: 15px; padding: 10px; border-left: 4px solid #ccc; }
        .paso.ok { border-left-color: #28a745; }
        .paso.no-ok { border-left-color: #dc3545; }
        .paso.neutral { border-left-color: #6c757d; }
    </style>
</head>
<body>
<div class="container mt-5">
    <h1>🔍 Diagnóstico del Sistema de Cuentas</h1>
    
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card mb-3">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">Estado del Sistema</h5>
                </div>
                <div class="card-body">
                    <?php
                    $pasosOk = array_filter($diagnostico, fn($d) => $d['ok'] ?? false);
                    $pasosTotales = count($diagnostico);
                    $porcentaje = round(count($pasosOk) / $pasosTotales * 100);
                    ?>
                    <p><strong>Pasos completados correctamente:</strong> <?= count($pasosOk) ?> / <?= $pasosTotales ?> (<?= $porcentaje ?>%)</p>
                    <div class="progress">
                        <div class="progress-bar" role="progressbar" style="width: <?= $porcentaje ?>%" aria-valuenow="<?= $porcentaje ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-12">
            <?php foreach ($diagnostico as $key => $paso): ?>
                <div class="paso <?= ($paso['ok'] ?? false) ? 'ok' : (isset($paso['error']) ? 'no-ok' : 'neutral') ?>">
                    <div class="row">
                        <div class="col-md-8">
                            <h6><?= htmlspecialchars($paso['nombre']) ?></h6>
                            <p class="mb-0"><strong>Resultado:</strong> <?= htmlspecialchars($paso['valor']) ?></p>
                            <?php if (isset($paso['error'])): ?>
                                <p class="mb-0 text-danger"><small><strong>Error:</strong> <?= htmlspecialchars($paso['error']) ?></small></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4 text-end">
                            <?php if ($paso['ok'] ?? false): ?>
                                <span class="badge bg-success">✓ OK</span>
                            <?php elseif (isset($paso['error'])): ?>
                                <span class="badge bg-danger">✗ ERROR</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">- N/A</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-warning">
                    <h5 class="mb-0">Próximos pasos</h5>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Revisá los errores arriba indicados</li>
                        <li>Si ves error de permisos, contactá al administrador de BD para que le dé permisos al usuario <code><?= $diagnostico['usuario_mysql']['valor'] ?></code></li>
                        <li>Los permisos necesarios son: CREATE TABLE, ALTER TABLE, INSERT, UPDATE en la BD "<?php try { $s = $pdo->query("SELECT DATABASE()"); echo htmlspecialchars($s->fetchColumn()); } catch (Exception $e) { echo '?'; } ?>"</li>
                        <li>Una vez arreglados los permisos, vuelve a ejecutar este diagnóstico o accede a <code>setup_cuentas.php</code></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
