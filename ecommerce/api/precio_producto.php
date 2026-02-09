<?php
require '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$producto_id = isset($_GET['producto_id']) ? intval($_GET['producto_id']) : 0;
$alto = isset($_GET['alto']) ? intval($_GET['alto']) : 0;
$ancho = isset($_GET['ancho']) ? intval($_GET['ancho']) : 0;

if ($producto_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'producto_id inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre, tipo_precio, precio_base FROM ecommerce_productos WHERE id = ?");
    $stmt->execute([$producto_id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$producto) {
        http_response_code(404);
        echo json_encode(['error' => 'Producto no encontrado']);
        exit;
    }

    $precio = (float)$producto['precio_base'];
    $medidas = null;
    $info = 'Precio base';

    if ($producto['tipo_precio'] === 'variable') {
        if ($alto <= 0 || $ancho <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Debe especificar alto y ancho']);
            exit;
        }

        $stmt = $pdo->prepare("
            SELECT alto_cm, ancho_cm, precio
            FROM ecommerce_matriz_precios
            WHERE producto_id = ?
            ORDER BY ABS(alto_cm - ?) + ABS(ancho_cm - ?)
            LIMIT 1
        ");
        $stmt->execute([$producto_id, $alto, $ancho]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => 'No hay precios disponibles para ese producto']);
            exit;
        }

        $precio = (float)$row['precio'];
        $medidas = [
            'alto_cm' => (int)$row['alto_cm'],
            'ancho_cm' => (int)$row['ancho_cm']
        ];
        $info = 'Precio por matriz';
    }

    echo json_encode([
        'producto_id' => (int)$producto['id'],
        'producto' => $producto['nombre'],
        'tipo_precio' => $producto['tipo_precio'],
        'alto' => $alto,
        'ancho' => $ancho,
        'precio' => $precio,
        'moneda' => 'ARS',
        'medidas_originales' => $medidas,
        'info' => $info
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno']);
}
