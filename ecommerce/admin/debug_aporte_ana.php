<?php
require '../../config.php';

echo "<h2>Debug: Verificar Aporte en sueldo_conceptos</h2>";

// Obtener Ana
$stmt = $pdo->prepare("SELECT id, nombre, sueldo_base FROM empleados WHERE LOWER(nombre) LIKE ?");
$stmt->execute(['%ana%']);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

$mes = '2026-02';

// Verificar qué hay en sueldo_conceptos
$stmt = $pdo->prepare("
    SELECT sc.id, sc.monto, sc.es_porcentaje, sc.formula, c.id as concepto_id, c.nombre, c.tipo
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
    ORDER BY c.tipo DESC
");
$stmt->execute([$empleado['id'], $mes]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p><strong>Empleado:</strong> {$empleado['nombre']}</p>";
echo "<p><strong>Mes:</strong> {$mes}</p>";

if (empty($conceptos)) {
    echo "<p style='color: red;'>❌ No hay conceptos en sueldo_conceptos</p>";
} else {
    echo "<h3>Conceptos cargados:</h3>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Concepto</th><th>Tipo</th><th>Monto</th><th>Es %</th><th>Fórmula</th></tr>";
    foreach ($conceptos as $c) {
        echo "<tr>";
        echo "<td>{$c['nombre']}</td>";
        echo "<td>{$c['tipo']}</td>";
        echo "<td>\${$c['monto']}</td>";
        echo "<td>" . ($c['es_porcentaje'] ? 'Sí' : 'No') . "</td>";
        echo "<td>" . ($c['formula'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Verificar la plantilla_items
echo "<h3>Verificar plantilla_items para 'Aporte No Remunerativo':</h3>";

$stmt = $pdo->query("
    SELECT pi.*, c.nombre, c.tipo
    FROM plantilla_items pi
    JOIN conceptos c ON pi.concepto_id = c.id
    WHERE c.nombre = 'Aporte No Remunerativo'
");
$plantilla_item = $stmt->fetch(PDO::FETCH_ASSOC);

if ($plantilla_item) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Concepto</th><th>Tipo</th><th>Valor Fijo</th><th>Fórmula</th><th>Es %</th></tr>";
    echo "<tr>";
    echo "<td>{$plantilla_item['nombre']}</td>";
    echo "<td>{$plantilla_item['tipo']}</td>";
    echo "<td>\${$plantilla_item['valor_fijo']}</td>";
    echo "<td>" . ($plantilla_item['formula'] ?? 'N/A') . "</td>";
    echo "<td>" . ($plantilla_item['es_porcentaje'] ? 'Sí' : 'No') . "</td>";
    echo "</tr>";
    echo "</table>";
}

// Si el aporte no está cargado, cargarlo manualmente
$aporte_encontrado = false;
foreach ($conceptos as $c) {
    if ($c['nombre'] === 'Aporte No Remunerativo') {
        $aporte_encontrado = true;
        break;
    }
}

if (!$aporte_encontrado && $plantilla_item) {
    echo "<p style='color: orange;'>⚠️ El aporte no está en sueldo_conceptos, pero existe en plantilla_items</p>";
    echo "<p>Cargando aporte manualmente...</p>";
    
    $stmt = $pdo->prepare("
        INSERT INTO sueldo_conceptos (empleado_id, concepto_id, mes, monto, formula, es_porcentaje)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE monto = VALUES(monto)
    ");
    
    $stmt->execute([
        $empleado['id'],
        $plantilla_item['concepto_id'],
        $mes,
        $plantilla_item['valor_fijo'],
        $plantilla_item['formula'],
        $plantilla_item['es_porcentaje']
    ]);
    
    echo "<p style='color: green;'>✓ Aporte cargado: \${$plantilla_item['valor_fijo']}</p>";
}

echo "<br><br>";
echo "<h2>Cálculo Final del Sueldo:</h2>";

// Recalcular
$stmt = $pdo->prepare("
    SELECT sc.monto, c.tipo
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
");
$stmt->execute([$empleado['id'], $mes]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$bonificaciones = 0;
$descuentos = 0;

foreach ($conceptos as $c) {
    if ($c['tipo'] === 'bonificacion') {
        $bonificaciones += $c['monto'];
    } else {
        $descuentos += $c['monto'];
    }
}

$sueldo_total = $empleado['sueldo_base'] + $bonificaciones - $descuentos;

echo "<ul>";
echo "<li>Sueldo Base: \${$empleado['sueldo_base']}</li>";
echo "<li>Bonificaciones: +\${$bonificaciones}</li>";
echo "<li>Descuentos (aportes): -\${$descuentos}</li>";
echo "<li><strong style='color: green;'>TOTAL A PAGAR: \${$sueldo_total}</strong></li>";
echo "</ul>";

// Verificar pagos realizados
$stmt = $pdo->prepare("
    SELECT SUM(monto_pagado) as total_pagado
    FROM pagos_sueldos_parciales
    WHERE empleado_id = ? AND mes_pago = ?
");
$stmt->execute([$empleado['id'], $mes]);
$pagos = $stmt->fetch(PDO::FETCH_ASSOC);
$total_pagado = $pagos['total_pagado'] ?? 0;

echo "<h3>Estado de Pagos:</h3>";
echo "<ul>";
echo "<li>Sueldo Total: \${$sueldo_total}</li>";
echo "<li>Ya Pagado: \${$total_pagado}</li>";
echo "<li><strong>Queda por Pagar: \$" . ($sueldo_total - $total_pagado) . "</strong></li>";
echo "</ul>";

if ($total_pagado >= $sueldo_total) {
    echo "<p style='color: red; font-weight: bold;'>❌ Ya se pagó el total o más</p>";
} else {
    echo "<p style='color: green; font-weight: bold;'>✓ Se puede pagar hasta \$" . ($sueldo_total - $total_pagado) . "</p>";
}

echo "<br><br>";
echo '<a href="debug_sueldo_ana.php" class="btn btn-secondary">Volver</a>';
?>
