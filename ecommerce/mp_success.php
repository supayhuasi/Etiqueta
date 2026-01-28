<?php
require 'config.php';
require 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pedido_id = intval($_GET['pedido_id'] ?? 0);

if ($pedido_id <= 0) {
    die("Pedido no especificado");
}

// Obtener pedido
$stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE id = ?");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

// Obtener cliente
$stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes WHERE id = ?");
$stmt->execute([$pedido['cliente_id']]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, p.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos p ON pi.producto_id = p.id
    WHERE pi.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Limpiar carrito
unset($_SESSION['carrito']);
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">✓ ¡Pago Realizado Exitosamente!</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-success" role="alert">
                        <h4>🎉 Tu compra ha sido confirmada</h4>
                        <p>Recibirás un email de confirmación en <strong><?= htmlspecialchars($cliente['email']) ?></strong></p>
                    </div>

                    <!-- Resumen del pedido -->
                    <div class="card mb-4 border-success">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">📋 Detalles del Pedido</h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <p>
                                        <strong>Número de Pedido:</strong><br>
                                        <span style="font-size: 1.2rem; color: #28a745;">
                                            <?= htmlspecialchars($pedido['numero_pedido']) ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p>
                                        <strong>Fecha de Compra:</strong><br>
                                        <?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?>
                                    </p>
                                </div>
                            </div>

                            <!-- Items -->
                            <h6 class="mt-4 mb-3">Productos:</h6>
                            <div class="list-group mb-4">
                                <?php foreach ($items as $item): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1"><?= htmlspecialchars($item['producto_nombre']) ?></h6>
                                            <span class="badge bg-primary rounded-pill"><?= $item['cantidad'] ?>x</span>
                                        </div>
                                        <p class="mb-1 text-muted small">
                                            Precio unitario: $<?= number_format($item['precio_unitario'], 2, ',', '.') ?>
                                        </p>
                                        <p class="mb-0">
                                            <strong>Subtotal: $<?= number_format($item['subtotal'], 2, ',', '.') ?></strong>
                                        </p>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <!-- Totales -->
                            <div class="border-top pt-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span>Total:</span>
                                    <h5 class="text-success">$<?= number_format($pedido['total'], 2, ',', '.') ?></h5>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span>Método de Pago:</span>
                                    <strong><?= htmlspecialchars($pedido['metodo_pago']) ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Información de envío -->
                    <div class="card mb-4 border-info">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">📍 Dirección de Envío</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-1"><strong><?= htmlspecialchars($cliente['nombre']) ?></strong></p>
                            <p class="mb-1"><?= htmlspecialchars($cliente['direccion']) ?></p>
                            <p class="mb-1">
                                <?= htmlspecialchars($cliente['ciudad']) ?>, 
                                <?= htmlspecialchars($cliente['provincia']) ?>
                                <?php if ($cliente['codigo_postal']): ?>
                                    - <?= htmlspecialchars($cliente['codigo_postal']) ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($cliente['telefono']): ?>
                                <p class="mb-0">
                                    <strong>Teléfono:</strong> <?= htmlspecialchars($cliente['telefono']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Próximos pasos -->
                    <div class="alert alert-light border border-info">
                        <h6>📧 Próximos Pasos</h6>
                        <ul class="mb-0">
                            <li>Recibirás un email de confirmación con los detalles de tu pedido</li>
                            <li>En breve te enviaremos la información de seguimiento del envío</li>
                            <li>Puedes contactarnos si tienes alguna duda</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2">
                        <a href="tienda.php" class="btn btn-outline-primary">Seguir Comprando</a>
                        <a href="index.php" class="btn btn-primary">← Volver al Inicio</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
