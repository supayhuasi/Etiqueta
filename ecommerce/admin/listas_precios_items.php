<?php
require 'includes/header.php';

// Obtener lista de precios
$stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
$stmt->execute([$_GET['id'] ?? 0]);
$lista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
}

// Obtener los precios de esta lista
$stmt = $pdo->prepare("
    SELECT lpi.*, p.nombre as producto_nombre, p.precio as precio_original
    FROM ecommerce_lista_precio_items lpi
    JOIN ecommerce_productos p ON lpi.producto_id = p.id
    WHERE lpi.lista_precio_id = ?
    ORDER BY p.nombre
");
$stmt->execute([$lista['id']]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar eliminación de item
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM ecommerce_lista_precio_items WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    header("refresh:1; url=listas_precios_items.php?id=" . $_GET['id']);
}

// Procesar actualización de precio
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $precio_nuevo = $_POST['precio_nuevo'] ?? 0;
    $descuento = $_POST['descuento_porcentaje'] ?? 0;
    
    $stmt = $pdo->prepare("
        UPDATE ecommerce_lista_precio_items 
        SET precio_nuevo = ?, descuento_porcentaje = ?
        WHERE id = ?
    ");
    $stmt->execute([$precio_nuevo, $descuento, $_POST['item_id']]);
    header("refresh:1; url=listas_precios_items.php?id=" . $_GET['id']);
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Precios - <?= htmlspecialchars($lista['nombre']) ?></h1>
            <p class="text-muted"><?= htmlspecialchars($lista['descripcion']) ?></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios_items_agregar.php?lista_id=<?= $lista['id'] ?>" class="btn btn-success">+ Agregar Productos</a>
            <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="alert alert-info">
                    Esta lista no tiene productos. <a href="listas_precios_items_agregar.php?lista_id=<?= $lista['id'] ?>">Agrega productos ahora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-dark">
                            <tr>
                                <th>Producto</th>
                                <th>Precio Original</th>
                                <th>Precio Nuevo</th>
                                <th>Descuento %</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                                    <td>$<?= number_format($item['precio_original'], 2) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                            <input type="number" name="precio_nuevo" step="0.01" value="<?= $item['precio_nuevo'] ?>" class="form-control form-control-sm" style="width: 120px;">
                                    </td>
                                    <td>
                                            <input type="number" name="descuento_porcentaje" step="0.01" value="<?= $item['descuento_porcentaje'] ?>" class="form-control form-control-sm" style="width: 100px;">
                                    </td>
                                    <td>
                                            <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                        </form>
                                        <a href="?id=<?= $lista['id'] ?>&delete=<?= $item['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este precio?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
