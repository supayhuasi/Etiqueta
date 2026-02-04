<?php
require 'includes/header.php';

// Asegurar columnas para manual de categoría
$cols_cat = $pdo->query("SHOW COLUMNS FROM ecommerce_categorias")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('manual_archivo', $cols_cat, true)) {
    $pdo->exec("ALTER TABLE ecommerce_categorias ADD COLUMN manual_archivo VARCHAR(255) NULL AFTER icono");
}
if (!in_array('manual_titulo', $cols_cat, true)) {
    $pdo->exec("ALTER TABLE ecommerce_categorias ADD COLUMN manual_titulo VARCHAR(255) NULL AFTER manual_archivo");
}

$id = $_GET['id'] ?? 0;
$categoria = null;
$titulo = 'Nueva Categoría';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_categorias WHERE id = ?");
    $stmt->execute([$id]);
    $categoria = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$categoria) die("Categoría no encontrada");
    $titulo = 'Editar Categoría';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $icono = $_POST['icono'] ?? '';
    $manual_titulo = trim($_POST['manual_titulo'] ?? '');
    $parent_id = !empty($_POST['parent_id']) ? intval($_POST['parent_id']) : null;
    $orden = intval($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;

    $manual_archivo = $categoria['manual_archivo'] ?? null;
    $eliminar_manual = !empty($_POST['eliminar_manual']);

    if ($eliminar_manual) {
        $manual_archivo = null;
    }

    if (isset($_FILES['manual_archivo']) && $_FILES['manual_archivo']['size'] > 0) {
        $file = $_FILES['manual_archivo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['pdf'];

        if (!in_array($ext, $allowed, true)) {
            $error = "El manual debe ser un archivo PDF";
        } else {
            $dir = '../../uploads/manuales/';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $filename = 'manual_categoria_' . ($id > 0 ? $id : 'new') . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $manual_archivo = $filename;
            } else {
                $error = "Error al subir el manual";
            }
        }
    }
    
    if (empty($nombre)) {
        $error = "El nombre es obligatorio";
    } else {
        try {
            if ($id > 0) {
                // Validar que no se asigne una subcategoría como padre de sí misma
                if ($parent_id === $id) {
                    $error = "Una categoría no puede ser padre de sí misma";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE ecommerce_categorias 
                        SET nombre = ?, descripcion = ?, icono = ?, manual_archivo = ?, manual_titulo = ?, parent_id = ?, orden = ?, activo = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre, $descripcion, $icono, $manual_archivo, $manual_titulo, $parent_id, $orden, $activo, $id]);
                    $mensaje = "Categoría actualizada";
                }
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_categorias (nombre, descripcion, icono, manual_archivo, manual_titulo, parent_id, orden, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $descripcion, $icono, $manual_archivo, $manual_titulo, $parent_id, $orden, $activo]);
                $mensaje = "Categoría creada";
            }
            
            if (isset($mensaje)) {
                header("Location: categorias.php");
                exit;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<h1><?= $titulo ?></h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($categoria['nombre'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($categoria['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="mb-3">
                <label for="parent_id" class="form-label">Categoría Padre (para subcategorías)</label>
                <select class="form-select" id="parent_id" name="parent_id">
                    <option value="">-- Sin categoría padre --</option>
                    <?php
                    $stmt = $pdo->query("
                        SELECT id, nombre FROM ecommerce_categorias 
                        WHERE activo = 1 AND id != " . ($id > 0 ? $id : 0) . "
                        ORDER BY nombre
                    ");
                    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $cat):
                    ?>
                        <option value="<?= $cat['id'] ?>" <?= ($categoria['parent_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Selecciona una categoría padre si esta es una subcategoría</small>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="icono" class="form-label">Icono (emoji o texto)</label>
                    <input type="text" class="form-control" id="icono" name="icono" value="<?= htmlspecialchars($categoria['icono'] ?? '📦') ?>" placeholder="ej: 🎨">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="orden" class="form-label">Orden</label>
                    <input type="number" class="form-control" id="orden" name="orden" value="<?= $categoria['orden'] ?? 0 ?>">
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">Manual de medición (PDF)</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($categoria['manual_archivo'])): ?>
                        <div class="mb-2">
                            <a href="/uploads/manuales/<?= htmlspecialchars($categoria['manual_archivo']) ?>" target="_blank">
                                Ver manual actual
                            </a>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="eliminar_manual" name="eliminar_manual" value="1">
                            <label class="form-check-label" for="eliminar_manual">Eliminar manual actual</label>
                        </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="manual_titulo" class="form-label">Título del manual</label>
                        <input type="text" class="form-control" id="manual_titulo" name="manual_titulo" value="<?= htmlspecialchars($categoria['manual_titulo'] ?? '') ?>" placeholder="Ej: Manual de medición">
                    </div>
                    <div class="mb-3">
                        <label for="manual_archivo" class="form-label">Archivo PDF</label>
                        <input type="file" class="form-control" id="manual_archivo" name="manual_archivo" accept="application/pdf">
                        <small class="text-muted">Subí un PDF con instrucciones de medición para esta categoría.</small>
                    </div>
                </div>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="activo" name="activo" <?= ($categoria['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <div class="d-flex gap-2">
                <a href="categorias.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
