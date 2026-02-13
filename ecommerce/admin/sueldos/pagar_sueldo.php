<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

function calcularSueldoDetalle(PDO $pdo, int $empleado_id, string $mes): array
{
    $stmt = $pdo->prepare("SELECT sueldo_base FROM empleados WHERE id = ?");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empleado) {
        return [
            'sueldo_base' => 0.0,
            'bonificaciones' => 0.0,
            'descuentos' => 0.0,
            'sueldo_total' => 0.0
        ];
    }

    $sueldo_base = (float)$empleado['sueldo_base'];
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

    $sueldo_total = max(0, $sueldo_base + $bonificaciones - $descuentos);

    return [
        'sueldo_base' => $sueldo_base,
        'bonificaciones' => $bonificaciones,
        'descuentos' => $descuentos,
        'sueldo_total' => $sueldo_total
    ];
}

$empleado_id = $_GET['id'] ?? 0;
$mes = $_GET['mes'] ?? date('Y-m');

// Obtener datos del empleado y sueldo total
$stmt = $pdo->prepare("
    SELECT e.id, e.nombre, e.email, e.sueldo_base
    FROM empleados e
    WHERE e.id = ?
");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

// Obtener el sueldo total del mes (base + conceptos)
$sueldo_info = calcularSueldoDetalle($pdo, (int)$empleado_id, $mes);
$sueldo_total = $sueldo_info['sueldo_total'];

// Obtener pago registrado para este mes
$stmt = $pdo->prepare("
    SELECT * FROM pagos_sueldos
    WHERE empleado_id = ? AND mes_pago = ?
");
$stmt->execute([$empleado_id, $mes]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener total de pagos parciales del mes
$stmt_parciales = $pdo->prepare("
    SELECT COALESCE(SUM(monto_pagado), 0) as total_pagado
    FROM pagos_sueldos_parciales
    WHERE empleado_id = ? AND mes_pago = ?
");
$stmt_parciales->execute([$empleado_id, $mes]);
$parciales = $stmt_parciales->fetch(PDO::FETCH_ASSOC);
$total_parciales = (float)($parciales['total_pagado'] ?? 0);

$monto_pagado_actual = (float)($pago['monto_pagado'] ?? 0);
$monto_maximo = max(0, $sueldo_total - $total_parciales);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
    $observaciones = $_POST['observaciones'] ?? '';
    
    if ($monto_pagado <= 0 || $monto_pagado > $monto_maximo) {
        $error = "El monto debe ser mayor a 0 y no puede exceder el saldo disponible de \$" . number_format($monto_maximo, 2);
    } else {
        try {
            if ($pago) {
                // Actualizar pago existente
                $stmt = $pdo->prepare("
                    UPDATE pagos_sueldos 
                    SET monto_pagado = ?, 
                        fecha_pago = NOW(),
                        observaciones = ?,
                        usuario_registra = ?
                    WHERE id = ?
                ");
                $stmt->execute([$monto_pagado, $observaciones, $_SESSION['user']['id'], $pago['id']]);
                $mensaje = "Pago actualizado correctamente";
            } else {
                // Crear nuevo registro de pago
                $stmt = $pdo->prepare("
                    INSERT INTO pagos_sueldos 
                    (empleado_id, mes_pago, sueldo_total, monto_pagado, fecha_pago, usuario_registra, observaciones)
                    VALUES (?, ?, ?, ?, NOW(), ?, ?)
                ");
                $stmt->execute([$empleado_id, $mes, $sueldo_total, $monto_pagado, $_SESSION['user']['id'], $observaciones]);
                $mensaje = "Pago registrado correctamente";
            }
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM pagos_sueldos WHERE empleado_id = ? AND mes_pago = ?");
            $stmt->execute([$empleado_id, $mes]);
            $pago = $stmt->fetch(PDO::FETCH_ASSOC);

            $monto_pagado_actual = (float)($pago['monto_pagado'] ?? 0);
        } catch (Exception $e) {
            $error = "Error al registrar pago: " . $e->getMessage();
        }
    }
}

$total_pagado = $monto_pagado_actual + $total_parciales;
$porcentaje_pagado = $sueldo_total > 0 ? round(($total_pagado / $sueldo_total) * 100, 1) : 0;
$saldo_pendiente = $sueldo_total - $total_pagado;
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Registrar Pago - <?= htmlspecialchars($empleado['nombre']) ?></h2>
            <p class="text-muted">Mes: <strong><?= htmlspecialchars($mes) ?></strong></p>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert"><?= $mensaje ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <!-- Información del sueldo -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Detalles del Sueldo</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Sueldo Base:</strong> $<?= number_format($sueldo_info['sueldo_base'], 2) ?></p>
                            <p><strong>Bonificaciones:</strong> $<?= number_format($sueldo_info['bonificaciones'], 2) ?></p>
                            <p><strong>Descuentos:</strong> $<?= number_format($sueldo_info['descuentos'], 2) ?></p>
                        </div>
                        <div class="col-md-6">
                            <h4 class="text-primary">Sueldo Total: $<?= number_format($sueldo_total, 2) ?></h4>
                            <?php if ($total_parciales > 0): ?>
                                <small class="text-info d-block">Pagos parciales: $<?= number_format($total_parciales, 2) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estado del pago -->
            <?php if ($pago): ?>
            <div class="card mb-4">
                <div class="card-header bg-info">
                    <h5>Estado de Pago</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Pagado:</strong> $<?= number_format($total_pagado, 2) ?></p>
                            <?php if ($total_parciales > 0): ?>
                                <small class="text-info d-block">↳ Incluye $<?= number_format($total_parciales, 2) ?> en pagos parciales</small>
                            <?php endif; ?>
                            <p><strong>Pendiente:</strong> $<?= number_format($saldo_pendiente, 2) ?></p>
                            <p><strong>Porcentaje:</strong> <span class="badge bg-info"><?= $porcentaje_pagado ?>%</span></p>
                        </div>
                        <div class="col-md-6">
                            <?php if ($porcentaje_pagado == 100): ?>
                                <div class="alert alert-success">✓ Totalmente pagado</div>
                            <?php else: ?>
                                <div class="alert alert-warning">⚠ Pago pendiente</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?= $porcentaje_pagado ?>%;" aria-valuenow="<?= $porcentaje_pagado ?>" aria-valuemin="0" aria-valuemax="100"><?= $porcentaje_pagado ?>%</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Formulario de pago -->
            <div class="card">
                <div class="card-header">
                    <h5><?= $pago ? 'Actualizar Pago' : 'Registrar Pago' ?></h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="monto_pagado" class="form-label">Monto a Pagar</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="monto_pagado" name="monto_pagado" 
                                       value="<?= $pago['monto_pagado'] ?? $monto_maximo ?>" 
                                       step="0.01" min="0" max="<?= $monto_maximo ?>" required>
                            </div>
                            <small class="form-text text-muted">Máximo disponible: $<?= number_format($monto_maximo, 2) ?></small>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones (opcional)</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars($pago['observaciones'] ?? '') ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="sueldos.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">
                                <?= $pago ? 'Actualizar Pago' : 'Registrar Pago' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
