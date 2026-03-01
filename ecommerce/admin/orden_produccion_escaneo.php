<?php
/**
 * Interfaz de Escaneo para Control de Producción
 * Permite escanear códigos individuales de productos y actualizar su estado
 */

require 'includes/header.php';

// Obtener items en corte y armado
$stmt = $pdo->query("
    SELECT 
        pib.*,
        pi.producto_id,
        pr.nombre as producto_nombre,
        op.pedido_id,
        p.numero_pedido,
        u_inicio.nombre as usuario_inicio_nombre,
        u_termino.nombre as usuario_termino_nombre
    FROM ecommerce_produccion_items_barcode pib
    JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
    JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    JOIN ecommerce_ordenes_produccion op ON pib.orden_produccion_id = op.id
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    LEFT JOIN usuarios u_inicio ON pib.usuario_inicio = u_inicio.id
    LEFT JOIN usuarios u_termino ON pib.usuario_termino = u_termino.id
    WHERE pib.estado IN ('en_corte', 'armado')
    ORDER BY pib.fecha_creacion DESC
    LIMIT 50
");
$items_activos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas del día
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'terminado' THEN 1 ELSE 0 END) as terminados,
        SUM(CASE WHEN estado = 'armado' THEN 1 ELSE 0 END) as armado,
        SUM(CASE WHEN estado = 'en_corte' THEN 1 ELSE 0 END) as en_corte
    FROM ecommerce_produccion_items_barcode
    WHERE DATE(fecha_creacion) = CURDATE()
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Control de Producción - Escaneo</title>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .scanner-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .scanner-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
            margin-bottom: 20px;
        }
        
        .scanner-input {
            font-size: 24px;
            padding: 20px;
            text-align: center;
            border: 3px solid #667eea;
            border-radius: 10px;
            background-color: #f8f9fa;
            width: 100%;
        }
        
        .scanner-input:focus {
            outline: none;
            border-color: #28a745;
            background-color: #e8f5e9;
        }
        
        .status-message {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            font-size: 18px;
            text-align: center;
            display: none;
        }
        
        .status-success {
            background-color: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
        }
        
        .status-error {
            background-color: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
        }
        
        .status-warning {
            background-color: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
        }
        
        .item-info {
            margin-top: 15px;
            padding: 15px;
            background-color: #e7f3ff;
            border-radius: 8px;
            display: none;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border-left: 4px solid;
        }
        
        .stat-card.pendiente { border-color: #6c757d; }
        .stat-card.en-corte { border-color: #dc3545; }
        .stat-card.armado { border-color: #ffc107; }
        .stat-card.terminado { border-color: #28a745; }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: center;
        }
        
        .btn-action {
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-iniciar {
            background: #17a2b8;
            color: white;
        }
        
        .btn-terminar {
            background: #28a745;
            color: white;
        }
        
        .btn-rechazar {
            background: #dc3545;
            color: white;
        }
        
        .items-list {
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .item-row {
            padding: 12px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .item-row.en_corte { border-color: #dc3545; }
        .item-row.armado { border-color: #ffc107; }
        
        .fullscreen-btn {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1000;
        }
    </style>
</head>
<body>

<div class="container-fluid scanner-container">
    <button class="btn btn-secondary fullscreen-btn" onclick="toggleFullscreen()">
        <i class="bi bi-arrows-fullscreen"></i> Pantalla Completa
    </button>
    
    <div class="text-center mb-4">
        <h1 style="color: white;">🏭 Control de Producción</h1>
        <p style="color: rgba(255,255,255,0.9);">Escanee el código de barras del producto</p>
    </div>
    
    <!-- Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card en-corte">
            <div class="stat-label">En Corte</div>
            <div class="stat-number"><?= $stats['en_corte'] ?? 0 ?></div>
        </div>
        <div class="stat-card armado">
            <div class="stat-label">En Armado</div>
            <div class="stat-number"><?= $stats['armado'] ?? 0 ?></div>
        </div>
        <div class="stat-card terminado">
            <div class="stat-label">Terminados Hoy</div>
            <div class="stat-number"><?= $stats['terminados'] ?? 0 ?></div>
        </div>
    </div>
    
    <!-- Scanner -->
    <div class="scanner-card">
        <input 
            type="text" 
            id="barcode-input" 
            class="scanner-input" 
            placeholder="Escanee código aquí..." 
            autofocus
            autocomplete="off"
        >
        
        <div id="status-message" class="status-message"></div>
        
        <div id="item-info" class="item-info"></div>
        
        <div id="action-buttons" class="action-buttons" style="display: none;"></div>
    </div>
    
    <!-- Lista de items activos -->
    <div class="scanner-card">
        <h3>Items Activos en Producción</h3>
        <div class="items-list">
            <?php if (empty($items_activos)): ?>
                <p class="text-muted text-center">No hay items en producción actualmente</p>
            <?php else: ?>
                <?php foreach ($items_activos as $item): ?>
                    <div class="item-row <?= $item['estado'] ?>">
                        <div>
                            <strong><?= htmlspecialchars($item['producto_nombre']) ?></strong><br>
                            <small>
                                Item <?= $item['numero_item'] ?> | 
                                Orden: <?= htmlspecialchars($item['numero_pedido']) ?> |
                                <code><?= $item['codigo_barcode'] ?></code>
                            </small>
                        </div>
                        <div>
                            <span class="badge bg-<?= $item['estado'] == 'armado' ? 'warning' : 'danger' ?>">
                                <?= strtoupper(str_replace('_', ' ', $item['estado'])) ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Datos del usuario actual
const usuarioId = <?= $_SESSION['user_id'] ?? 'null' ?>;

// Actualizar reloj
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('es-AR');
    document.title = timeStr + ' - Control de Producción';
}
updateTime();
setInterval(updateTime, 1000);

// Pantalla completa
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen();
    } else {
        document.exitFullscreen();
    }
}

// Procesar código escaneado
const barcodeInput = document.getElementById('barcode-input');
const statusMessage = document.getElementById('status-message');
const itemInfo = document.getElementById('item-info');
const actionButtons = document.getElementById('action-buttons');
let processingTimeout = null;
let currentItemData = null;

barcodeInput.addEventListener('input', function(e) {
    if (processingTimeout) {
        clearTimeout(processingTimeout);
    }
    
    processingTimeout = setTimeout(function() {
        const codigo = barcodeInput.value.trim();
        
        if (codigo.length > 0) {
            buscarItem(codigo);
        }
    }, 100);
});

barcodeInput.addEventListener('blur', function() {
    setTimeout(() => barcodeInput.focus(), 100);
});

function buscarItem(codigo) {
    showStatus('Buscando item...', 'info');
    actionButtons.style.display = 'none';
    itemInfo.style.display = 'none';
    
    fetch('orden_produccion_escaneo_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ accion: 'buscar', codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentItemData = data.item;
            mostrarInfoItem(data.item);
            mostrarAcciones(data.item);
        } else {
            showStatus(data.message || 'Item no encontrado', 'error');
            playErrorSound();
        }
        
        barcodeInput.value = '';
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('Error de conexión', 'error');
        barcodeInput.value = '';
        playErrorSound();
    });
}

function mostrarInfoItem(item) {
    let estadoColor = {
        'en_corte': 'danger',
        'armado': 'warning',
        'terminado': 'success',
        'rechazado': 'danger'
    };
    
    itemInfo.innerHTML = `
        <h5>${item.producto_nombre}</h5>
        <p><strong>Item:</strong> ${item.numero_item} | <strong>Orden:</strong> ${item.numero_pedido}</p>
        <p><strong>Código:</strong> <code>${item.codigo_barcode}</code></p>
        <p><strong>Estado:</strong> <span class="badge bg-${estadoColor[item.estado]}">${item.estado.toUpperCase().replace('_', ' ')}</span></p>
        ${item.fecha_inicio ? `<p><small>Iniciado: ${item.fecha_inicio} por ${item.usuario_inicio_nombre || 'N/A'}</small></p>` : ''}
        ${item.fecha_termino ? `<p><small>Terminado: ${item.fecha_termino} por ${item.usuario_termino_nombre || 'N/A'}</small></p>` : ''}
    `;
    itemInfo.style.display = 'block';
}

function mostrarAcciones(item) {
    actionButtons.innerHTML = '';
    
    if (item.estado === 'en_corte') {
        actionButtons.innerHTML = `
            <button class="btn-action btn-iniciar" onclick="cambiarEstado('iniciar')">
                ▶️ Pasar a Armado
            </button>
        `;
    } else if (item.estado === 'armado') {
        actionButtons.innerHTML = `
            <button class="btn-action btn-terminar" onclick="cambiarEstado('terminar')">
                ✅ Marcar como Terminado
            </button>
            <button class="btn-action btn-rechazar" onclick="cambiarEstado('rechazar')">
                ❌ Rechazar
            </button>
        `;
    } else {
        actionButtons.innerHTML = `<p class="text-muted">Este item ya fue procesado</p>`;
    }
    
    actionButtons.style.display = 'flex';
}

function cambiarEstado(accion) {
    if (!currentItemData) return;
    
    const observaciones = accion === 'rechazar' ? prompt('Motivo del rechazo:') : null;
    
    if (accion === 'rechazar' && !observaciones) {
        return; // Cancelado
    }
    
    showStatus('Procesando...', 'info');
    
    fetch('orden_produccion_escaneo_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            accion: accion,
            item_id: currentItemData.id,
            observaciones: observaciones
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus(data.message, 'success');
            playSuccessSound();
            
            setTimeout(() => {
                location.reload();
            }, 2000);
        } else {
            showStatus(data.message || 'Error al procesar', 'error');
            playErrorSound();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('Error de conexión', 'error');
        playErrorSound();
    });
}

function showStatus(message, type) {
    statusMessage.textContent = message;
    statusMessage.className = 'status-message';
    
    if (type === 'success') {
        statusMessage.classList.add('status-success');
    } else if (type === 'error') {
        statusMessage.classList.add('status-error');
    } else if (type === 'warning') {
        statusMessage.classList.add('status-warning');
    }
    
    statusMessage.style.display = 'block';
    
    setTimeout(() => {
        if (type !== 'info') {
            statusMessage.style.display = 'none';
        }
    }, 5000);
}

function playSuccessSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 800;
    oscillator.type = 'sine';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
}

function playErrorSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();
    
    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);
    
    oscillator.frequency.value = 200;
    oscillator.type = 'sawtooth';
    
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.3);
}

window.addEventListener('load', () => {
    barcodeInput.focus();
});
</script>

</body>
</html>
