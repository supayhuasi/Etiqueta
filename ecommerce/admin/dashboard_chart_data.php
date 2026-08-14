<?php
require 'includes/header.php';

header('Content-Type: application/json; charset=utf-8');

if (!$can_access('dashboard_principal')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'Acceso denegado']);
    exit;
}

function dashboard_chart_tabla_existe(PDO $pdo, string $tabla): bool
{
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tabla]);
    return $stmt->rowCount() > 0;
}

$metricas_permitidas = ['ventas', 'cobros', 'compras', 'gastos', 'sueldos', 'pedidos', 'cotizaciones'];
$metrics = array_values(array_intersect($_GET['metrics'] ?? [], $metricas_permitidas));
if (empty($metrics)) {
    echo json_encode(['ok' => false, 'msg' => 'Seleccioná al menos una métrica']);
    exit;
}

$granularidad = ($_GET['granularidad'] ?? 'dia') === 'mes' ? 'mes' : 'dia';

$desde = DateTime::createFromFormat('Y-m-d', (string)($_GET['desde'] ?? ''));
$hasta = DateTime::createFromFormat('Y-m-d', (string)($_GET['hasta'] ?? ''));
if (!$desde || !$hasta || $desde > $hasta) {
    echo json_encode(['ok' => false, 'msg' => 'Rango de fechas inválido']);
    exit;
}
$desde->setTime(0, 0, 0);
$hasta->setTime(23, 59, 59);

$dias_rango = (int)$desde->diff($hasta)->days;
if ($dias_rango > 730) {
    echo json_encode(['ok' => false, 'msg' => 'El rango máximo permitido es de 2 años']);
    exit;
}

$tramos = [];
if ($granularidad === 'mes') {
    $cursor = new DateTime($desde->format('Y-m-01'));
    $fin_mes_final = new DateTime($hasta->format('Y-m-01'));
    while ($cursor <= $fin_mes_final) {
        $tramo_inicio = clone $cursor;
        $tramo_fin = (clone $cursor)->modify('last day of this month')->setTime(23, 59, 59);
        if ($tramo_inicio < $desde) {
            $tramo_inicio = clone $desde;
        }
        if ($tramo_fin > $hasta) {
            $tramo_fin = clone $hasta;
        }
        $tramos[] = [$cursor->format('m/Y'), $tramo_inicio->format('Y-m-d H:i:s'), $tramo_fin->format('Y-m-d H:i:s')];
        $cursor->modify('+1 month');
    }
} else {
    $cursor = clone $desde;
    while ($cursor <= $hasta) {
        $tramo_inicio = (clone $cursor)->setTime(0, 0, 0);
        $tramo_fin = (clone $cursor)->setTime(23, 59, 59);
        $tramos[] = [$cursor->format('d/m'), $tramo_inicio->format('Y-m-d H:i:s'), $tramo_fin->format('Y-m-d H:i:s')];
        $cursor->modify('+1 day');
    }
}

$mapa_metricas = [
    'ventas' => [
        'sql' => "SELECT COALESCE(SUM(total),0) FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'",
        'dep' => ['ecommerce_pedidos'],
    ],
    'cobros' => [
        'sql' => "SELECT COALESCE(SUM(monto),0) FROM ecommerce_pedido_pagos WHERE fecha_pago BETWEEN ? AND ?",
        'dep' => ['ecommerce_pedido_pagos'],
    ],
    'compras' => [
        'sql' => "SELECT COALESCE(SUM(ci.subtotal),0) FROM ecommerce_compra_items ci JOIN ecommerce_compras cc ON cc.id = ci.compra_id WHERE cc.fecha_compra BETWEEN ? AND ?",
        'dep' => ['ecommerce_compra_items', 'ecommerce_compras'],
    ],
    'gastos' => [
        'sql' => "SELECT COALESCE(SUM(monto),0) FROM gastos WHERE fecha BETWEEN ? AND ?",
        'dep' => ['gastos'],
    ],
    'sueldos' => [
        'sql' => "SELECT COALESCE(SUM(monto_pagado),0) FROM pagos_sueldos WHERE fecha_pago BETWEEN ? AND ?",
        'dep' => ['pagos_sueldos'],
    ],
    'pedidos' => [
        'sql' => "SELECT COUNT(*) FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'",
        'dep' => ['ecommerce_pedidos'],
    ],
    'cotizaciones' => [
        'sql' => "SELECT COUNT(*) FROM ecommerce_cotizaciones WHERE fecha_creacion BETWEEN ? AND ?",
        'dep' => ['ecommerce_cotizaciones'],
    ],
];

$deps_ok = [];
foreach ($mapa_metricas as $key => $def) {
    $ok = true;
    foreach ($def['dep'] as $t) {
        if (!array_key_exists($t, $deps_ok)) {
            $deps_ok[$t] = dashboard_chart_tabla_existe($pdo, $t);
        }
        if (!$deps_ok[$t]) {
            $ok = false;
            break;
        }
    }
    $mapa_metricas[$key]['disponible'] = $ok;
}

$labels = [];
$series = [];
foreach ($metrics as $m) {
    $series[$m] = [];
}

foreach ($tramos as [$label, $inicio, $fin]) {
    $labels[] = $label;
    foreach ($metrics as $m) {
        $valor = 0;
        if (!empty($mapa_metricas[$m]['disponible'])) {
            try {
                $stmt = $pdo->prepare($mapa_metricas[$m]['sql']);
                $stmt->execute([$inicio, $fin]);
                $valor = (float)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $valor = 0;
            }
        }
        $series[$m][] = $valor;
    }
}

echo json_encode(['ok' => true, 'labels' => $labels, 'series' => $series]);
