<?php
require '../includes/header.php';

// Verificar si existe sesión
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

// Obtener mes seleccionado o usar mes actual
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$mes_actual = date('Y-m');

// Obtener todos los meses disponibles con pagos
$stmt_meses = $pdo->query("
    SELECT DISTINCT mes_pago 
    FROM pagos_sueldos 
    ORDER BY mes_pago DESC
");
$meses_disponibles = $stmt_meses->fetchAll(PDO::FETCH_COLUMN);

// Obtener lista de empleados con estado de pago del mes filtrado
$stmt = $pdo->prepare("
    SELECT e.id, e.nombre, e.email, e.sueldo_base, e.activo, ep.plantilla_id, pc.nombre as plantilla_nombre,
           COALESCE(ps.monto_pagado, 0) as monto_pagado,
           COALESCE(ps.sueldo_total, 0) as sueldo_total,
           COALESCE(ps.id, 0) as pago_id,
           COALESCE(
               (SELECT SUM(monto_pagado) 
                FROM pagos_sueldos_parciales 
                WHERE empleado_id = e.id AND mes_pago = ?), 
               0
           ) as pagos_parciales
    FROM empleados e
    LEFT JOIN empleado_plantilla ep ON e.id = ep.empleado_id
    LEFT JOIN plantillas_conceptos pc ON ep.plantilla_id = pc.id
    LEFT JOIN pagos_sueldos ps ON e.id = ps.empleado_id AND ps.mes_pago = ?
    WHERE e.activo = 1
    ORDER BY e.nombre ASC
");
$stmt->execute([$mes_filtro, $mes_filtro]);
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales del mes
$total_sueldo = 0;
$total_pagado = 0;
$total_pendiente = 0;

foreach ($empleados as $emp) {
    // Calcular sueldo total si no existe pago registrado
    if ($emp['pago_id'] == 0) {
        $stmt_calc = $pdo->prepare("
            SELECT COALESCE(e.sueldo_base, 0) as sueldo_base,
                   COALESCE(SUM(CASE WHEN c.tipo = 'bonificacion' THEN sc.monto ELSE 0 END), 0) as bonificaciones,
                   COALESCE(SUM(CASE WHEN c.tipo = 'descuento' THEN sc.monto ELSE 0 END), 0) as descuentos
            FROM empleados e
            LEFT JOIN sueldo_conceptos sc ON e.id = sc.empleado_id
            LEFT JOIN conceptos c ON sc.concepto_id = c.id
            WHERE e.id = ?
            GROUP BY e.id
        ");
        $stmt_calc->execute([$emp['id']]);
        $sueldo_calc = $stmt_calc->fetch(PDO::FETCH_ASSOC);
        $sueldo_total_emp = $sueldo_calc['sueldo_base'] + $sueldo_calc['bonificaciones'] - $sueldo_calc['descuentos'];
    } else {
        $sueldo_total_emp = $emp['sueldo_total'];
    }
    
    // Sumar pagos completos + pagos parciales
    $monto_total_pagado = $emp['monto_pagado'] + $emp['pagos_parciales'];
    
    $total_sueldo += $sueldo_total_emp;
    $total_pagado += $monto_total_pagado;
    $total_pendiente += ($sueldo_total_emp - $monto_total_pagado);
}
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

    <!-- Filtro por mes -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Filtro por Mes</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Seleccionar Mes</label>
                    <input type="month" name="mes" id="mes" class="form-control" value="<?= $mes_filtro ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <a href="?mes=<?= $mes_actual ?>" class="btn btn-secondary">Mes Actual</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen de pago del mes -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Resumen de Pago - Mes: <strong><?= $mes_filtro ?></strong></h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Total a Pagar</h6>
                                <h3 class="text-primary">$<?= number_format($total_sueldo, 2, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Ya Pagado</h6>
                                <h3 class="text-success">$<?= number_format($total_pagado, 2, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Falta Pagar</h6>
                                <h3 class="text-danger">$<?= number_format($total_pendiente, 2, ',', '.') ?></h3>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <h6>Porcentaje Pagado</h6>
                                <?php $porcentaje_mes = $total_sueldo > 0 ? round(($total_pagado / $total_sueldo) * 100, 1) : 0; ?>
                                <h3 class="text-info"><?= $porcentaje_mes ?>%</h3>
                                <div class="progress mt-2" style="height: 10px;">
                                    <div class="progress-bar" role="progressbar" style="width: <?= $porcentaje_mes ?>%;" aria-valuenow="<?= $porcentaje_mes ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
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
                            <th>Sueldo Total</th>
                            <th>Estado de Pago</th>
                            <th>Pendiente</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                            <?php 
                                // Calcular sueldo total si no existe pago registrado
                                if ($emp['pago_id'] == 0) {
                                    $stmt_calc = $pdo->prepare("
                                        SELECT COALESCE(e.sueldo_base, 0) as sueldo_base,
                                               COALESCE(SUM(CASE WHEN c.tipo = 'bonificacion' THEN sc.monto ELSE 0 END), 0) as bonificaciones,
                                               COALESCE(SUM(CASE WHEN c.tipo = 'descuento' THEN sc.monto ELSE 0 END), 0) as descuentos
                                        FROM empleados e
                                        LEFT JOIN sueldo_conceptos sc ON e.id = sc.empleado_id
                                        LEFT JOIN conceptos c ON sc.concepto_id = c.id
                                        WHERE e.id = ?
                                        GROUP BY e.id
                                    ");
                                    $stmt_calc->execute([$emp['id']]);
                                    $sueldo_calc = $stmt_calc->fetch(PDO::FETCH_ASSOC);
                                    $sueldo_total_emp = $sueldo_calc['sueldo_base'] + $sueldo_calc['bonificaciones'] - $sueldo_calc['descuentos'];
                                } else {
                                    $sueldo_total_emp = $emp['sueldo_total'];
                                }
                                
                                // Sumar pagos completos + pagos parciales
                                $monto_total_pagado = $emp['monto_pagado'] + $emp['pagos_parciales'];
                                $saldo_pendiente = $sueldo_total_emp - $monto_total_pagado;
                                $porcentaje_pagado = 0;
                                if ($sueldo_total_emp > 0) {
                                    $porcentaje_pagado = round(($monto_total_pagado / $sueldo_total_emp) * 100, 0);
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
                            <td><strong>$<?= number_format($sueldo_total_emp, 2, ',', '.') ?></strong></td>
                            <td>
                                <?php if ($emp['pago_id'] > 0 || $emp['pagos_parciales'] > 0): ?>
                                    <div style="width: 180px;">
                                        <?php if ($porcentaje_pagado >= 100): ?>
                                            <span class="badge bg-success">✓ Pagado 100%</span>
                                        <?php else: ?>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" style="width: <?= $porcentaje_pagado ?>%;" aria-valuenow="<?= $porcentaje_pagado ?>" aria-valuemin="0" aria-valuemax="100"><?= $porcentaje_pagado ?>%</div>
                                            </div>
                                            <small>$<?= number_format($monto_total_pagado, 2, ',', '.') ?> de $<?= number_format($sueldo_total_emp, 2, ',', '.') ?></small>
                                            <?php if ($emp['pagos_parciales'] > 0): ?>
                                                <br><small class="text-info">↳ Incluye $<?= number_format($emp['pagos_parciales'], 2, ',', '.') ?> en pagos parciales</small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-warning">Sin registrar</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($saldo_pendiente > 0): ?>
                                    <span class="badge bg-danger">$<?= number_format($saldo_pendiente, 2, ',', '.') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success">$0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="pagar_sueldo.php?id=<?= $emp['id'] ?>&mes=<?= $mes_filtro ?>" class="btn btn-success" title="Registrar/Actualizar pago">💵</a>
                                    <a href="empleados_editar.php?id=<?= $emp['id'] ?>" class="btn btn-warning" title="Editar datos">✎</a>
                                    <a href="sueldo_conceptos.php?id=<?= $emp['id'] ?>" class="btn btn-info" title="Conceptos y plantilla">💰</a>
                                    <a href="sueldo_recibo.php?id=<?= $emp['id'] ?>&mes=<?= $mes_filtro ?>" class="btn btn-primary" title="Ver recibo">🧾</a>
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

<?php require '../includes/footer.php'; ?>
