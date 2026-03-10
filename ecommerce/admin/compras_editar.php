<?php
require 'includes/header.php';

$compra_id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_compras WHERE id = ?");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die('Compra no encontrada');
}

$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_proveedores WHERE activo = 1 ORDER BY nombre");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
        $fecha_compra = $_POST['fecha_compra'] ?? '';
        $observaciones = trim($_POST['observaciones'] ?? '');

        if ($proveedor_id <= 0) {
            throw new Exception('Seleccione un proveedor');
        }

        if (empty($fecha_compra)) {
            throw new Exception('Seleccione una fecha de compra');
        }

        $stmt = $pdo->prepare("
            UPDATE ecommerce_compras
            SET proveedor_id = ?, fecha_compra = ?, observaciones = ?
            WHERE id = ?
        ");
        $stmt->execute([$proveedor_id, $fecha_compra, $observaciones, $compra_id]);

        header('Location: compras_detalle.php?id=' . $compra_id . '&mensaje=editada');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>✏️ Editar Compra <?= htmlspecialchars($compra['numero_compra']) ?></h1>
        <p class="text-muted">Editar datos generales de la compra</p>
    </div>
    <a href="compras.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label for="proveedor_id" class="form-label">Proveedor *</label>
                <select class="form-select" id="proveedor_id" name="proveedor_id" required>
                    <option value="">-- Seleccionar --</option>
                    <?php foreach ($proveedores as $prov): ?>
                        <option value="<?= (int)$prov['id'] ?>" <?= (int)$compra['proveedor_id'] === (int)$prov['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prov['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label for="fecha_compra" class="form-label">Fecha de compra *</label>
                <input
                    type="date"
                    class="form-control"
                    id="fecha_compra"
                    name="fecha_compra"
                    value="<?= htmlspecialchars($compra['fecha_compra']) ?>"
                    required
                >
            </div>

            <div class="col-12">
                <label for="observaciones" class="form-label">Observaciones</label>
                <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($compra['observaciones'] ?? '') ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="compras_detalle.php?id=<?= $compra_id ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
