<?php
require 'config.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$articulo = null;

if ($slug !== '') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_blog_articulos WHERE slug = ? AND estado = 'publicado'");
        $stmt->execute([$slug]);
        $articulo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
    }
}

$productos_sugeridos = [];
$image_path = 'uploads/';

if ($articulo) {
    $seo_title = $articulo['titulo'];
    $seo_type = 'article';
    if (!empty($articulo['resumen'])) {
        $seo_description = trim(preg_replace('/\s+/', ' ', strip_tags($articulo['resumen'])));
    } else {
        $seo_description = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($articulo['contenido']))), 0, 160);
    }

    try {
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
            LIMIT 3
        ");
        $productos_sugeridos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
    }
}

require 'includes/header.php';
require 'includes/blog_publico_helper.php';
require 'includes/precios_publico.php';

$lista_publica_id = obtener_lista_precio_publica($pdo);
$mapas_lista_publica = cargar_mapas_lista_publica($pdo, $lista_publica_id);
?>

<div class="container py-5">
    <?php if (!$articulo): ?>
        <h1>Artículo no encontrado</h1>
        <p class="text-muted">El artículo que buscás no existe o ya no está disponible.</p>
        <a href="blog.php" class="btn btn-outline-primary">Volver al blog</a>
    <?php else: ?>
        <?php $imagen_url = blog_publico_imagen_url($articulo['imagen'], $public_base); ?>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="blog.php">Blog</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($articulo['titulo']) ?></li>
            </ol>
        </nav>

        <article class="mx-auto" style="max-width: 800px;">
            <h1><?= htmlspecialchars($articulo['titulo']) ?></h1>
            <?php if (!empty($articulo['publicado_en'])): ?>
                <p class="text-muted small">Publicado el <?= htmlspecialchars(date('d/m/Y', strtotime($articulo['publicado_en']))) ?></p>
            <?php endif; ?>

            <?php if ($imagen_url): ?>
                <img src="<?= htmlspecialchars($imagen_url) ?>" alt="<?= htmlspecialchars($articulo['titulo']) ?>" class="img-fluid rounded mb-4">
            <?php endif; ?>

            <div class="blog-contenido">
                <?= $articulo['contenido'] ?>
            </div>

            <div class="mt-5">
                <a href="blog.php" class="btn btn-outline-primary">← Volver al blog</a>
            </div>
        </article>

        <?php if (!empty($productos_sugeridos)): ?>
            <section class="mx-auto mt-5" style="max-width: 1140px;">
                <h3 class="text-center mb-4">Quizás te interese</h3>
                <div class="row">
                    <?php foreach ($productos_sugeridos as $producto): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card product-card h-100">
                                <?php if (!empty($producto['imagen_principal'])): ?>
                                    <div style="position: relative;">
                                        <img src="<?= $image_path . htmlspecialchars($producto['imagen_principal']) ?>" class="card-img-top" alt="<?= htmlspecialchars($producto['nombre']) ?>" style="height: 200px; object-fit: cover;">
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
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                    <?php if ($precio_info['descuento_pct'] > 0): ?>
                                        <div class="mb-1">
                                            <span class="text-muted text-decoration-line-through">$<?= number_format($precio_info['precio_original'], 2, ',', '.') ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <h4 class="text-primary">$<?= number_format($precio_info['precio'], 2, ',', '.') ?></h4>
                                    <a href="producto.php?id=<?= (int)$producto['id'] ?>" class="btn btn-primary mt-auto">Ver producto</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
