<?php
require '../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

// parámetros opcionales: tipo=materiales|productos|todos (def. todos)
// alerta=bajo_minimo|negativo|sin_stock|todos (def. bajo_minimo)
// buscar=textual en nombre

$tipo = $_GET['tipo'] ?? 'todos';
$alerta = $_GET['alerta'] ?? 'bajo_minimo';
$buscar = trim($_GET['buscar'] ?? '');

// obtener columnas para saber si existen stock y stock_minimo
function cols($pdo, $tabla) {
    $stmt = $pdo->query("SHOW COLUMNS FROM {$tabla}");
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
}
$cols_mat = cols($pdo, 'ecommerce_materiales');
$cols_prod = cols($pdo, 'ecommerce_productos');

$items = [];

if ($tipo === 'todos' || $tipo === 'materiales') {
    $where = ['1=1'];
    $params = [];
    if ($buscar !== '') {
        $where[] = 'nombre LIKE ?';
        $params[] = "%$buscar%";
    }
    $sql = "SELECT 'material' as tipo, id, nombre, ";
    $sql .= (in_array('stock',$cols_mat,true)?'stock':'0')." as stock, ";
    $sql .= (in_array('stock_minimo',$cols_mat,true)?'stock_minimo':'0')." as stock_minimo, ";
    $sql .= "unidad_medida, tipo_origen ";
    $sql .= "FROM ecommerce_materiales WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC));
}
if ($tipo === 'todos' || $tipo === 'productos') {
    $where = ['1=1'];
    $params = [];
    if ($buscar !== '') {
        $where[] = 'nombre LIKE ?';
        $params[] = "%$buscar%";
    }
    $sql = "SELECT 'producto' as tipo, id, nombre, ";
    $sql .= (in_array('stock',$cols_prod,true)?'stock':'0')." as stock, ";
    $sql .= (in_array('stock_minimo',$cols_prod,true)?'stock_minimo':'0')." as stock_minimo, ";
    $sql .= "'unidad' as unidad_medida, tipo_origen ";
    $sql .= "FROM ecommerce_productos WHERE " . implode(' AND ', $where);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC));
}

// filtrar por alerta
if ($alerta !== 'todos') {
    $items = array_filter($items, function($it) use ($alerta) {
        $st = floatval($it['stock']);
        $min = floatval($it['stock_minimo']);
        $estado = 'normal';
        if ($st < 0) $estado = 'negativo';
        elseif ($st == 0) $estado = 'sin_stock';
        elseif ($st <= $min) $estado = 'bajo_minimo';
        return $estado === $alerta;
    });
}

echo json_encode(['success'=>true,'items'=>array_values($items)]);
