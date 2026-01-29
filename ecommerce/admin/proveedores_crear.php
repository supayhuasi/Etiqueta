<?php
require 'includes/header.php';

$editar = false;
$proveedor = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_proveedores WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $proveedor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proveedor) {
        die("<div class='alert alert-danger'>Proveedor no encontrado</div>");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $cuit = trim($_POST['cuit'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        echo "<div class='alert alert-danger'>El nombre es obligatorio</div>";
    } else {
        try {
            if ($editar) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_proveedores
                    SET nombre = ?, email = ?, telefono = ?, direccion = ?, cuit = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $email, $telefono, $direccion, $cuit, $activo, $_GET['id']]);
                $mensaje = "✓ Proveedor actualizado correctamente";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_proveedores (nombre, email, telefono, direccion, cuit, activo)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $email, $telefono, $direccion, $cuit, $activo]);
                $mensaje = "✓ Proveedor creado correctamente";
            }
            echo "<div class='alert alert-success'>$mensaje</div>";
            header("refresh:1; url=proveedores.php");
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $editar ? '✏️ Editar Proveedor' : '🏭 Nuevo Proveedor' ?></h1>
    <a href="proveedores.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($proveedor['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($proveedor['email'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($proveedor['telefono'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">CUIT</label>
                    <input type="text" name="cuit" class="form-control" value="<?= htmlspecialchars($proveedor['cuit'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Dirección</label>
                <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($proveedor['direccion'] ?? '') ?>">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !isset($proveedor) || $proveedor['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
