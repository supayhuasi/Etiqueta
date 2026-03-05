<?php
require __DIR__ . '/config.php';

// Verificar sesión y permisos
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Permitir acceso a admin y operario (compatibilidad de sesión)
$usuario_id = $_SESSION['user_id'] ?? ($_SESSION['usuario_id'] ?? ($_SESSION['user']['id'] ?? null));
$rol_usuario = strtolower(trim((string)($_SESSION['rol'] ?? '')));

if (!$usuario_id) {
    header('Location: ecommerce/admin/auth/login.php');
    exit;
}

// Solo admin y operario pueden acceder
$roles_permitidos = ['admin', 'operario'];
if (!in_array($rol_usuario, $roles_permitidos, true)) {
    die('Acceso denegado. Solo administradores y operarios pueden acceder a esta página.');
}

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo_barra']);

    if ($codigo !== "") {
        // Buscar producto
        $stmt = $pdo->prepare("SELECT id, estado_id FROM productos WHERE codigo_barra = ?");
        $stmt->execute([$codigo]);
        $producto = $stmt->fetch();

        if ($producto) {
            // Cambiar a estado ENTREGADO (id = 4)
            $pdo->prepare(
                "UPDATE productos SET estado_id = 4 WHERE id = ?"
            )->execute([$producto['id']]);

            // Guardar historial
            $pdo->prepare(
                "INSERT INTO historial_estados (producto_id, estado_id) VALUES (?, 4)"
            )->execute([$producto['id']]);

            $mensaje = "✅ Producto ENTREGADO correctamente";
        } else {
            $mensaje = "❌ Código no encontrado";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escaneo Unificado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .scanner-container {
            width: 100%;
            max-width: 800px;
            padding: 30px;
            background: #222;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        input.scanner-input {
            font-size: 28px !important;
            padding: 20px !important;
            text-align: center;
            border: 3px solid #007bff;
            border-radius: 10px;
            width: 100%;
            background-color: #f8f9fa;
            color: #000;
        }
        .status-message { margin-top: 20px; padding: 20px; border-radius: 10px; font-size: 18px; text-align: center; display: none; }
        .status-success { background-color: #d4edda; border: 2px solid #28a745; color: #155724; }
        .status-error { background-color: #f8d7da; border: 2px solid #dc3545; color: #721c24; }
        .status-warning { background-color: #fff3cd; border: 2px solid #ffc107; color: #856404; }
        .employee-info, .item-info, .detalle-info { margin-top: 15px; padding: 15px; background-color: #e7f3ff; border-radius: 8px; display: none; color: #000; }
        .action-buttons { display: flex; gap: 10px; margin-top: 15px; justify-content: center; }
        .btn-action { padding: 12px 24px; font-size: 16px; border-radius: 8px; border: none; cursor: pointer; transition: all 0.3s; }
        .btn-iniciar { background: #17a2b8; color: white; }
        .btn-terminar { background: #28a745; color: white; }
        .btn-rechazar { background: #dc3545; color: white; }
    </style>
</head>
<body>

<div class="scanner-container text-center">
    <h1 class="mb-4">📱 Escaneo Unificado</h1>
    <input type="text" id="barcode-input" class="scanner-input" placeholder="Escanee aquí..." autofocus autocomplete="off">
    <div id="status-message" class="status-message"></div>
    <div id="employee-info" class="employee-info"></div>
    <div id="item-info" class="item-info"></div>
    <div id="detalle-info" class="detalle-info"></div>
    <div id="action-buttons" class="action-buttons" style="display: none;"></div>
    <div class="mt-4">
        <a href="index.php" class="btn btn-secondary">⬅️ Volver</a>
    </div>
</div>

<script>
const input = document.getElementById('barcode-input');
const statusMessage = document.getElementById('status-message');
const employeeInfo = document.getElementById('employee-info');
const itemInfo = document.getElementById('item-info');
const detalleInfo = document.getElementById('detalle-info');
const actionButtons = document.getElementById('action-buttons');
let processingTimeout = null;
let currentItem = null;
const SCAN_API_ENDPOINTS = (() => {
    const base = window.location.pathname.replace(/[^/]*$/, '');
    return [
        `${base}scan_api.php`,
        'scan_api.php',
        '/scan_api.php',
        '/ecommerce/scan_api.php'
    ];
})();

input.addEventListener('input', function() {
    if (processingTimeout) clearTimeout(processingTimeout);
    processingTimeout = setTimeout(() => {
        const code = input.value.trim();
        if (code.length) {
            scanCode(code);
        }
    }, 100);
});

input.addEventListener('blur', () => setTimeout(()=>input.focus(),100));

async function parseApiResponse(response) {
    const text = await response.text();
    try {
        return JSON.parse(text);
    } catch (parseErr) {
        const plainText = String(text || '').replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
        if (/auth\/login\.php|ingreso al admin|usuario no autenticado/i.test(text)) {
            throw new Error('Sesión expirada. Volvé a iniciar sesión.');
        }
        if (plainText) {
            throw new Error(`Respuesta inválida del servidor (${response.status}): ${plainText.slice(0, 180)}`);
        }
        throw new Error(`Error HTTP ${response.status}`);
    }
}

async function postScanApi(payload) {
    let lastError = null;

    for (const endpoint of SCAN_API_ENDPOINTS) {
        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (response.status === 404) {
                lastError = new Error(`Endpoint no encontrado: ${endpoint}`);
                continue;
            }

            return await parseApiResponse(response);
        } catch (err) {
            lastError = err;
        }
    }

    if (lastError) {
        throw lastError;
    }

    throw new Error('No se encontró un endpoint válido para escaneo.');
}

function scanCode(code) {
    showStatus('Procesando...', 'info');
    postScanApi({ codigo: code })
    .then(handleResponse)
    .catch(err=>{
        console.error(err);
        const msg = (err && err.message && !/^\s*</.test(err.message)) ? err.message : 'Error de conexión';
        showStatus(msg, 'error');
    })
    .finally(()=>{ input.value = ''; });
}

function handleResponse(data) {
    // ocultar secciones previas
    employeeInfo.style.display = 'none';
    itemInfo.style.display = 'none';
    detalleInfo.style.display = 'none';
    actionButtons.style.display = 'none';

    if (!data.success) {
        showStatus(data.message || 'No reconocido', 'error');
        playErrorSound();
        return;
    }
    showStatus(data.message || 'OK', 'success');
    switch(data.tipo) {
        case 'asistencia':
            renderAsistencia(data);
            break;
        case 'produccion':
            renderProduccion(data.item, data);
            break;
        case 'entrega':
            // nothing else to show
            break;
        case 'detalle':
            renderDetalle(data.detalle);
            break;
    }
}

function renderAsistencia(data) {
    let html = `<h5>${data.empleado.nombre}</h5>`;
    if (data.subtipo === 'entrada') {
        html += `<p><strong>Hora de entrada:</strong> ${data.hora_entrada}</p>`;
        html += `<p><strong>Estado:</strong> <span class="badge bg-${data.estado=='presente'? 'success':'warning'}">${data.estado.toUpperCase()}</span></p>`;
    } else if (data.subtipo === 'salida') {
        html += `<p><strong>Hora de salida:</strong> ${data.hora_salida}</p>`;
        html += `<p><em>✓ Jornada completada</em></p>`;
    }
    employeeInfo.innerHTML = html;
    employeeInfo.style.display = 'block';
    playSuccessSound();
}

function renderProduccion(item, data = {}) {
    currentItem = item;
    let estadoColor = { en_corte:'danger', armado:'warning', terminado:'success', rechazado:'danger' };
    let html = `
        <h5>${item.producto_nombre}</h5>
        <p><strong>Item:</strong> ${item.numero_item} | <strong>Orden:</strong> ${item.numero_pedido}</p>
        <p><strong>Código:</strong> <code>${item.codigo_barcode}</code></p>
        <p><strong>Estado:</strong> <span class="badge bg-${estadoColor[item.estado]}">${item.estado.toUpperCase().replace('_',' ')}</span></p>
        ${data.etapa ? `<p><strong>Etapa registrada:</strong> <span class="badge bg-info">${data.etapa.toUpperCase()}</span></p>` : ''}
        ${data.escaneos ? `<p><small>Escaneos registrados: ${data.escaneos}/3</small></p>` : ''}
        ${item.fecha_inicio ? `<p><small>Iniciado: ${item.fecha_inicio} por ${item.usuario_inicio_nombre||'N/A'}</small></p>` : ''}
        ${item.fecha_termino ? `<p><small>Terminado: ${item.fecha_termino} por ${item.usuario_termino_nombre||'N/A'}</small></p>` : ''}
    `;
    itemInfo.innerHTML = html;
    itemInfo.style.display = 'block';

    if (data.auto) {
        actionButtons.innerHTML = `<p class="text-muted mb-0">Escaneo automático aplicado</p>`;
        actionButtons.style.display = 'flex';
        playSuccessSound();
        return;
    }

    // actions
    let buttons = '';
    if (item.estado === 'en_corte') {
        buttons = `<button class="btn-action btn-iniciar" onclick="procesarAccion('iniciar')">▶️ Pasar a Armado</button>`;
    } else if (item.estado === 'armado') {
        buttons = `
            <button class="btn-action btn-terminar" onclick="procesarAccion('terminar')">✅ Marcar como Terminado</button>
            <button class="btn-action btn-rechazar" onclick="procesarAccion('rechazar')">❌ Rechazar</button>
        `;
    } else {
        buttons = `<p class="text-muted">Este item ya fue procesado</p>`;
    }
    actionButtons.innerHTML = buttons;
    actionButtons.style.display = 'flex';
    playSuccessSound()
}

function renderDetalle(det) {
    let html = `<pre>${JSON.stringify(det, null, 2)}</pre>`;
    detalleInfo.innerHTML = html;
    detalleInfo.style.display = 'block';
    playSuccessSound();
}

function procesarAccion(accion) {
    if (!currentItem) return;
    let data = { accion: accion, item_id: currentItem.id };
    if (accion === 'rechazar') {
        let obs = prompt('Motivo del rechazo:');
        if (!obs) return;
        data.observaciones = obs;
    }
    showStatus('Procesando...', 'info');
    postScanApi(data)
    .then(resp=>{
        if (resp.success) {
            showStatus(resp.message, 'success');
            playSuccessSound();
            setTimeout(()=>location.reload(),2000);
        } else {
            showStatus(resp.message||'Error', 'error');
            playErrorSound();
        }
    })
    .catch(err=>{
        console.error(err);
        const msg = (err && err.message && !/^\s*</.test(err.message)) ? err.message : 'Error de conexión';
        showStatus(msg, 'error');
    });
}

function showStatus(msg, type) {
    statusMessage.textContent = msg;
    statusMessage.className = 'status-message';
    if (type === 'success') statusMessage.classList.add('status-success');
    else if (type === 'error') statusMessage.classList.add('status-error');
    else if (type === 'warning') statusMessage.classList.add('status-warning');
    statusMessage.style.display = 'block';
    if (type !== 'info') {
        setTimeout(()=>{ statusMessage.style.display='none'; }, 4000);
    }
}

function playSuccessSound() {
    const audioContext = new (window.AudioContext||window.webkitAudioContext)();
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();
    osc.connect(gain); gain.connect(audioContext.destination);
    osc.frequency.value = 800; osc.type='sine';
    gain.gain.setValueAtTime(0.3,audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01,audioContext.currentTime+0.5);
    osc.start(audioContext.currentTime); osc.stop(audioContext.currentTime+0.5);
}

function playErrorSound() {
    const audioContext = new (window.AudioContext||window.webkitAudioContext)();
    const osc = audioContext.createOscillator();
    const gain = audioContext.createGain();
    osc.connect(gain); gain.connect(audioContext.destination);
    osc.frequency.value = 200; osc.type='sawtooth';
    gain.gain.setValueAtTime(0.3,audioContext.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.01,audioContext.currentTime+0.3);
    osc.start(audioContext.currentTime); osc.stop(audioContext.currentTime+0.3);
}

window.addEventListener('load', ()=>{ input.focus(); });
</script>

</body>
</html>
