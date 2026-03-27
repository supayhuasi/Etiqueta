<?php
require 'includes/header.php';

$producto_id = $_GET['producto_id'] ?? 0;
if ($producto_id <= 0) die("Producto no especificado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ? AND tipo_precio = 'variable'");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$producto) die("Producto variable no encontrado");

// Obtener matriz de precios
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_matriz_precios 
    WHERE producto_id = ? 
    ORDER BY alto_cm, ancho_cm
");
$stmt->execute([$producto_id]);
$matriz = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="matriz_precios_' . $producto_id . '.xls"');

echo "Alto (cm)\tAncho (cm)\tÁrea (m²)\tPrecio ($)\tStock\n";
foreach ($matriz as $item) {
    $area = number_format(($item['alto_cm'] * $item['ancho_cm']) / 10000, 2);
    $precio = number_format($item['precio'], 2);
    echo $item['alto_cm'] . "\t" . $item['ancho_cm'] . "\t" . $area . "\t" . $precio . "\t" . $item['stock'] . "\n";
}
exit;
