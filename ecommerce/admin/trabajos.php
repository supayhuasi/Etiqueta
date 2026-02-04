<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

$upload_dir = __DIR__ . '/../uploads/trabajos/';
$public_dir = 'uploads/trabajos/';
$allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'subir') {
        if (!empty($_FILES['trabajos']) && is_array($_FILES['trabajos']['name'])) {
            $total = count($_FILES['trabajos']['name']);
            $subidas = 0;
            for ($i = 0; $i < $total; $i++) {
                $name = $_FILES['trabajos']['name'][$i] ?? '';
                $tmp = $_FILES['trabajos']['tmp_name'][$i] ?? '';
                $err = $_FILES['trabajos']['error'][$i] ?? UPLOAD_ERR_NO_FILE;

                if ($err !== UPLOAD_ERR_OK || empty($name) || empty($tmp)) {
                    continue;
                }

                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowed_ext, true)) {
                    continue;
                }

                $filename = 'trabajo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($tmp, $upload_dir . $filename)) {
                    $subidas++;
                }
            }

            if ($subidas > 0) {
                $mensaje = "Se subieron {$subidas} imagen(es)";
            } else {
                $error = 'No se pudo subir ninguna imagen';
            }
        } else {
            $error = 'No se seleccionaron archivos';
        }
    }

    if ($accion === 'eliminar') {
        $archivo = $_POST['archivo'] ?? '';
        $archivo = basename($archivo);
        $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));

        if ($archivo && in_array($ext, $allowed_ext, true)) {
            $path = $upload_dir . $archivo;
            if (is_file($path)) {
                unlink($path);
                $mensaje = 'Imagen eliminada';
            } else {
                $error = 'Archivo no encontrado';
            }
        } else {
            $error = 'Archivo inválido';
        }
    }
}

$imagenes = [];
$archivos = glob($upload_dir . '/*.{jpg,jpeg,png,webp,gif}', GLOB_BRACE);
if ($archivos) {
    foreach ($archivos as $file) {
        $imagenes[] = $public_dir . basename($file);
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🖼️ Trabajos Realizados</h1>
        <p class="text-muted">Administrá las fotos que aparecen en el inicio</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Subir imágenes</h5>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="accion" value="subir">
            <div class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label class="form-label">Seleccionar imágenes</label>
                    <input type="file" class="form-control" name="trabajos[]" accept="image/*" multiple required>
                    <small class="text-muted">Formatos permitidos: JPG, PNG, WEBP, GIF</small>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">Subir</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Galería</h5>
    </div>
    <div class="card-body">
        <?php if (empty($imagenes)): ?>
            <div class="alert alert-info">No hay imágenes cargadas.</div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($imagenes as $img): ?>
                    <div class="col-6 col-md-4 col-lg-3">
                        <div class="card h-100">
                            <img src="<?= htmlspecialchars($img) ?>" class="card-img-top" alt="Trabajo" style="height: 180px; object-fit: cover;">
                            <div class="card-body p-2">
                                <form method="POST" onsubmit="return confirm('¿Eliminar esta imagen?');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="archivo" value="<?= htmlspecialchars(basename($img)) ?>">
                                    <button type="submit" class="btn btn-sm btn-danger w-100">Eliminar</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
