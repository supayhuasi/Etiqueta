<?php
require 'includes/header.php';

$pedido_id = $_GET['id'] ?? 0;

// Obtener pedido
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.email, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

// Obtener items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre, pr.imagen
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="pedidos.php" class="btn btn-outline-secondary">← Volver a Pedidos</a>
        <h1 class="mt-3">📦 Pedido: <?= htmlspecialchars($pedido['numero_pedido']) ?></h1>
    </div>
</div>

<div class="row">
    <!-- Datos del cliente -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5>👤 Datos del Cliente</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['nombre']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($pedido['email']) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? 'N/A') ?></p>
                <p><strong>Ciudad:</strong> <?= htmlspecialchars($pedido['ciudad'] ?? 'N/A') ?></p>
                <p><strong>Provincia:</strong> <?= htmlspecialchars($pedido['provincia'] ?? 'N/A') ?></p>
                <p><strong>Código Postal:</strong> <?= htmlspecialchars($pedido['codigo_postal'] ?? 'N/A') ?></p>
            </div>
        </div>
    </div>

    <!-- Datos del pedido -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5>📋 Datos del Pedido</h5>
            </div>
            <div class="card-body">
                <p><strong>Número:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i:s', strtotime($pedido['fecha_creacion'])) ?></p>
                <p><strong>Método de Pago:</strong> <span class="badge bg-info"><?= htmlspecialchars($pedido['metodo_pago']) ?></span></p>
                <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format($pedido['total'], 2, ',', '.') ?></span></p>
            </div>
        </div>
    </div>
</div>

<!-- Items del pedido -->
<div class="card">
    <div class="card-header bg-light">
        <h5>🛒 Items del Pedido</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th>Atributos</th>
                    <th>Precio Unitario</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $atributos = !empty($item['atributos']) ? json_decode($item['atributos'], true) : [];
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong>
                        </td>
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
                                        <div><?= htmlspecialchars($attr['nombre'] ?? 'Attr') ?>: <?= htmlspecialchars($attr['valor'] ?? '') ?>
                                            <?php if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0): ?>
                                                <span class="badge bg-success">+$<?= number_format($attr['costo_adicional'], 2, ',', '.') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>$<?= number_format($item['precio_unitario'], 2, ',', '.') ?></td>
                        <td><?= $item['cantidad'] ?></td>
                        <td><strong>$<?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
