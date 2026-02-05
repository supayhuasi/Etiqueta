<?php
require 'config.php';
require 'auth/check.php';

$empleado_id = intval($_GET['empleado_id'] ?? 0);
$mes = $_GET['mes'] ?? date('Y-m');

// Filtros
$filtro_query = "WHERE 1=1";
$filtro_params = [];

if ($empleado_id > 0) {
    $filtro_query .= " AND psp.empleado_id = ?";
    $filtro_params[] = $empleado_id;
}

if (!empty($mes) && preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $filtro_query .= " AND psp.mes_pago = ?";
    $filtro_params[] = $mes;
}

// Obtener pagos parciales
$query = "
    SELECT 
        psp.*,
        e.nombre,
        e.sueldo_base
    FROM pagos_sueldos_parciales psp
    JOIN empleados e ON psp.empleado_id = e.id
    $filtro_query
    ORDER BY psp.mes_pago DESC, psp.fecha_pago DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($filtro_params);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados para dropdown
$stmt = $pdo->prepare("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por mes-empleado para resumen
$resumen = [];
foreach ($pagos as $pago) {
    $clave = $pago['mes_pago'] . '-' . $pago['empleado_id'];
    if (!isset($resumen[$clave])) {
        $resumen[$clave] = [
            'mes' => $pago['mes_pago'],
            'empleado_id' => $pago['empleado_id'],
            'nombre' => $pago['nombre'],
            'sueldo_base' => $pago['sueldo_base'],
            'total_pagado' => 0,
            'cantidad_pagos' => 0
        ];
    }
    $resumen[$clave]['total_pagado'] += $pago['monto_pagado'];
    $resumen[$clave]['cantidad_pagos']++;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagos Parciales de Sueldos</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>👨‍💼 Pagos Parciales de Sueldos</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="flujo_caja_egreso.php?tipo=sueldo" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Nuevo Pago
            </a>
            <a href="flujo_caja.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="empleado_id" class="form-label">Empleado</label>
                    <select id="empleado_id" name="empleado_id" class="form-select" onchange="this.form.submit()">
                        <option value="">Todos los empleados</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $empleado_id === $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mes</label>
                    <input type="month" id="mes" name="mes" class="form-control" value="<?= $mes ?>" onchange="this.form.submit()">
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen por Mes-Empleado -->
    <?php if (!empty($resumen)): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Resumen de Pagos</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Mes</th>
                            <th>Empleado</th>
                            <th>Sueldo Base</th>
                            <th>Total Pagado</th>
                            <th>Pendiente</th>
                            <th>Pagos</th>
                            <th>% Pagado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen as $res): ?>
                            <tr>
                                <td><strong><?= date('M/Y', strtotime($res['mes'] . '-01')) ?></strong></td>
                                <td><?= htmlspecialchars($res['nombre']) ?></td>
                                <td>$<?= number_format($res['sueldo_base'], 2) ?></td>
                                <td class="text-success"><strong>$<?= number_format($res['total_pagado'], 2) ?></strong></td>
                                <td class="text-danger">$<?= number_format($res['sueldo_base'] - $res['total_pagado'], 2) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $res['cantidad_pagos'] ?> pago<?= $res['cantidad_pagos'] > 1 ? 's' : '' ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $porcentaje = ($res['total_pagado'] / $res['sueldo_base']) * 100;
                                    $color = $porcentaje >= 100 ? 'success' : ($porcentaje >= 75 ? 'info' : 'warning');
                                    ?>
                                    <div class="progress" style="height: 20px;">
                                        <div class="progress-bar bg-<?= $color ?>" role="progressbar" style="width: <?= min($porcentaje, 100) ?>%">
                                            <?= number_format($porcentaje, 1) ?>%
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detalle de Pagos -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Detalle de Pagos</h5>
        </div>
        <div class="card-body">
            <?php if (empty($pagos)): ?>
                <p class="text-muted text-center">No hay pagos parciales registrados</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha Pago</th>
                                <th>Empleado</th>
                                <th>Mes</th>
                                <th>Monto Pagado</th>
                                <th>Sueldo Pendiente</th>
                                <th>Registrado por</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pagos as $pago): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($pago['fecha_pago'])) ?></td>
                                    <td><strong><?= htmlspecialchars($pago['nombre']) ?></strong></td>
                                    <td><?= date('M/Y', strtotime($pago['mes_pago'] . '-01')) ?></td>
                                    <td class="text-success"><strong>$<?= number_format($pago['monto_pagado'], 2) ?></strong></td>
                                    <td class="text-danger">$<?= number_format($pago['sueldo_pendiente'], 2) ?></td>
                                    <td><small class="text-muted">Usuario #<?= $pago['usuario_registra'] ?></small></td>
                                    <td>
                                        <?php
                                        if ($pago['observaciones']) {
                                            echo '<small>' . htmlspecialchars($pago['observaciones']) . '</small>';
                                        } else {
                                            echo '<small class="text-muted">-</small>';
                                        }
                                        ?>
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
</body>
</html>
