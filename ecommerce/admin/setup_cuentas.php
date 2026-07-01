<?php
require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require 'includes/header.php';
require_once 'includes/cuentas_helper.php';

$error_manual = null;
try {
    ensureCuentasSchema($pdo);
} catch (Throwable $e) {
    // ensureCuentasSchema ya atrapa sus propios errores internamente; esto es un resguardo extra.
    $error_manual = $e->getMessage();
}

$tabla_cuentas_existe = admin_table_exists($pdo, 'cuentas');
$columna_flujo_caja_existe = admin_table_exists($pdo, 'flujo_caja') && admin_column_exists($pdo, 'flujo_caja', 'cuenta_id');
$columna_gastos_existe = admin_table_exists($pdo, 'gastos') && admin_column_exists($pdo, 'gastos', 'cuenta_id');
$cuentas = $tabla_cuentas_existe ? cuentas_listar($pdo, false) : [];
$todo_ok = $tabla_cuentas_existe && $columna_flujo_caja_existe && !empty($cuentas);
?>

<div class="container my-4">
    <?php if ($error_manual): ?>
        <div class="alert alert-danger">✗ Error al preparar el esquema: <?= htmlspecialchars($error_manual) ?></div>
    <?php elseif ($todo_ok): ?>
        <div class="alert alert-success">✓ Todo en orden: tabla de cuentas y columnas relacionadas creadas correctamente.</div>
    <?php else: ?>
        <div class="alert alert-warning">
            ⚠️ Algo no terminó de crearse. Revisá el detalle abajo y, si persiste, el log de errores de PHP del servidor
            debería tener una línea que empieza con <code>cuentas_helper:</code> explicando el motivo (por ejemplo, permisos
            insuficientes del usuario de la base de datos para crear tablas/columnas).
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header"><h5 class="mb-0">Diagnóstico</h5></div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Tabla <code>cuentas</code>: <?= $tabla_cuentas_existe ? '✅ existe' : '❌ no existe' ?></li>
                <li>Columna <code>flujo_caja.cuenta_id</code>: <?= $columna_flujo_caja_existe ? '✅ existe' : '❌ no existe' ?></li>
                <li>Columna <code>gastos.cuenta_id</code>: <?= $columna_gastos_existe ? '✅ existe' : '❌ no existe' ?></li>
                <li>Cuenta "Caja / Operativa" por defecto: <?= !empty($cuentas) ? '✅ creada' : '❌ no se creó' ?></li>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Cuentas existentes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($cuentas)): ?>
                <p class="text-muted mb-0">No hay cuentas todavía.</p>
            <?php else: ?>
                <ul class="mb-0">
                    <?php foreach ($cuentas as $c): ?>
                        <li><?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['tipo']) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <a href="cuentas.php" class="btn btn-primary mt-3">Ir a Cuentas</a>
</div>

<?php require 'includes/footer.php'; ?>
