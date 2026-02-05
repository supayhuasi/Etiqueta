<?php
require 'includes/header.php';

echo "<div class='container mt-4'>";
echo "<h2>Diagnóstico de Inventario</h2>";

// Verificar si la tabla existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_inventario_movimientos'");
    $tabla_existe = $stmt->fetch();
    
    if ($tabla_existe) {
        echo "<div class='alert alert-success'>✓ La tabla ecommerce_inventario_movimientos existe</div>";
        
        // Mostrar estructura de la tabla
        $stmt = $pdo->query("DESCRIBE ecommerce_inventario_movimientos");
        $columnas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<h4>Estructura de la tabla:</h4>";
        echo "<table class='table table-bordered table-sm'>";
        echo "<thead><tr><th>Campo</th><th>Tipo</th><th>Nulo</th><th>Default</th></tr></thead>";
        echo "<tbody>";
        foreach ($columnas as $col) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        
        // Contar registros
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos");
        $total = $stmt->fetch()['total'];
        echo "<div class='alert alert-info'>Total de movimientos registrados: <strong>$total</strong></div>";
        
        // Mostrar últimos 5 movimientos
        if ($total > 0) {
            $stmt = $pdo->query("SELECT * FROM ecommerce_inventario_movimientos ORDER BY fecha_movimiento DESC LIMIT 5");
            $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo "<h4>Últimos 5 movimientos:</h4>";
            echo "<table class='table table-striped table-sm'>";
            echo "<thead><tr><th>ID</th><th>Tipo Item</th><th>Item ID</th><th>Tipo Mov</th><th>Cantidad</th><th>Referencia</th><th>Fecha</th></tr></thead>";
            echo "<tbody>";
            foreach ($movimientos as $mov) {
                echo "<tr>";
                echo "<td>" . $mov['id'] . "</td>";
                echo "<td>" . $mov['tipo_item'] . "</td>";
                echo "<td>" . $mov['item_id'] . "</td>";
                echo "<td>" . $mov['tipo_movimiento'] . "</td>";
                echo "<td>" . number_format($mov['cantidad'], 2) . "</td>";
                echo "<td>" . htmlspecialchars($mov['referencia'] ?? '-') . "</td>";
                echo "<td>" . $mov['fecha_movimiento'] . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
        }
        
    } else {
        echo "<div class='alert alert-danger'>❌ La tabla ecommerce_inventario_movimientos NO existe</div>";
        echo "<div class='alert alert-warning'>";
        echo "<strong>Solución:</strong> Ejecuta el script de setup:<br>";
        echo "<a href='../setup_inventario_avanzado.php' class='btn btn-primary mt-2'>Ejecutar Setup de Inventario Avanzado</a>";
        echo "</div>";
    }
    
    // Verificar tabla ecommerce_materiales
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_materiales");
    $columnas_mat = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Columnas en ecommerce_materiales:</h4>";
    echo "<div class='alert alert-secondary'>";
    echo "Tiene columna 'stock': " . (in_array('stock', $columnas_mat) ? '<span class="text-success">✓ SÍ</span>' : '<span class="text-danger">✗ NO</span>') . "<br>";
    echo "Tiene columna 'tipo_origen': " . (in_array('tipo_origen', $columnas_mat) ? '<span class="text-success">✓ SÍ</span>' : '<span class="text-danger">✗ NO</span>');
    echo "</div>";
    
    // Verificar tabla ecommerce_productos
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_productos");
    $columnas_prod = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h4>Columnas en ecommerce_productos:</h4>";
    echo "<div class='alert alert-secondary'>";
    echo "Tiene columna 'stock': " . (in_array('stock', $columnas_prod) ? '<span class="text-success">✓ SÍ</span>' : '<span class="text-danger">✗ NO</span>') . "<br>";
    echo "Tiene columna 'tipo_origen': " . (in_array('tipo_origen', $columnas_prod) ? '<span class="text-success">✓ SÍ</span>' : '<span class="text-danger">✗ NO</span>');
    echo "</div>";
    
    // Test de acceso a inventario_movimientos.php
    echo "<h4>Test de acceso:</h4>";
    echo "<div class='alert alert-info'>";
    echo "Probá estos enlaces:<br>";
    echo "<a href='inventario_movimientos.php?tipo=producto&id=2' target='_blank' class='btn btn-sm btn-outline-primary mt-2'>Ver movimientos del producto ID 2</a><br>";
    echo "<a href='inventario_movimientos.php?tipo=material&id=1' target='_blank' class='btn btn-sm btn-outline-primary mt-2'>Ver movimientos del material ID 1</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div>";

require 'includes/footer.php';
?>
