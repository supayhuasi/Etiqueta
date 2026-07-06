<?php
require 'config.php';
require 'includes/header.php';
require 'includes/blog_publico_helper.php';

$articulos = [];
try {
    $stmt = $pdo->query("
        SELECT id, titulo, slug, resumen, imagen, destacado, publicado_en
        FROM ecommerce_blog_articulos
        WHERE estado = 'publicado'
        ORDER BY destacado DESC, orden ASC, publicado_en DESC, id DESC
    ");
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>

<div class="container py-5">
    <h1>Blog</h1>
    <p class="text-muted">Novedades, consejos y noticias de <?= htmlspecialchars($site_name) ?>.</p>

    <?php if (empty($articulos)): ?>
        <div class="alert alert-info">Todavía no hay artículos publicados.</div>
    <?php else: ?>
        <div class="row g-4 mt-2">
            <?php foreach ($articulos as $art): ?>
                <?php $imagen_url = blog_publico_imagen_url($art['imagen'], $public_base); ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card h-100 <?= !empty($art['destacado']) ? 'border-warning' : '' ?>">
                        <?php if ($imagen_url): ?>
                            <a href="blog_articulo.php?slug=<?= urlencode($art['slug']) ?>">
                                <img src="<?= htmlspecialchars($imagen_url) ?>" class="card-img-top" alt="<?= htmlspecialchars($art['titulo']) ?>" style="height: 200px; object-fit: cover;">
                            </a>
                        <?php endif; ?>
                        <div class="card-body d-flex flex-column">
                            <?php if (!empty($art['destacado'])): ?>
                                <span class="badge bg-warning text-dark align-self-start mb-2">Destacado</span>
                            <?php endif; ?>
                            <h5 class="card-title">
                                <a href="blog_articulo.php?slug=<?= urlencode($art['slug']) ?>" class="text-decoration-none text-dark">
                                    <?= htmlspecialchars($art['titulo']) ?>
                                </a>
                            </h5>
                            <?php if (!empty($art['resumen'])): ?>
                                <p class="card-text text-muted"><?= htmlspecialchars(mb_substr(strip_tags($art['resumen']), 0, 140)) ?><?= mb_strlen(strip_tags($art['resumen'])) > 140 ? '…' : '' ?></p>
                            <?php endif; ?>
                            <div class="mt-auto pt-2">
                                <?php if (!empty($art['publicado_en'])): ?>
                                    <div class="small text-muted mb-2"><?= htmlspecialchars(date('d/m/Y', strtotime($art['publicado_en']))) ?></div>
                                <?php endif; ?>
                                <a href="blog_articulo.php?slug=<?= urlencode($art['slug']) ?>" class="btn btn-outline-primary btn-sm">Leer más</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
