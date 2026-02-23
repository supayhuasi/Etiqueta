<?php
// Prefer includes config; if not available/readable, fall back to uploads copy created by admin
$cfg_path = __DIR__ . '/typebot_config.json';
$alt_path = __DIR__ . '/../uploads/typebot_config.json';
$cfg = [
    'enabled' => false,
    'placement' => 'bottom-right',
    'delay_seconds' => 3,
    'embed_code' => ''
];
// Cargar preferentemente desde includes; si no existe o no es legible, usar uploads
if (file_exists($cfg_path) && is_readable($cfg_path)) {
  try {
    $raw = file_get_contents($cfg_path);
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) $cfg = array_merge($cfg, $parsed);
  } catch (Exception $e) {}
} elseif (file_exists($alt_path) && is_readable($alt_path)) {
  try {
    $raw = file_get_contents($alt_path);
    $parsed = json_decode($raw, true);
    if (is_array($parsed)) $cfg = array_merge($cfg, $parsed);
  } catch (Exception $e) {}
}
if (empty($cfg['enabled'])) {
    return;
}
// Escape the JSON for inline usage
$embed_raw = $cfg['embed_code'] ?? '';
// Escape closing script tags to avoid breaking the surrounding <script> when inlined
$embed_safe = str_replace('</script>', '<\/script>', $embed_raw);
$jsCfg = json_encode([
  'placement' => $cfg['placement'] ?? 'bottom-right',
  'delay_seconds' => (int)($cfg['delay_seconds'] ?? 3),
  'embed_code' => $embed_safe
], JSON_UNESCAPED_UNICODE);
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
    // Si el embed es solo una URL, envolver en iframe para Typebot
    try {
      var t = embed.trim();
      if (/^https?:\/\//i.test(t) && !/^<\s*(iframe|script)/i.test(t)) {
        var url = t;
        var w = 360;
        var h = Math.max(400, Math.min(window.innerHeight - 120, 800));
        embed = '<iframe src="' + url.replace(/\"/g,'') + '" width="' + w + '" height="' + h + '" style="border:0;max-width:100%" loading="lazy" allow="clipboard-write; microphone; camera; autoplay; encrypted-media"></iframe>';
      }
    } catch(e) { console.warn('Typebot: embed auto-wrap failed', e); }
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
          // If embed contains any <script> tag, insert it inside an isolated iframe
          if (/\<script/i.test(embed)) {
            var iframe = document.createElement('iframe');
            iframe.setAttribute('aria-label','Typebot frame');
            iframe.style.border = '0';
            iframe.style.width = '100%';
            iframe.style.height = Math.max(420, Math.min(window.innerHeight - 120, 800)) + 'px';
            iframe.style.maxWidth = '100%';
            iframe.style.background = 'transparent';
            inner.appendChild(iframe);
            try {
              var idoc = iframe.contentWindow.document;
              idoc.open();
              idoc.write('<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>');
              idoc.close();
            } catch(e) {
              // fallback: set srcdoc when direct document write not allowed
              try { iframe.srcdoc = '<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>'; } catch(er) { console.error('Typebot iframe fallback failed', er); }
            }
            wrapper.style.display = '';
            wrapper.removeAttribute('aria-hidden');
            return;
          }
          // Otherwise treat embed as HTML (iframe or markup) and insert
          var parser = new DOMParser();
          var doc = parser.parseFromString('<div>' + embed + '</div>', 'text/html');
          var container = doc.body.firstChild;
          var fragment = document.createDocumentFragment();
          Array.prototype.slice.call(container.childNodes).forEach(function(node){
            fragment.appendChild(document.importNode(node, true));
          });
          inner.appendChild(fragment);
          wrapper.style.display = '';
          wrapper.removeAttribute('aria-hidden');
        } catch(e){ console.error('Typebot insert error', e); }
    }, delay);
  } catch(e) { console.error('Typebot widget init error', e); }
})();
</script>
