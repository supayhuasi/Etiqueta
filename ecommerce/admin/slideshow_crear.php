<?php
require 'includes/header.php';

// Si llega ID, es edición
$editar = false;
$slide = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_slideshow WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $slide = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$slide) {
        die("<div class='alert alert-danger'>Slideshow no encontrado</div>");
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $enlace = $_POST['enlace'] ?? '';
    $orden = $_POST['orden'] ?? 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $imagen_url = $_POST['imagen_url'] ?? '';

    // Manejo de carga de imagen
    if (!empty($_FILES['imagen']['name'])) {
        $file = $_FILES['imagen'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            die("<div class='alert alert-danger'>Formato de imagen no permitido</div>");
        }

        $filename = 'slideshow_' . time() . '.' . $ext;
        $filepath = '../uploads/slideshow/' . $filename;
        
        if (!is_dir('../uploads/slideshow')) {
            mkdir('../uploads/slideshow', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $imagen_url = 'slideshow/' . $filename;
        }
    }

    try {
        if ($editar) {
            $stmt = $pdo->prepare("
                UPDATE ecommerce_slideshow 
                SET titulo = ?, descripcion = ?, enlace = ?, orden = ?, activo = ?, imagen_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$titulo, $descripcion, $enlace, $orden, $activo, $imagen_url, $_GET['id']]);
            $mensaje = "✓ Slideshow actualizado correctamente";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_slideshow (titulo, descripcion, enlace, orden, activo, imagen_url)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$titulo, $descripcion, $enlace, $orden, $activo, $imagen_url]);
            $mensaje = "✓ Slideshow creado correctamente";
        }
        echo "<div class='alert alert-success'>$mensaje</div>";
        header("refresh:2; url=slideshow.php");
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1><?= $editar ? '✏️ Editar Slideshow' : '📸 Crear Slideshow' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="slideshow.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?= $slide['titulo'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= $slide['orden'] ?? 0 ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3"><?= $slide['descripcion'] ?? '' ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enlace (URL)</label>
                    <input type="text" name="enlace" class="form-control" placeholder="https://..." value="<?= $slide['enlace'] ?? '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Máx 5MB</small>
                    <?php if ($slide && $slide['imagen_url']): ?>
                        <div class="mt-2">
                            <img src="../uploads/<?= htmlspecialchars($slide['imagen_url']) ?>" alt="Actual" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($slide['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar</button>
                <a href="slideshow.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
