<?php
require 'config.php';
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

function scanCode(code) {
    showStatus('Procesando...', 'info');
    fetch('scan_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ codigo: code })
    })
    .then(r=>r.json())
    .then(handleResponse)
    .catch(err=>{
        console.error(err);
        showStatus('Error de conexión', 'error');
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
            renderProduccion(data.item);
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

function renderProduccion(item) {
    currentItem = item;
    let estadoColor = { en_corte:'danger', armado:'warning', terminado:'success', rechazado:'danger' };
    let html = `
        <h5>${item.producto_nombre}</h5>
        <p><strong>Item:</strong> ${item.numero_item} | <strong>Orden:</strong> ${item.numero_pedido}</p>
        <p><strong>Código:</strong> <code>${item.codigo_barcode}</code></p>
        <p><strong>Estado:</strong> <span class="badge bg-${estadoColor[item.estado]}">${item.estado.toUpperCase().replace('_',' ')}</span></p>
        ${item.fecha_inicio ? `<p><small>Iniciado: ${item.fecha_inicio} por ${item.usuario_inicio_nombre||'N/A'}</small></p>` : ''}
        ${item.fecha_termino ? `<p><small>Terminado: ${item.fecha_termino} por ${item.usuario_termino_nombre||'N/A'}</small></p>` : ''}
    `;
    itemInfo.innerHTML = html;
    itemInfo.style.display = 'block';
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
    fetch('scan_api.php', {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(data)
    })
    .then(r=>r.json())
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
        showStatus('Error de conexión', 'error');
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
