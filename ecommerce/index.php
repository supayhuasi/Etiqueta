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

// Obtener fotos de trabajos (galería)
$trabajos = [];
$trabajos_dir = __DIR__ . '/uploads/trabajos';
if (is_dir($trabajos_dir)) {
    $archivos = glob($trabajos_dir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
    if ($archivos) {
        foreach ($archivos as $file) {
            $trabajos[] = 'uploads/trabajos/' . basename($file);
        }
    }
}

$suscripcion_mensaje = '';
$suscripcion_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'suscribir') {
    $email_sus = trim($_POST['email'] ?? '');
    if ($email_sus === '') {
        $suscripcion_error = 'Ingresá un email válido.';
    } elseif (!filter_var($email_sus, FILTER_VALIDATE_EMAIL)) {
        $suscripcion_error = 'Email inválido.';
    } else {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_suscriptores (
                id INT PRIMARY KEY AUTO_INCREMENT,
                email VARCHAR(255) NOT NULL UNIQUE,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            )");
            $stmt = $pdo->prepare("INSERT IGNORE INTO ecommerce_suscriptores (email) VALUES (?)");
            $stmt->execute([$email_sus]);
            $suscripcion_mensaje = '¡Gracias por suscribirte!';
        } catch (Exception $e) {
            $suscripcion_error = 'No pudimos registrar el email. Intentá de nuevo.';
        }
    }
}
?>

<?php if (!empty($empresa['marquesina_activa']) && !empty($empresa['marquesina_texto'])): ?>
    <div class="marquesina" style="background: <?= htmlspecialchars($empresa['marquesina_bg'] ?? '#111827') ?>; color: <?= htmlspecialchars($empresa['marquesina_text_color'] ?? '#ffffff') ?>;">
        <div class="container">
            <div class="marquesina__track">
                <div class="marquesina__content">
                    <?php if (!empty($empresa['marquesina_link'])): ?>
                        <a href="<?= htmlspecialchars($empresa['marquesina_link']) ?>" class="marquesina__link" style="color: inherit;">
                            <?= htmlspecialchars($empresa['marquesina_texto']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($empresa['marquesina_texto']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($suscripcion_mensaje)): ?>
    <div class="container mt-3">
        <div class="alert alert-success">✓ <?= htmlspecialchars($suscripcion_mensaje) ?></div>
    </div>
<?php elseif (!empty($suscripcion_error)): ?>
    <div class="container mt-3">
        <div class="alert alert-danger"><?= htmlspecialchars($suscripcion_error) ?></div>
    </div>
<?php endif; ?>

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
                <img src="<?= $image_path . htmlspecialchars($slide['imagen_url']) ?>" class="d-block w-100 slider-img" alt="<?= htmlspecialchars($slide['titulo']) ?>">
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

<!-- Trabajos realizados -->
<?php if (!empty($trabajos)): ?>
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Trabajos Realizados</h2>
                <p class="text-muted mb-0">Algunas instalaciones y proyectos recientes</p>
            </div>
        </div>
        <div class="row g-3">
            <?php foreach (array_slice($trabajos, 0, 12) as $foto): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border-0 shadow-sm h-100">
                        <img src="<?= htmlspecialchars($foto) ?>" class="card-img-top" alt="Trabajo" style="height: 200px; object-fit: cover;">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Modal Suscripción -->
<div class="modal fade" id="suscripcionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Suscribite y recibí novedades</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">Dejanos tu email para enviarte ofertas y lanzamientos.</p>
                <form method="POST" id="suscripcionForm">
                    <input type="hidden" name="accion" value="suscribir">
                    <div class="mb-3">
                        <label for="suscripcion_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="suscripcion_email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Suscribirme</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const suscrito = localStorage.getItem('suscripcion_popup_visto');
    const tieneMensaje = <?= !empty($suscripcion_mensaje) ? 'true' : 'false' ?>;
    if (!suscrito && !tieneMensaje) {
        const modalEl = document.getElementById('suscripcionModal');
        if (modalEl && window.bootstrap) {
            const modal = new bootstrap.Modal(modalEl);
            setTimeout(() => modal.show(), 1200);
            modalEl.addEventListener('hidden.bs.modal', () => {
                localStorage.setItem('suscripcion_popup_visto', '1');
            });
        }
    }
});
</script>

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
