<?php
require 'config.php';
require 'includes/header.php';

// Obtener categorías
$stmt = $pdo->query("SELECT * FROM ecommerce_categorias WHERE activo = 1 ORDER BY orden, nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtro por categoría
$categoria_filtro = $_GET['categoria'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Construir query de productos
$query = "SELECT * FROM ecommerce_productos WHERE activo = 1";
$params = [];

if ($categoria_filtro !== 'todos') {
    $query .= " AND categoria_id = ?";
    $params[] = $categoria_filtro;
}

if (!empty($busqueda)) {
    $query .= " AND (nombre LIKE ? OR descripcion LIKE ?)";
    $params[] = "%$busqueda%";
    $params[] = "%$busqueda%";
}

$query .= " ORDER BY orden, nombre";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Encabezado de la tienda -->
<div class="bg-light py-4 mb-5">
    <div class="container">
        <h1>🛒 Tienda Online</h1>
        <p class="text-muted">Explora nuestro catálogo de productos</p>
    </div>
</div>

<div class="container mb-5">
    <div class="row">
        <!-- Sidebar de categorías y búsqueda -->
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5>Búsqueda</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="d-flex gap-2">
                        <input type="text" class="form-control" name="busqueda" placeholder="Buscar..." value="<?= htmlspecialchars($busqueda) ?>">
                        <button type="submit" class="btn btn-primary">🔍</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Categorías</h5>
                </div>
                <div class="card-body">
                    <a href="tienda.php" class="btn btn-outline-primary category-btn w-100 mb-2 <?= $categoria_filtro === 'todos' ? 'active' : '' ?>">
                        Todos
                    </a>
                    <?php foreach ($categorias as $categoria): ?>
                        <a href="tienda.php?categoria=<?= $categoria['id'] ?>" class="btn btn-outline-primary category-btn w-100 mb-2 <?= $categoria_filtro == $categoria['id'] ? 'active' : '' ?>">
                            <?= $categoria['icono'] ?? '📦' ?> <?= htmlspecialchars($categoria['nombre']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Grid de productos -->
        <div class="col-md-9">
            <?php if (empty($productos)): ?>
                <div class="alert alert-info text-center">
                    <h4>No se encontraron productos</h4>
                    <p>Intenta con otros filtros o busca un término diferente</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($productos as $producto): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card h-100">
                                <?php if (!empty($producto['imagen'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($producto['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-secondary d-flex align-items-center justify-content-center" style="height: 200px;">
                                        <span class="text-white">📦</span>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                    <p class="card-text text-muted flex-grow-1">
                                        <?= htmlspecialchars(substr($producto['descripcion'], 0, 80)) ?>...
                                    </p>
                                    
                                    <?php if ($producto['tipo_precio'] === 'fijo'): ?>
                                        <h4 class="text-primary">$<?= number_format($producto['precio_base'], 2, ',', '.') ?></h4>
                                    <?php else: ?>
                                        <h5 class="text-primary">Precio según medidas</h5>
                                        <small class="text-muted">desde $<?= number_format($producto['precio_base'], 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                    
                                    <a href="producto.php?id=<?= $producto['id'] ?>" class="btn btn-primary mt-auto">Ver Detalles</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
