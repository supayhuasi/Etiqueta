<?php
require 'config.php';
require 'includes/seo_helper.php';

// Obtener categorías
$stmt = $pdo->query("SELECT * FROM ecommerce_categorias WHERE activo = 1 ORDER BY orden, nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filtro por categoría
$categoria_filtro = $_GET['categoria'] ?? 'todos';
$busqueda = trim((string)($_GET['busqueda'] ?? ''));

$categoria_actual = null;
if ($categoria_filtro !== 'todos') {
    foreach ($categorias as $cat) {
        if ((string)$cat['id'] === (string)$categoria_filtro) {
            $categoria_actual = $cat;
            break;
        }
    }
}

// SEO: título y descripción según filtro/búsqueda activos
if ($busqueda !== '') {
    $page_title = 'Resultados para "' . $busqueda . '"';
    $seo_description = seo_truncar_descripcion('Resultados de búsqueda de "' . $busqueda . '" en nuestra tienda online.');
    // Las páginas de resultados de búsqueda no aportan valor único: se excluyen del índice pero se sigue el enlace.
    $seo_robots = 'noindex,follow';
} elseif ($categoria_actual) {
    $page_title = $categoria_actual['nombre'] . ' - Tienda Online';
    $seo_description = seo_truncar_descripcion('Comprá ' . $categoria_actual['nombre'] . ' online. Envío gratis y hasta 3 cuotas sin interés.');
} else {
    $page_title = 'Tienda Online';
    $seo_description = 'Comprá cortinas, toldos y persianas online. Envío gratis, instalación en Tucumán y hasta 3 cuotas sin interés.';
}

require 'includes/header.php';
require 'includes/precios_publico.php';
require 'includes/banners_publico_helper.php';

$banners_sidebar = obtener_banners_zona($pdo, 'tienda_sidebar');

// Obtener slideshow activos (Tienda)
$stmt = $pdo->query("SELECT * FROM ecommerce_slideshow WHERE activo = 1 AND ubicacion = 'tienda' ORDER BY orden ASC");
$slideshows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuración de lista de precios pública
$lista_publica_id = obtener_lista_precio_publica($pdo);
$mapas_lista_publica = cargar_mapas_lista_publica($pdo, $lista_publica_id);

// Determinar la ruta correcta para las imágenes
$image_path = 'uploads/';

// Construir query de productos
$query = "SELECT p.*, pi.imagen AS imagen
FROM ecommerce_productos p
LEFT JOIN (
    SELECT producto_id, imagen
    FROM ecommerce_producto_imagenes
    WHERE es_principal = 1
) pi ON pi.producto_id = p.id
WHERE p.activo = 1 AND p.mostrar_ecommerce = 1";
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
                <img src="<?= $image_path . htmlspecialchars($slide['imagen_url']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['titulo']) ?>" style="height: 320px; object-fit: cover;">
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
        <h1>🛒 <?= htmlspecialchars($categoria_actual['nombre'] ?? 'Tienda Online') ?></h1>
        <p class="text-muted">Explora nuestro catálogo de productos</p>
    </div>
</div>

<div class="container mb-5">
    <style>
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        .btn-outline-primary {
            color: #0d6efd;
            border-color: #0d6efd;
        }
        .btn-outline-primary:hover,
        .btn-outline-primary:focus,
        .btn-outline-primary.active {
            color: #fff;
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>
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

            <?php if (!empty($banners_sidebar)): ?>
                <?php foreach ($banners_sidebar as $banner): ?>
                    <div class="card mt-4">
                        <?php if (!empty($banner['enlace'])): ?>
                            <a href="<?= htmlspecialchars($banner['enlace']) ?>">
                                <img src="<?= $image_path . htmlspecialchars($banner['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($banner['titulo']) ?>">
                            </a>
                        <?php else: ?>
                            <img src="<?= $image_path . htmlspecialchars($banner['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($banner['titulo']) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
                                        <img src="<?= $image_path . htmlspecialchars($producto['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>">
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
                                    
                                    <div class="small text-muted mb-2" style="font-family: 'Poppins', 'Arial', sans-serif; font-weight: 700;">
                                        <i class="bi bi-eye"></i> <strong><?= rand(1, 15) ?> clientes consultaron hoy</strong>
                                    </div>
                                    
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
                                    
                                    <a href="producto.php?id=<?= $producto['id'] ?>" class="btn btn-primary mt-auto">Cotizar Ahora</a>
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
