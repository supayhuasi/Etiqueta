<?php
require 'config.php';
require 'includes/header.php';

$id = $_GET['id'] ?? 0;
$mes = $_GET['mes'] ?? date('Y-m');

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

// Obtener conceptos del empleado para el mes especificado
$stmt = $pdo->prepare("
    SELECT sc.*, c.nombre, c.tipo 
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
    ORDER BY c.tipo DESC, c.nombre
");
$stmt->execute([$id, $mes]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Función para evaluar fórmulas
function evaluarFormula($formula, $sueldo_base) {
    if (!$formula) return null;
    
    // Reemplazar variables
    $formula = str_replace('sueldo_base', $sueldo_base, $formula);
    
    // Evaluar la fórmula de forma segura
    try {
        $resultado = @eval("return " . $formula . ";");
        return $resultado !== false ? $resultado : null;
    } catch (Exception $e) {
        return null;
    }
}

// Calcular totales
$sueldo_base = $empleado['sueldo_base'];
$descuentos = 0;
$bonificaciones = 0;

foreach ($conceptos as $c) {
    $monto = $c['monto'];
    
    // Si hay fórmula, calcularla
    if ($c['formula']) {
        $monto = evaluarFormula($c['formula'], $sueldo_base);
    } elseif ($c['es_porcentaje']) {
        // Si es porcentaje, calcular del sueldo base
        $monto = ($sueldo_base * $c['monto']) / 100;
    }
    
    if ($c['tipo'] === 'descuento') {
        $descuentos += $monto;
    } else {
        $bonificaciones += $monto;
    }
}

$sueldo_neto = $sueldo_base + $bonificaciones - $descuentos;
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">Recibo de Sueldo - <?= date('F Y', strtotime($mes . '-01')) ?></h4>
                </div>
                <div class="card-body">
                    <!-- Encabezado -->
                    <div class="row mb-4 border-bottom pb-3">
                        <div class="col-md-6">
                            <h5><?= htmlspecialchars($empleado['nombre']) ?></h5>
                            <p class="text-muted">ID: #<?= $empleado['id'] ?></p>
                        </div>
                        <div class="col-md-6 text-end">
                            <p><strong>Mes:</strong> <?= date('F Y', strtotime($mes . '-01')) ?></p>
                            <p><strong>Generado:</strong> <?= date('d/m/Y') ?></p>
                        </div>
                    </div>
                    
                    <!-- Detalles -->
                    <table class="table">
                        <tr>
                            <td>Sueldo Base</td>
                            <td class="text-end">$<?= number_format($sueldo_base, 2, ',', '.') ?></td>
                        </tr>
                        
                        <?php if ($bonificaciones > 0): ?>
                            <tr class="table-success">
                                <td colspan="2" class="fw-bold">BONIFICACIONES</td>
                            </tr>
                            <?php foreach ($conceptos as $c): ?>
                                <?php if ($c['tipo'] === 'bonificacion'): ?>
                                    <?php
                                    $monto = $c['monto'];
                                    if ($c['formula']) {
                                        $monto = evaluarFormula($c['formula'], $sueldo_base);
                                    } elseif ($c['es_porcentaje']) {
                                        $monto = ($sueldo_base * $c['monto']) / 100;
                                    }
                                    ?>
                                    <tr>
                                        <td class="ps-4"><?= htmlspecialchars($c['nombre']) ?></td>
                                        <td class="text-end">+ $<?= number_format($monto, 2, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <?php if ($descuentos > 0): ?>
                            <tr class="table-danger">
                                <td colspan="2" class="fw-bold">DESCUENTOS</td>
                            </tr>
                            <?php foreach ($conceptos as $c): ?>
                                <?php if ($c['tipo'] === 'descuento'): ?>
                                    <?php
                                    $monto = $c['monto'];
                                    if ($c['formula']) {
                                        $monto = evaluarFormula($c['formula'], $sueldo_base);
                                    } elseif ($c['es_porcentaje']) {
                                        $monto = ($sueldo_base * $c['monto']) / 100;
                                    }
                                    ?>
                                    <tr>
                                        <td class="ps-4"><?= htmlspecialchars($c['nombre']) ?></td>
                                        <td class="text-end">- $<?= number_format($monto, 2, ',', '.') ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <tr class="table-light border-top border-bottom fw-bold">
                            <td>TOTAL A PAGAR</td>
                            <td class="text-end fs-5">$<?= number_format($sueldo_neto, 2, ',', '.') ?></td>
                        </tr>
                    </table>
                    
                    <!-- Botones de Acción -->
                    <div class="mt-4 d-flex gap-2 justify-content-end">
                        <button class="btn btn-primary" onclick="window.print()">Imprimir</button>
                        <a href="sueldo_conceptos.php?id=<?= $id ?>" class="btn btn-warning">Editar Conceptos</a>
                        <a href="sueldos.php" class="btn btn-secondary">Volver</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
