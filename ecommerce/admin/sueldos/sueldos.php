<?php
require '../includes/header.php';

// Verificar si existe sesión
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

// Acción: aplicar aumento porcentual a sueldos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'aplicar_aumento') {
    $porcentaje = floatval($_POST['porcentaje'] ?? 0);
    $mes_target = trim($_POST['mes_target'] ?? ''); // formato YYYY-MM
    $aplicar_global = !empty($_POST['aplicar_global']);
    $aplicar_mensual = !empty($_POST['aplicar_mensual']) && $mes_target !== '';

    if ($porcentaje > 0) {
        try {
            $pdo->beginTransaction();
            $stmt_emp = $pdo->query("SELECT id, sueldo_base FROM empleados");
            $emps = $stmt_emp->fetchAll(PDO::FETCH_ASSOC);
            $up_emp = $pdo->prepare("UPDATE empleados SET sueldo_base = ? WHERE id = ?");
            $ins_month = $pdo->prepare("INSERT INTO sueldo_base_mensual (empleado_id, mes, sueldo_base) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE sueldo_base = VALUES(sueldo_base)");
            foreach ($emps as $e) {
                $nuevo = round(((float)$e['sueldo_base']) * (1 + $porcentaje / 100), 2);
                if ($aplicar_global) {
                    $up_emp->execute([$nuevo, $e['id']]);
                }
                if ($aplicar_mensual) {
                    $ins_month->execute([$e['id'], $mes_target, $nuevo]);
                }
            }
            $pdo->commit();
            header('Location: sueldos.php?success=' . urlencode('Aumento aplicado') . '&mes=' . urlencode($mes_target));
            exit;
        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error_msg = $ex->getMessage();
        }
    } else {
        $error_msg = 'Porcentaje inválido';
    }
}

function calcularSueldoTotal(PDO $pdo, int $empleado_id, string $mes): float
{
    // Priorizar sueldo base mensual si existe para el mes indicado
    $stmt_month = $pdo->prepare("SELECT sueldo_base FROM sueldo_base_mensual WHERE empleado_id = ? AND mes = ? LIMIT 1");
    $stmt_month->execute([$empleado_id, $mes]);
    $row_month = $stmt_month->fetch(PDO::FETCH_ASSOC);
    if ($row_month) {
        $sueldo_base = (float)$row_month['sueldo_base'];
    } else {
        $stmt = $pdo->prepare("SELECT sueldo_base FROM empleados WHERE id = ?");
        $stmt->execute([$empleado_id]);
        $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$empleado) return 0.0;
        $sueldo_base = (float)$empleado['sueldo_base'];
    }
    $bonificaciones = 0.0;
    $descuentos = 0.0;

    $evaluarFormula = function (?string $formula, float $sueldo_base): ?float {
        if (!$formula) {
            return null;
        }
        $formula = str_replace('sueldo_base', (string)$sueldo_base, $formula);
        try {
            $resultado = @eval("return " . $formula . ";");
            return $resultado !== false ? (float)$resultado : null;
        } catch (Exception $e) {
            return null;
        }
    };

    $stmt_conceptos = $pdo->prepare("
        SELECT sc.monto, sc.formula, sc.es_porcentaje, c.tipo
        FROM sueldo_conceptos sc
        JOIN conceptos c ON sc.concepto_id = c.id
        WHERE sc.empleado_id = ? AND (sc.mes = ? OR sc.mes IS NULL OR sc.mes = '')
    ");
    $stmt_conceptos->execute([$empleado_id, $mes]);
    $conceptos = $stmt_conceptos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conceptos as $c) {
        $monto_concepto = (float)$c['monto'];
        if (!empty($c['formula'])) {
            $calc = $evaluarFormula($c['formula'], $sueldo_base);
            if ($calc !== null) {
                $monto_concepto = $calc;
            }
        } elseif (!empty($c['es_porcentaje'])) {
            $monto_concepto = ($sueldo_base * $monto_concepto) / 100;
        }

        if ($c['tipo'] === 'descuento') {
            $descuentos += $monto_concepto;
        } else {
            $bonificaciones += $monto_concepto;
        }
    }

    return max(0, $sueldo_base + $bonificaciones - $descuentos);
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

$sueldos_calculados = [];

foreach ($empleados as $emp) {
    // Calcular sueldo total si no existe pago registrado
    if ($emp['pago_id'] == 0 || (float)$emp['sueldo_total'] <= 0) {
        $sueldo_total_emp = calcularSueldoTotal($pdo, (int)$emp['id'], $mes_filtro);
    } else {
        $sueldo_total_emp = (float)$emp['sueldo_total'];
    }

    $sueldos_calculados[$emp['id']] = $sueldo_total_emp;
    
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
            <a href="../empleados_crear.php" class="btn btn-primary">+ Nuevo Empleado</a>
            <a href="plantillas.php" class="btn btn-success">📋 Plantillas</a>
        </div>
    </div>

    <?php if (!empty($error_msg)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>
    <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>

    <!-- Acción: aplicar aumento porcentual -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="POST" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="aplicar_aumento">
                <div class="col-auto">
                    <label class="form-label">Porcentaje (%)</label>
                    <input type="number" name="porcentaje" step="0.01" class="form-control" placeholder="e.g. 5" required>
                </div>
                <div class="col-auto">
                    <label class="form-label">Mes objetivo (opcional)</label>
                    <input type="month" name="mes_target" class="form-control">
                </div>
                <div class="col-auto form-check">
                    <input class="form-check-input mt-3" type="checkbox" name="aplicar_mensual" id="aplicar_mensual">
                    <label class="form-check-label" for="aplicar_mensual">Crear sobrescritura mensual</label>
                </div>
                <div class="col-auto form-check">
                    <input class="form-check-input mt-3" type="checkbox" name="aplicar_global" id="aplicar_global">
                    <label class="form-check-label" for="aplicar_global">Actualizar sueldo base global</label>
                </div>
                <div class="col-auto">
                    <button class="btn btn-warning">Aplicar Aumento</button>
                </div>
            </form>
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
                                $sueldo_total_emp = $sueldos_calculados[$emp['id']] ?? 0;
                                
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
                                    <a href="../empleados_editar.php?id=<?= $emp['id'] ?>" class="btn btn-warning" title="Editar datos">✎</a>
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
                    <a href="../empleados_crear.php" class="btn btn-primary">Nuevo Empleado</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
