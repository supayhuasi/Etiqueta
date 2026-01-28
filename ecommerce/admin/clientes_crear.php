<?php
require 'includes/header.php';

// Si llega ID, es edición
$editar = false;
$cliente = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes_logos WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cliente) {
        die("<div class='alert alert-danger'>Cliente no encontrado</div>");
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $enlace = $_POST['enlace'] ?? '';
    $orden = $_POST['orden'] ?? 0;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $logo_url = $_POST['logo_url'] ?? '';

    // Manejo de carga de imagen
    if (!empty($_FILES['logo']['name'])) {
        $file = $_FILES['logo'];
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'svg', 'webp'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $allowed)) {
            die("<div class='alert alert-danger'>Formato de imagen no permitido</div>");
        }

        $filename = 'cliente_' . time() . '.' . $ext;
        $filepath = '../uploads/clientes/' . $filename;
        
        if (!is_dir('../uploads/clientes')) {
            mkdir('../uploads/clientes', 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            $logo_url = 'clientes/' . $filename;
        }
    }

    try {
        if ($editar) {
            $stmt = $pdo->prepare("
                UPDATE ecommerce_clientes_logos 
                SET nombre = ?, enlace = ?, orden = ?, activo = ?, logo_url = ?
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $enlace, $orden, $activo, $logo_url, $_GET['id']]);
            $mensaje = "✓ Cliente actualizado correctamente";
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_clientes_logos (nombre, enlace, orden, activo, logo_url)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$nombre, $enlace, $orden, $activo, $logo_url]);
            $mensaje = "✓ Cliente creado correctamente";
        }
        echo "<div class='alert alert-success'>$mensaje</div>";
        header("refresh:2; url=clientes.php");
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1><?= $editar ? '✏️ Editar Cliente' : '👥 Crear Cliente' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="clientes.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del Cliente</label>
                        <input type="text" name="nombre" class="form-control" value="<?= $cliente['nombre'] ?? '' ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= $cliente['orden'] ?? 0 ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enlace (URL del cliente)</label>
                    <input type="text" name="enlace" class="form-control" placeholder="https://..." value="<?= $cliente['enlace'] ?? '' ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <small class="text-muted">Formatos: JPG, PNG, GIF, SVG, WebP. Máx 5MB</small>
                    <?php if ($cliente && $cliente['logo_url']): ?>
                        <div class="mt-2">
                            <p>Logo actual:</p>
                            <img src="../uploads/<?= htmlspecialchars($cliente['logo_url']) ?>" alt="Logo" style="max-height: 80px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= ($cliente['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar</button>
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
