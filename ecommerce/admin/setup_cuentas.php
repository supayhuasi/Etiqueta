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
$cuentas = cuentas_listar($pdo, false);
?>

<div class="container my-4">
    <div class="alert alert-success">✓ Tabla de cuentas creada/actualizada correctamente</div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Cuentas existentes</h5>
        </div>
        <div class="card-body">
            <ul class="mb-0">
                <?php foreach ($cuentas as $c): ?>
                    <li><?= htmlspecialchars($c['nombre']) ?> (<?= htmlspecialchars($c['tipo']) ?>)</li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <a href="cuentas.php" class="btn btn-primary mt-3">Ir a Cuentas</a>
</div>

<?php require 'includes/footer.php'; ?>
