<?php
/**
 * Procesador de Escaneo de Asistencias
 * API para procesar códigos de barras escaneados y registrar asistencias
 */

require_once __DIR__ . '/../../../config.php';

// Permisos: ventas, operario y admin pueden usar esta API
session_start();
if (!in_array($_SESSION['rol'] ?? '', ['ventas','operario','admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

header('Content-Type: application/json');

try {
    // Obtener datos del POST
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!isset($data['codigo'])) {
        throw new Exception('Código de barras no proporcionado');
    }
    
    $codigo = $data['codigo'];
    
    // Extraer ID del empleado del código
    // Formato esperado: EMP000001, EMP000002, etc.
    if (!preg_match('/^EMP(\d{6})$/', $codigo, $matches)) {
        throw new Exception('Formato de código inválido. Use el formato: EMP000001');
    }
    
    $empleado_id = (int)$matches[1];
    
    // Verificar que el empleado existe y está activo
    $stmt = $pdo->prepare("
        SELECT id, nombre, puesto, departamento 
        FROM empleados 
        WHERE id = ? AND activo = 1
    ");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$empleado) {
        throw new Exception('Empleado no encontrado o inactivo');
    }
    
    // Verificar si ya registró asistencia hoy
    $stmt = $pdo->prepare("
        SELECT id, hora_entrada, hora_salida
        FROM asistencias 
        WHERE empleado_id = ? AND fecha = CURDATE()
    ");
    $stmt->execute([$empleado_id]);
    $asistencia_existente = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $hora_actual = date('H:i:s');
    
    // Si ya existe registro de entrada pero no de salida, registrar salida
    if ($asistencia_existente && !$asistencia_existente['hora_salida']) {
        // Registrar hora de salida
        $stmt = $pdo->prepare("
            UPDATE asistencias 
            SET hora_salida = ? 
            WHERE id = ?
        ");
        $stmt->execute([$hora_actual, $asistencia_existente['id']]);
        
        echo json_encode([
            'success' => true,
            'message' => '✓ Salida registrada correctamente',
            'empleado' => $empleado,
            'tipo' => 'salida',
            'hora_salida' => date('H:i', strtotime($hora_actual))
        ]);
        exit;
    } elseif ($asistencia_existente && $asistencia_existente['hora_salida']) {
        // Ya tiene entrada y salida registradas
        echo json_encode([
            'success' => false,
            'message' => 'Este empleado ya completó su jornada hoy',
            'empleado' => $empleado
        ]);
        exit;
    }
    
    // Obtener horario del empleado para determinar el estado
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(ehd.hora_entrada, eh.hora_entrada) as hora_entrada,
            COALESCE(ehd.hora_salida, eh.hora_salida) as hora_salida,
            COALESCE(ehd.tolerancia_minutos, eh.tolerancia_minutos, 10) as tolerancia_minutos
        FROM empleados e
        LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id AND eh.activo = 1
        LEFT JOIN empleados_horarios_dias ehd ON e.id = ehd.empleado_id 
            AND ehd.dia_semana = DAYOFWEEK(CURDATE()) - 1 
            AND ehd.activo = 1
        WHERE e.id = ?
    ");
    $stmt->execute([$empleado_id]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Determinar el estado (presente, tarde)
    $hora_actual = date('H:i:s');
    $estado = 'presente';
    
    if ($horario && $horario['hora_entrada']) {
        $hora_entrada_esperada = strtotime($horario['hora_entrada']);
        $hora_entrada_real = strtotime($hora_actual);
        $tolerancia_segundos = ($horario['tolerancia_minutos'] ?? 10) * 60;
        
        if ($hora_entrada_real > ($hora_entrada_esperada + $tolerancia_segundos)) {
            $estado = 'tarde';
        }
    }
    
    // Registrar asistencia (solo entrada)
    $stmt = $pdo->prepare("
        INSERT INTO asistencias (empleado_id, fecha, hora_entrada, estado, creado_por, fecha_creacion)
        VALUES (?, CURDATE(), ?, ?, ?, NOW())
    ");
    
    // Obtener usuario del sistema (si hay sesión activa)
    session_start();
    $usuario_id = $_SESSION['user_id'] ?? null;
    
    $stmt->execute([
        $empleado_id,
        $hora_actual,
        $estado,
        $usuario_id
    ]);
    
    // Respuesta exitosa
    $mensaje = $estado === 'presente' 
        ? '✓ Entrada registrada correctamente' 
        : '⚠ Entrada tardía registrada';
    
    echo json_encode([
        'success' => true,
        'message' => $mensaje,
        'empleado' => $empleado,
        'hora_entrada' => date('H:i', strtotime($hora_actual)),
        'tipo' => 'entrada',
        'estado' => $estado
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
