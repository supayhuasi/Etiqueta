<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/includes/header.php';

$cfg_path = __DIR__ . '/../../includes/typebot_config.json';
$msg = '';
$cfg = [
    'enabled' => false,
    'placement' => 'bottom-right',
    'delay_seconds' => 3,
    'embed_code' => ''
];
if (file_exists($cfg_path)) {
    $raw = @file_get_contents($cfg_path);
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) $cfg = array_merge($cfg, $parsed);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = [];
    $new['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? true : false;
    $new['placement'] = in_array($_POST['placement'] ?? '', ['bottom-right','bottom-left','top-right','top-left']) ? $_POST['placement'] : 'bottom-right';
    $new['delay_seconds'] = max(0, (int)($_POST['delay_seconds'] ?? 3));
    $new['embed_code'] = trim($_POST['embed_code'] ?? '');
    $w = @file_put_contents($cfg_path, json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    if ($w !== false) {
        $msg = 'Guardado correctamente.';
        $cfg = $new;
    } else {
        $msg = 'Error al guardar el archivo de configuración. Ver permisos.';
    }
}
?>
<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Typebot — Configuración</h1>
        <a href="<?= $admin_url ?>index.php" class="btn btn-sm btn-outline-secondary">Volver</a>
    </div>
    <?php if ($msg): ?><div class="alert alert-<?= strpos($msg,'Error') === 0 ? 'danger' : 'success' ?>"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="post">
        <div class="mb-3 form-check">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" class="form-check-input" id="enabled" name="enabled" value="1" <?= !empty($cfg['enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="enabled">Activar Typebot</label>
        </div>
        <div class="mb-3">
            <label class="form-label">Ubicación</label>
            <select name="placement" class="form-select">
                <option value="bottom-right" <?= ($cfg['placement'] ?? '') === 'bottom-right' ? 'selected' : '' ?>>Abajo derecha</option>
                <option value="bottom-left" <?= ($cfg['placement'] ?? '') === 'bottom-left' ? 'selected' : '' ?>>Abajo izquierda</option>
                <option value="top-right" <?= ($cfg['placement'] ?? '') === 'top-right' ? 'selected' : '' ?>>Arriba derecha</option>
                <option value="top-left" <?= ($cfg['placement'] ?? '') === 'top-left' ? 'selected' : '' ?>>Arriba izquierda</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Segundos antes de aparecer</label>
            <input type="number" min="0" name="delay_seconds" class="form-control" value="<?= htmlspecialchars($cfg['delay_seconds']) ?>">
            <div class="form-text">Ingrese 0 para que aparezca inmediatamente.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Código de inserción (embed)</label>
            <textarea name="embed_code" rows="8" class="form-control" placeholder="Pegue aquí el snippet de Typebot o iframe"><?= htmlspecialchars($cfg['embed_code']) ?></textarea>
            <div class="form-text">Pegue aquí el script o iframe que Typebot provee (ej. &lt;script type="module"&gt;... o &lt;iframe&gt;). También puede ser solo la URL del typebot.</div>
        </div>
        <button class="btn btn-primary">Guardar</button>
    </form>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
