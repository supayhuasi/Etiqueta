<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

$cliente = require_cliente_login($pdo);

$mensaje = '';
if (($_GET['mensaje'] ?? '') === 'registro') {
    $mensaje = 'Cuenta creada. Ya podés ver tus pedidos.';
}

$pedidos = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.numero_pedido, p.total, p.estado, p.fecha_creacion, op.estado AS estado_produccion
        FROM ecommerce_pedidos p
        LEFT JOIN ecommerce_ordenes_produccion op ON op.pedido_id = p.id
        WHERE p.cliente_id = ?
        ORDER BY p.fecha_creacion DESC
    ");
    $stmt->execute([$cliente['id']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stmt = $pdo->prepare("
        SELECT id, numero_pedido, total, estado, fecha_creacion
        FROM ecommerce_pedidos
        WHERE cliente_id = ?
        ORDER BY fecha_creacion DESC
    ");
    $stmt->execute([$cliente['id']]);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Mis pedidos</h1>
            <p class="text-muted mb-0">Seguimiento de tus compras</p>
        </div>
        <a href="tienda.php" class="btn btn-secondary">Seguir comprando</a>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($pedidos)): ?>
        <div class="alert alert-info">Aún no tenés pedidos registrados.</div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Número</th>
                        <th>Fecha</th>
                        <th>Total</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos as $pedido): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($pedido['numero_pedido']) ?></strong></td>
                            <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?></td>
                            <td>$<?= number_format($pedido['total'], 2, ',', '.') ?></td>
                            <td>
                                <span class="badge bg-secondary">Pedido: <?= htmlspecialchars(str_replace('_', ' ', $pedido['estado'])) ?></span>
                                <?php if (!empty($pedido['estado_produccion'])): ?>
                                    <span class="badge bg-info text-dark ms-1">Producción: <?= htmlspecialchars(str_replace('_', ' ', $pedido['estado_produccion'])) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="pedido_detalle.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
