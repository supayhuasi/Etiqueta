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
        height: 480px;
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
        gap: 8px;
        flex: 0 0 auto;
    }
    #chatWidgetBack {
        background: none;
        border: none;
        color: #fff;
        font-size: 1.1rem;
        cursor: pointer;
        line-height: 1;
        padding: 0 2px 0 0;
    }
    .chat-widget-header-texts { flex: 1 1 auto; min-width: 0; }
    #chatWidgetTitle { font-weight: 600; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #chatWidgetSubtitle { font-size: 0.72rem; opacity: 0.85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #chatWidgetClose { background: none; border: none; color: #fff; font-size: 1.1rem; cursor: pointer; line-height: 1; flex: 0 0 auto; }

    #chatWidgetVistaLista, #chatWidgetVistaConversacion, #chatWidgetVistaGrupo {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
    }

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
        cursor: pointer;
    }
    .chat-widget-chip:hover { border-color: #93c5fd; }
    .chat-widget-dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; display: inline-block; }
    .chat-widget-conectados-vacio { font-size: 0.74rem; color: #94a3b8; padding: 3px 0; }

    .chat-widget-list-actions { flex: 0 0 auto; padding: 8px 10px 4px; }
    #chatWidgetNuevoGrupoBtn {
        width: 100%;
        background: #eef2f7;
        color: #1d4ed8;
        border: 1px dashed #bfdbfe;
        border-radius: 8px;
        padding: 6px 8px;
        font-size: 0.78rem;
        cursor: pointer;
    }
    #chatWidgetNuevoGrupoBtn:hover { background: #e0e9f8; }

    #chatWidgetConversaciones { flex: 1 1 auto; overflow-y: auto; padding: 4px 6px 8px; }
    .chat-widget-conv-row {
        width: 100%;
        display: flex;
        align-items: center;
        gap: 8px;
        background: none;
        border: none;
        text-align: left;
        padding: 7px 6px;
        border-radius: 8px;
        cursor: pointer;
    }
    .chat-widget-conv-row:hover { background: #f1f5f9; }
    .chat-widget-conv-icono { font-size: 1.1rem; flex: 0 0 auto; }
    .chat-widget-conv-info { flex: 1 1 auto; min-width: 0; display: flex; flex-direction: column; }
    .chat-widget-conv-nombre { font-size: 0.85rem; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-widget-conv-preview { font-size: 0.72rem; color: #94a3b8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-widget-conv-badge {
        flex: 0 0 auto;
        background: #dc3545;
        color: #fff;
        border-radius: 999px;
        font-size: 0.68rem;
        padding: 1px 6px;
        min-width: 16px;
        text-align: center;
    }

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
    .chat-widget-msg-estado { font-size: 0.7rem; margin-left: 3px; letter-spacing: -2px; }
    .chat-widget-msg-estado.leido { color: #38bdf8; }
    .chat-widget-msg-estado.enviado { color: rgba(255,255,255,0.75); }
    .chat-widget-msg-mio .chat-widget-msg-hora { display: flex; justify-content: flex-end; align-items: center; }

    .chat-widget-adjunto-img {
        display: block;
        max-width: 100%;
        max-height: 160px;
        border-radius: 10px;
        margin-top: 4px;
        cursor: pointer;
    }
    .chat-widget-adjunto-archivo {
        display: flex;
        align-items: center;
        gap: 6px;
        background: rgba(0,0,0,0.05);
        border-radius: 8px;
        padding: 6px 8px;
        margin-top: 4px;
        text-decoration: none;
        color: inherit;
        font-size: 0.78rem;
    }
    .chat-widget-msg-mio .chat-widget-adjunto-archivo { background: rgba(255,255,255,0.18); }
    .chat-widget-adjunto-archivo .chat-widget-adjunto-icono { font-size: 1.1rem; }
    .chat-widget-adjunto-archivo .chat-widget-adjunto-info { min-width: 0; display: flex; flex-direction: column; text-align: left; }
    .chat-widget-adjunto-archivo .chat-widget-adjunto-nombre { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 160px; }
    .chat-widget-adjunto-archivo .chat-widget-adjunto-tamano { font-size: 0.68rem; opacity: 0.75; }

    #chatWidgetAdjuntoPreview {
        display: none;
        align-items: center;
        gap: 6px;
        padding: 6px 8px;
        margin: 0 8px;
        background: #eef2f7;
        border-radius: 8px;
        font-size: 0.78rem;
        color: #334155;
    }
    #chatWidgetAdjuntoPreview .chat-widget-adjunto-nombre { flex: 1 1 auto; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    #chatWidgetAdjuntoQuitar { background: none; border: none; color: #dc3545; font-size: 1rem; cursor: pointer; line-height: 1; }

    #chatWidgetForm {
        position: relative;
        flex: 0 0 auto;
        display: flex;
        align-items: flex-end;
        gap: 4px;
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
    #chatWidgetForm button, #chatWidgetGrupoCrear {
        background: #2563eb;
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 0 14px;
        font-size: 0.9rem;
        cursor: pointer;
    }
    #chatWidgetForm button:disabled, #chatWidgetGrupoCrear:disabled { opacity: 0.6; }
    #chatWidgetEmojiBtn, #chatWidgetAdjuntoBtn {
        background: none;
        border: none;
        color: #64748b;
        font-size: 1.15rem;
        padding: 0 4px;
        cursor: pointer;
        flex: 0 0 auto;
        height: 34px;
    }
    #chatWidgetEmojiBtn:hover, #chatWidgetAdjuntoBtn:hover { color: #2563eb; }
    #chatWidgetEmojiPanel {
        display: none;
        position: absolute;
        bottom: 46px;
        left: 8px;
        width: 236px;
        max-height: 160px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #e5eaf2;
        border-radius: 10px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.18);
        padding: 6px;
        grid-template-columns: repeat(8, 1fr);
        gap: 2px;
        z-index: 5;
    }
    .chat-widget-emoji-item {
        background: none;
        border: none;
        font-size: 1.1rem;
        padding: 3px;
        cursor: pointer;
        border-radius: 6px;
        line-height: 1;
    }
    .chat-widget-emoji-item:hover { background: #eef2f7; }

    #chatWidgetVistaGrupo { padding: 10px; overflow-y: auto; gap: 8px; }
    #chatWidgetVistaGrupo label.chat-widget-field-label { font-size: 0.78rem; font-weight: 600; color: #334155; margin-bottom: -2px; }
    #chatWidgetGrupoNombre {
        border: 1px solid #dbe2ea;
        border-radius: 8px;
        padding: 7px 10px;
        font-size: 0.85rem;
    }
    #chatWidgetGrupoMiembros {
        border: 1px solid #eef1f6;
        border-radius: 8px;
        padding: 6px 8px;
        max-height: 190px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .chat-widget-grupo-miembro { display: flex; align-items: center; gap: 6px; font-size: 0.82rem; color: #334155; cursor: pointer; }
    #chatWidgetGrupoCrear { padding: 8px; margin-top: 4px; }

    @media (max-width: 480px) {
        #chatWidgetPanel { right: 10px; left: 10px; width: auto; bottom: 80px; }
        #chatWidgetBtn { right: 14px; bottom: 14px; }
    }
</style>

<button type="button" id="chatWidgetBtn" title="Chat entre usuarios">
    <i class="bi bi-chat-dots-fill"></i>
    <span id="chatWidgetBadge"></span>
</button>

<div id="chatWidgetPanel">
    <div class="chat-widget-header">
        <button type="button" id="chatWidgetBack" aria-label="Volver" style="display:none;">&larr;</button>
        <div class="chat-widget-header-texts">
            <div id="chatWidgetTitle">Chat del equipo</div>
            <div id="chatWidgetSubtitle">0 conectado(s) ahora</div>
        </div>
        <button type="button" id="chatWidgetClose" aria-label="Cerrar">&times;</button>
    </div>

    <div id="chatWidgetVistaLista">
        <div id="chatWidgetConectados"></div>
        <div class="chat-widget-list-actions">
            <button type="button" id="chatWidgetNuevoGrupoBtn">+ Nuevo grupo</button>
        </div>
        <div id="chatWidgetConversaciones"></div>
    </div>

    <div id="chatWidgetVistaConversacion" style="display:none;">
        <div id="chatWidgetMensajes"></div>
        <div id="chatWidgetAdjuntoPreview">
            <span>📎</span>
            <span class="chat-widget-adjunto-nombre" id="chatWidgetAdjuntoNombre"></span>
            <button type="button" id="chatWidgetAdjuntoQuitar" aria-label="Quitar adjunto">&times;</button>
        </div>
        <form id="chatWidgetForm">
            <button type="button" id="chatWidgetEmojiBtn" title="Emojis">🙂</button>
            <div id="chatWidgetEmojiPanel"></div>
            <button type="button" id="chatWidgetAdjuntoBtn" title="Adjuntar archivo"><i class="bi bi-paperclip"></i></button>
            <input type="file" id="chatWidgetAdjuntoInput" style="display:none;">
            <textarea id="chatWidgetInput" rows="1" maxlength="1000" placeholder="Escribí un mensaje..." autocomplete="off"></textarea>
            <button type="submit"><i class="bi bi-send-fill"></i></button>
        </form>
    </div>

    <div id="chatWidgetVistaGrupo" style="display:none;">
        <label class="chat-widget-field-label" for="chatWidgetGrupoNombre">Nombre del grupo</label>
        <input type="text" id="chatWidgetGrupoNombre" maxlength="255" placeholder="Ej: Producción">
        <label class="chat-widget-field-label">Integrantes</label>
        <div id="chatWidgetGrupoMiembros"></div>
        <button type="button" id="chatWidgetGrupoCrear">Crear grupo</button>
    </div>
</div>

<script>
(function () {
    var ADMIN_URL = <?= json_encode($admin_url) ?>;
    var MI_USUARIO_ID = <?= (int)$chat_mi_id ?>;

    var EMOJIS = ['😀','😁','😂','🤣','😊','😉','😍','😘','😎','🤔','😅','😢','😭','😡','😱','🥳',
                  '👍','👎','👏','🙏','💪','🤝','✌️','👌','🙌','👋','🤙','✋',
                  '❤️','💙','💚','💛','🧡','💜','🔥','⭐','✨','🎉','🎊','✅','❌','⚠️','❓','❗',
                  '📌','📎','📷','📅','⏰','☕','🍕','🚀','💡','👀','😴','🤝'];

    var btn = document.getElementById('chatWidgetBtn');
    var panel = document.getElementById('chatWidgetPanel');
    var badge = document.getElementById('chatWidgetBadge');
    var backBtn = document.getElementById('chatWidgetBack');
    var closeBtn = document.getElementById('chatWidgetClose');
    var titleEl = document.getElementById('chatWidgetTitle');
    var subtitleEl = document.getElementById('chatWidgetSubtitle');

    var vistaLista = document.getElementById('chatWidgetVistaLista');
    var vistaConversacion = document.getElementById('chatWidgetVistaConversacion');
    var vistaGrupo = document.getElementById('chatWidgetVistaGrupo');

    var conectadosEl = document.getElementById('chatWidgetConectados');
    var conversacionesEl = document.getElementById('chatWidgetConversaciones');
    var nuevoGrupoBtn = document.getElementById('chatWidgetNuevoGrupoBtn');

    var mensajesEl = document.getElementById('chatWidgetMensajes');
    var form = document.getElementById('chatWidgetForm');
    var input = document.getElementById('chatWidgetInput');
    var emojiBtn = document.getElementById('chatWidgetEmojiBtn');
    var emojiPanel = document.getElementById('chatWidgetEmojiPanel');
    var adjuntoBtn = document.getElementById('chatWidgetAdjuntoBtn');
    var adjuntoInput = document.getElementById('chatWidgetAdjuntoInput');
    var adjuntoPreview = document.getElementById('chatWidgetAdjuntoPreview');
    var adjuntoNombreEl = document.getElementById('chatWidgetAdjuntoNombre');
    var adjuntoQuitarBtn = document.getElementById('chatWidgetAdjuntoQuitar');

    var grupoNombreEl = document.getElementById('chatWidgetGrupoNombre');
    var grupoMiembrosEl = document.getElementById('chatWidgetGrupoMiembros');
    var grupoCrearBtn = document.getElementById('chatWidgetGrupoCrear');

    if (!btn || !panel || !form || !input) return;

    var panelOpen = false;
    var vista = 'lista'; // lista | conversacion | grupo
    var activeConvId = 0;
    var activeConvNombre = '';
    var lastIdMap = {};
    var mensajesCache = {};
    var conversacionesCache = [];
    var usuariosCache = [];
    var conectadosCache = [];
    var noLeidosPrevios = {};
    var primerPollHecho = false;
    var archivoSeleccionado = null;
    var tituloOriginal = document.title;
    var audioCtx = null;

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

    function formatTamano(bytes) {
        bytes = parseInt(bytes, 10) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function reproducirSonido() {
        try {
            if (!audioCtx) {
                var AC = window.AudioContext || window.webkitAudioContext;
                if (!AC) return;
                audioCtx = new AC();
            }
            if (audioCtx.state === 'suspended') { audioCtx.resume(); }
            var osc = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.0001, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.18, audioCtx.currentTime + 0.01);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioCtx.currentTime + 0.32);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.33);
        } catch (e) { /* audio no disponible en este navegador */ }
    }

    function totalNoLeidos() {
        return conversacionesCache.reduce(function (acc, c) {
            return acc + (parseInt(c.no_leidos, 10) || 0);
        }, 0);
    }

    function actualizarBadgeGlobal() {
        var total = totalNoLeidos();
        if (total > 0 && !panelOpen) {
            badge.textContent = total > 99 ? '99+' : String(total);
            badge.style.display = '';
        } else {
            badge.style.display = 'none';
        }
        document.title = total > 0 ? '(' + (total > 99 ? '99+' : total) + ') ' + tituloOriginal : tituloOriginal;
    }

    function renderConectados() {
        conectadosEl.innerHTML = '';
        if (conectadosCache.length === 0) {
            var vacio = document.createElement('div');
            vacio.className = 'chat-widget-conectados-vacio';
            vacio.textContent = 'Nadie más conectado ahora';
            conectadosEl.appendChild(vacio);
            return;
        }
        conectadosCache.forEach(function (u) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'chat-widget-chip';
            chip.innerHTML = '<span class="chat-widget-dot"></span>' + escapeHtml(u.nombre_mostrar);
            chip.addEventListener('click', function () { iniciarDirecto(u.id, u.nombre_mostrar); });
            conectadosEl.appendChild(chip);
        });
    }

    function iconoConversacion(tipo) {
        if (tipo === 'general') return '🌐';
        if (tipo === 'grupo') return '👥';
        return '👤';
    }

    function renderConversaciones() {
        conversacionesEl.innerHTML = '';
        conversacionesCache.forEach(function (c) {
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'chat-widget-conv-row';
            var noLeidos = parseInt(c.no_leidos, 10) || 0;
            var preview;
            if (c.ultimo_mensaje) {
                preview = escapeHtml(String(c.ultimo_mensaje).slice(0, 40));
            } else if (c.ultimo_mensaje_adjunto) {
                preview = '📎 ' + escapeHtml(String(c.ultimo_mensaje_adjunto).slice(0, 36));
            } else {
                preview = 'Sin mensajes todavía';
            }
            row.innerHTML =
                '<span class="chat-widget-conv-icono">' + iconoConversacion(c.tipo) + '</span>' +
                '<span class="chat-widget-conv-info">' +
                    '<span class="chat-widget-conv-nombre">' + escapeHtml(c.nombre) + '</span>' +
                    '<span class="chat-widget-conv-preview">' + preview + '</span>' +
                '</span>' +
                (noLeidos > 0 ? '<span class="chat-widget-conv-badge">' + (noLeidos > 99 ? '99+' : noLeidos) + '</span>' : '');
            row.addEventListener('click', function () { abrirConversacion(c.id, c.nombre); });
            conversacionesEl.appendChild(row);
        });
    }

    function renderAdjunto(m) {
        if (!m.adjunto_url) return '';
        var esImagen = (m.adjunto_tipo || '').indexOf('image/') === 0;
        if (esImagen) {
            return '<a href="' + escapeHtml(m.adjunto_url) + '" target="_blank" rel="noopener">' +
                   '<img class="chat-widget-adjunto-img" src="' + escapeHtml(m.adjunto_url) + '" alt="' + escapeHtml(m.adjunto_nombre || 'imagen') + '"></a>';
        }
        return '<a class="chat-widget-adjunto-archivo" href="' + escapeHtml(m.adjunto_url) + '" target="_blank" rel="noopener" download>' +
               '<span class="chat-widget-adjunto-icono">📄</span>' +
               '<span class="chat-widget-adjunto-info">' +
                   '<span class="chat-widget-adjunto-nombre">' + escapeHtml(m.adjunto_nombre || 'Archivo') + '</span>' +
                   '<span class="chat-widget-adjunto-tamano">' + formatTamano(m.adjunto_tamano) + '</span>' +
               '</span></a>';
    }

    function renderMensaje(m) {
        var mio = parseInt(m.usuario_id, 10) === MI_USUARIO_ID;
        var wrap = document.createElement('div');
        wrap.className = 'chat-widget-msg' + (mio ? ' chat-widget-msg-mio' : '');

        var burbuja = '';
        if (m.mensaje) {
            burbuja += '<div class="chat-widget-msg-burbuja">' + escapeHtml(m.mensaje) + '</div>';
        }
        var adjuntoHtml = renderAdjunto(m);

        var estadoHtml = '';
        if (mio) {
            estadoHtml = m.leido
                ? '<span class="chat-widget-msg-estado leido" title="Leído">✓✓</span>'
                : '<span class="chat-widget-msg-estado enviado" title="Enviado">✓</span>';
        }

        wrap.innerHTML =
            (mio ? '' : '<div class="chat-widget-msg-autor">' + escapeHtml(m.autor_nombre) + '</div>') +
            burbuja + adjuntoHtml +
            '<div class="chat-widget-msg-hora">' + formatHora(m.fecha_creacion) + estadoHtml + '</div>';
        return wrap;
    }

    function renderGrupoMiembros() {
        grupoMiembrosEl.innerHTML = '';
        if (usuariosCache.length === 0) {
            grupoMiembrosEl.innerHTML = '<div class="chat-widget-conectados-vacio">No hay otros usuarios disponibles</div>';
            return;
        }
        usuariosCache.forEach(function (u) {
            var wrap = document.createElement('label');
            wrap.className = 'chat-widget-grupo-miembro';
            wrap.innerHTML =
                '<input type="checkbox" value="' + u.id + '"> ' +
                '<span>' + escapeHtml(u.nombre_mostrar) + '</span>';
            grupoMiembrosEl.appendChild(wrap);
        });
    }

    function renderEmojiPanel() {
        if (!emojiPanel) return;
        emojiPanel.innerHTML = '';
        EMOJIS.forEach(function (e) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'chat-widget-emoji-item';
            b.textContent = e;
            b.addEventListener('click', function () { insertarEmoji(e); });
            emojiPanel.appendChild(b);
        });
    }

    function insertarEmoji(emoji) {
        var inicio = input.selectionStart || input.value.length;
        var fin = input.selectionEnd || input.value.length;
        input.value = input.value.slice(0, inicio) + emoji + input.value.slice(fin);
        var nuevaPos = inicio + emoji.length;
        input.focus();
        input.setSelectionRange(nuevaPos, nuevaPos);
    }

    function cerrarEmojiPanel() {
        if (emojiPanel) emojiPanel.style.display = 'none';
    }

    if (emojiBtn && emojiPanel) {
        renderEmojiPanel();
        emojiBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            emojiPanel.style.display = emojiPanel.style.display === 'grid' ? 'none' : 'grid';
        });
        document.addEventListener('click', function (e) {
            if (emojiPanel.style.display === 'grid' && !emojiPanel.contains(e.target) && e.target !== emojiBtn) {
                cerrarEmojiPanel();
            }
        });
    }

    function limpiarAdjuntoSeleccionado() {
        archivoSeleccionado = null;
        if (adjuntoInput) adjuntoInput.value = '';
        if (adjuntoPreview) adjuntoPreview.style.display = 'none';
    }

    if (adjuntoBtn && adjuntoInput) {
        adjuntoBtn.addEventListener('click', function () { adjuntoInput.click(); });
        adjuntoInput.addEventListener('change', function () {
            var archivo = adjuntoInput.files && adjuntoInput.files[0];
            if (!archivo) return;
            if (archivo.size > 10 * 1024 * 1024) {
                alert('El archivo supera el máximo de 10MB');
                adjuntoInput.value = '';
                return;
            }
            archivoSeleccionado = archivo;
            if (adjuntoNombreEl) adjuntoNombreEl.textContent = archivo.name;
            if (adjuntoPreview) adjuntoPreview.style.display = 'flex';
        });
    }
    if (adjuntoQuitarBtn) {
        adjuntoQuitarBtn.addEventListener('click', limpiarAdjuntoSeleccionado);
    }

    function mostrarVista(nueva) {
        vista = nueva;
        vistaLista.style.display = nueva === 'lista' ? 'flex' : 'none';
        vistaConversacion.style.display = nueva === 'conversacion' ? 'flex' : 'none';
        vistaGrupo.style.display = nueva === 'grupo' ? 'flex' : 'none';
        backBtn.style.display = nueva === 'lista' ? 'none' : '';
        cerrarEmojiPanel();

        if (nueva === 'lista') {
            activeConvId = 0;
            titleEl.textContent = 'Chat del equipo';
            subtitleEl.textContent = conectadosCache.length + ' conectado(s) ahora';
            renderConectados();
            renderConversaciones();
        } else if (nueva === 'conversacion') {
            titleEl.textContent = activeConvNombre;
            subtitleEl.textContent = '';
        } else if (nueva === 'grupo') {
            titleEl.textContent = 'Nuevo grupo';
            subtitleEl.textContent = '';
        }
    }

    function abrirConversacion(id, nombre) {
        activeConvId = id;
        activeConvNombre = nombre;
        limpiarAdjuntoSeleccionado();
        mensajesEl.innerHTML = '';
        if (mensajesCache[id]) {
            mensajesCache[id].forEach(function (m) { mensajesEl.appendChild(renderMensaje(m)); });
            mensajesEl.scrollTop = mensajesEl.scrollHeight;
        }
        conversacionesCache.forEach(function (c) { if (c.id === id) c.no_leidos = 0; });
        actualizarBadgeGlobal();
        mostrarVista('conversacion');
        poll();
        input.focus();
    }

    function iniciarDirecto(destinoId, nombre) {
        var body = new URLSearchParams();
        body.set('usuario_id', destinoId);
        body.set('csrf_token', csrfToken());
        fetch(ADMIN_URL + 'chat_iniciar_directo.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    abrirConversacion(data.conversacion_id, nombre);
                } else {
                    alert((data && data.msg) || 'No se pudo iniciar la conversación');
                }
            })
            .catch(function () { alert('No se pudo iniciar la conversación'); });
    }

    if (nuevoGrupoBtn) {
        nuevoGrupoBtn.addEventListener('click', function () {
            grupoNombreEl.value = '';
            renderGrupoMiembros();
            mostrarVista('grupo');
        });
    }

    if (grupoCrearBtn) {
        grupoCrearBtn.addEventListener('click', function () {
            var nombre = grupoNombreEl.value.trim();
            if (!nombre) { alert('Ponele un nombre al grupo'); return; }
            var miembros = Array.prototype.slice.call(grupoMiembrosEl.querySelectorAll('input:checked')).map(function (i) { return i.value; });
            if (miembros.length === 0) { alert('Elegí al menos un integrante'); return; }

            var body = new URLSearchParams();
            body.set('nombre', nombre);
            miembros.forEach(function (m) { body.append('miembros[]', m); });
            body.set('csrf_token', csrfToken());

            grupoCrearBtn.disabled = true;
            fetch(ADMIN_URL + 'chat_crear_grupo.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    grupoCrearBtn.disabled = false;
                    if (data && data.ok) {
                        abrirConversacion(data.conversacion_id, nombre);
                    } else {
                        alert((data && data.msg) || 'No se pudo crear el grupo');
                    }
                })
                .catch(function () {
                    grupoCrearBtn.disabled = false;
                    alert('No se pudo crear el grupo');
                });
        });
    }

    if (backBtn) {
        backBtn.addEventListener('click', function () { mostrarVista('lista'); });
    }

    function poll() {
        var url = ADMIN_URL + 'chat_polling.php';
        var desdeEnviado = lastIdMap[activeConvId] || 0;
        if (activeConvId > 0) {
            url += '?conversacion_id=' + activeConvId + '&desde=' + desdeEnviado;
        }

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data || !data.ok) return;

                if (data.conectados) {
                    conectadosCache = data.conectados;
                    if (vista === 'lista') renderConectados();
                }
                if (data.usuarios) usuariosCache = data.usuarios;
                if (data.conversaciones) {
                    var huboNuevoEnSegundoPlano = false;
                    data.conversaciones.forEach(function (c) {
                        var anterior = noLeidosPrevios.hasOwnProperty(c.id) ? noLeidosPrevios[c.id] : (parseInt(c.no_leidos, 10) || 0);
                        var actual = parseInt(c.no_leidos, 10) || 0;
                        if (primerPollHecho && actual > anterior && c.id !== activeConvId) {
                            huboNuevoEnSegundoPlano = true;
                        }
                        noLeidosPrevios[c.id] = actual;
                    });
                    if (huboNuevoEnSegundoPlano) reproducirSonido();

                    conversacionesCache = data.conversaciones;
                    if (vista === 'lista') {
                        renderConversaciones();
                        subtitleEl.textContent = conectadosCache.length + ' conectado(s) ahora';
                    }
                    actualizarBadgeGlobal();
                }

                if (data.mensajes && data.mensajes.length && activeConvId > 0 && data.conversacion_id === activeConvId) {
                    var eraVacio = mensajesEl.children.length === 0;
                    var estabaAbajo = mensajesEl.scrollTop + mensajesEl.clientHeight >= mensajesEl.scrollHeight - 20;
                    var hayMensajeDeOtros = false;
                    if (!mensajesCache[activeConvId]) mensajesCache[activeConvId] = [];
                    data.mensajes.forEach(function (m) {
                        mensajesEl.appendChild(renderMensaje(m));
                        mensajesCache[activeConvId].push(m);
                        lastIdMap[activeConvId] = Math.max(lastIdMap[activeConvId] || 0, parseInt(m.id, 10));
                        if (parseInt(m.usuario_id, 10) !== MI_USUARIO_ID) hayMensajeDeOtros = true;
                    });
                    // Solo notificar si ya había historial cargado (desde > 0): evita sonar
                    // al abrir por primera vez una conversación con mensajes viejos.
                    if (desdeEnviado > 0 && hayMensajeDeOtros) reproducirSonido();
                    if (vista === 'conversacion' && (eraVacio || estabaAbajo)) {
                        mensajesEl.scrollTop = mensajesEl.scrollHeight;
                    }
                }

                primerPollHecho = true;
            })
            .catch(function () { /* reintenta en el próximo ciclo */ });
    }

    function togglePanel() {
        panelOpen = !panelOpen;
        panel.style.display = panelOpen ? 'flex' : 'none';
        if (panelOpen) {
            mostrarVista(vista === 'grupo' ? 'lista' : vista);
            actualizarBadgeGlobal();
            poll();
            if (vista === 'conversacion') input.focus();
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
        if (activeConvId <= 0) return;
        var texto = input.value.trim();
        if (!texto && !archivoSeleccionado) return;

        input.disabled = true;
        if (adjuntoBtn) adjuntoBtn.disabled = true;
        var conversacionEnvio = activeConvId;

        var body = new FormData();
        body.set('conversacion_id', conversacionEnvio);
        body.set('mensaje', texto);
        body.set('csrf_token', csrfToken());
        if (archivoSeleccionado) {
            body.set('adjunto', archivoSeleccionado);
        }

        fetch(ADMIN_URL + 'chat_enviar.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: body
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                input.disabled = false;
                if (adjuntoBtn) adjuntoBtn.disabled = false;
                if (data && data.ok && data.mensaje) {
                    input.value = '';
                    limpiarAdjuntoSeleccionado();
                    if (activeConvId === conversacionEnvio) {
                        mensajesEl.appendChild(renderMensaje(data.mensaje));
                        mensajesEl.scrollTop = mensajesEl.scrollHeight;
                    }
                    if (!mensajesCache[conversacionEnvio]) mensajesCache[conversacionEnvio] = [];
                    mensajesCache[conversacionEnvio].push(data.mensaje);
                    lastIdMap[conversacionEnvio] = Math.max(lastIdMap[conversacionEnvio] || 0, parseInt(data.mensaje.id, 10));
                } else {
                    alert((data && data.msg) ? data.msg : 'No se pudo enviar el mensaje');
                }
                input.focus();
            })
            .catch(function () {
                input.disabled = false;
                if (adjuntoBtn) adjuntoBtn.disabled = false;
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
