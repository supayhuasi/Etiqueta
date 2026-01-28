<?php
require 'includes/header.php';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_clientes_logos WHERE id = ?");
$stmt->execute([$_GET['id'] ?? 0]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    die("<div class='alert alert-danger'>Cliente no encontrado</div>");
}
?>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-8">
            <h1>Editar Cliente</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="clientes.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" action="clientes_crear.php?id=<?= $cliente['id'] ?>" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Nombre del Cliente</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($cliente['nombre']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden" class="form-control" value="<?= $cliente['orden'] ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Enlace (URL del cliente)</label>
                    <input type="text" name="enlace" class="form-control" placeholder="https://..." value="<?= htmlspecialchars($cliente['enlace'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label">Logo</label>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                    <small class="text-muted">Dejalo en blanco para mantener el logo actual</small>
                    <?php if ($cliente['logo_url']): ?>
                        <div class="mt-2">
                            <p>Logo actual:</p>
                            <img src="../uploads/<?= htmlspecialchars($cliente['logo_url']) ?>" alt="Logo" style="max-height: 80px;">
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-check mb-3">
                    <input type="checkbox" name="activo" class="form-check-input" id="activo" <?= $cliente['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">
                        Activo
                    </label>
                </div>

                <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                <a href="clientes.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
