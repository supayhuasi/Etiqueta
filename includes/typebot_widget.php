<?php
$cfg_path = __DIR__ . '/typebot_config.json';
$cfg = [
    'enabled' => false,
    'placement' => 'bottom-right',
    'delay_seconds' => 3,
    'embed_code' => ''
];
if (file_exists($cfg_path)) {
    try {
        $raw = file_get_contents($cfg_path);
        $parsed = json_decode($raw, true);
        if (is_array($parsed)) $cfg = array_merge($cfg, $parsed);
    } catch (Exception $e) {}
}
if (empty($cfg['enabled'])) {
    return;
}
// Escape the JSON for inline usage
$jsCfg = json_encode([
    'placement' => $cfg['placement'] ?? 'bottom-right',
    'delay_seconds' => (int)($cfg['delay_seconds'] ?? 3),
    'embed_code' => $cfg['embed_code'] ?? ''
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<style>
  .typebot-wrapper { position: fixed; z-index: 999999; display: block; }
  .typebot-wrapper.bottom-right { right: 20px; bottom: 20px; }
  .typebot-wrapper.bottom-left { left: 20px; bottom: 20px; }
  .typebot-wrapper.top-right { right: 20px; top: 80px; }
  .typebot-wrapper.top-left { left: 20px; top: 80px; }
  .typebot-wrapper .tb-inner { max-width: 420px; max-height: 80vh; overflow: hidden; }
  @media (max-width:600px) { .typebot-wrapper { right: 12px; left: auto; bottom: 12px; } }
</style>
<div id="typebot-placeholder" aria-hidden="true"></div>
<script>
(function(){
  try {
    var cfg = <?= $jsCfg ?>;
    var delay = (parseInt(cfg.delay_seconds,10) || 0) * 1000;
    var placement = cfg.placement || 'bottom-right';
    var embed = cfg.embed_code || '';
    var placeholder = document.getElementById('typebot-placeholder');
    if (!placeholder) return;
    var wrapper = document.createElement('div');
    wrapper.className = 'typebot-wrapper ' + placement;
    wrapper.setAttribute('role','region');
    wrapper.setAttribute('aria-label','Asistente de chat');
    wrapper.style.display = 'none';
    var inner = document.createElement('div');
    inner.className = 'tb-inner';
    wrapper.appendChild(inner);
    document.body.appendChild(wrapper);
    setTimeout(function(){
      try {
        inner.innerHTML = embed;
        wrapper.style.display = '';
        wrapper.removeAttribute('aria-hidden');
      } catch(e){ console.error('Typebot insert error', e); }
    }, delay);
  } catch(e) { console.error('Typebot widget init error', e); }
})();
</script>
