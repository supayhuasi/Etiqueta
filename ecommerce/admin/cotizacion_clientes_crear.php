<?php
require 'includes/header.php';

$editar = false;
$cliente = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizacion_clientes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cliente) {
        die("<div class='alert alert-danger'>Cliente no encontrado</div>");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $empresa = trim($_POST['empresa'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($nombre)) {
        echo "<div class='alert alert-danger'>El nombre es obligatorio</div>";
    } else {
        try {
            if ($editar) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_cotizacion_clientes
                    SET nombre = ?, email = ?, telefono = ?, empresa = ?, activo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $email ?: null, $telefono ?: null, $empresa ?: null, $activo, $_GET['id']]);
                $mensaje = "✓ Cliente actualizado correctamente";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_cotizacion_clientes (nombre, email, telefono, empresa, activo)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $email ?: null, $telefono ?: null, $empresa ?: null, $activo]);
                $mensaje = "✓ Cliente creado correctamente";
            }
            echo "<div class='alert alert-success'>$mensaje</div>";
            header("refresh:1; url=cotizacion_clientes.php");
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $editar ? '✏️ Editar Cliente' : '👥 Nuevo Cliente' ?></h1>
    <a href="cotizacion_clientes.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($cliente['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Empresa</label>
                    <input type="text" name="empresa" class="form-control" value="<?= htmlspecialchars($cliente['empresa'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !isset($cliente) || $cliente['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
