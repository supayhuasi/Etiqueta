<?php
/**
 * Interfaz de Escaneo de Asistencias
 * Pantalla para registrar asistencias escaneando códigos de barras de tarjetas
 */

require '../includes/header.php';

// Obtener configuración de horarios
$stmt = $pdo->query("
    SELECT 
        eh.empleado_id,
        e.nombre,
        COALESCE(ehd.hora_entrada, eh.hora_entrada) as hora_entrada,
        COALESCE(ehd.hora_salida, eh.hora_salida) as hora_salida,
        COALESCE(ehd.tolerancia_minutos, eh.tolerancia_minutos) as tolerancia_minutos
    FROM empleados e
    LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id AND eh.activo = 1
    LEFT JOIN empleados_horarios_dias ehd ON e.id = ehd.empleado_id 
        AND ehd.dia_semana = DAYOFWEEK(CURDATE()) - 1 
        AND ehd.activo = 1
    WHERE e.activo = 1
    ORDER BY e.nombre
");
$empleados_horarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener asistencias de hoy
$stmt = $pdo->query("
    SELECT a.*, e.nombre as empleado_nombre
    FROM asistencias a
    JOIN empleados e ON a.empleado_id = e.id
    WHERE a.fecha = CURDATE()
    ORDER BY a.fecha_creacion DESC
");
$asistencias_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escanear Asistencia</title>
    <style>
        .scanner-container {
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
        }
        
        .scanner-input {
            font-size: 24px;
            padding: 20px;
            text-align: center;
            border: 3px solid #007bff;
            border-radius: 10px;
            background-color: #f8f9fa;
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
        
        .employee-info {
            margin-top: 15px;
            padding: 15px;
            background-color: #e7f3ff;
            border-radius: 5px;
            display: none;
        }
        
        .today-list {
            margin-top: 30px;
        }
        
        .attendance-card {
            padding: 10px;
            margin-bottom: 10px;
            border-left: 4px solid;
            background-color: #f8f9fa;
        }
        
        .attendance-card.presente {
            border-color: #28a745;
        }
        
        .attendance-card.tarde {
            border-color: #ffc107;
        }
        
        .attendance-card.ausente {
            border-color: #dc3545;
        }
        
        .scanner-instructions {
            text-align: center;
            color: #6c757d;
            margin-top: 10px;
            font-size: 14px;
        }
        
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
        <h1>📱 Registro de Asistencia</h1>
        <p class="text-muted">Escanee el código de barras de su tarjeta de empleado</p>
        <p class="scanner-instructions">
            <strong>Fecha:</strong> <?= date('d/m/Y') ?> | 
            <strong>Hora:</strong> <span id="current-time"></span>
        </p>
    </div>
    
    <div class="card shadow">
        <div class="card-body">
            <input 
                type="text" 
                id="barcode-input" 
                class="form-control scanner-input" 
                placeholder="Escanee aquí..." 
                autofocus
                autocomplete="off"
            >
            
            <div id="status-message" class="status-message"></div>
            
            <div id="employee-info" class="employee-info"></div>
        </div>
    </div>
    
    <div class="today-list">
        <h3>Asistencias Registradas Hoy</h3>
        <div id="today-attendance">
            <?php if (empty($asistencias_hoy)): ?>
                <p class="text-muted">No hay asistencias registradas aún</p>
            <?php else: ?>
                <?php foreach ($asistencias_hoy as $asistencia): ?>
                    <div class="attendance-card <?= $asistencia['estado'] ?>">
                        <strong><?= htmlspecialchars($asistencia['empleado_nombre']) ?></strong>
                        <span class="float-end">
                            <?= $asistencia['hora_entrada'] ? date('H:i', strtotime($asistencia['hora_entrada'])) : '-' ?>
                            <span class="badge bg-<?= $asistencia['estado'] == 'presente' ? 'success' : ($asistencia['estado'] == 'tarde' ? 'warning' : 'danger') ?>">
                                <?= strtoupper($asistencia['estado']) ?>
                            </span>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Actualizar reloj
function updateTime() {
    const now = new Date();
    const timeStr = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    document.getElementById('current-time').textContent = timeStr;
}
updateTime();
setInterval(updateTime, 1000);

// Pantalla completa
function toggleFullscreen() {
    if (!document.fullscreenElement) {
        document.documentElement.requestFullscreen().catch(err => {
            console.log('Error al activar pantalla completa:', err);
        });
    } else {
        document.exitFullscreen();
    }
}

// Procesar código escaneado
const barcodeInput = document.getElementById('barcode-input');
const statusMessage = document.getElementById('status-message');
const employeeInfo = document.getElementById('employee-info');
let processingTimeout = null;

barcodeInput.addEventListener('input', function(e) {
    // Limpiar timeout anterior
    if (processingTimeout) {
        clearTimeout(processingTimeout);
    }
    
    // Esperar un breve momento para capturar el código completo
    processingTimeout = setTimeout(function() {
        const codigo = barcodeInput.value.trim();
        
        if (codigo.length > 0) {
            registrarAsistencia(codigo);
        }
    }, 100);
});

// Mantener el foco en el input
barcodeInput.addEventListener('blur', function() {
    setTimeout(() => barcodeInput.focus(), 100);
});

function registrarAsistencia(codigo) {
    // Mostrar que se está procesando
    showStatus('Procesando...', 'info');
    
    fetch('escanear_asistencia_procesar.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ codigo: codigo })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showStatus(data.message, 'success');
            
            // Mostrar info del empleado
            let infoHtml = `
                <h5>${data.empleado.nombre}</h5>
            `;
            
            if (data.tipo === 'entrada') {
                infoHtml += `
                    <p><strong>Hora de entrada:</strong> ${data.hora_entrada}</p>
                    <p><strong>Estado:</strong> <span class="badge bg-${data.estado == 'presente' ? 'success' : 'warning'}">${data.estado.toUpperCase()}</span></p>
                `;
            } else if (data.tipo === 'salida') {
                infoHtml += `
                    <p><strong>Hora de salida:</strong> ${data.hora_salida}</p>
                    <p><em>✓ Jornada completada</em></p>
                `;
            }
            
            employeeInfo.innerHTML = infoHtml;
            employeeInfo.style.display = 'block';
            
            // Reproducir sonido de éxito (opcional)
            playSuccessSound();
            
            // Recargar lista de asistencias
            setTimeout(() => {
                location.reload();
            }, 2000);
            
        } else {
            showStatus(data.message || 'Error al registrar asistencia', 'error');
            employeeInfo.style.display = 'none';
            playErrorSound();
        }
        
        // Limpiar input
        barcodeInput.value = '';
        
    })
    .catch(error => {
        console.error('Error:', error);
        showStatus('Error de conexión al procesar la asistencia', 'error');
        barcodeInput.value = '';
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
    
    // Ocultar después de unos segundos
    setTimeout(() => {
        statusMessage.style.display = 'none';
    }, 5000);
}

function playSuccessSound() {
    // Sonido de éxito simple
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
    // Sonido de error
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

// Auto-foco al cargar
window.addEventListener('load', () => {
    barcodeInput.focus();
});
</script>

</body>
</html>
