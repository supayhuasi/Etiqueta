<?php
// admin/reporte_productos.php
require_once '../includes/header.php';

// Verificar permisos si es necesario
// if (!isset($can_access) || !$can_access('productos')) { die('Acceso denegado'); }

$pdo = $pdo ?? null;
if (!$pdo) {
    require_once '../config.php';
}

// Consulta para obtener productos vendidos y pendientes de entrega, agrupados por producto y color
$sql = "
SELECT p.id AS producto_id, p.nombre AS producto, pa.valor AS color,
    SUM(CASE WHEN pe.estado IN ('confirmado','preparando','enviado','entregado') THEN pi.cantidad ELSE 0 END) AS vendidos,
    SUM(CASE WHEN pe.estado IN ('confirmado','preparando','enviado') THEN pi.cantidad ELSE 0 END) AS faltan_entregar
FROM ecommerce_pedidos pe
JOIN ecommerce_pedidos_items pi ON pe.id = pi.pedido_id
JOIN ecommerce_productos p ON pi.producto_id = p.id
LEFT JOIN ecommerce_producto_atributos pa ON pa.producto_id = p.id AND pa.nombre = 'color'
WHERE pe.estado != 'cancelado'
GROUP BY p.id, pa.valor
ORDER BY p.nombre, pa.valor
";

$reporte = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

?>
<div class="container mt-4">
    <h2>Reporte de Productos Vendidos y Pendientes de Entrega</h2>
    <table class="table table-bordered table-hover mt-3">
        <thead>
            <tr>
                <th>Producto</th>
                <th>Color</th>
                <th>Vendidos</th>
                <th>Faltan Entregar</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reporte as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['producto']) ?></td>
                    <td><?= htmlspecialchars($row['color'] ?? 'Sin color') ?></td>
                    <td><?= (int)$row['vendidos'] ?></td>
                    <td><?= (int)$row['faltan_entregar'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once '../includes/footer.php'; ?>
