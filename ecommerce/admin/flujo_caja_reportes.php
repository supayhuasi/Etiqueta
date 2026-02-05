<?php
session_start();
require '../../config.php';

// Obtener parámetros
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01', strtotime('first day of previous month'));
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t', strtotime('last day of previous month'));

// Validar fechas
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_fin)) {
    $fecha_inicio = date('Y-m-01', strtotime('first day of previous month'));
    $fecha_fin = date('Y-m-t', strtotime('last day of previous month'));
}

// Obtener todas las transacciones en el rango
$stmt = $pdo->prepare("
    SELECT * FROM flujo_caja 
    WHERE fecha BETWEEN ? AND ?
    ORDER BY fecha ASC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular acumulados por día
$acumulado_diario = [];
$saldo_corriente = 0;

foreach ($transacciones as $trans) {
    $fecha = $trans['fecha'];
    
    if (!isset($acumulado_diario[$fecha])) {
        $acumulado_diario[$fecha] = [
            'ingresos' => 0,
            'egresos' => 0,
            'saldo_anterior' => $saldo_corriente
        ];
    }
    
    if ($trans['tipo'] === 'ingreso') {
        $acumulado_diario[$fecha]['ingresos'] += $trans['monto'];
    } else {
        $acumulado_diario[$fecha]['egresos'] += $trans['monto'];
    }
    
    $saldo_corriente = $acumulado_diario[$fecha]['saldo_anterior'] + 
                       $acumulado_diario[$fecha]['ingresos'] - 
                       $acumulado_diario[$fecha]['egresos'];
    
    $acumulado_diario[$fecha]['saldo'] = $saldo_corriente;
}

// Resumen total
$total_ingresos = 0;
$total_egresos = 0;

foreach ($transacciones as $trans) {
    if ($trans['tipo'] === 'ingreso') {
        $total_ingresos += $trans['monto'];
    } else {
        $total_egresos += $trans['monto'];
    }
}

$saldo_neto = $total_ingresos - $total_egresos;

// Resumen por categoría
$stmt = $pdo->prepare("
    SELECT 
        tipo,
        categoria,
        COUNT(*) as cantidad,
        SUM(monto) as total
    FROM flujo_caja
    WHERE fecha BETWEEN ? AND ?
    GROUP BY tipo, categoria
    ORDER BY tipo DESC, total DESC
");
$stmt->execute([$fecha_inicio, $fecha_fin]);
$resumen_categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reporte de Flujo de Caja</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
    <style>
        .print-button { display: none; }
        @media print {
            .print-button { display: inline-block; }
            body { background: white; }
            .btn, .form-control, .card-header { display: none; }
        }
    </style>
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>📈 Reporte de Flujo de Caja</h1>
        </div>
        <div class="col-md-4 text-end">
            <button onclick="window.print()" class="btn btn-primary me-2">
                <i class="bi bi-printer"></i> Imprimir
            </button>
            <a href="flujo_caja.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                    <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label for="fecha_fin" class="form-label">Fecha Fin</label>
                    <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen General -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card" style="border-top: 4px solid #28A745;">
                <div class="card-body">
                    <h6 class="card-title text-muted">INGRESOS</h6>
                    <h3 class="text-success">$<?= number_format($total_ingresos, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-top: 4px solid #DC3545;">
                <div class="card-body">
                    <h6 class="card-title text-muted">EGRESOS</h6>
                    <h3 class="text-danger">$<?= number_format($total_egresos, 2) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card" style="border-top: 4px solid #0066cc;">
                <div class="card-body">
                    <h6 class="card-title text-muted">SALDO NETO</h6>
                    <h3 style="color: <?= $saldo_neto >= 0 ? '#28A745' : '#DC3545' ?>">
                        $<?= number_format($saldo_neto, 2) ?>
                    </h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Ingresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotal_ingresos = 0;
                                foreach ($resumen_categorias as $cat) {
                                    if ($cat['tipo'] === 'ingreso') {
                                        $subtotal_ingresos += $cat['total'];
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($cat['categoria']) . '</td>';
                                        echo '<td>' . $cat['cantidad'] . '</td>';
                                        echo '<td class="text-end"><strong>$' . number_format($cat['total'], 2) . '</strong></td>';
                                        echo '</tr>';
                                    }
                                }
                                if ($subtotal_ingresos === 0) {
                                    echo '<tr><td colspan="3" class="text-muted text-center">Sin ingresos</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Egresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead class="table-light">
                                <tr>
                                    <th>Categoría</th>
                                    <th>Cantidad</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $subtotal_egresos = 0;
                                foreach ($resumen_categorias as $cat) {
                                    if ($cat['tipo'] === 'egreso') {
                                        $subtotal_egresos += $cat['total'];
                                        echo '<tr>';
                                        echo '<td>' . htmlspecialchars($cat['categoria']) . '</td>';
                                        echo '<td>' . $cat['cantidad'] . '</td>';
                                        echo '<td class="text-end"><strong>$' . number_format($cat['total'], 2) . '</strong></td>';
                                        echo '</tr>';
                                    }
                                }
                                if ($subtotal_egresos === 0) {
                                    echo '<tr><td colspan="3" class="text-muted text-center">Sin egresos</td></tr>';
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalle Diario -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Movimientos por Día</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th class="text-end">Saldo Anterior</th>
                            <th class="text-end">Ingresos</th>
                            <th class="text-end">Egresos</th>
                            <th class="text-end">Saldo del Día</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $saldo_acum = 0;
                        foreach ($acumulado_diario as $fecha => $datos) {
                            $saldo_acum = $datos['saldo'];
                            echo '<tr>';
                            echo '<td><strong>' . date('d/m/Y', strtotime($fecha)) . '</strong></td>';
                            echo '<td class="text-end">$' . number_format($datos['saldo_anterior'], 2) . '</td>';
                            echo '<td class="text-end text-success"><strong>+$' . number_format($datos['ingresos'], 2) . '</strong></td>';
                            echo '<td class="text-end text-danger"><strong>-$' . number_format($datos['egresos'], 2) . '</strong></td>';
                            echo '<td class="text-end" style="background-color: ' . ($datos['saldo'] >= 0 ? '#d4edda' : '#f8d7da') . '">';
                            echo '<strong style="color: ' . ($datos['saldo'] >= 0 ? '#28A745' : '#DC3545') . '">$' . number_format($datos['saldo'], 2) . '</strong>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        if (empty($acumulado_diario)) {
                            echo '<tr><td colspan="5" class="text-muted text-center">Sin movimientos en este período</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="mt-4 text-muted text-center">
        <small>Reporte generado: <?= date('d/m/Y H:i') ?></small>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
