<?php
require 'config.php';
require 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$numero_pedido = $_SESSION['pedido_numero'] ?? '';
$metodo_nombre = $_SESSION['pedido_metodo_nombre'] ?? '';
$metodo_instrucciones = $_SESSION['pedido_metodo_instrucciones'] ?? '';
$total = $_SESSION['pedido_total'] ?? null;

// limpiar datos temporales
unset($_SESSION['pedido_numero'], $_SESSION['pedido_metodo_nombre'], $_SESSION['pedido_metodo_instrucciones'], $_SESSION['pedido_total']);
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0">✓ Pedido Confirmado</h4>
                </div>
                <div class="card-body">
                    <?php if ($numero_pedido): ?>
                        <p>Tu pedido fue creado correctamente.</p>
                        <p><strong>Número de pedido:</strong> <?= htmlspecialchars($numero_pedido) ?></p>
                    <?php else: ?>
                        <p>Tu pedido fue creado correctamente.</p>
                    <?php endif; ?>

                    <?php if (!empty($metodo_nombre)): ?>
                        <p><strong>Método de pago:</strong> <?= htmlspecialchars($metodo_nombre) ?></p>
                    <?php endif; ?>

                    <?php if ($total !== null): ?>
                        <p><strong>Total:</strong> $<?= number_format((float)$total, 2, ',', '.') ?></p>
                    <?php endif; ?>

                    <?php if (!empty($metodo_instrucciones)): ?>
                        <hr>
                        <div class="alert alert-light border">
                            <?= $metodo_instrucciones ?>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex gap-2 mt-4">
                        <a href="index.php" class="btn btn-outline-secondary">Volver al Inicio</a>
                        <?php if (!empty($_SESSION['cliente_id'])): ?>
                            <a href="mis_pedidos.php" class="btn btn-primary">Ver mis pedidos</a>
                        <?php endif; ?>
                        <a href="tienda.php" class="btn btn-success">Seguir comprando</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
