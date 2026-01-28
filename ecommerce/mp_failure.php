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
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">❌ Pago No Completado</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <h4>⚠️ El pago no se ha procesado</h4>
                        <p class="mb-0">Tu transacción fue cancelada o rechazada. El pedido permanece sin pagar.</p>
                    </div>

                    <div class="card mb-4 border-danger">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">📋 Información del Pedido</h6>
                        </div>
                        <div class="card-body">
                            <p>
                                <strong>Número de Pedido:</strong><br>
                                <span style="font-size: 1.1rem;">
                                    <?= htmlspecialchars($pedido['numero_pedido']) ?>
                                </span>
                            </p>
                            <p class="mt-3">
                                <strong>Total:</strong><br>
                                <span style="font-size: 1.3rem; color: #dc3545;">
                                    $<?= number_format($pedido['total'], 2, ',', '.') ?>
                                </span>
                            </p>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6>¿Qué puedes hacer?</h6>
                        <ul class="mb-0">
                            <li><strong>Reintentar el pago:</strong> Haz clic en el botón de abajo para intentar nuevamente</li>
                            <li><strong>Usar otro método:</strong> Puedes cambiar el método de pago en el checkout</li>
                            <li><strong>Contactarnos:</strong> Si tienes problemas, ponte en contacto con nuestro equipo</li>
                        </ul>
                    </div>

                    <div class="alert alert-light border">
                        <strong>Notas Importantes:</strong>
                        <ul class="mb-0 mt-2">
                            <li>El pedido se mantendrá en tu cuenta durante 24 horas</li>
                            <li>Puedes intentar pagar en cualquier momento</li>
                            <li>Si tienes preguntas, revisa tu email o contacta con nosotros</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2">
                        <a href="mp_checkout.php?pedido_id=<?= $pedido_id ?>" class="btn btn-primary">
                            🔄 Reintentar Pago
                        </a>
                        <a href="checkout.php" class="btn btn-outline-secondary">
                            Cambiar Método de Pago
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            ← Volver al Inicio
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
