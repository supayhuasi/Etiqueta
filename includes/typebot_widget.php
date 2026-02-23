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
    // Unescape any escaped closing script tags produced when JSON-encoding
    try { embed = embed.replace(/<\\\/script>/gi, '</script>'); } catch(e) { }
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
    // estado visible mientras carga
    var loading = document.createElement('div');
    loading.className = 'tb-loading';
    loading.textContent = 'Cargando asistente...';
    inner.appendChild(loading);
    wrapper.appendChild(inner);
    document.body.appendChild(wrapper);
    setTimeout(function(){
      try {
          // If embed contains a module script, insert it as a real module in the top-level document
          if (/\<script[^>]*type=["']?module["']?/i.test(embed)) {
            try {
              // Support both inline module scripts and module scripts with a src attribute.
              var matchSrc = embed.match(/<script[^>]*type=["']?module["']?[^>]*src=["']([^"']+)["'][^>]*>/i);
              var matchInline = embed.match(/<script[^>]*type=["']?module["']?[^>]*>([\s\S]*?)<\/script>/i);
              if (matchSrc && matchSrc[1]) {
                var mod = document.createElement('script');
                mod.type = 'module';
                mod.src = matchSrc[1];
                document.head.appendChild(mod);
                try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){}
                wrapper.style.display = '';
                wrapper.removeAttribute('aria-hidden');
                return;
              } else if (matchInline && matchInline[1]) {
                var moduleSource = matchInline[1];
                var mod = document.createElement('script');
                mod.type = 'module';
                mod.textContent = moduleSource;
                document.head.appendChild(mod);
                try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){}
                wrapper.style.display = '';
                wrapper.removeAttribute('aria-hidden');
                return;
              }
            } catch(e) { console.error('Typebot module injection failed', e); }
          }
          // If embed contains any other <script> tag, insert it inside an isolated iframe
          if (/\<script/i.test(embed)) {
            var iframe = document.createElement('iframe');
            iframe.setAttribute('aria-label','Typebot frame');
            iframe.style.border = '0';
            iframe.style.width = '100%';
            iframe.style.height = Math.max(420, Math.min(window.innerHeight - 120, 800)) + 'px';
            iframe.style.maxWidth = '100%';
            iframe.style.background = 'transparent';
            // debug border for visibility
            iframe.style.outline = '2px dashed rgba(0,0,0,0.12)';
            // Allow common capabilities the widget may need (media, clipboard, autoplay)
            try { iframe.setAttribute('allow', 'clipboard-write; microphone; camera; autoplay; encrypted-media; display-capture'); } catch(e) {}
            try { iframe.allowFullscreen = true; } catch(e) {}
            inner.appendChild(iframe);
            // prepare blob url for debug open
            var __tb_blob = null;
            try {
              var idoc = iframe.contentWindow.document;
              idoc.open();
              idoc.write('<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>');
              idoc.close();
              try { __tb_blob = URL.createObjectURL(new Blob(['<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>'], {type:'text/html'})); } catch(e) { console.warn('Typebot: blob creation failed', e); }
              // quitar loader si aún está
              try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){}
            } catch(e) {
              // fallback: set srcdoc when direct document write not allowed
              try { iframe.srcdoc = '<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>'; } catch(er) { console.error('Typebot iframe fallback failed', er); }
              try { __tb_blob = URL.createObjectURL(new Blob(['<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>'], {type:'text/html'})); } catch(e) { console.warn('Typebot: blob creation failed', e); }
              try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){}
            }
            // attach listeners to report load/error
            try {
              iframe.addEventListener('load', function(){ console.log('Typebot iframe loaded'); try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){} });
              iframe.addEventListener('error', function(ev){ console.error('Typebot iframe error', ev); try { var errEl = document.createElement('div'); errEl.className='tb-error'; errEl.textContent='Error al cargar el iframe. Revisa la consola.'; inner.appendChild(errEl);} catch(e){} });
            } catch(e){}
            // add debug toolbar button
            try {
              var dbg = document.createElement('div'); dbg.style.position='absolute'; dbg.style.right='8px'; dbg.style.top='8px'; dbg.style.zIndex='1000001';
              dbg.innerHTML = '<button id="tb-open-debug" class="btn btn-sm btn-outline-secondary">Abrir asistente (nueva pestaña)</button>';
              wrapper.style.position = 'fixed';
              wrapper.appendChild(dbg);
              var openBtn = dbg.querySelector('#tb-open-debug');
              if (openBtn) openBtn.addEventListener('click', function(){
                try {
                  var html = '<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>';
                  var w = window.open('', '_blank');
                  if (!w) {
                    // fallback to data URL if popup blocked
                    var data = 'data:text/html,' + encodeURIComponent(html);
                    window.open(data, '_blank');
                    return;
                  }
                  w.document.open();
                  w.document.write(html);
                  w.document.close();
                } catch(e) {
                  console.error('Typebot debug open failed', e);
                  if (__tb_blob) { window.open(__tb_blob, '_blank'); }
                }
              });
            } catch(e){}
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
          try { if (loading && loading.parentNode) loading.parentNode.removeChild(loading); } catch(e){}
          wrapper.style.display = '';
          wrapper.removeAttribute('aria-hidden');
        } catch(e){ console.error('Typebot insert error', e); }
    }, delay);
      // si algo falla después del timeout, mostrar botón de fallback
      setTimeout(function(){
        try {
          if (inner && inner.children.length === 0) {
            var err = document.createElement('div');
            err.className = 'tb-error';
            err.innerHTML = 'No se pudo cargar el asistente aquí. <button id="tb-open-new" class="btn btn-sm btn-primary">Abrir en nueva pestaña</button>';
            inner.appendChild(err);
            var btn = document.getElementById('tb-open-new');
            if (btn) {
              btn.addEventListener('click', function(){
                try {
                  try {
                    var html = '<!doctype html><html><head><meta charset="utf-8"></head><body>' + embed + '</body></html>';
                    var nw = window.open('', '_blank');
                    if (!nw) {
                      var data = 'data:text/html,' + encodeURIComponent(html);
                      window.open(data, '_blank');
                      return;
                    }
                    nw.document.open();
                    nw.document.write(html);
                    nw.document.close();
                  } catch(err) { console.error('Typebot open new tab failed', err); }
                } catch(err) { console.error('Typebot open new tab failed', err); }
              });
            }
          }
        } catch(e) {}
      }, delay + 1000);
  } catch(e) { console.error('Typebot widget init error', e); }
})();
</script>
