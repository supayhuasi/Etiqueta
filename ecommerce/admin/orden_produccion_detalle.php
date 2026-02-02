<?php
require 'includes/header.php';

$pedido_id = $_GET['pedido_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

// Orden de producción
$stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

// Items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recetas por producto
$producto_ids = array_unique(array_filter(array_map(function($i){ return (int)$i['producto_id']; }, $items)));
$recetas_map = [];
if (!empty($producto_ids)) {
    $placeholders = implode(',', array_fill(0, count($producto_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT r.*, m.nombre AS material_nombre
        FROM ecommerce_producto_recetas_productos r
        JOIN ecommerce_productos m ON r.material_producto_id = m.id
        WHERE r.producto_id IN ($placeholders)
        ORDER BY m.nombre
    ");
    $stmt->execute($producto_ids);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $recetas_map[$r['producto_id']][] = $r;
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="ordenes_produccion.php" class="btn btn-outline-secondary">← Volver a Órdenes</a>
        <a href="orden_produccion_imprimir.php?pedido_id=<?= $pedido_id ?>" class="btn btn-outline-primary" target="_blank">🖨️ Imprimir</a>
        <h1 class="mt-3">🏭 Orden de Producción</h1>
        <p class="text-muted mb-0">Pedido: <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Estado de Producción</h5>
            </div>
            <div class="card-body">
                <p><strong>Estado:</strong> <?= htmlspecialchars(str_replace('_', ' ', $orden['estado'] ?? 'pendiente')) ?></p>
                <p><strong>Entrega:</strong> <?= !empty($orden['fecha_entrega']) ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '-' ?></p>
                <?php if (!empty($orden['notas'])): ?>
                    <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars($orden['notas'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Datos del Cliente</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['nombre']) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? 'N/A') ?></p>
                <p><strong>Ciudad:</strong> <?= htmlspecialchars($pedido['ciudad'] ?? 'N/A') ?></p>
                <p><strong>Provincia:</strong> <?= htmlspecialchars($pedido['provincia'] ?? 'N/A') ?></p>
                <p><strong>Código Postal:</strong> <?= htmlspecialchars($pedido['codigo_postal'] ?? 'N/A') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h5>🧵 Productos a fabricar</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th>Atributos</th>
                    <th>Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $atributos = !empty($item['atributos']) ? json_decode($item['atributos'], true) : [];
                    $alto_m = !empty($item['alto_cm']) ? ((float)$item['alto_cm'] / 100) : 0;
                    $ancho_m = !empty($item['ancho_cm']) ? ((float)$item['ancho_cm'] / 100) : 0;
                    $area_m2 = $alto_m * $ancho_m;
                    $recetas = $recetas_map[$item['producto_id']] ?? [];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong></td>
                        <td>
                            <?php if ($item['alto_cm'] && $item['ancho_cm']): ?>
                                <small><?= $item['alto_cm'] ?>cm × <?= $item['ancho_cm'] ?>cm</small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (is_array($atributos) && count($atributos) > 0): ?>
                                <small>
                                    <?php foreach ($atributos as $attr): ?>
                                        <div><?= htmlspecialchars($attr['nombre'] ?? 'Attr') ?>: <?= htmlspecialchars($attr['valor'] ?? '') ?></div>
                                    <?php endforeach; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td><?= $item['cantidad'] ?></td>
                    </tr>
                    <?php if (!empty($recetas)): ?>
                        <tr class="table-light">
                            <td colspan="4">
                                <small class="text-muted"><strong>Receta:</strong></small>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th>Consumo</th>
                                                <th>Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recetas as $r):
                                                $factor = (float)$r['factor'];
                                                $merma = (float)$r['merma_pct'];
                                                $cantidad_base = 0;
                                                if ($r['tipo_calculo'] === 'fijo') {
                                                    $cantidad_base = $factor;
                                                } elseif ($r['tipo_calculo'] === 'por_area') {
                                                    $cantidad_base = $area_m2 * $factor;
                                                } elseif ($r['tipo_calculo'] === 'por_ancho') {
                                                    $cantidad_base = $ancho_m * $factor;
                                                } elseif ($r['tipo_calculo'] === 'por_alto') {
                                                    $cantidad_base = $alto_m * $factor;
                                                }
                                                $cantidad_total = $cantidad_base * (1 + ($merma / 100));
                                                $cantidad_total = $cantidad_total * (int)$item['cantidad'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['material_nombre']) ?></td>
                                                    <td><?= number_format($cantidad_total, 4, ',', '.') ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($r['tipo_calculo']) ?>
                                                        <?= $merma > 0 ? ' + ' . $merma . '%' : '' ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
