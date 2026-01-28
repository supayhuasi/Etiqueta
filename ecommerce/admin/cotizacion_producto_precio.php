<?php
require '../../config.php';
header('Content-Type: application/json');

$producto_id = intval($_GET['producto_id'] ?? 0);
$ancho = floatval($_GET['ancho'] ?? 0);
$alto = floatval($_GET['alto'] ?? 0);

if ($producto_id <= 0) {
    echo json_encode(['error' => 'Producto no especificado']);
    exit;
}

// Obtener producto
$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ? AND activo = 1");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    echo json_encode(['error' => 'Producto no encontrado']);
    exit;
}

// Obtener atributos del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_atributos 
    WHERE producto_id = ? 
    ORDER BY orden
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar atributos con sus costos adicionales
$costo_atributos = 0;
$atributos_detalles = [];
foreach ($atributos as $attr) {
    $valor_attr = $_GET['attr_' . $attr['id']] ?? '';
    if (!empty($valor_attr)) {
        $costo_atributos += floatval($attr['costo_adicional']);
        $atributos_detalles[] = [
            'id' => $attr['id'],
            'nombre' => $attr['nombre'],
            'valor' => $valor_attr,
            'costo_adicional' => floatval($attr['costo_adicional'])
        ];
    }
}

$respuesta = [
    'id' => $producto['id'],
    'nombre' => $producto['nombre'],
    'descripcion' => $producto['descripcion_corta'],
    'tipo_precio' => $producto['tipo_precio'],
    'precio_base' => floatval($producto['precio_base']),
    'atributos' => $atributos_detalles,
    'costo_atributos' => $costo_atributos,
    'requiere_medidas' => $producto['tipo_precio'] === 'variable'
];

// Si es precio variable y se proporcionaron medidas
if ($producto['tipo_precio'] === 'variable' && $ancho > 0 && $alto > 0) {
    // Buscar en matriz de precios
    $stmt = $pdo->prepare("
        SELECT * FROM ecommerce_matriz_precios 
        WHERE producto_id = ? 
        ORDER BY alto_cm, ancho_cm
    ");
    $stmt->execute([$producto_id]);
    $matriz_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($matriz_precios)) {
        // Función para encontrar el precio más cercano
        $precio_encontrado = null;
        $distancia_minima = PHP_INT_MAX;
        
        foreach ($matriz_precios as $item) {
            $distancia = abs($item['alto_cm'] - $alto) + abs($item['ancho_cm'] - $ancho);
            if ($distancia < $distancia_minima) {
                $distancia_minima = $distancia;
                $precio_encontrado = [
                    'precio' => floatval($item['precio']),
                    'alto_original' => $item['alto_cm'],
                    'ancho_original' => $item['ancho_cm']
                ];
            }
        }
        
        if ($precio_encontrado) {
            $precio_base = $precio_encontrado['precio'];
            $respuesta['precio_matriz'] = $precio_base;
            $respuesta['medidas_redondeadas'] = "{$precio_encontrado['ancho_original']}x{$precio_encontrado['alto_original']} cm";
            $respuesta['precio_info'] = "Precio aproximado para {$ancho}x{$alto} cm (basado en {$precio_encontrado['ancho_original']}x{$precio_encontrado['alto_original']} cm)";
        } else {
            $precio_base = $producto['precio_base'];
            $respuesta['precio_info'] = "No hay precio exacto. Usando precio base.";
        }
    } else {
        $precio_base = $producto['precio_base'];
        $respuesta['precio_info'] = "Sin matriz de precios. Usando precio base.";
    }
} else {
    // Precio fijo o sin medidas especificadas
    $precio_base = $producto['precio_base'];
    $respuesta['precio_info'] = $producto['tipo_precio'] === 'variable' 
        ? "Ingrese medidas para calcular precio" 
        : "Precio fijo";
}

// Precio final = precio_base + costo_atributos
$respuesta['precio'] = $precio_base;
$respuesta['precio_con_atributos'] = $precio_base + $costo_atributos;

echo json_encode($respuesta);
