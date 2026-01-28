<?php
require 'includes/header.php';

$lista_id = $_GET['lista_id'] ?? 0;

// Obtener lista de precios
$stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
$stmt->execute([$lista_id]);
$lista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
}

// Obtener productos que ya están en esta lista
$stmt = $pdo->prepare("
    SELECT producto_id FROM ecommerce_lista_precio_items WHERE lista_precio_id = ?
");
$stmt->execute([$lista_id]);
$productos_en_lista = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Obtener todos los productos
$stmt = $pdo->query("SELECT id, nombre, precio FROM ecommerce_productos WHERE activo = 1 ORDER BY nombre");
$todos_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productos_agregados = 0;
    
    foreach ($_POST['productos'] ?? [] as $producto_id) {
        if (!in_array($producto_id, $productos_en_lista)) {
            $precio_nuevo = $_POST['precio_' . $producto_id] ?? 0;
            $descuento = $_POST['descuento_' . $producto_id] ?? 0;
            
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_lista_precio_items (lista_precio_id, producto_id, precio_nuevo, descuento_porcentaje)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$lista_id, $producto_id, $precio_nuevo, $descuento]);
            $productos_agregados++;
        }
    }
    
    if ($productos_agregados > 0) {
        echo "<div class='alert alert-success'>✓ $productos_agregados producto(s) agregado(s)</div>";
        header("refresh:2; url=listas_precios_items.php?id=$lista_id");
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Agregar Productos - <?= htmlspecialchars($lista['nombre']) ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios_items.php?id=<?= $lista_id ?>" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST">
                <div class="table-responsive">
                    <table class="table">
                        <thead class="table-dark">
                            <tr>
                                <th width="50"></th>
                                <th>Producto</th>
                                <th>Precio Original</th>
                                <th>Precio Nuevo</th>
                                <th>Descuento %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($todos_productos as $prod): ?>
                                <?php if (!in_array($prod['id'], $productos_en_lista)): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="productos[]" value="<?= $prod['id'] ?>" class="form-check-input">
                                        </td>
                                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                        <td>$<?= number_format($prod['precio'], 2) ?></td>
                                        <td>
                                            <input type="number" name="precio_<?= $prod['id'] ?>" step="0.01" value="<?= $prod['precio'] ?>" class="form-control form-control-sm" style="width: 120px;">
                                        </td>
                                        <td>
                                            <input type="number" name="descuento_<?= $prod['id'] ?>" step="0.01" value="0" class="form-control form-control-sm" style="width: 100px;">
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn btn-primary">✓ Agregar Seleccionados</button>
                <a href="listas_precios_items.php?id=<?= $lista_id ?>" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
