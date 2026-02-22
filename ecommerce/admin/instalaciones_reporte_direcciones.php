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
    SELECT op.pedido_id, p.numero_pedido, p.envio_nombre, p.envio_telefono, p.envio_direccion,
           p.envio_localidad, p.envio_provincia, p.envio_codigo_postal,
           c.nombre AS cliente_nombre
    FROM ecommerce_ordenes_produccion op
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE op.estado = 'terminado'
";
$params = [];
if (!$incluir_entregados) {
    $sql .= " AND op.estado != 'entregado'";
}
if ($fecha_desde !== '') {
    $sql .= " AND DATE(p.fecha_creacion) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $sql .= " AND DATE(p.fecha_creacion) <= ?";
    $params[] = $fecha_hasta;
}
$sql .= " ORDER BY p.envio_localidad, p.envio_direccion, p.numero_pedido";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items_por_pedido = [];
if (!empty($pedidos)) {
    $ids = array_column($pedidos, 'pedido_id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("
        SELECT pi.pedido_id, pi.cantidad, pi.ancho_cm, pi.alto_cm, pi.atributos,
               pr.nombre AS producto_nombre
        FROM ecommerce_pedido_items pi
        LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
        WHERE pi.pedido_id IN ($placeholders)
        ORDER BY pi.pedido_id, pi.id
    ");
    $stmt->execute($ids);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $items_por_pedido[$row['pedido_id']][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte instalaciones por direcciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>@media print { .no-print { display: none !important; } } body { font-size: 12px; }</style>
</head>
<body class="p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 no-print">
        <h1>Reporte de instalaciones - Por direcciones y cortinas</h1>
        <button onclick="window.print()" class="btn btn-primary">Imprimir</button>
    </div>
    <p class="text-muted no-print">Pedidos terminados. Filtros: desde <?= $fecha_desde ?: '-' ?> hasta <?= $fecha_hasta ?: '-' ?></p>

    <?php if (empty($pedidos)): ?>
        <p>No hay pedidos para mostrar.</p>
    <?php else:
        foreach ($pedidos as $p):
            $nombre = trim($p['envio_nombre'] ?? '') ?: $p['cliente_nombre'] ?? 'Sin nombre';
            $dir = trim($p['envio_direccion'] ?? '');
            $loc = trim($p['envio_localidad'] ?? '');
            $prov = trim($p['envio_provincia'] ?? '');
            $direccion_completa = $dir;
            if ($loc) $direccion_completa .= ($direccion_completa ? ', ' : '') . $loc;
            if ($prov) $direccion_completa .= ($direccion_completa ? ', ' : '') . $prov;
            $items = $items_por_pedido[$p['pedido_id']] ?? [];
    ?>
    <div class="card mb-3">
        <div class="card-header bg-light">
            <strong><?= htmlspecialchars($nombre) ?></strong>
            <?php if (!empty($p['envio_telefono'])): ?>
                <span class="text-muted ms-2">Tel: <?= htmlspecialchars($p['envio_telefono']) ?></span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <p class="mb-1"><strong>Direccion:</strong> <?= htmlspecialchars($direccion_completa) ?: '-' ?></p>
            <p class="mb-2 text-muted small">Pedido: <?= htmlspecialchars($p['numero_pedido']) ?></p>
            <p class="mb-1"><strong>Cortinas a instalar:</strong></p>
            <ul class="list-unstyled mb-0">
                <?php foreach ($items as $it):
                    $detalle = $it['producto_nombre'] ?? 'Producto';
                    $detalle .= ' - Cant: ' . (int)$it['cantidad'];
                    if (!empty($it['ancho_cm']) || !empty($it['alto_cm'])) {
                        $detalle .= ' - ' . ($it['ancho_cm'] ?? '?') . ' x ' . ($it['alto_cm'] ?? '?') . ' cm';
                    }
                    if (!empty($it['atributos'])) {
                        $attrs = json_decode($it['atributos'], true);
                        if (is_array($attrs)) {
                            $parts = [];
                            foreach ($attrs as $a) {
                                if (!empty($a['nombre']) && !empty($a['valor'])) $parts[] = $a['nombre'] . ': ' . $a['valor'];
                            }
                            if ($parts) $detalle .= ' - ' . implode(', ', $parts);
                        }
                    }
                ?>
                <li>• <?= htmlspecialchars($detalle) ?></li>
                <?php endforeach; ?>
                <?php if (empty($items)): ?><li class="text-muted">- Sin items</li><?php endif; ?>
            </ul>
        </div>
    </div>
    <?php endforeach; endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
