<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_envio_config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $config = null;
}

if (!$config) {
    $error = 'No existe la configuración de envío. Ejecutá setup_ecommerce.php primero.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config) {
    $costo_base = (float)($_POST['costo_base'] ?? 0);
    $gratis_importe = trim($_POST['gratis_desde_importe'] ?? '');
    $gratis_cantidad = trim($_POST['gratis_desde_cantidad'] ?? '');
    $activo = isset($_POST['activo']) ? 1 : 0;

    $gratis_importe_val = $gratis_importe === '' ? null : (float)$gratis_importe;
    $gratis_cantidad_val = $gratis_cantidad === '' ? null : (int)$gratis_cantidad;

    if ($costo_base < 0) {
        $error = 'El costo base no puede ser negativo.';
    } elseif ($gratis_importe_val !== null && $gratis_importe_val < 0) {
        $error = 'El monto para envío gratis no puede ser negativo.';
    } elseif ($gratis_cantidad_val !== null && $gratis_cantidad_val < 0) {
        $error = 'La cantidad para envío gratis no puede ser negativa.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_envio_config SET costo_base = ?, gratis_desde_importe = ?, gratis_desde_cantidad = ?, activo = ? WHERE id = 1");
            $stmt->execute([$costo_base, $gratis_importe_val, $gratis_cantidad_val, $activo]);

            $mensaje = 'Configuración guardada correctamente.';
            $stmt = $pdo->query("SELECT * FROM ecommerce_envio_config WHERE id = 1 LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>

<h1>Configuración de Envío</h1>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($config): ?>
<form method="POST" class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Costo base de envío</label>
                <input type="number" name="costo_base" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($config['costo_base'] ?? 0) ?>" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Envío gratis desde (monto)</label>
                <input type="number" name="gratis_desde_importe" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($config['gratis_desde_importe'] ?? '') ?>" placeholder="Ej: 15000">
                <small class="text-muted">Dejar vacío si no aplica.</small>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Envío gratis desde (cantidad)</label>
                <input type="number" name="gratis_desde_cantidad" class="form-control" step="1" min="0" value="<?= htmlspecialchars($config['gratis_desde_cantidad'] ?? '') ?>" placeholder="Ej: 3">
                <small class="text-muted">Dejar vacío si no aplica.</small>
            </div>
        </div>

        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !empty($config['activo']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">Envío activo</label>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
