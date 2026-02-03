<?php
require 'config.php';
require 'includes/header.php';
require 'includes/precios_publico.php';

// Determinar la ruta correcta para las imágenes
$image_path = 'uploads/';

// Obtener información de la empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener slideshow activos (Inicio)
$stmt = $pdo->query("SELECT * FROM ecommerce_slideshow WHERE activo = 1 AND (ubicacion = 'inicio' OR ubicacion IS NULL) ORDER BY orden ASC");
$slideshows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes activos
$stmt = $pdo->query("SELECT * FROM ecommerce_clientes_logos WHERE activo = 1 ORDER BY orden ASC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Configuración de lista de precios pública
$lista_publica_id = obtener_lista_precio_publica($pdo);
$mapas_lista_publica = cargar_mapas_lista_publica($pdo, $lista_publica_id);

// Obtener algunos productos destacados
$stmt = $pdo->query(" 
    SELECT p.*, pi.imagen AS imagen_principal
    FROM ecommerce_productos p
    LEFT JOIN (
        SELECT producto_id, imagen
        FROM ecommerce_producto_imagenes
        WHERE es_principal = 1
    ) pi ON pi.producto_id = p.id
    WHERE p.activo = 1 AND p.mostrar_ecommerce = 1
    ORDER BY RAND() 
    LIMIT 6
");
$productos_destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Slideshow/Carrusel -->
<?php if (!empty($slideshows)): ?>
<div id="carouselSlideshow" class="carousel slide mb-4" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <?php foreach ($slideshows as $key => $slide): ?>
            <button type="button" data-bs-target="#carouselSlideshow" data-bs-slide-to="<?= $key ?>" <?= $key === 0 ? 'class="active"' : '' ?>></button>
        <?php endforeach; ?>
    </div>
    <div class="carousel-inner">
        <?php foreach ($slideshows as $key => $slide): ?>
            <div class="carousel-item <?= $key === 0 ? 'active' : '' ?>">
                <img src="<?= $image_path . htmlspecialchars($slide['imagen_url']) ?>" class="d-block w-100" alt="<?= htmlspecialchars($slide['titulo']) ?>" style="height: 600px; object-fit: cover;">
                <div class="carousel-caption d-none d-md-block">
                    <h1><?= htmlspecialchars($slide['titulo']) ?></h1>
                    <p><?= htmlspecialchars($slide['descripcion']) ?></p>
                    <?php if ($slide['enlace']): ?>
                        <a href="<?= htmlspecialchars($slide['enlace']) ?>" class="btn btn-light btn-lg">Ver más</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <button class="carousel-control-prev" type="button" data-bs-target="#carouselSlideshow" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#carouselSlideshow" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>
<?php else: ?>
<!-- Sección Hero por defecto -->
<section class="hero">
    <div class="container">
        <h1>Bienvenido a Tucu Roller</h1>
        <p>Los mejores productos en cortinas, toldos y persianas</p>
        <a href="tienda.php" class="btn btn-light btn-lg">Ir a la Tienda</a>
    </div>
</section>
<?php endif; ?>

<!-- Información de la empresa -->
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="col-md-6">
                <h2>Sobre Nosotros</h2>
                <?php if ($empresa): ?>
                    <p><?= nl2br(htmlspecialchars($empresa['about_us'] ?? 'Bienvenidos a nuestra tienda online.')) ?></p>
                <?php else: ?>
                    <p>Somos una empresa especializada en cortinas, toldos y persianas de alta calidad. Con más de 20 años de experiencia en el mercado, nos comprometemos a brindar los mejores productos y servicio al cliente.</p>
                <?php endif; ?>
                <a href="nosotros.php" class="btn btn-primary">Conoce más</a>
            </div>
            <div class="col-md-6">
                <div class="ratio ratio-16x9">
                    <iframe src="https://www.youtube.com/embed/iohBEiZP2qY" allowfullscreen="" loading="lazy"></iframe>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Productos Destacados -->
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Productos Destacados</h2>
        <div class="row">
            <?php foreach ($productos_destacados as $producto): ?>
                <div class="col-md-4 mb-4">
                    <div class="card product-card h-100">
                        <?php if (!empty($producto['imagen_principal'])): ?>
                            <div style="position: relative;">
                                <img src="<?= $image_path . htmlspecialchars($producto['imagen_principal']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>" style="height: 250px; object-fit: cover;">
                                <!-- Mostrar descuento si existe -->
                                <?php
                                $precio_info = calcular_precio_publico(
                                    (int)$producto['id'],
                                    (int)($producto['categoria_id'] ?? 0),
                                    (float)$producto['precio_base'],
                                    $lista_publica_id,
                                    $mapas_lista_publica['items'],
                                    $mapas_lista_publica['categorias']
                                );
                                if ($precio_info['descuento_pct'] > 0):
                                ?>
                                    <span class="badge bg-danger" style="position: absolute; top: 10px; right: 10px; font-size: 14px;">
                                        -<?= $precio_info['descuento_pct'] ?>%
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="card-img-top bg-secondary" style="height: 200px; display: flex; align-items: center; justify-content: center;">
                                <span class="text-white">📦 Sin imagen</span>
                            </div>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                            <p class="card-text text-muted flex-grow-1">
                                <?= htmlspecialchars(substr($producto['descripcion'], 0, 100)) ?>...
                            </p>
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
                            <?php if ($precio_info['descuento_pct'] > 0): ?>
                                <div class="mb-1">
                                    <span class="text-muted text-decoration-line-through">$<?= number_format($precio_info['precio_original'], 2, ',', '.') ?></span>
                                </div>
                            <?php endif; ?>
                            <h4 class="text-primary">$<?= number_format($precio_info['precio'], 2, ',', '.') ?></h4>
                            <a href="producto.php?id=<?= $producto['id'] ?>" class="btn btn-primary mt-auto">Ver Detalles</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-5">
            <a href="tienda.php" class="btn btn-secondary btn-lg">Ver Todos los Productos</a>
        </div>
    </div>
</section>

<!-- Nuestros Clientes -->
<?php if (!empty($clientes)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="text-center mb-5">Nuestros Clientes</h2>
        <div class="row align-items-center">
            <?php foreach ($clientes as $cliente): ?>
                <div class="col-md-2 text-center mb-3">
                    <?php if ($cliente['enlace']): ?>
                        <a href="<?= htmlspecialchars($cliente['enlace']) ?>" target="_blank" title="<?= htmlspecialchars($cliente['nombre']) ?>">
                            <img src="<?= $image_path . htmlspecialchars($cliente['logo_url']) ?>" alt="<?= htmlspecialchars($cliente['nombre']) ?>" style="max-height: 60px; max-width: 100%;">
                        </a>
                    <?php else: ?>
                        <img src="<?= $image_path . htmlspecialchars($cliente['logo_url']) ?>" alt="<?= htmlspecialchars($cliente['nombre']) ?>" style="max-height: 60px; max-width: 100%;">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Ventajas -->
<section class="py-5">
    <div class="container">
        <h2 class="text-center mb-5">¿Por Qué Elegirnos?</h2>
        <div class="row">
            <div class="col-md-3">
                <div class="text-center">
                    <h3>🎨</h3>
                    <h5>Variedad</h5>
                    <p>Gran variedad de diseños y colores para cada gusto</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3>💰</h3>
                    <h5>Precios Justos</h5>
                    <p>Precios competitivos sin comprometer la calidad</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3>🚚</h3>
                    <h5>Envíos Rápidos</h5>
                    <p>Entrega rápida y segura a toda el país</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="text-center">
                    <h3>👥</h3>
                    <h5>Atención al Cliente</h5>
                    <p>Soporte profesional disponible para ayudarte</p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require 'includes/footer.php'; ?>
