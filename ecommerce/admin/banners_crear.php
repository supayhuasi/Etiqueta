<?php
require 'includes/header.php';
require '../includes/banners_publico_helper.php';

banners_asegurar_tabla($pdo);
$zonas = banners_zonas_disponibles();

$editar = false;
$banner = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_banners WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $banner = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$banner) {
        die("<div class='alert alert-danger'>Banner no encontrado</div>");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'] ?? '';
    $enlace = $_POST['enlace'] ?? '';
    $orden = $_POST['orden'] ?? 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $ubicacion = $_POST['ubicacion'] ?? 'blog_sidebar';
    if (!array_key_exists($ubicacion, $zonas)) {
        $ubicacion = 'blog_sidebar';
    }
    $imagen = $banner['imagen'] ?? '';

    if (!empty($_FILES['imagen']['name'])) {
        $file = $_FILES['imagen'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            die("<div class='alert alert-danger'>Formato de imagen no permitido</div>");
        }

        $filename = 'banner_' . time() . '.' . $ext;
        $filepath = '../uploads/banners/' . $filename;

        if (!is_dir('../uploads/banners')) {
            mkdir('../uploads/banners', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $imagen = 'banners/' . $filename;
        }
    }

    if ($imagen === '') {
        echo "<div class='alert alert-danger'>Debés cargar una imagen</div>";
    } else {
        try {
            if ($editar) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_banners
                    SET titulo = ?, enlace = ?, orden = ?, activo = ?, imagen = ?, ubicacion = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titulo, $enlace, $orden, $activo, $imagen, $ubicacion, $_GET['id']]);
                $mensaje = "✓ Banner actualizado correctamente";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_banners (titulo, enlace, orden, activo, imagen, ubicacion)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$titulo, $enlace, $orden, $activo, $imagen, $ubicacion]);
                $mensaje = "✓ Banner creado correctamente";
            }
            echo "<div class='alert alert-success'>$mensaje</div>";
            header("refresh:2; url=banners.php");
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1><?= $editar ? '✏️ Editar Banner' : '🖼️ Crear Banner' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="banners.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8 mb-3">
                        <label class="form-label">Título</label>
                        <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($banner['titulo'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= (int)($banner['orden'] ?? 0) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enlace (URL)</label>
                    <input type="text" name="enlace" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($banner['enlace'] ?? '') ?>">
                    <small class="text-muted">Opcional. Si se completa, el banner será clickeable.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Ubicación</label>
                    <select name="ubicacion" class="form-select">
                        <?php foreach ($zonas as $valor => $etiqueta): ?>
                            <option value="<?= htmlspecialchars($valor) ?>" <?= ($banner['ubicacion'] ?? 'blog_sidebar') === $valor ? 'selected' : '' ?>><?= htmlspecialchars($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Selecciona dónde se mostrará este banner</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Imagen</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*" <?= $editar ? '' : 'required' ?>>
                    <small class="text-muted">Formatos: JPG, PNG, GIF, WebP. Máx 5MB</small>
                    <?php if ($banner && $banner['imagen']): ?>
                        <div class="mt-2">
                            <img src="../uploads/<?= htmlspecialchars($banner['imagen']) ?>" alt="Actual" style="max-width: 200px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($banner['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar</button>
                <a href="banners.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
