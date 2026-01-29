<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Listas activas
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_listas_precios WHERE activo = 1 ORDER BY nombre");
$listas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Config actual
$lista_actual = null;
try {
    $stmt = $pdo->query("SELECT lista_precio_id FROM ecommerce_config LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $lista_actual = $row['lista_precio_id'] ?? null;
} catch (Exception $e) {
    $lista_actual = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lista_precio_id = !empty($_POST['lista_precio_id']) ? intval($_POST['lista_precio_id']) : null;
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_config");
        $count = (int)($stmt->fetch()['total'] ?? 0);
        if ($count === 0) {
            $stmt = $pdo->prepare("INSERT INTO ecommerce_config (id, lista_precio_id) VALUES (1, ?)");
            $stmt->execute([$lista_precio_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE ecommerce_config SET lista_precio_id = ? WHERE id = 1");
            $stmt->execute([$lista_precio_id]);
        }
        $lista_actual = $lista_precio_id;
        $mensaje = '✓ Lista de precios del ecommerce actualizada';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🛍️ Precios del Ecommerce</h1>
        <p class="text-muted">Seleccioná la lista que verán los clientes</p>
    </div>
    <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Lista de precios activa</label>
                <select class="form-select" name="lista_precio_id">
                    <option value="">-- Sin lista (precio base) --</option>
                    <?php foreach ($listas as $lista): ?>
                        <option value="<?= $lista['id'] ?>" <?= (int)$lista_actual === (int)$lista['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lista['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Si la lista tiene descuentos, se verán reflejados en la tienda.</small>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
