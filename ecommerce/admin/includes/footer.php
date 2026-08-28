        </div>
    </div>
</div>

<?php if (isset($_SESSION['user']['id'])):
    require_once __DIR__ . '/chat_helper.php';
    try {
        chat_asegurar_tablas($pdo);
        chat_actualizar_actividad($pdo, (int)$_SESSION['user']['id']);
    } catch (Throwable $e) {
        error_log('chat widget bootstrap error: ' . $e->getMessage());
    }
    $chat_mi_id = (int)$_SESSION['user']['id'];
?>
<style>
    #chatWidgetBtn {
        position: fixed;
        right: 20px;
        bottom: 20px;
        width: 56px;
        height: 56px;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        border: none;
        box-shadow: 0 8px 20px rgba(37, 99, 235, 0.35);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.4rem;
        z-index: 1080;
        cursor: pointer;
    }
    #chatWidgetBtn:hover { background: #1d4ed8; }
    #chatWidgetBadge {
        position: absolute;
        top: -4px;
        right: -4px;
        background: #dc3545;
        color: #fff;
        border-radius: 999px;
        font-size: 0.68rem;
        padding: 2px 6px;
        line-height: 1;
        display: none;
        min-width: 18px;
        text-align: center;
    }
    #chatWidgetPanel {
        position: fixed;
        right: 20px;
        bottom: 88px;
        width: 320px;
        max-width: calc(100vw - 40px);
        height: 440px;
        max-height: calc(100vh - 120px);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 14px 40px rgba(15, 23, 42, 0.22);
        display: none;
        flex-direction: column;
        overflow: hidden;
        z-index: 1080;
        border: 1px solid #e5eaf2;
    }
    #chatWidgetPanel .chat-widget-header {
        background: #2563eb;
        color: #fff;
        padding: 10px 12px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex: 0 0 auto;
    }
    #chatWidgetPanel .chat-widget-header .chat-widget-title { font-weight: 600; font-size: 0.95rem; }
    #chatWidgetPanel .chat-widget-header .chat-widget-sub { font-size: 0.72rem; opacity: 0.85; }
    #chatWidgetClose { background: none; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; line-height: 1; }
    #chatWidgetConectados {
        flex: 0 0 auto;
        display: flex;
        gap: 6px;
        padding: 8px 10px;
        overflow-x: auto;
        border-bottom: 1px solid #eef1f6;
        background: #f8fafc;
    }
    .chat-widget-chip {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: 999px;
        padding: 3px 9px;
        font-size: 0.74rem;
        white-space: nowrap;
        color: #334155;
    }
    .chat-widget-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; }
    .chat-widget-conectados-vacio { font-size: 0.74rem; color: #94a3b8; padding: 3px 0; }
    #chatWidgetMensajes {
        flex: 1 1 auto;
        overflow-y: auto;
        padding: 10px;
        background: #fbfcfe;
    }
    .chat-widget-msg { margin-bottom: 10px; max-width: 82%; }
    .chat-widget-msg-autor { font-size: 0.68rem; color: #64748b; margin-bottom: 2px; font-weight: 600; }
    .chat-widget-msg-burbuja {
        background: #eef2f7;
        color: #1e293b;
        padding: 6px 10px;
        border-radius: 12px;
        font-size: 0.85rem;
        word-wrap: break-word;
        white-space: pre-wrap;
    }
    .chat-widget-msg-hora { font-size: 0.65rem; color: #94a3b8; margin-top: 2px; }
    .chat-widget-msg-mio { margin-left: auto; text-align: right; }
    .chat-widget-msg-mio .chat-widget-msg-burbuja { background: #2563eb; color: #fff; margin-left: auto; display: inline-block; }
    #chatWidgetForm {
        flex: 0 0 auto;
        display: flex;
        gap: 6px;
        padding: 8px;
        border-top: 1px solid #eef1f6;
        background: #fff;
    }
    #chatWidgetInput {
        flex: 1 1 auto;
        border: 1px solid #dbe2ea;
        border-radius: 8px;
        padding: 7px 10px;
        font-size: 0.85rem;
        resize: none;
    }
    #chatWidgetForm button {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 0 14px;
        font-size: 0.9rem;
    }
    #chatWidgetForm button:disabled { opacity: 0.6; }
    @media (max-width: 480px) {
        #chatWidgetPanel { right: 10px; left: 10px; width: auto; bottom: 80px; }
        #chatWidgetBtn { right: 14px; bottom: 14px; }
    }
</style>

<button type="button" id="chatWidgetBtn" title="Chat entre usuarios conectados">
    <i class="bi bi-chat-dots-fill"></i>
    <span id="chatWidgetBadge"></span>
</button>

<div id="chatWidgetPanel">
    <div class="chat-widget-header">
        <div>
            <div class="chat-widget-title">Chat del equipo</div>
            <div class="chat-widget-sub"><span id="chatWidgetConectadosCount">0</span> conectado(s) ahora</div>
        </div>
        <button type="button" id="chatWidgetClose" aria-label="Cerrar">&times;</button>
    </div>
    <div id="chatWidgetConectados"></div>
    <div id="chatWidgetMensajes"></div>
    <form id="chatWidgetForm">
        <textarea id="chatWidgetInput" rows="1" maxlength="1000" placeholder="Escribí un mensaje..." autocomplete="off"></textarea>
        <button type="submit"><i class="bi bi-send-fill"></i></button>
    </form>
</div>

<script>
(function () {
    var ADMIN_URL = <?= json_encode($admin_url) ?>;
    var MI_USUARIO_ID = <?= (int)$chat_mi_id ?>;

    var btn = document.getElementById('chatWidgetBtn');
    var panel = document.getElementById('chatWidgetPanel');
    var badge = document.getElementById('chatWidgetBadge');
    var closeBtn = document.getElementById('chatWidgetClose');
    var listaMensajes = document.getElementById('chatWidgetMensajes');
    var listaConectados = document.getElementById('chatWidgetConectados');
    var conectadosCount = document.getElementById('chatWidgetConectadosCount');
    var form = document.getElementById('chatWidgetForm');
    var input = document.getElementById('chatWidgetInput');

    if (!btn || !panel || !form || !input) return;

    var lastId = 0;
    var unread = 0;
    var panelOpen = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function formatHora(fechaStr) {
        if (!fechaStr) return '';
        var d = new Date(String(fechaStr).replace(' ', 'T'));
        if (isNaN(d.getTime())) return '';
        var h = ('0' + d.getHours()).slice(-2);
        var m = ('0' + d.getMinutes()).slice(-2);
        return h + ':' + m;
    }

    function renderMensaje(m) {
        var mio = parseInt(m.usuario_id, 10) === MI_USUARIO_ID;
        var wrap = document.createElement('div');
        wrap.className = 'chat-widget-msg' + (mio ? ' chat-widget-msg-mio' : '');
        wrap.innerHTML =
            (mio ? '' : '<div class="chat-widget-msg-autor">' + escapeHtml(m.autor_nombre) + '</div>') +
            '<div class="chat-widget-msg-burbuja">' + escapeHtml(m.mensaje) + '</div>' +
            '<div class="chat-widget-msg-hora">' + formatHora(m.fecha_creacion) + '</div>';
        return wrap;
    }

    function actualizarBadge() {
        if (unread > 0 && !panelOpen) {
            badge.textContent = unread > 99 ? '99+' : String(unread);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
    }

    function actualizarConectados(conectados) {
        conectadosCount.textContent = conectados.length;
        listaConectados.innerHTML = '';
        if (conectados.length === 0) {
            var vacio = document.createElement('div');
            vacio.className = 'chat-widget-conectados-vacio';
            vacio.textContent = 'Nadie más conectado ahora';
            listaConectados.appendChild(vacio);
            return;
        }
        conectados.forEach(function (u) {
            var chip = document.createElement('span');
            chip.className = 'chat-widget-chip';
            chip.innerHTML = '<span class="chat-widget-dot"></span>' + escapeHtml(u.nombre_mostrar);
            listaConectados.appendChild(chip);
        });
    }

    function poll() {
        fetch(ADMIN_URL + 'chat_polling.php?desde=' + lastId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;

                if (data.conectados) actualizarConectados(data.conectados);

                if (data.mensajes && data.mensajes.length) {
                    var estabaAbajo = listaMensajes.scrollTop + listaMensajes.clientHeight >= listaMensajes.scrollHeight - 20;
                    data.mensajes.forEach(function (m) {
                        listaMensajes.appendChild(renderMensaje(m));
                        lastId = Math.max(lastId, parseInt(m.id, 10));
                    });
                    if (!panelOpen) {
                        unread += data.mensajes.length;
                        actualizarBadge();
                    } else if (estabaAbajo) {
                        listaMensajes.scrollTop = listaMensajes.scrollHeight;
                    }
                }
            })
            .catch(function () { /* reintenta en el próximo ciclo */ });
    }

    function togglePanel() {
        panelOpen = !panelOpen;
        panel.style.display = panelOpen ? 'flex' : 'none';
        if (panelOpen) {
            unread = 0;
            actualizarBadge();
            listaMensajes.scrollTop = listaMensajes.scrollHeight;
            input.focus();
        }
    }

    btn.addEventListener('click', togglePanel);
    if (closeBtn) closeBtn.addEventListener('click', togglePanel);

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.requestSubmit ? form.requestSubmit() : form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var texto = input.value.trim();
        if (!texto) return;
        input.disabled = true;
        var body = new URLSearchParams();
        body.set('mensaje', texto);
        body.set('csrf_token', csrfToken());
        fetch(ADMIN_URL + 'chat_enviar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                input.disabled = false;
                if (data && data.ok && data.mensaje) {
                    input.value = '';
                    listaMensajes.appendChild(renderMensaje(data.mensaje));
                    lastId = Math.max(lastId, parseInt(data.mensaje.id, 10));
                    listaMensajes.scrollTop = listaMensajes.scrollHeight;
                } else {
                    alert((data && data.msg) ? data.msg : 'No se pudo enviar el mensaje');
                }
                input.focus();
            })
            .catch(function () {
                input.disabled = false;
                alert('No se pudo enviar el mensaje. Revisá tu conexión.');
            });
    });

    poll();
    setInterval(poll, 5000);
})();
</script>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    if ('serviceWorker' in navigator && window.ADMIN_URL) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register(window.ADMIN_URL + 'sw.js');
        });
    }
</script>
</body>
</html>
<?php
if (ob_get_level() > 0) {
    ob_end_flush();
}
?>
