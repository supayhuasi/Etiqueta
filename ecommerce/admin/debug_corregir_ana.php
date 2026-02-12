<?php
require '../../config.php';

echo "<h2>Verificar y Corregir Concepto 'Aporte No Remunerativo'</h2>";

// Obtener concepto
$stmt = $pdo->query("SELECT id, nombre, tipo FROM conceptos WHERE nombre = 'Aporte No Remunerativo'");
$concepto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$concepto) {
    echo "<p style='color: red;'>❌ Concepto no existe</p>";
    echo "<p>Creando concepto 'Aporte No Remunerativo' como DESCUENTO...</p>";
    
    $stmt = $pdo->prepare("
        INSERT INTO conceptos (nombre, tipo, descripcion)
        VALUES (?, ?, ?)
    ");
    $stmt->execute(['Aporte No Remunerativo', 'descuento', 'Aporte no remunerativo']);
    
    echo "<p style='color: green;'>✓ Concepto creado correctamente como DESCUENTO</p>";
} else {
    echo "<p>Concepto encontrado:</p>";
    echo "<ul>";
    echo "<li>ID: {$concepto['id']}</li>";
    echo "<li>Nombre: {$concepto['nombre']}</li>";
    echo "<li>Tipo: {$concepto['tipo']}</li>";
    echo "</ul>";
    
    if ($concepto['tipo'] === 'bonificacion') {
        echo "<p style='color: red;'>⚠️ ERROR: El tipo es BONIFICACION, pero debería ser DESCUENTO</p>";
        echo "<p>Corrigiendo...</p>";
        
        $stmt = $pdo->prepare("UPDATE conceptos SET tipo = ? WHERE id = ?");
        $stmt->execute(['descuento', $concepto['id']]);
        
        echo "<p style='color: green;'>✓ Concepto corregido a DESCUENTO</p>";
    } else {
        echo "<p style='color: green;'>✓ El tipo es correcto (DESCUENTO)</p>";
    }
}

echo "<br><br>";
echo "<h2>Ahora cargar la plantilla para Ana Dominguez</h2>";

// Obtener Ana
$stmt = $pdo->prepare("SELECT id, nombre, sueldo_base FROM empleados WHERE LOWER(nombre) LIKE ?");
$stmt->execute(['%ana%']);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if ($empleado) {
    echo "<p>Empleado: {$empleado['nombre']} (ID: {$empleado['id']})</p>";
    echo "<p>Sueldo Base: \${$empleado['sueldo_base']}</p>";
    
    // Obtener plantilla Standard
    $stmt = $pdo->query("SELECT id FROM plantillas_conceptos WHERE nombre = 'Standard'");
    $plantilla = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($plantilla) {
        echo "<p>Plantilla: Standard (ID: {$plantilla['id']})</p>";
        
        // Obtener items de la plantilla
        $stmt = $pdo->prepare("SELECT * FROM plantilla_items WHERE plantilla_id = ?");
        $stmt->execute([$plantilla['id']]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Insertar conceptos para febrero 2026
        $mes = '2026-02';
        $stmt_insert = $pdo->prepare("
            INSERT INTO sueldo_conceptos (empleado_id, concepto_id, mes, monto, formula, es_porcentaje)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE monto = VALUES(monto), formula = VALUES(formula), es_porcentaje = VALUES(es_porcentaje)
        ");
        
        foreach ($items as $item) {
            $monto = $item['valor_fijo'] ?? 0;
            $stmt_insert->execute([
                $empleado['id'],
                $item['concepto_id'],
                $mes,
                $monto,
                $item['formula'],
                $item['es_porcentaje']
            ]);
        }
        
        echo "<p style='color: green;'>✓ Conceptos de plantilla cargados para febrero 2026</p>";
        
        // Mostrar resumen del sueldo
        echo "<h3>Cálculo actualizado del sueldo:</h3>";
        
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
        echo "<li>Descuentos (incluye aportes): -\${$descuentos}</li>";
        echo "<li><strong style='color: green;'>Sueldo Total Neto: \${$sueldo_total}</strong></li>";
        echo "</ul>";
        
        // Comparar con pagos realizados
        $stmt = $pdo->prepare("
            SELECT SUM(monto_pagado) as total_pagado
            FROM pagos_sueldos_parciales
            WHERE empleado_id = ? AND mes_pago = ?
        ");
        $stmt->execute([$empleado['id'], $mes]);
        $pagos = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_pagado = $pagos['total_pagado'] ?? 0;
        
        echo "<h3>Estado de pagos:</h3>";
        echo "<ul>";
        echo "<li>Total Sueldo: \${$sueldo_total}</li>";
        echo "<li>Ya Pagado: \${$total_pagado}</li>";
        echo "<li><strong style='color: " . ($total_pagado <= $sueldo_total ? 'green' : 'red') . ";'>Diferencia: \$" . ($sueldo_total - $total_pagado) . "</strong></li>";
        echo "</ul>";
        
        if ($total_pagado <= $sueldo_total) {
            echo "<p style='color: green; font-weight: bold;'>✓ Ahora sí se puede registrar el pago correctamente</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>⚠️ Aún hay diferencia. Pagaron más de lo que corresponde.</p>";
        }
    }
}

echo "<br><br>";
echo '<a href="debug_sueldo_ana.php" class="btn btn-secondary">Volver al debug</a>';
?>
