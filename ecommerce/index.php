<?php
require 'config.php';
require 'includes/navbar.php';

// Obtener información de la empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener algunos productos destacados
$stmt = $pdo->query("
    SELECT * FROM ecommerce_productos 
    WHERE activo = 1 
    ORDER BY RAND() 
    LIMIT 6
");
$productos_destacados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!-- Sección Hero -->
<section class="hero">
    <div class="container">
        <h1>Bienvenido a Tucu Roller</h1>
        <p>Los mejores productos en cortinas, toldos y persianas</p>
        <a href="tienda.php" class="btn btn-light btn-lg">Ir a la Tienda</a>
    </div>
</section>

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
                    <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ" allowfullscreen="" loading="lazy"></iframe>
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
                        <?php if (!empty($producto['imagen'])): ?>
                            <img src="uploads/<?= htmlspecialchars($producto['imagen']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>">
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
                            <h4 class="text-primary">$<?= number_format($producto['precio_base'], 2, ',', '.') ?></h4>
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
