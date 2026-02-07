<?php
require 'config.php';
require 'includes/header.php';

$faqs = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_faq WHERE activo = 1 ORDER BY orden ASC, id DESC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>

<div class="container py-5">
    <h1>Preguntas Frecuentes</h1>
    <p class="text-muted">Respondemos las dudas más comunes.</p>

    <?php if (empty($faqs)): ?>
        <div class="alert alert-info">No hay preguntas cargadas.</div>
    <?php else: ?>
        <div class="accordion" id="faqAccordion">
            <?php foreach ($faqs as $i => $f):
                $item_id = 'faq' . $f['id'];
            ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading-<?= htmlspecialchars($item_id) ?>">
                        <button class="accordion-button <?= $i === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= htmlspecialchars($item_id) ?>" aria-expanded="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="collapse-<?= htmlspecialchars($item_id) ?>">
                            <?= htmlspecialchars($f['pregunta']) ?>
                        </button>
                    </h2>
                    <div id="collapse-<?= htmlspecialchars($item_id) ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" aria-labelledby="heading-<?= htmlspecialchars($item_id) ?>" data-bs-parent="#faqAccordion">
                        <div class="accordion-body">
                            <?= nl2br(htmlspecialchars($f['respuesta'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
