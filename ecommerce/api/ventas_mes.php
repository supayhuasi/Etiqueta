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

if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Formato de mes inválido. Use YYYY-MM']);
    exit;
}

try {
    $inicioMes = $mes . '-01 00:00:00';
    $finMes = date('Y-m-d 23:59:59', strtotime($mes . '-01 +1 month -1 day'));

    $columnas = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos")->fetchAll(PDO::FETCH_ASSOC);
    $columnas_nombres = array_column($columnas, 'Field');
    if (in_array('fecha_pedido', $columnas_nombres, true)) {
        $fecha_columna = 'fecha_pedido';
    } elseif (in_array('fecha_creacion', $columnas_nombres, true)) {
        $fecha_columna = 'fecha_creacion';
    } elseif (in_array('fecha', $columnas_nombres, true)) {
        $fecha_columna = 'fecha';
    } elseif (in_array('created_at', $columnas_nombres, true)) {
        $fecha_columna = 'created_at';
    } else {
        throw new Exception('No se encontró columna de fecha en ecommerce_pedidos');
    }

    $stmtVentas = $pdo->prepare(
        "SELECT COALESCE(SUM(p.total),0) as total_ventas
         FROM ecommerce_pedidos p
         WHERE p.$fecha_columna BETWEEN ? AND ?
           AND p.estado NOT IN ('cancelado')"
    );
    $stmtVentas->execute([$inicioMes, $finMes]);
    $total_ventas = (float)($stmtVentas->fetch(PDO::FETCH_ASSOC)['total_ventas'] ?? 0);

    $total_cobrado = 0.0;
    $tabla_pagos = $pdo->query("SHOW TABLES LIKE 'ecommerce_pedido_pagos'")->rowCount() > 0;
    if ($tabla_pagos) {
        $stmtCobrado = $pdo->prepare(
            "SELECT COALESCE(SUM(pp.monto),0) as total_cobrado
             FROM ecommerce_pedido_pagos pp
             INNER JOIN ecommerce_pedidos p ON p.id = pp.pedido_id
             WHERE p.$fecha_columna BETWEEN ? AND ?
               AND p.estado NOT IN ('cancelado')"
        );
        $stmtCobrado->execute([$inicioMes, $finMes]);
        $total_cobrado = (float)($stmtCobrado->fetch(PDO::FETCH_ASSOC)['total_cobrado'] ?? 0);
    }

    $saldo = round($total_ventas - $total_cobrado, 2);
    $falta_cobrar = max(0, $saldo);

    echo json_encode([
        'success' => true,
        'mes' => $mes,
        'total_ventas' => round($total_ventas, 2),
        'total_cobrado' => round($total_cobrado, 2),
        'saldo' => $saldo,
        'falta_cobrar' => $falta_cobrar
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno']);
}
