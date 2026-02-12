<?php
require '../../config.php';

// Obtener empleado
$stmt = $pdo->prepare("SELECT id, nombre, sueldo_base FROM empleados WHERE LOWER(nombre) LIKE ?");
$stmt->execute(['%ana%']);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

echo "<h2>Debug: {$empleado['nombre']} (ID: {$empleado['id']})</h2>";
echo "<p>Sueldo Base: \${$empleado['sueldo_base']}</p>";

$mes_actual = date('Y-m');
echo "<p>Mes: {$mes_actual}</p>";

// Obtener conceptos
$stmt = $pdo->prepare("
    SELECT sc.id, sc.monto, sc.formula, sc.es_porcentaje, c.tipo, c.nombre
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
    ORDER BY c.tipo DESC
");
$stmt->execute([$empleado['id'], $mes_actual]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Conceptos del mes:</h3>";
if (empty($conceptos)) {
    echo "<p style='color: red;'>⚠️ No hay conceptos cargados para este mes</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Concepto</th><th>Tipo</th><th>Monto</th><th>Fórmula</th><th>Es %</th></tr>";
    
    $bonificaciones = 0;
    $descuentos = 0;
    
    foreach ($conceptos as $c) {
        $monto = $c['monto'];
        $nota = '';
        
        if (!empty($c['formula'])) {
            $nota .= "Fórmula: {$c['formula']}";
        }
        if (!empty($c['es_porcentaje'])) {
            $nota .= " (porcentaje)";
        }
        
        echo "<tr>";
        echo "<td>{$c['nombre']}</td>";
        echo "<td>{$c['tipo']}</td>";
        echo "<td>\${$monto}</td>";
        echo "<td>{$c['formula']}</td>";
        echo "<td>" . ($c['es_porcentaje'] ? 'Sí' : 'No') . "</td>";
        echo "</tr>";
        
        if ($c['tipo'] === 'descuento') {
            $descuentos += $monto;
        } else {
            $bonificaciones += $monto;
        }
    }
    
    echo "</table>";
    
    $sueldo_total = $empleado['sueldo_base'] + $bonificaciones - $descuentos;
    
    echo "<h3>Cálculo del sueldo:</h3>";
    echo "<ul>";
    echo "<li>Sueldo Base: \${$empleado['sueldo_base']}</li>";
    echo "<li>Bonificaciones: +\${$bonificaciones}</li>";
    echo "<li>Descuentos: -\${$descuentos}</li>";
    echo "<li><strong>Sueldo Total Neto: \${$sueldo_total}</strong></li>";
    echo "</ul>";
}

// Obtener pagos realizados este mes
$stmt = $pdo->prepare("
    SELECT id, monto_pagado, fecha_pago
    FROM pagos_sueldos_parciales
    WHERE empleado_id = ? AND mes_pago = ?
    ORDER BY fecha_pago DESC
");
$stmt->execute([$empleado['id'], $mes_actual]);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Pagos realizados este mes:</h3>";
if (empty($pagos)) {
    echo "<p>Sin pagos registrados</p>";
} else {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Fecha</th><th>Monto</th></tr>";
    $total_pagado = 0;
    foreach ($pagos as $p) {
        echo "<tr>";
        echo "<td>{$p['fecha_pago']}</td>";
        echo "<td>\${$p['monto_pagado']}</td>";
        echo "</tr>";
        $total_pagado += $p['monto_pagado'];
    }
    echo "</table>";
    echo "<p><strong>Total Pagado: \${$total_pagado}</strong></p>";
}

echo "<br><br><a href='flujo_caja_egreso.php?tipo=sueldo' class='btn btn-primary'>Volver a Flujo de Caja</a>";
?>
