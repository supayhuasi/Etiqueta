<?php
require '../includes/navbar.php';

$categoria_filter = $_GET['categoria'] ?? '';
$query = "
    SELECT p.*, c.nombre as categoria_nombre 
    FROM ecommerce_productos p
    JOIN ecommerce_categorias c ON p.categoria_id = c.id
    WHERE 1=1
";
$params = [];

if (!empty($categoria_filter)) {
    $query .= " AND p.categoria_id = ?";
    $params[] = $categoria_filter;
}

$query .= " ORDER BY p.orden, p.nombre";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para el filtro
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Productos</h1>
    <a href="productos_crear.php" class="btn btn-primary">+ Nuevo Producto</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row align-items-end">
            <div class="col-auto">
                <label for="categoria" class="form-label">Categoría:</label>
                <select name="categoria" id="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoria_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
                <?php if (!empty($categoria_filter)): ?>
                    <a href="productos.php" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($productos)): ?>
    <div class="alert alert-info">No hay productos</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio Base</th>
                    <th>Tipo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $prod): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($prod['codigo']) ?></small></td>
                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                        <td><?= htmlspecialchars($prod['categoria_nombre']) ?></td>
                        <td>$<?= number_format($prod['precio_base'], 2, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-<?= $prod['tipo_precio'] === 'variable' ? 'warning' : 'info' ?>">
                                <?= $prod['tipo_precio'] === 'variable' ? 'Variable' : 'Fijo' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($prod['activo']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="productos_crear.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="productos_atributos.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-info" title="Atributos">⚙️</a>
                            <a href="productos_imagenes.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-secondary" title="Galería">🖼️</a>
                            <a href="productos_eliminar.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-danger">Eliminar</a>
                            <?php if ($prod['tipo_precio'] === 'variable'): ?>
                                <a href="matriz_precios.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-info">Matriz</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
