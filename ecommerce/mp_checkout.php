<?php
require 'config.php';
require 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pedido_id = intval($_GET['pedido_id'] ?? $_SESSION['pedido_id'] ?? 0);

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

// Obtener configuración de Mercado Pago
$stmt = $pdo->query("SELECT * FROM ecommerce_mercadopago_config WHERE activo = 1 LIMIT 1");
$config_mp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$config_mp) {
    die("Mercado Pago no está configurado");
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">💳 Finalizar Pago con Mercado Pago</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6>Resumen de Tu Compra</h6>
                        <p class="mb-0">
                            <strong>Número de Pedido:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?><br>
                            <strong>Total a Pagar:</strong> <span class="h5 text-success">$<?= number_format($pedido['total'], 2, ',', '.') ?></span>
                        </p>
                    </div>

                    <div id="mercadopago-container" style="display: none;" class="mb-3">
                        <p class="text-muted">Redirigiendo a Mercado Pago...</p>
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                    </div>

                    <div id="loading-container" class="text-center">
                        <div class="spinner-border" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-3">Preparando la compra...</p>
                    </div>

                    <div id="error-container" style="display: none;">
                        <div class="alert alert-danger" role="alert">
                            <h5>Error al procesar el pago</h5>
                            <p id="error-message"></p>
                            <a href="carrito.php" class="btn btn-secondary">← Volver al Carrito</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-3">
                <a href="carrito.php" class="btn btn-outline-secondary">← Volver al Carrito</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Procesar pago con Mercado Pago
    const pedidoId = <?= $pedido_id ?>;
    
    // Crear FormData para enviar al servidor
    const formData = new FormData();
    formData.append('pedido_id', pedidoId);
    
    // Enviar solicitud para crear preferencia
    fetch('procesar_pago_mp.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.url) {
            // Redirigir a Mercado Pago
            document.getElementById('loading-container').style.display = 'none';
            document.getElementById('mercadopago-container').style.display = 'block';
            setTimeout(() => {
                window.location.href = data.url;
            }, 1500);
        } else {
            throw new Error(data.error || 'Error desconocido');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('loading-container').style.display = 'none';
        document.getElementById('error-container').style.display = 'block';
        document.getElementById('error-message').textContent = error.message || 'No se pudo procesar el pago. Intenta nuevamente.';
    });
});
</script>

<?php require 'includes/footer.php'; ?>
