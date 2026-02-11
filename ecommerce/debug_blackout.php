<?php
require 'config.php';

// Buscar producto Blackout Meret
$stmt = $pdo->prepare("SELECT id, nombre FROM ecommerce_productos WHERE LOWER(nombre) LIKE ?");
$stmt->execute(['%blackout%']);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    echo "❌ Producto 'Blackout Meret' no encontrado\n";
    exit;
}

echo "✅ Producto encontrado:\n";
echo "  ID: " . $producto['id'] . "\n";
echo "  Nombre: " . $producto['nombre'] . "\n\n";

$producto_id = $producto['id'];

// Buscar atributos del producto
$stmt = $pdo->prepare("SELECT id, nombre, tipo FROM ecommerce_producto_atributos WHERE producto_id = ?");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "📋 Atributos del producto:\n";
if (empty($atributos)) {
    echo "  ❌ Sin atributos\n";
} else {
    foreach ($atributos as $attr) {
        echo "  - ID: {$attr['id']}, Nombre: {$attr['nombre']}, Tipo: {$attr['tipo']}\n";
        
        // Buscar opciones si es select
        if ($attr['tipo'] === 'select') {
            $stmt2 = $pdo->prepare("SELECT id, nombre, color FROM ecommerce_atributo_opciones WHERE atributo_id = ?");
            $stmt2->execute([$attr['id']]);
            $opciones = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($opciones)) {
                echo "    ❌ Sin opciones\n";
            } else {
                foreach ($opciones as $op) {
                    echo "    • Opción: {$op['nombre']}, Color: " . ($op['color'] ?? 'N/A') . "\n";
                }
            }
        }
    }
}

echo "\n🔍 Verificar query de colores:\n";

// Ejecutar la query que usa compras_crear.php
$stmt = $pdo->query("
    SELECT p.id AS producto_id, o.id AS opcion_id, o.nombre AS opcion_nombre, o.color
    FROM ecommerce_atributo_opciones o
    JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
    JOIN ecommerce_productos p ON p.id = a.producto_id
    WHERE a.tipo = 'select'
      AND (
            LOWER(a.nombre) LIKE '%color%'
         OR (o.color IS NOT NULL AND o.color <> '')
         OR LOWER(o.nombre) LIKE '%color%'
         OR LOWER(o.nombre) REGEXP '(negro|blanco|rojo|azul|verde|gris|plata|dorado|amarillo|naranja|violeta|fucsia|rosa|celeste|turquesa|marr[oó]n|beige|crema|aqua)'
      )
    AND p.id = ?
    ORDER BY p.nombre, o.nombre
");
$stmt->execute([$producto_id]);
$colores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($colores)) {
    echo "  ❌ NO se detectó color en la query\n";
    echo "\n  Probando variantes:\n";
    
    // Probar cada condición por separado
    $condiciones = [
        "LOWER(a.nombre) LIKE '%color%'" => "Atributo contiene 'color'",
        "(o.color IS NOT NULL AND o.color <> '')" => "Opción tiene color hex",
        "LOWER(o.nombre) LIKE '%color%'" => "Opción contiene 'color'",
        "LOWER(o.nombre) REGEXP '(negro|blanco|rojo|azul|verde|gris|plata|dorado|amarillo|naranja|violeta|fucsia|rosa|celeste|turquesa|marr[oó]n|beige|crema|aqua)'" => "Opción contiene nombre de color"
    ];
    
    foreach ($condiciones as $cond => $desc) {
        $test_query = "
            SELECT COUNT(*) as cnt
            FROM ecommerce_atributo_opciones o
            JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
            WHERE a.tipo = 'select' AND a.producto_id = ? AND $cond
        ";
        $stmt2 = $pdo->prepare($test_query);
        $stmt2->execute([$producto_id]);
        $result = $stmt2->fetch(PDO::FETCH_ASSOC);
        echo "    " . ($result['cnt'] > 0 ? "✅" : "❌") . " $desc: " . $result['cnt'] . "\n";
    }
} else {
    echo "  ✅ Se encontraron " . count($colores) . " opción(es) de color:\n";
    foreach ($colores as $col) {
        echo "    • {$col['opcion_nombre']} - Color: {$col['color']}\n";
    }
}
?>
