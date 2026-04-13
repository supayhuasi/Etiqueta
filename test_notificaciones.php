<?php
/**
 * Script para probar el sistema de notificaciones de tareas
 * Ejecutar: php test_notificaciones.php desde el directorio raíz
 */

require __DIR__ . '/ecommerce/admin/auth/check.php';

if (($role ?? '') !== 'admin') {
    die('[ERROR] Solo administradores pueden ejecutar este script.');
}

require_once __DIR__ . '/ecommerce/admin/includes/tareas_notificaciones_helper.php';

echo "=== PRUEBA DE SISTEMA DE NOTIFICACIONES DE TAREAS ===\n\n";

// Verificar que existe la configuración de email
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_email_config WHERE activo = 1 LIMIT 1");
    $email_config = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($email_config) {
        echo "[OK] Configuración de email encontrada:\n";
        echo "  - From Email: " . htmlspecialchars($email_config['from_email']) . "\n";
        echo "  - SMTP Host: " . htmlspecialchars($email_config['smtp_host']) . "\n";
        echo "  - SMTP Port: " . htmlspecialchars($email_config['smtp_port']) . "\n";
    } else {
        echo "[ADVERTENCIA] No hay configuración de email activa. Ejecutá ecommerce/admin/email_config.php\n";
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}

// Obtener tareas recientes
echo "\n=== TAREAS RECIENTES ===\n";
try {
    $stmt = $pdo->query("
        SELECT 
            t.id, t.usuario_id, t.titulo, t.estado, 
            COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS usuario_nombre,
            t.fecha_asignacion
        FROM ecommerce_tareas_usuarios t
        JOIN usuarios u ON u.id = t.usuario_id
        ORDER BY t.fecha_asignacion DESC
        LIMIT 5
    ");
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($tareas)) {
        echo "No hay tareas en el sistema.\n";
    } else {
        foreach ($tareas as $idx => $tarea) {
            echo "\n[Tarea #" . ($idx+1) . "]\n";
            echo "  - ID: " . $tarea['id'] . "\n";
            echo "  - Usuario: " . htmlspecialchars($tarea['usuario_nombre']) . "\n";
            echo "  - Título: " . htmlspecialchars($tarea['titulo']) . "\n";
            echo "  - Estado: " . $tarea['estado'] . "\n";
            echo "  - Asignada: " . date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])) . "\n";
            
            // Obtener usuario para verificar email
            $stmt_user = $pdo->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
            $stmt_user->execute([$tarea['usuario_id']]);
            $user_email = $stmt_user->fetchColumn();
            
            if ($user_email) {
                echo "  - Email: " . htmlspecialchars($user_email) . "\n";
                
                // Botón para enviar notificación
                echo "  - Enviar notificación: php test_notificaciones.php send_task " . $tarea['id'] . "\n";
            } else {
                echo "  - Email: [SIN EMAIL]\n";
            }
        }
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

// Modo para enviar notificación específica
if (isset($argv[1]) && $argv[1] === 'send_task' && isset($argv[2])) {
    $tarea_id = (int)$argv[2];
    
    echo "\n=== ENVIANDO NOTIFICACIÓN DE TAREA #" . $tarea_id . " ===\n";
    
    try {
        $stmt = $pdo->prepare("
            SELECT usuario_id, asignada_por FROM ecommerce_tareas_usuarios WHERE id = ? LIMIT 1
        ");
        $stmt->execute([$tarea_id]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tarea) {
            echo "[ERROR] Tarea no encontrada.\n";
        } else {
            $resultado = enviar_notificacion_tarea_asignada($pdo, $tarea_id, $tarea['usuario_id'], $tarea['asignada_por'] ?? 0);
            
            if ($resultado) {
                echo "[OK] Notificación enviada correctamente.\n";
            } else {
                echo "[ERROR] No se pudo enviar la notificación.\n";
            }
        }
    } catch (Exception $e) {
        echo "[ERROR] " . $e->getMessage() . "\n";
    }
}

// Obtener recordatorios recientes
echo "\n\n=== RECORDATORIOS RECIENTES ===\n";
try {
    $stmt = $pdo->query("
        SELECT 
            r.id, r.usuario_id, r.titulo, r.estado, 
            COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS usuario_nombre,
            r.fecha_creacion
        FROM ecommerce_recordatorios_usuarios r
        JOIN usuarios u ON u.id = r.usuario_id
        ORDER BY r.fecha_creacion DESC
        LIMIT 5
    ");
    $recordatorios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($recordatorios)) {
        echo "No hay recordatorios en el sistema.\n";
    } else {
        foreach ($recordatorios as $idx => $recordatorio) {
            echo "\n[Recordatorio #" . ($idx+1) . "]\n";
            echo "  - ID: " . $recordatorio['id'] . "\n";
            echo "  - Usuario: " . htmlspecialchars($recordatorio['usuario_nombre']) . "\n";
            echo "  - Título: " . htmlspecialchars($recordatorio['titulo']) . "\n";
            echo "  - Estado: " . $recordatorio['estado'] . "\n";
            echo "  - Creado: " . date('d/m/Y H:i', strtotime($recordatorio['fecha_creacion'])) . "\n";
        }
    }
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}

echo "\n=== FIN DE LA PRUEBA ===\n";
?>
