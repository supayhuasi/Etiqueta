<?php
require '../../config.php';

// Obtener empleado
$stmt = $pdo->prepare("SELECT id, nombre, sueldo_base FROM empleados WHERE LOWER(nombre) LIKE ?");
$stmt->execute(['%ana%']);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

echo "<h2>Plantillas de {$empleado['nombre']}</h2>";

// Obtener plantilla asignada
$stmt = $pdo->prepare("
    SELECT pc.id, pc.nombre, pc.descripcion
    FROM empleado_plantilla ep
    JOIN plantillas_conceptos pc ON ep.plantilla_id = pc.id
    WHERE ep.empleado_id = ?
");
$stmt->execute([$empleado['id']]);
$plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

if ($plantilla) {
    echo "<p>✓ Plantilla asignada: <strong>{$plantilla['nombre']}</strong></p>";
    echo "<p>Descripción: {$plantilla['descripcion']}</p>";
    
    // Obtener conceptos de la plantilla
    $stmt = $pdo->prepare("
        SELECT pi.*, c.nombre, c.tipo
        FROM plantilla_items pi
        JOIN conceptos c ON pi.concepto_id = c.id
        WHERE pi.plantilla_id = ?
        ORDER BY pi.orden
    ");
    $stmt->execute([$plantilla['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($items)) {
        echo "<h3>Conceptos en la plantilla:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Concepto</th><th>Tipo</th><th>Valor Fijo</th><th>Fórmula</th><th>Es %</th></tr>";
        foreach ($items as $item) {
            echo "<tr>";
            echo "<td>{$item['nombre']}</td>";
            echo "<td>{$item['tipo']}</td>";
            echo "<td>" . ($item['valor_fijo'] ?? 'N/A') . "</td>";
            echo "<td>" . ($item['formula'] ?? 'N/A') . "</td>";
            echo "<td>" . ($item['es_porcentaje'] ? 'Sí' : 'No') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        echo "<h3>Cargar conceptos de esta plantilla para febrero 2026:</h3>";
        echo '<form method="POST">';
        echo '<input type="hidden" name="empleado_id" value="' . $empleado['id'] . '">';
        echo '<input type="hidden" name="plantilla_id" value="' . $plantilla['id'] . '">';
        echo '<button type="submit" class="btn btn-success">Aplicar Plantilla a Febrero</button>';
        echo '</form>';
    }
} else {
    echo "<p style='color: red;'>⚠️ No tiene plantilla asignada</p>";
    
    // Listar plantillas disponibles
    $stmt = $pdo->query("SELECT id, nombre, descripcion FROM plantillas_conceptos WHERE activo = 1");
    $plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($plantillas)) {
        echo "<h3>Plantillas disponibles:</h3>";
        echo "<ul>";
        foreach ($plantillas as $p) {
            echo "<li><strong>{$p['nombre']}</strong>: {$p['descripcion']}</li>";
        }
        echo "</ul>";
    }
}

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plantilla_id = $_POST['plantilla_id'];
    $empleado_id = $_POST['empleado_id'];
    $mes = '2026-02';
    
    // Obtener items de la plantilla
    $stmt = $pdo->prepare("SELECT * FROM plantilla_items WHERE plantilla_id = ?");
    $stmt->execute([$plantilla_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Insertar conceptos
    $stmt_insert = $pdo->prepare("
        INSERT INTO sueldo_conceptos (empleado_id, concepto_id, mes, monto, formula, es_porcentaje)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE monto = VALUES(monto), formula = VALUES(formula), es_porcentaje = VALUES(es_porcentaje)
    ");
    
    foreach ($items as $item) {
        $monto = $item['valor_fijo'] ?? 0;
        $stmt_insert->execute([
            $empleado_id,
            $item['concepto_id'],
            $mes,
            $monto,
            $item['formula'],
            $item['es_porcentaje']
        ]);
    }
    
    echo "<div style='color: green; font-weight: bold;'>✓ Plantilla aplicada a febrero 2026</div>";
    echo '<a href="debug_sueldo_ana.php">Volver al debug</a>';
}
?>
