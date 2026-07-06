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

if ($articulo) {
    $seo_title = $articulo['titulo'];
    $seo_type = 'article';
    if (!empty($articulo['resumen'])) {
        $seo_description = trim(preg_replace('/\s+/', ' ', strip_tags($articulo['resumen'])));
    } else {
        $seo_description = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($articulo['contenido']))), 0, 160);
    }
}

require 'includes/header.php';
require 'includes/blog_publico_helper.php';
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
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
