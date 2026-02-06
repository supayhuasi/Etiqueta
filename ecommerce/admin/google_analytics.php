<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Asegurar columnas
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'ga_enabled'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN ga_enabled TINYINT(1) DEFAULT 0 AFTER redes_sociales");
    }
    $stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_empresa LIKE 'ga_measurement_id'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE ecommerce_empresa ADD COLUMN ga_measurement_id VARCHAR(50) AFTER ga_enabled");
    }
} catch (Exception $e) {
    $error = 'Error preparando columnas: ' . $e->getMessage();
}

// Obtener o crear registro de empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$empresa) {
    $pdo->exec("INSERT INTO ecommerce_empresa (nombre, email) VALUES ('Mi Empresa', 'info@empresa.com')");
    $stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $ga_enabled = isset($_POST['ga_enabled']) ? 1 : 0;
    $ga_measurement_id = trim($_POST['ga_measurement_id'] ?? '');

    if ($ga_enabled && $ga_measurement_id === '') {
        $error = 'El ID de medición es obligatorio si activás Google Analytics.';
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_empresa SET ga_enabled = ?, ga_measurement_id = ? WHERE id = ?");
            $stmt->execute([$ga_enabled, $ga_measurement_id ?: null, $empresa['id']]);
            $mensaje = 'Configuración guardada correctamente.';

            $stmt = $pdo->prepare("SELECT * FROM ecommerce_empresa WHERE id = ?");
            $stmt->execute([$empresa['id']]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC) ?? $empresa;
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}

$ga_enabled_val = !empty($empresa['ga_enabled']);
$ga_measurement_id_val = $empresa['ga_measurement_id'] ?? '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📈 Google Analytics</h1>
        <p class="text-muted">Configurá el ID de medición para el seguimiento del sitio</p>
    </div>
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
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="ga_enabled" name="ga_enabled" <?= $ga_enabled_val ? 'checked' : '' ?>>
                <label class="form-check-label" for="ga_enabled">Activar Google Analytics</label>
            </div>
            <div class="mb-3">
                <label for="ga_measurement_id" class="form-label">ID de medición (GA4)</label>
                <input type="text" class="form-control" id="ga_measurement_id" name="ga_measurement_id" placeholder="G-XXXXXXXXXX" value="<?= htmlspecialchars($ga_measurement_id_val) ?>">
                <small class="text-muted">Encontrás el ID en Google Analytics &gt; Administrar &gt; Flujos de datos.</small>
            </div>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
