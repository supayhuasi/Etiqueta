<?php
require 'includes/header.php';

// Obtener producto por nombre
$nombre = 'Cortina Blackout Meret';
$stmt = $pdo->prepare("SELECT id FROM ecommerce_productos WHERE nombre = ?");
$stmt->execute([$nombre]);
$producto = $stmt->fetch();

if (!$producto) {
    die("Producto no encontrado");
}

$producto_id = $producto['id'];

echo "<h2>Debug: $nombre (ID: $producto_id)</h2>";

// Query que usa compras_crear.php
$stmt = $pdo->query("
    SELECT p.id AS producto_id, o.id AS opcion_id, o.nombre AS opcion_nombre, o.color
    FROM ecommerce_atributo_opciones o
    JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
    JOIN ecommerce_productos p ON p.id = a.producto_id
    WHERE a.tipo = 'select'
      AND LOWER(a.nombre) LIKE '%color%'
    ORDER BY p.nombre, o.nombre
");

$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
echo "Total de filas: " . count($resultados) . "\n\n";
foreach ($resultados as $row) {
    if ($row['producto_id'] == $producto_id) {
        echo "✓ ENCONTRADO - Opción ID: " . $row['opcion_id'] . ", Nombre: " . $row['opcion_nombre'] . ", Color: '" . $row['color'] . "'\n";
    }
}

echo "\n\nTodas las opciones del producto con 'Color' en atributo:\n";
foreach ($resultados as $row) {
    if ($row['producto_id'] == $producto_id) {
        echo "Producto: " . $row['producto_id'] . " | Opción: " . $row['opcion_nombre'] . "\n";
    }
}

// Verificar directamente atributos y opciones
echo "\n\n=== VERIFICACIÓN DIRECTA ===\n";
$stmt = $pdo->prepare("
    SELECT a.id, a.nombre as attr_nombre, a.tipo, COUNT(o.id) as total_opciones
    FROM ecommerce_producto_atributos a
    LEFT JOIN ecommerce_atributo_opciones o ON o.atributo_id = a.id
    WHERE a.producto_id = ?
    GROUP BY a.id
");
$stmt->execute([$producto_id]);
$attrs = $stmt->fetchAll();

foreach ($attrs as $attr) {
    echo "\nAtributo: {$attr['attr_nombre']} | Tipo: {$attr['tipo']} | Opciones: {$attr['total_opciones']}\n";
    
    if ($attr['tipo'] === 'select') {
        $stmt2 = $pdo->prepare("
            SELECT o.id, o.nombre, o.color
            FROM ecommerce_atributo_opciones o
            WHERE o.atributo_id = ?
            ORDER BY o.nombre
        ");
        $stmt2->execute([$attr['id']]);
        $opciones = $stmt2->fetchAll();
        
        foreach ($opciones as $opc) {
            echo "  - {$opc['nombre']} | Color: '{$opc['color']}'\n";
        }
    }
}

echo "</pre>";
echo "<br><br><a href='compras_crear.php' class='btn btn-primary'>Volver a Compras</a>";
?>
