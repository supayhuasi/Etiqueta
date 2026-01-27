<?php
require 'includes/header.php';

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
    $orden = intval($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    
    if (empty($nombre)) {
        $error = "El nombre es obligatorio";
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_categorias 
                    SET nombre = ?, descripcion = ?, icono = ?, orden = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $descripcion, $icono, $orden, $activo, $id]);
                $mensaje = "Categoría actualizada";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_categorias (nombre, descripcion, icono, orden, activo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $descripcion, $icono, $orden, $activo]);
                $mensaje = "Categoría creada";
            }
            
            header("Location: categorias.php");
            exit;
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
        <form method="POST">
            <div class="mb-3">
                <label for="nombre" class="form-label">Nombre *</label>
                <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($categoria['nombre'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($categoria['descripcion'] ?? '') ?></textarea>
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
