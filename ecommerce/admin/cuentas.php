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
foreach ($cuentas as &$c) {
    $c['saldo'] = cuentas_saldo_total($pdo, (int)$c['id']);
}
unset($c);

$saldo_total_general = array_sum(array_column($cuentas, 'saldo'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuentas</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>🏦 Cuentas</h1>
            <p class="text-muted">Organizá el flujo de caja en distintas cuentas (caja, inversión, producción, etc.). El saldo de cada cuenta se calcula solo, sumando todos sus movimientos históricos.</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="flujo_caja.php" class="btn btn-secondary me-2">← Flujo de Caja</a>
            <a href="cuentas_crear.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Nueva Cuenta
            </a>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="h6 text-muted mb-1">SALDO TOTAL (todas las cuentas activas)</div>
            <div class="h3" style="color: <?= $saldo_total_general >= 0 ? '#28A745' : '#DC3545' ?>">
                $<?= number_format($saldo_total_general, 2, ',', '.') ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($cuentas)): ?>
                <p class="text-muted text-center">No hay cuentas creadas todavía</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th class="text-end">Saldo actual</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cuentas as $c): ?>
                                <tr class="<?= (int)$c['activo'] === 0 ? 'text-muted' : '' ?>">
                                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($c['tipo']) ?></span></td>
                                    <td><?= htmlspecialchars($c['descripcion'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <strong style="color: <?= $c['saldo'] >= 0 ? '#28A745' : '#DC3545' ?>">
                                            $<?= number_format($c['saldo'], 2, ',', '.') ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="cuentas_crear.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <a href="cuentas_eliminar.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-outline-danger">Desactivar</a>
                                        <?php else: ?>
                                            <a href="cuentas_eliminar.php?id=<?= (int)$c['id'] ?>&accion=activar" class="btn btn-sm btn-outline-success">Activar</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
