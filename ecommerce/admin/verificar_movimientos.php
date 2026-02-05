<?php
require 'includes/header.php';

echo "<div class='container mt-4'>";
echo "<h2>Verificación de Movimientos de Inventario</h2>";

try {
    // 1. Verificar estructura de la tabla
    echo "<div class='card mb-3'>";
    echo "<div class='card-header bg-primary text-white'><h5>1. Estructura de la tabla</h5></div>";
    echo "<div class='card-body'>";
    
    $stmt = $pdo->query("DESCRIBE ecommerce_inventario_movimientos");
    $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table class='table table-sm table-bordered'>";
    echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Key</th><th>Default</th></tr></thead><tbody>";
    foreach ($columnas as $col) {
        echo "<tr>";
        echo "<td><code>" . htmlspecialchars($col['Field']) . "</code></td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "</div></div>";
    
    // 2. Verificar registros existentes
    echo "<div class='card mb-3'>";
    echo "<div class='card-header bg-info text-white'><h5>2. Registros en la tabla</h5></div>";
    echo "<div class='card-body'>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos");
    $total = $stmt->fetch()['total'];
    echo "<p class='alert alert-info'>Total de movimientos registrados: <strong>$total</strong></p>";
    
    if ($total > 0) {
        // Mostrar últimos 10
        $stmt = $pdo->query("
            SELECT * FROM ecommerce_inventario_movimientos 
            ORDER BY id DESC 
            LIMIT 10
        ");
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h6>Últimos 10 movimientos:</h6>";
        echo "<div class='table-responsive'>";
        echo "<table class='table table-sm table-striped'>";
        echo "<thead><tr>";
        foreach (array_keys($movimientos[0]) as $columna) {
            echo "<th>" . htmlspecialchars($columna) . "</th>";
        }
        echo "</tr></thead><tbody>";
        
        foreach ($movimientos as $mov) {
            echo "<tr>";
            foreach ($mov as $valor) {
                echo "<td>" . htmlspecialchars($valor ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</tbody></table>";
        echo "</div>";
    } else {
        echo "<div class='alert alert-warning'>";
        echo "<strong>No hay movimientos registrados.</strong><br>";
        echo "Los movimientos se registran automáticamente cuando:<br>";
        echo "- Se crea una orden de producción con materiales<br>";
        echo "- Se realiza un ajuste de inventario<br>";
        echo "- Se procesa una venta con descuento de stock";
        echo "</div>";
    }
    echo "</div></div>";
    
    // 3. Verificar si hay órdenes de producción
    echo "<div class='card mb-3'>";
    echo "<div class='card-header bg-success text-white'><h5>3. Órdenes de producción</h5></div>";
    echo "<div class='card-body'>";
    
    $stmt = $pdo->query("
        SELECT id, pedido_id, estado, materiales_descontados, fecha_creacion 
        FROM ecommerce_ordenes_produccion 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($ordenes)) {
        echo "<table class='table table-sm'>";
        echo "<thead><tr><th>ID Orden</th><th>Pedido ID</th><th>Estado</th><th>Materiales Descontados</th><th>Fecha</th></tr></thead>";
        echo "<tbody>";
        foreach ($ordenes as $orden) {
            $descontado = $orden['materiales_descontados'] ? '<span class="badge bg-success">SÍ</span>' : '<span class="badge bg-danger">NO</span>';
            echo "<tr>";
            echo "<td>{$orden['id']}</td>";
            echo "<td>{$orden['pedido_id']}</td>";
            echo "<td>{$orden['estado']}</td>";
            echo "<td>$descontado</td>";
            echo "<td>{$orden['fecha_creacion']}</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
    } else {
        echo "<div class='alert alert-info'>No hay órdenes de producción creadas aún.</div>";
    }
    echo "</div></div>";
    
    // 4. Test de consulta
    echo "<div class='card mb-3'>";
    echo "<div class='card-header bg-warning'><h5>4. Test de consulta</h5></div>";
    echo "<div class='card-body'>";
    
    // Obtener un producto/material para testing
    $stmt = $pdo->query("SELECT id, nombre FROM ecommerce_productos WHERE stock > 0 LIMIT 1");
    $test_item = $stmt->fetch();
    
    if ($test_item) {
        echo "<p>Probando consulta para producto: <strong>{$test_item['nombre']}</strong> (ID: {$test_item['id']})</p>";
        
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_inventario_movimientos 
            WHERE producto_id = ?
            LIMIT 5
        ");
        $stmt->execute([$test_item['id']]);
        $test_movs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($test_movs)) {
            echo "<div class='alert alert-success'>✓ Se encontraron " . count($test_movs) . " movimientos</div>";
        } else {
            echo "<div class='alert alert-info'>No se encontraron movimientos para este item (normal si nunca se usó)</div>";
        }
        
        echo "<p><a href='inventario_movimientos.php?tipo=producto&id={$test_item['id']}' target='_blank' class='btn btn-primary'>Ver historial de este producto</a></p>";
    }
    
    echo "</div></div>";
    
    // 5. Acciones
    echo "<div class='card mb-3'>";
    echo "<div class='card-header bg-secondary text-white'><h5>5. Acciones disponibles</h5></div>";
    echo "<div class='card-body'>";
    echo "<a href='inventario.php' class='btn btn-primary'>Ver Inventario</a> ";
    echo "<a href='ordenes_produccion.php' class='btn btn-success'>Ver Órdenes de Producción</a> ";
    echo "<a href='diagnostico_inventario.php' class='btn btn-info'>Diagnóstico Completo</a>";
    echo "</div></div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

require 'includes/footer.php';
?>
