<?php
require 'config.php';
require 'includes/header.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$carrito = $_SESSION['carrito'] ?? [];

// Procesar eliminación de item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar'])) {
    $item_key = $_POST['eliminar'];
    unset($_SESSION['carrito'][$item_key]);
    header("Location: carrito.php");
    exit;
}

// Procesar actualización de cantidad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_cantidad'])) {
    foreach ($_POST['cantidades'] as $item_key => $cantidad) {
        if (isset($_SESSION['carrito'][$item_key])) {
            $_SESSION['carrito'][$item_key]['cantidad'] = max(1, intval($cantidad));
        }
    }
    header("Location: carrito.php");
    exit;
}

// Calcular totales
$subtotal = 0;
foreach ($carrito as $item) {
    $precio_item = $item['precio'];
    
    // Sumar costos adicionales de atributos
    if (isset($item['atributos']) && is_array($item['atributos'])) {
        foreach ($item['atributos'] as $attr) {
            if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                $precio_item += $attr['costo_adicional'];
            }
        }
    }
    
    $subtotal += $precio_item * $item['cantidad'];
}
$envio = $subtotal > 0 ? 500 : 0; // Envío fijo
$total = $subtotal + $envio;
?>

<div class="container py-5">
    <h1>🛍️ Carrito de Compras</h1>

    <?php if (empty($carrito)): ?>
        <div class="alert alert-info text-center mt-5" style="padding: 50px;">
            <h4>Tu carrito está vacío</h4>
            <p>¿Qué estás esperando? ¡Vamos de compras!</p>
            <a href="tienda.php" class="btn btn-primary btn-lg">Ir a la Tienda</a>
        </div>
    <?php else: ?>
        <div class="row mt-5">
            <!-- Tabla de items -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Precio</th>
                                            <th>Cantidad</th>
                                            <th>Subtotal</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($carrito as $item_key => $item): 
                                            $precio_item = $item['precio'];
                                            $costo_atributos = 0;
                                            $desglose_atributos = [];
                                            
                                            if (isset($item['atributos']) && is_array($item['atributos'])) {
                                                foreach ($item['atributos'] as $attr) {
                                                    if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                                                        $costo_atributos += $attr['costo_adicional'];
                                                        $desglose_atributos[] = [
                                                            'valor' => $attr['valor'] ?? '',
                                                            'costo' => $attr['costo_adicional']
                                                        ];
                                                    }
                                                }
                                            }
                                            $precio_item += $costo_atributos;
                                        ?>
                                            <tr>
                                                <td>
                                                    <a href="producto.php?id=<?= $item['id'] ?>" class="text-decoration-none">
                                                        <strong><?= htmlspecialchars($item['nombre']) ?></strong>
                                                    </a>
                                                    <?php if ($item['alto'] > 0 && $item['ancho'] > 0): ?>
                                                        <br><small class="text-muted"><?= $item['ancho'] ?>cm × <?= $item['alto'] ?>cm</small>
                                                    <?php endif; ?>
                                                    <?php if (isset($item['atributos']) && is_array($item['atributos']) && count($item['atributos']) > 0): ?>
                                                        <br><small class="text-muted">
                                                            <?php foreach ($item['atributos'] as $attr): ?>
                                                                <div><?= htmlspecialchars($attr['nombre']) ?>: <?= htmlspecialchars($attr['valor']) ?>
                                                                    <?php if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0): ?>
                                                                        <span class="badge bg-success">+$<?= number_format($attr['costo_adicional'], 2, ',', '.') ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>$<?= number_format($item['precio'], 2, ',', '.') ?></div>
                                                    <?php if ($costo_atributos > 0): ?>
                                                        <small class="text-muted d-block">+$<?= number_format($costo_atributos, 2, ',', '.') ?></small>
                                                        <small class="text-success fw-bold">$<?= number_format($precio_item, 2, ',', '.') ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <input type="number" class="form-control" style="width: 80px;" name="cantidades[<?= $item_key ?>]" value="<?= $item['cantidad'] ?>" min="1">
                                                </td>
                                                <td><strong>$<?= number_format($precio_item * $item['cantidad'], 2, ',', '.') ?></strong></td>
                                                <td>
                                                    <button type="submit" name="eliminar" value="<?= $item_key ?>" class="btn btn-sm btn-danger">🗑️</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex gap-2 mt-3">
                                <button type="submit" name="actualizar_cantidad" value="1" class="btn btn-secondary">Actualizar Cantidades</button>
                                <a href="tienda.php" class="btn btn-outline-primary">Continuar Comprando</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Resumen de compra -->
            <div class="col-md-4">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h5>Resumen de Compra</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <strong>$<?= number_format($subtotal, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3 pb-3 border-bottom">
                            <span>Envío:</span>
                            <strong>$<?= number_format($envio, 2, ',', '.') ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-4">
                            <span style="font-size: 1.2rem;">Total:</span>
                            <h4 class="text-primary">$<?= number_format($total, 2, ',', '.') ?></h4>
                        </div>
                        <a href="checkout.php" class="btn btn-success btn-lg w-100 mb-2">Ir al Checkout</a>
                        <a href="tienda.php" class="btn btn-outline-primary w-100">Seguir Comprando</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
