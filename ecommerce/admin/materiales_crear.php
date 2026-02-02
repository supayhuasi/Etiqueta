<?php
require 'includes/header.php';

$editar = false;
$material = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_materiales WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        die("<div class='alert alert-danger'>Material no encontrado</div>");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $unidad = trim($_POST['unidad'] ?? '');
    $costo = $_POST['costo'] !== '' ? floatval($_POST['costo']) : null;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($nombre === '' || $unidad === '') {
        echo "<div class='alert alert-danger'>Nombre y unidad son obligatorios</div>";
    } else {
        try {
            if ($editar) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_materiales
                    SET nombre = ?, unidad = ?, costo = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $unidad, $costo, $activo, $_GET['id']]);
                $mensaje = "✓ Material actualizado";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_materiales (nombre, unidad, costo, activo)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $unidad, $costo, $activo]);
                $mensaje = "✓ Material creado";
            }
            echo "<div class='alert alert-success'>$mensaje</div>";
            header("refresh:1; url=materiales.php");
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $editar ? '✏️ Editar Material' : '🧵 Nuevo Material' ?></h1>
    <a href="materiales.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($material['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Unidad *</label>
                    <input type="text" name="unidad" class="form-control" placeholder="m, m2, un" value="<?= htmlspecialchars($material['unidad'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Costo</label>
                    <input type="number" step="0.01" name="costo" class="form-control" value="<?= htmlspecialchars($material['costo'] ?? '') ?>">
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !isset($material) || $material['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
