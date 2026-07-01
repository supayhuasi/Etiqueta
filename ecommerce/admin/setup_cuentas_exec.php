<?php
/**
 * Script de setup que puede ejecutarse desde web
 * Acceder a: https://tudominio.com/ecommerce/admin/setup_cuentas_exec.php
 */

ob_start();

require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Seguridad: Solo admin o con token especial
$es_admin = isset($_SESSION['user']) && ($_SESSION['rol'] === 'admin' || $_SESSION['user'] === 'admin');
$token_valido = (isset($_GET['token']) && $_GET['token'] === md5('cuentas_setup_' . date('Y-m-d')));

if (!$es_admin && !$token_valido) {
    http_response_code(403);
    die("Acceso denegado. Solo para administradores.");
}

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Setup Cuentas - Ejecución</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f5f5f5; font-family: monospace; }
        .container { margin-top: 20px; }
        .log { background-color: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 5px; overflow-y: auto; max-height: 600px; }
        .paso-ok { color: #00ff00; }
        .paso-error { color: #ff3333; }
        .paso-info { color: #00aaff; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Setup del Sistema de Cuentas - Ejecución en Vivo</h1>
    <div class="log" id="log"></div>
</div>

<script>
const log = document.getElementById('log');

function escribir(texto, tipo = 'info') {
    const clase = {
        'ok': 'paso-ok',
        'error': 'paso-error',
        'info': 'paso-info'
    }[tipo] || 'paso-info';
    
    const p = document.createElement('div');
    p.className = clase;
    p.textContent = texto;
    log.appendChild(p);
    log.scrollTop = log.scrollHeight;
}

fetch('setup_cuentas_exec.json.php?action=run', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
})
.then(r => r.json())
.then(data => {
    data.pasos.forEach(paso => {
        const emoji = paso.ok ? '✅' : '❌';
        escribir(`[${paso.numero}] ${emoji} ${paso.descripcion}`, paso.ok ? 'ok' : 'error');
        if (paso.detalle) {
            escribir(`    → ${paso.detalle}`, paso.ok ? 'ok' : 'error');
        }
    });
    
    escribir('', 'info');
    escribir('═════════════════════════════════════════════════', 'info');
    escribir(`Resultado: ${data.pasos_ok}/${data.pasos_total} completados (${data.porcentaje}%)`, 'info');
    escribir('═════════════════════════════════════════════════', 'info');
    
    if (data.pasos_ok === data.pasos_total) {
        escribir('✅ SETUP COMPLETADO EXITOSAMENTE', 'ok');
    } else {
        escribir('⚠️ SETUP COMPLETADO CON ERRORES', 'error');
    }
})
.catch(err => {
    escribir(`ERROR: ${err.message}`, 'error');
});
</script>
</body>
</html>
