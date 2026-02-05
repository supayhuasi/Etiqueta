<?php
// Archivo de diagnóstico simple sin dependencias

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Test de Movimientos</h1>";

// 1. Verificar conexión a la base de datos
try {
    require '../config.php';
    echo "<p style='color: green;'>✓ Conexión a base de datos OK</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error en config.php: " . $e->getMessage() . "</p>";
    exit;
}

// 2. Verificar si la tabla existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_inventario_movimientos'");
    $existe = $stmt->fetch();
    if ($existe) {
        echo "<p style='color: green;'>✓ Tabla ecommerce_inventario_movimientos existe</p>";
    } else {
        echo "<p style='color: red;'>✗ Tabla ecommerce_inventario_movimientos NO existe</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error al verificar tabla: " . $e->getMessage() . "</p>";
}

// 3. Contar registros
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos");
    $resultado = $stmt->fetch();
    echo "<p style='color: blue;'>Total de movimientos en tabla: <strong>" . $resultado['total'] . "</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error al contar registros: " . $e->getMessage() . "</p>";
}

// 4. Mostrar últimos 5 registros
try {
    $stmt = $pdo->query("
        SELECT * FROM ecommerce_inventario_movimientos 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($registros)) {
        echo "<h2>Últimos 5 registros:</h2>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr>";
        foreach (array_keys($registros[0]) as $col) {
            echo "<th>$col</th>";
        }
        echo "</tr>";
        
        foreach ($registros as $reg) {
            echo "<tr>";
            foreach ($reg as $valor) {
                echo "<td>" . ($valor ?? 'NULL') . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: orange;'>⚠ No hay registros en la tabla</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error al obtener registros: " . $e->getMessage() . "</p>";
}

// 5. Verificar producto específico
try {
    $item_id = 14;
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos 
        WHERE producto_id = ?
    ");
    $stmt->execute([$item_id]);
    $resultado = $stmt->fetch();
    echo "<p style='color: blue;'>Movimientos para producto ID 14: <strong>" . $resultado['total'] . "</strong></p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

// 6. Verificar que el producto existe
try {
    $stmt = $pdo->prepare("SELECT id, nombre, stock FROM ecommerce_productos WHERE id = ?");
    $stmt->execute([14]);
    $producto = $stmt->fetch();
    
    if ($producto) {
        echo "<p style='color: green;'>✓ Producto ID 14 existe: " . htmlspecialchars($producto['nombre']) . " (Stock: " . $producto['stock'] . ")</p>";
    } else {
        echo "<p style='color: red;'>✗ Producto ID 14 NO existe</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>✗ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='inventario_movimientos.php?tipo=producto&id=14'>Ir a inventario_movimientos.php</a></p>";
?>
