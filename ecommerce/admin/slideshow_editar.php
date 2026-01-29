<?php
require 'includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_slideshow WHERE id = ?");
$stmt->execute([$_GET['id'] ?? 0]);
$slide = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$slide) {
    die("<div class='alert alert-danger'>Slideshow no encontrado</div>");
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Editar Slideshow</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="slideshow.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" action="slideshow_crear.php?id=<?= $slide['id'] ?>" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($slide['titulo']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= $slide['orden'] ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($slide['descripcion'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enlace (URL)</label>
                    <input type="text" name="enlace" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($slide['enlace'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Ubicación</label>
                    <select name="ubicacion" class="form-select">
                        <option value="inicio" <?= ($slide['ubicacion'] ?? 'inicio') === 'inicio' ? 'selected' : '' ?>>Inicio</option>
                        <option value="tienda" <?= ($slide['ubicacion'] ?? 'inicio') === 'tienda' ? 'selected' : '' ?>>Tienda</option>
                    </select>
                    <small class="text-muted">Selecciona dónde se mostrará este slide</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <small class="text-muted">Dejalo en blanco para mantener la imagen actual</small>
                    <?php if ($slide['imagen_url']): ?>
                        <div class="mt-2">
                            <p>Imagen actual:</p>
                            <img src="../uploads/<?= htmlspecialchars($slide['imagen_url']) ?>" alt="Actual" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $slide['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                <a href="slideshow.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
