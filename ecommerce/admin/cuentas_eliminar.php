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
ensureCuentasSchema($pdo);

$id = intval($_GET['id'] ?? 0);
$accion = ($_GET['accion'] ?? '') === 'activar' ? 'activar' : 'desactivar';

if ($id <= 0) {
    header('Location: cuentas.php');
    exit;
}

$cuenta = cuentas_get($pdo, $id);
if (!$cuenta) {
    header('Location: cuentas.php');
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM flujo_caja WHERE cuenta_id = ?");
$stmt->execute([$id]);
$movimientos = (int)$stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nuevoEstado = $accion === 'activar' ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE cuentas SET activo = ? WHERE id = ?");
    $stmt->execute([$nuevoEstado, $id]);

    header('Location: cuentas.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $accion === 'activar' ? 'Activar' : 'Desactivar' ?> Cuenta</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <div class="card">
        <div class="card-header <?= $accion === 'activar' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
            <h5 class="mb-0">⚠️ Confirmar <?= $accion === 'activar' ? 'Activación' : 'Desactivación' ?></h5>
        </div>
        <div class="card-body">
            <p>Cuenta: <strong><?= htmlspecialchars($cuenta['nombre']) ?></strong> (<?= htmlspecialchars($cuenta['tipo']) ?>)</p>

            <?php if ($accion === 'desactivar'): ?>
                <div class="alert alert-warning">
                    <?php if ($movimientos > 0): ?>
                        Esta cuenta tiene <strong><?= $movimientos ?></strong> movimiento(s) de flujo de caja asociados.
                        Al desactivarla, esos movimientos siguen visibles en los reportes históricos, pero la cuenta
                        no va a poder elegirse en transacciones nuevas.
                    <?php else: ?>
                        Esta cuenta no tiene movimientos asociados todavía.
                    <?php endif; ?>
                    No se borra ningún dato, solo se marca como inactiva.
                </div>
            <?php else: ?>
                <div class="alert alert-info">La cuenta va a volver a estar disponible para elegir en nuevas transacciones.</div>
            <?php endif; ?>

            <form method="POST" class="d-flex gap-2">
                <button type="submit" class="btn <?= $accion === 'activar' ? 'btn-success' : 'btn-danger' ?> btn-lg">
                    Sí, <?= $accion === 'activar' ? 'activar' : 'desactivar' ?> cuenta
                </button>
                <a href="cuentas.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
