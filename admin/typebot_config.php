<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require __DIR__ . '/../includes/header.php';

$cfg_path = __DIR__ . '/../includes/typebot_config.json';
$msg = '';
$cfg = [
  'enabled' => false,
  'placement' => 'bottom-right',
  'delay_seconds' => 3,
  'embed_code' => ''
];
if (file_exists($cfg_path)) {
  $raw = file_get_contents($cfg_path);
  $parsed = json_decode($raw, true);
  if (is_array($parsed)) $cfg = array_merge($cfg, $parsed);
}

$cfg_dir = dirname($cfg_path);
$cfg_real = file_exists($cfg_path) ? realpath($cfg_path) : $cfg_path;
$cfg_dir_exists = is_dir($cfg_dir);
$cfg_dir_writable = $cfg_dir_exists && is_writable($cfg_dir);
$cfg_file_exists = file_exists($cfg_path);
$cfg_file_writable = $cfg_file_exists ? is_writable($cfg_path) : false;
$cfg_ready = $cfg_dir_writable && (!$cfg_file_exists || $cfg_file_writable);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $new = [];
  $new['enabled'] = isset($_POST['enabled']) && $_POST['enabled'] === '1' ? true : false;
  $new['placement'] = in_array($_POST['placement'] ?? '', ['bottom-right','bottom-left','top-right','top-left']) ? $_POST['placement'] : 'bottom-right';
  $new['delay_seconds'] = max(0, (int)($_POST['delay_seconds'] ?? 3));
  $new['embed_code'] = trim($_POST['embed_code'] ?? '');

  $cfg_dir = dirname($cfg_path);
  if (!is_dir($cfg_dir)) {
    @mkdir($cfg_dir, 0755, true);
  }

  $json_payload = json_encode($new, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  $w_includes = @file_put_contents($cfg_path, $json_payload, LOCK_EX);

  if ($w_includes !== false) {
    $msg = 'Guardado correctamente en includes (' . $cfg_path . ').';
    $cfg = $new;
  } else {
    $real = file_exists($cfg_path) ? realpath($cfg_path) : $cfg_path;
    $dir_writable = is_dir($cfg_dir) && is_writable($cfg_dir) ? 'sí' : 'no';
    $file_writable = file_exists($cfg_path) ? (is_writable($cfg_path) ? 'sí' : 'no') : 'no existe';
    $msg = 'Error al guardar el archivo de configuración. Ver permisos. Ruta esperada: ' . $real . '. Writable dir: ' . $dir_writable . '. Writable archivo: ' . $file_writable . '.';
    error_log('Typebot config save failed for includes path: ' . $cfg_path . ' | payload: ' . $json_payload);
    $raw_json_for_copy = $json_payload;
  }
}
?>
<div class="container mt-4">
  <h2>Configuración Typebot</h2>
  <?php if ($msg): ?><div class="alert alert-info"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
  <div class="alert alert-<?= $cfg_ready ? 'success' : 'warning' ?>">
    <strong>Estado de guardado:</strong>
    Ruta: <code><?= htmlspecialchars($cfg_real) ?></code><br>
    Directorio existe: <strong><?= $cfg_dir_exists ? 'sí' : 'no' ?></strong> ·
    Directorio writable: <strong><?= $cfg_dir_writable ? 'sí' : 'no' ?></strong> ·
    Archivo existe: <strong><?= $cfg_file_exists ? 'sí' : 'no' ?></strong> ·
    Archivo writable: <strong><?= $cfg_file_exists ? ($cfg_file_writable ? 'sí' : 'no') : 'n/a' ?></strong>
  </div>
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
      <div class="form-text">Pegue aquí el script o iframe que Typebot provee (ej. &lt;script src="..."&gt; o &lt;iframe&gt;).</div>
    </div>
    <button class="btn btn-primary">Guardar</button>
  </form>
  <?php if (!empty($raw_json_for_copy)): ?>
    <hr>
    <div class="alert alert-warning">No fue posible guardar automáticamente. Copie manualmente el siguiente JSON y péguelo en <code>includes/typebot_config.json</code> (o ajuste permisos del servidor):</div>
    <pre style="white-space:pre-wrap;background:#f8f9fa;padding:12px;border-radius:6px;border:1px solid #ddd;"><?= htmlspecialchars($raw_json_for_copy) ?></pre>
    <div class="mt-2"><strong>Comandos sugeridos (ejecutar en el servidor):</strong>
      <pre>sudo chown www-data:www-data <?= dirname(__DIR__) . '/includes/typebot_config.json' ?>
sudo chmod 664 <?= dirname(__DIR__) . '/includes/typebot_config.json' ?></pre>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
