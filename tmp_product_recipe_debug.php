<?php
require __DIR__ . '/config.php';
$pid = 103;
$prod = $pdo->query('SELECT id, nombre, precio_base, es_material, usa_receta FROM ecommerce_productos WHERE id = ' . (int)$pid)->fetch(PDO::FETCH_ASSOC);
var_export($prod);
echo PHP_EOL;
echo '--- recipe ---' . PHP_EOL;
$rec = $pdo->query('SELECT * FROM ecommerce_producto_recetas_productos WHERE producto_id = ' . (int)$pid)->fetchAll(PDO::FETCH_ASSOC);
var_export($rec);
echo PHP_EOL;
foreach ($rec as $row) {
    $mid = (int)($row['material_producto_id'] ?? 0);
    $m = $pdo->query('SELECT id, nombre, precio_base, es_material, stock FROM ecommerce_productos WHERE id = ' . $mid)->fetch(PDO::FETCH_ASSOC);
    echo 'material ' . $mid . ' => '; var_export($m); echo PHP_EOL;
}
