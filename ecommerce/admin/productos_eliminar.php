<?php
require '../includes/navbar.php';

$id = $_GET['id'] ?? 0;
if ($id <= 0) die("Producto no encontrado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
$stmt->execute([$id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Producto no encontrado");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Eliminar imagen si existe
        if (!empty($producto['imagen']) && file_exists("../uploads/" . $producto['imagen'])) {
            unlink("../uploads/" . $producto['imagen']);
        }
        
        // Eliminar matriz de precios asociada
        $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE producto_id = ?")->execute([$id]);
        
        // Eliminar producto
        $pdo->prepare("DELETE FROM ecommerce_productos WHERE id = ?")->execute([$id]);
        
        header("Location: productos.php");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="card">
    <div class="card-header bg-danger text-white">
        <h5>Eliminar Producto</h5>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            ¿Estás seguro de que deseas eliminar el producto "<strong><?= htmlspecialchars($producto['nombre']) ?></strong>"?
        </div>
        
        <div class="bg-light p-3 rounded mb-3">
            <p><strong>Código:</strong> <?= htmlspecialchars($producto['codigo']) ?></p>
            <p><strong>Tipo:</strong> <?= $producto['tipo_precio'] === 'variable' ? 'Variable' : 'Fijo' ?></p>
            <p><strong>Precio Base:</strong> $<?= number_format($producto['precio_base'], 2) ?></p>
        </div>
        
        <form method="POST">
            <div class="d-flex gap-2">
                <a href="productos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-danger" onclick="return confirm('Esta acción no se puede deshacer')">Eliminar</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
