<?php
session_start();
require __DIR__ . '/../../config.php';
if (empty($_SESSION['user'])) {
    header('Location: auth/login.php');
    exit;
}

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$incluir_entregados = !empty($_GET['incluir_entregados']);

$sql = "
    SELECT op.pedido_id
    FROM ecommerce_ordenes_produccion op
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    WHERE " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'") . "
";
$params = [];
if ($fecha_desde !== '') {
    $sql .= " AND DATE(p.fecha_pedido) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $sql .= " AND DATE(p.fecha_pedido) <= ?";
    $params[] = $fecha_hasta;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedido_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

$agregados = []; // producto_nombre => [ cantidad, tiempo_estimado_min ]
$tiempo_por_unidad = 15; // minutos por unidad por defecto (configurable)

if (!empty($pedido_ids)) {
    $placeholders = implode(',', array_fill(0, count($pedido_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT pi.producto_id, pi.cantidad, pr.nombre AS producto_nombre
        FROM ecommerce_pedido_items pi
        LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
        WHERE pi.pedido_id IN ($placeholders)
    ");
    $stmt->execute($pedido_ids);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $nombre = $row['producto_nombre'] ?? 'Producto #' . $row['producto_id'];
        $cant = (int)$row['cantidad'];
        if (!isset($agregados[$nombre])) {
            $agregados[$nombre] = ['cantidad' => 0, 'pedidos' => 0];
        }
        $agregados[$nombre]['cantidad'] += $cant;
        $agregados[$nombre]['pedidos'] += 1;
    }
}

// Tiempo estimado: cantidad * minutos por unidad
foreach ($agregados as $nombre => &$datos) {
    $datos['tiempo_estimado_min'] = $datos['cantidad'] * $tiempo_por_unidad;
}
unset($datos);

$total_unidades = array_sum(array_column($agregados, 'cantidad'));
$total_minutos = array_sum(array_column($agregados, 'tiempo_estimado_min'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte instalaciones — Productos y tiempos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print { .no-print { display: none !important; } }
        body { font-size: 12px; }
    </style>
</head>
<body class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1>📦 Reporte de instalaciones — Productos y tiempos estimados</h1>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
    </div>
    <p class="text-muted no-print">Pedidos terminados. Filtros: desde <?= $fecha_desde ?: '—' ?> hasta <?= $fecha_hasta ?: '—' ?>. Tiempo estimado: <?= $tiempo_por_unidad ?> min por unidad.</p>

    <?php if (empty($agregados)): ?>
        <p>No hay productos para mostrar.</p>
    <?php else: ?>
        <div class="card mb-4">
            <div class="card-body">
                <p class="mb-0"><strong>Total unidades a instalar:</strong> <?= $total_unidades ?></p>
                <p class="mb-0"><strong>Tiempo total estimado:</strong> <?= (int)($total_minutos / 60) ?> h <?= $total_minutos % 60 ?> min</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Tiempo estimado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($agregados as $nombre => $d): ?>
                        <tr>
                            <td><?= htmlspecialchars($nombre) ?></td>
                            <td class="text-end"><?= $d['cantidad'] ?></td>
                            <td class="text-end"><?= (int)($d['tiempo_estimado_min'] / 60) ?> h <?= $d['tiempo_estimado_min'] % 60 ?> min</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <th>Total</th>
                        <th class="text-end"><?= $total_unidades ?></th>
                        <th class="text-end"><?= (int)($total_minutos / 60) ?> h <?= $total_minutos % 60 ?> min</th>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
