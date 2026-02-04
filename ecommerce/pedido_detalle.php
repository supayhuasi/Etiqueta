<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

$cliente = require_cliente_login($pdo);
$pedido_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*
    FROM ecommerce_pedidos p
    WHERE p.id = ? AND p.cliente_id = ?
");
$stmt->execute([$pedido_id, $cliente['id']]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    echo "<div class='container py-5'><div class='alert alert-danger'>Pedido no encontrado.</div></div>";
    require 'includes/footer.php';
    exit;
}

$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container py-5">
    <div class="mb-4">
        <a href="mis_pedidos.php" class="btn btn-outline-secondary">← Volver a Mis pedidos</a>
        <h1 class="mt-3">Pedido <?= htmlspecialchars($pedido['numero_pedido']) ?></h1>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Estado del pedido</h5>
                </div>
                <div class="card-body">
                    <p><strong>Estado:</strong> <span class="badge bg-info"><?= htmlspecialchars(str_replace('_', ' ', $pedido['estado'])) ?></span></p>
                    <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?></p>
                    <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format($pedido['total'], 2, ',', '.') ?></span></p>
                    <p><strong>Método de pago:</strong> <?= htmlspecialchars($pedido['metodo_pago'] ?? '-') ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Datos de envío</h5>
                </div>
                <div class="card-body">
                    <p><strong>Nombre:</strong> <?= htmlspecialchars($cliente['nombre']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
                    <p><strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono'] ?? '-') ?></p>
                    <p><strong>Dirección:</strong> <?= htmlspecialchars($cliente['direccion'] ?? '-') ?></p>
                    <p><strong>Ciudad:</strong> <?= htmlspecialchars($cliente['ciudad'] ?? '-') ?></p>
                    <p><strong>Provincia:</strong> <?= htmlspecialchars($cliente['provincia'] ?? '-') ?></p>
                    <p><strong>Código Postal:</strong> <?= htmlspecialchars($cliente['codigo_postal'] ?? '-') ?></p>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-light">
            <h5 class="mb-0">Items del pedido</h5>
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
                            <td><strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong></td>
                            <td>
                                <?php if ($item['alto_cm'] && $item['ancho_cm']): ?>
                                    <small><?= $item['ancho_cm'] ?>cm × <?= $item['alto_cm'] ?>cm</small>
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
                            <td><?= (int)$item['cantidad'] ?></td>
                            <td><strong>$<?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
