<?php
require 'config.php';
require 'includes/header.php';

// Verificar si existe sesión
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}


// Obtener lista de empleados con estado de pago del mes actual
$mes_actual = date('Y-m');

$stmt = $pdo->prepare("
    SELECT e.id, e.nombre, e.email, e.sueldo_base, e.activo, ep.plantilla_id, pc.nombre as plantilla_nombre,
           COALESCE(ps.monto_pagado, 0) as monto_pagado,
           COALESCE(ps.sueldo_total, 0) as sueldo_total,
           COALESCE(ps.id, 0) as pago_id
    FROM empleados e
    LEFT JOIN empleado_plantilla ep ON e.id = ep.empleado_id
    LEFT JOIN plantillas_conceptos pc ON ep.plantilla_id = pc.id
    LEFT JOIN pagos_sueldos ps ON e.id = ps.empleado_id AND ps.mes_pago = ?
    ORDER BY e.nombre ASC
");
$stmt->execute([$mes_actual]);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Gestión de Sueldos</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="empleados_crear.php" class="btn btn-primary">+ Nuevo Empleado</a>
            <a href="plantillas.php" class="btn btn-success">📋 Plantillas</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Empleados</h5>
        </div>
        <div class="card-body">
            <?php if (empty($empleados)): ?>
                <p class="text-muted">No hay empleados registrados</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Sueldo Base</th>
                            <th>Plantilla Actual</th>
                            <th>Estado de Pago (<?= $mes_actual ?>)</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                            <?php 
                                $porcentaje_pagado = 0;
                                if ($emp['sueldo_total'] > 0) {
                                    $porcentaje_pagado = round(($emp['monto_pagado'] / $emp['sueldo_total']) * 100, 0);
                                }
                            ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($emp['nombre']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($emp['email']) ?></small>
                            </td>
                            <td>$<?= number_format($emp['sueldo_base'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($emp['plantilla_nombre']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($emp['plantilla_nombre']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sin plantilla</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($emp['pago_id'] > 0): ?>
                                    <div style="width: 150px;">
                                        <?php if ($porcentaje_pagado == 100): ?>
                                            <span class="badge bg-success">Pagado 100%</span>
                                        <?php else: ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $porcentaje_pagado ?>%;" aria-valuenow="<?= $porcentaje_pagado ?>" aria-valuemin="0" aria-valuemax="100"><?= $porcentaje_pagado ?>%</div>
                                            </div>
                                            <small>$<?= number_format($emp['monto_pagado'], 2, ',', '.') ?> de $<?= number_format($emp['sueldo_total'], 2, ',', '.') ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning">Sin registrar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="pagar_sueldo.php?id=<?= $emp['id'] ?>&mes=<?= $mes_actual ?>" class="btn btn-success" title="Registrar/Actualizar pago">💵</a>
                                    <a href="sueldo_editar.php?id=<?= $emp['id'] ?>" class="btn btn-warning" title="Editar datos">✎</a>
                                    <a href="sueldo_conceptos.php?id=<?= $emp['id'] ?>" class="btn btn-info" title="Conceptos y plantilla">💰</a>
                                    <a href="sueldo_recibo.php?id=<?= $emp['id'] ?>&mes=<?= $mes_actual ?>" class="btn btn-primary" title="Ver recibo">🧾</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resumen de Acciones -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">📋 Plantillas</h5>
                    <p class="card-text">Crear y gestionar plantillas de conceptos</p>
                    <a href="plantillas.php" class="btn btn-success">Ir a Plantillas</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">👥 Empleados</h5>
                    <p class="card-text">Crear y editar empleados</p>
                    <a href="empleados_crear.php" class="btn btn-primary">Nuevo Empleado</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
