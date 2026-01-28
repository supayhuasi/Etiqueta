<?php
require 'includes/header.php';

// Si llega ID, es edición
$editar = false;
$lista = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $lista = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lista) {
        die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $activo = isset($_POST['activo']) ? 1 : 0;

    // Validar nombre único
    if (!$editar) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_listas_precios WHERE nombre = ?");
        $stmt->execute([$nombre]);
        if ($stmt->fetch()) {
            die("<div class='alert alert-danger'>Ya existe una lista con este nombre</div>");
        }
    }

    try {
        if ($editar) {
            $stmt = $pdo->prepare("
                UPDATE ecommerce_listas_precios 
                SET nombre = ?, descripcion = ?, activo = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $descripcion, $activo, $_GET['id']]);
            $mensaje = "✓ Lista de precios actualizada correctamente";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_listas_precios (nombre, descripcion, activo)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$nombre, $descripcion, $activo]);
            $mensaje = "✓ Lista de precios creada correctamente";
        }
        echo "<div class='alert alert-success'>$mensaje</div>";
        header("refresh:2; url=listas_precios.php");
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1><?= $editar ? '✏️ Editar Lista de Precios' : '💰 Crear Lista de Precios' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre de la Lista</label>
                    <input type="text" name="nombre" class="form-control" value="<?= $lista['nombre'] ?? '' ?>" placeholder="Ej: Mayorista, Distribuidor, etc." required>
                    <small class="text-muted">Este nombre debe ser único</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="3" placeholder="Describe para quién es esta lista de precios"><?= $lista['descripcion'] ?? '' ?></textarea>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($lista['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activa
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar</button>
                <a href="listas_precios.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
