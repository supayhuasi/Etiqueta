<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$mes = $_GET['mes'] ?? ''; // formato YYYY-MM
if ($mes === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Parámetro mes requerido (YYYY-MM)']);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT 
            COALESCE(SUM(total),0) as total_ventas,
            COALESCE(SUM(CASE WHEN estado <> 'pagado' THEN total ELSE 0 END),0) as falta_cobrar
         FROM ecommerce_pedidos
         WHERE DATE_FORMAT(fecha_pedido,'%Y-%m') = ?"
    );
    $stmt->execute([$mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'mes'=>$mes,'total_ventas'=>$row['total_ventas'],'falta_cobrar'=>$row['falta_cobrar']]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno']);
}
