<?php

/**
 * Helper para notificaciones de tareas de usuarios
 * Proporciona funciones para enviar notificaciones por email cuando se asignan/actualizan tareas
 */

// Cargar el mailer
require_once __DIR__ . '/../../includes/mailer.php';

/**
 * Envía notificación de tarea asignada al usuario destinatario
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $tarea_id ID de la tarea
 * @param int $usuario_destino_id ID del usuario que recibe la tarea
 * @param int $asignada_por_id ID del usuario que asigna (admin)
 * @return bool True si se envió, false si hubo error
 */
function enviar_notificacion_tarea_asignada(PDO $pdo, int $tarea_id, int $usuario_destino_id, int $asignada_por_id): bool {
    try {
        // Obtener datos de la tarea
        $stmt = $pdo->prepare("
            SELECT 
                id, usuario_id, titulo, descripcion, fecha_limite, fecha_asignacion
            FROM ecommerce_tareas_usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$tarea_id]);
        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$tarea) {
            return false;
        }
        
        // Obtener datos del usuario destinatario
        $stmt = $pdo->prepare("
            SELECT id, nombre, usuario, email
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$usuario_destino_id]);
        $usuario_destino = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario_destino || empty($usuario_destino['email'])) {
            return false;
        }
        
        // Obtener datos del usuario que asigna
        $stmt = $pdo->prepare("
            SELECT id, nombre, usuario
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$asignada_por_id]);
        $usuario_asigna = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $nombre_asignador = $usuario_asigna ? ($usuario_asigna['nombre'] ?? $usuario_asigna['usuario']) : 'Sistema';
        
        // Construir email
        $nombre_usuario = $usuario_destino['nombre'] ?? $usuario_destino['usuario'];
        $fecha_limite = $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : 'Sin límite';
        $descripcion = $tarea['descripcion'] ? htmlspecialchars($tarea['descripcion']) : '(Sin descripción)';
        
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; }
                .content { padding: 20px 0; }
                .task-box { background-color: #e7f3ff; border-left: 4px solid #007bff; padding: 15px; margin: 15px 0; }
                .footer { color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px; }
                .btn { display: inline-block; background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Nueva Tarea Asignada</h1>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>,</p>
                    
                    <p>" . htmlspecialchars($nombre_asignador) . " ha asignado una nueva tarea para vos:</p>
                    
                    <div class='task-box'>
                        <h3 style='margin-top: 0; color: #007bff;'>" . htmlspecialchars($tarea['titulo']) . "</h3>
                        <p><strong>Descripción:</strong></p>
                        <p>" . nl2br($descripcion) . "</p>
                        <p><strong>Fecha límite:</strong> " . htmlspecialchars($fecha_limite) . "</p>
                        <p><strong>Asignada el:</strong> " . date('d/m/Y H:i', strtotime($tarea['fecha_asignacion'])) . "</p>
                    </div>
                    
                    <p>Por favor, revisá la tarea y actualizá su estado en el sistema.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático del sistema. Por favor, no respondas directamente a este email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $asunto = 'Nueva tarea: ' . substr($tarea['titulo'], 0, 50);
        
        // Enviar email
        return enviar_email($usuario_destino['email'], $asunto, $html);
        
    } catch (Throwable $e) {
        error_log('Error en enviar_notificacion_tarea_asignada: ' . $e->getMessage());
        return false;
    }
}

/**
 * Envía notificación cuando se autoasigna una tarea rápida
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $recordatorio_id ID del recordatorio
 * @param int $usuario_id ID del usuario
 * @return bool True si se envió, false si hubo error
 */
function enviar_notificacion_recordatorio_creado(PDO $pdo, int $recordatorio_id, int $usuario_id): bool {
    try {
        // Obtener datos del recordatorio
        $stmt = $pdo->prepare("
            SELECT id, titulo, descripcion, fecha_recordatorio, fecha_creacion
            FROM ecommerce_recordatorios_usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$recordatorio_id]);
        $recordatorio = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$recordatorio) {
            return false;
        }
        
        // Obtener datos del usuario
        $stmt = $pdo->prepare("
            SELECT id, nombre, usuario, email
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || empty($usuario['email'])) {
            return false;
        }
        
        // Construir email
        $nombre_usuario = $usuario['nombre'] ?? $usuario['usuario'];
        $fecha_recordatorio = $recordatorio['fecha_recordatorio'] ? date('d/m/Y', strtotime($recordatorio['fecha_recordatorio'])) : 'Hoy';
        $descripcion = $recordatorio['descripcion'] ? htmlspecialchars($recordatorio['descripcion']) : '(Sin descripción)';
        
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; }
                .content { padding: 20px 0; }
                .reminder-box { background-color: #fffaed; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0; }
                .footer { color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Recordatorio Creado</h1>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>,</p>
                    
                    <p>Se ha creado un nuevo recordatorio para vos:</p>
                    
                    <div class='reminder-box'>
                        <h3 style='margin-top: 0; color: #ff9800;'>" . htmlspecialchars($recordatorio['titulo']) . "</h3>
                        <p><strong>Descripción:</strong></p>
                        <p>" . nl2br($descripcion) . "</p>
                        <p><strong>Fecha del recordatorio:</strong> " . htmlspecialchars($fecha_recordatorio) . "</p>
                        <p><strong>Creado el:</strong> " . date('d/m/Y H:i', strtotime($recordatorio['fecha_creacion'])) . "</p>
                    </div>
                    
                    <p>Recordá revisar este elemento en la fecha indicada.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático del sistema. Por favor, no respondas directamente a este email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $asunto = 'Recordatorio: ' . substr($recordatorio['titulo'], 0, 50);
        
        // Enviar email
        return enviar_email($usuario['email'], $asunto, $html);
        
    } catch (Throwable $e) {
        error_log('Error en enviar_notificacion_recordatorio_creado: ' . $e->getMessage());
        return false;
    }
}

/**
 * Envía resumen de tareas pendientes de un usuario
 * 
 * @param PDO $pdo Conexión a la base de datos
 * @param int $usuario_id ID del usuario
 * @return bool True si se envió, false si hubo error
 */
function enviar_resumen_tareas_pendientes(PDO $pdo, int $usuario_id): bool {
    try {
        // Obtener datos del usuario
        $stmt = $pdo->prepare("
            SELECT id, nombre, usuario, email
            FROM usuarios
            WHERE id = ?
            LIMIT 1
        ");
        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$usuario || empty($usuario['email'])) {
            return false;
        }
        
        // Obtener tareas pendientes
        $stmt = $pdo->prepare("
            SELECT id, titulo, descripcion, estado, fecha_limite, fecha_asignacion
            FROM ecommerce_tareas_usuarios
            WHERE usuario_id = ? AND estado NOT IN ('completada', 'cancelada')
            ORDER BY fecha_limite ASC, fecha_asignacion DESC
            LIMIT 10
        ");
        $stmt->execute([$usuario_id]);
        $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($tareas)) {
            return false; // No hay tareas, no enviar resumen
        }
        
        // Construir listado de tareas
        $html_tareas = '';
        foreach ($tareas as $tarea) {
            $fecha_limite = $tarea['fecha_limite'] ? date('d/m/Y', strtotime($tarea['fecha_limite'])) : 'Sin límite';
            $estado_label = '';
            switch ($tarea['estado']) {
                case 'pendiente':
                    $estado_label = '<span style="background-color: #f8d7da; color: #721c24; padding: 3px 8px; border-radius: 3px; font-size: 12px;">Pendiente</span>';
                    break;
                case 'en_progreso':
                    $estado_label = '<span style="background-color: #cfe2ff; color: #084298; padding: 3px 8px; border-radius: 3px; font-size: 12px;">En Progreso</span>';
                    break;
                default:
                    $estado_label = '<span style="background-color: #e2e3e5; color: #383d41; padding: 3px 8px; border-radius: 3px; font-size: 12px;">' . ucfirst($tarea['estado']) . '</span>';
            }
            
            $html_tareas .= "
                <tr style='border-bottom: 1px solid #ddd;'>
                    <td style='padding: 10px;'>" . htmlspecialchars($tarea['titulo']) . "</td>
                    <td style='padding: 10px;'>" . $estado_label . "</td>
                    <td style='padding: 10px;'>" . htmlspecialchars($fecha_limite) . "</td>
                </tr>
            ";
        }
        
        $nombre_usuario = $usuario['nombre'] ?? $usuario['usuario'];
        $html = "
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #e7f3ff; padding: 15px; border-left: 4px solid #007bff; }
                .content { padding: 20px 0; }
                .tasks-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
                .footer { color: #666; font-size: 12px; border-top: 1px solid #ddd; padding-top: 15px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Resumen de Tareas Pendientes</h1>
                </div>
                
                <div class='content'>
                    <p>Hola <strong>" . htmlspecialchars($nombre_usuario) . "</strong>,</p>
                    
                    <p>Tenés <strong>" . count($tareas) . "</strong> tarea(s) pendiente(s):</p>
                    
                    <table class='tasks-table'>
                        <tr style='background-color: #f8f9fa; font-weight: bold;'>
                            <td style='padding: 10px;'>Tarea</td>
                            <td style='padding: 10px;'>Estado</td>
                            <td style='padding: 10px;'>Fecha Límite</td>
                        </tr>
                        " . $html_tareas . "
                    </table>
                    
                    <p>Ingresá al sistema para ver más detalles y actualizar el estado de tus tareas.</p>
                </div>
                
                <div class='footer'>
                    <p>Este es un mensaje automático del sistema. Por favor, no respondas directamente a este email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $asunto = 'Resumen de Tareas Pendientes - ' . count($tareas) . ' tarea(s)';
        
        // Enviar email
        return enviar_email($usuario['email'], $asunto, $html);
        
    } catch (Throwable $e) {
        error_log('Error en enviar_resumen_tareas_pendientes: ' . $e->getMessage());
        return false;
    }
}

?>
