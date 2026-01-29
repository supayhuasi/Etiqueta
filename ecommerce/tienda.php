<?php
require 'config.php';
require 'includes/header.php';
require 'includes/precios_publico.php';

// Obtener slideshow activos (Tienda)
$stmt = $pdo->query("SELECT * FROM ecommerce_slideshow WHERE activo = 1 AND ubicacion = 'tienda' ORDER BY orden ASC");
$slideshows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías
$stmt = $pdo->query("SELECT * FROM ecommerce_categorias WHERE activo = 1 ORDER BY orden, nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtro por categoría
$categoria_filtro = $_GET['categoria'] ?? 'todos';
$busqueda = $_GET['busqueda'] ?? '';

// Configuración de lista de precios pública
$lista_publica_id = obtener_lista_precio_publica($pdo);
$mapas_lista_publica = cargar_mapas_lista_publica($pdo, $lista_publica_id);

// Construir query de productos
$query = "SELECT * FROM ecommerce_productos WHERE activo = 1 AND mostrar_ecommerce = 1";
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

<!-- Slideshow/Carrusel -->
<?php if (!empty($slideshows)): ?>
<div id="carouselSlideshowTienda" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach ($slideshows as $key => $slide): ?>
            <button type="button" data-bs-target="#carouselSlideshowTienda" data-bs-slide-to="<?= $key ?>" <?= $key === 0 ? 'class="active"' : '' ?>></button>
        <?php endforeach; ?>
    </div>
    <div class="carousel-inner">
        <?php foreach ($slideshows as $key => $slide): ?>
            <div class="carousel-item <?= $key === 0 ? 'active' : '' ?>">
                <img src="uploads/<?= htmlspecialchars($slide['imagen_url']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['titulo']) ?>" style="height: 320px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h2><?= htmlspecialchars($slide['titulo']) ?></h2>
                    <p><?= htmlspecialchars($slide['descripcion']) ?></p>
                    <?php if ($slide['enlace']): ?>
                        <a href="<?= htmlspecialchars($slide['enlace']) ?>" class="btn btn-light btn-lg">Ver más</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselSlideshowTienda" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselSlideshowTienda" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>
<?php endif; ?>

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
                        <?php
                        $precio_info = calcular_precio_publico(
                            (int)$producto['id'],
                            (int)($producto['categoria_id'] ?? 0),
                            (float)$producto['precio_base'],
                            $lista_publica_id,
                            $mapas_lista_publica['items'],
                            $mapas_lista_publica['categorias']
                        );
                        ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card h-100">
                                <?php if (!empty($producto['imagen'])): ?>
                                    <div style="position: relative;">
                                        <img src="uploads/<?= htmlspecialchars($producto['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>">
                                        <?php if ($precio_info['descuento_pct'] > 0): ?>
                                            <span class="badge bg-danger" style="position: absolute; top: 10px; right: 10px; font-size: 14px;">
                                                -<?= $precio_info['descuento_pct'] ?>%
                                            </span>
                                        <?php endif; ?>
                                    </div>
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
                                        <?php if ($precio_info['descuento_pct'] > 0): ?>
                                            <div class="mb-1">
                                                <span class="text-muted text-decoration-line-through">$<?= number_format($precio_info['precio_original'], 2, ',', '.') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <h4 class="text-primary">$<?= number_format($precio_info['precio'], 2, ',', '.') ?></h4>
                                    <?php else: ?>
                                        <h5 class="text-primary">Precio según medidas</h5>
                                        <?php if ($precio_info['descuento_pct'] > 0): ?>
                                            <small class="text-muted text-decoration-line-through">desde $<?= number_format($precio_info['precio_original'], 2, ',', '.') ?></small><br>
                                        <?php endif; ?>
                                        <small class="text-muted">desde $<?= number_format($precio_info['precio'], 2, ',', '.') ?></small>
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
