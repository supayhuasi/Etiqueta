<?php
require 'config.php';
require 'includes/navbar.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
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
$stmt = $pdo->prepare("
    SELECT COALESCE(e.sueldo_base, 0) as sueldo_base,
           COALESCE(SUM(CASE WHEN c.tipo = 'bonificacion' THEN sc.monto ELSE 0 END), 0) as bonificaciones,
           COALESCE(SUM(CASE WHEN c.tipo = 'descuento' THEN sc.monto ELSE 0 END), 0) as descuentos
    FROM empleados e
    LEFT JOIN sueldo_conceptos sc ON e.id = sc.empleado_id
    LEFT JOIN conceptos c ON sc.concepto_id = c.id
    WHERE e.id = ?
    GROUP BY e.id
");
$stmt->execute([$empleado_id]);
$sueldo_info = $stmt->fetch(PDO::FETCH_ASSOC);

$sueldo_total = $sueldo_info['sueldo_base'] + $sueldo_info['bonificaciones'] - $sueldo_info['descuentos'];

// Obtener pago registrado para este mes
$stmt = $pdo->prepare("
    SELECT * FROM pagos_sueldos
    WHERE empleado_id = ? AND mes_pago = ?
");
$stmt->execute([$empleado_id, $mes]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $monto_pagado = floatval($_POST['monto_pagado'] ?? 0);
    $observaciones = $_POST['observaciones'] ?? '';
    
    if ($monto_pagado <= 0 || $monto_pagado > $sueldo_total) {
        $error = "El monto debe ser mayor a 0 y no puede exceder el sueldo total de \$" . number_format($sueldo_total, 2);
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
        } catch (Exception $e) {
            $error = "Error al registrar pago: " . $e->getMessage();
        }
    }
}

$porcentaje_pagado = $sueldo_total > 0 ? round(($pago['monto_pagado'] / $sueldo_total) * 100, 1) : 0;
$saldo_pendiente = $sueldo_total - ($pago['monto_pagado'] ?? 0);
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
                            <p><strong>Pagado:</strong> $<?= number_format($pago['monto_pagado'], 2) ?></p>
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
                                       value="<?= $pago['monto_pagado'] ?? $sueldo_total ?>" 
                                       step="0.01" min="0" max="<?= $sueldo_total ?>" required>
                            </div>
                            <small class="form-text text-muted">Máximo: $<?= number_format($sueldo_total, 2) ?></small>
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

<?php require 'includes/footer.php'; ?>
