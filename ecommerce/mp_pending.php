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
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">⏳ Pago en Espera</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning" role="alert">
                        <h4>Estamos verificando tu pago</h4>
                        <p class="mb-0">Tu transacción está pendiente de confirmación. Esto puede tardar algunos minutos.</p>
                    </div>

                    <div class="card mb-4 border-warning">
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
                                <span style="font-size: 1.3rem; color: #ffc107;">
                                    $<?= number_format($pedido['total'], 2, ',', '.') ?>
                                </span>
                            </p>
                            <p class="mt-3">
                                <strong>Estado:</strong><br>
                                <span class="badge bg-warning text-dark">Pendiente de Confirmación</span>
                            </p>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <h6>¿Qué sucede ahora?</h6>
                        <ul class="mb-0">
                            <li>Verificaremos tu pago en Mercado Pago</li>
                            <li>Recibirás un email cuando se confirme</li>
                            <li>Generalmente toma entre 5 a 10 minutos</li>
                            <li>Si tienes dudas, revisa tu email o contacta con nosotros</li>
                        </ul>
                    </div>

                    <div class="alert alert-light border">
                        <strong>💡 Consejos:</strong>
                        <ul class="mb-0 mt-2">
                            <li>No cierres esta página inmediatamente</li>
                            <li>Revisa tu email de confirmación en unos minutos</li>
                            <li>Si el pago sigue pendiente después de 30 minutos, contacta con soporte</li>
                        </ul>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2">
                        <a href="index.php" class="btn btn-primary">
                            ← Volver al Inicio
                        </a>
                        <a href="javascript:location.reload()" class="btn btn-outline-secondary">
                            🔄 Actualizar Estado
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
