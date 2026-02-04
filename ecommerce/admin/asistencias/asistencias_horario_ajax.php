<?php
header('Content-Type: application/json');

$empleado_id = intval($_GET['empleado_id'] ?? 0);
$fecha = $_GET['fecha'] ?? date('Y-m-d');

if ($empleado_id <= 0) {
    echo json_encode(['tiene_horario' => false]);
    exit;
}

// Obtener día de la semana (0=Domingo, 1=Lunes, etc.)
$dia_semana = date('w', strtotime($fecha));

// Intentar obtener horario específico del día
$stmt = $pdo->prepare("
    SELECT hora_entrada, hora_salida, tolerancia_minutos
    FROM empleados_horarios_dias 
    WHERE empleado_id = ? AND dia_semana = ? AND activo = 1
");
$stmt->execute([$empleado_id, $dia_semana]);
$horario = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no hay horario específico del día, usar horario general
if (!$horario) {
    $stmt = $pdo->prepare("
        SELECT hora_entrada, hora_salida, tolerancia_minutos
        FROM empleados_horarios 
        WHERE empleado_id = ? AND activo = 1
    ");
    $stmt->execute([$empleado_id]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);
}

if ($horario) {
    $entrada = new DateTime($horario['hora_entrada']);
    $salida = new DateTime($horario['hora_salida']);
    $hora_entrada_str = $entrada->format('H:i');
    $hora_salida_str = $salida->format('H:i');
    $tolerancia = $horario['tolerancia_minutos'] ?? 10;
    
    echo json_encode([
        'tiene_horario' => true,
        'hora_entrada' => $hora_entrada_str,
        'hora_salida' => $hora_salida_str,
        'tolerancia' => $tolerancia,
        'texto' => "Horario: $hora_entrada_str - $hora_salida_str (Tolerancia: {$tolerancia} min)"
    ]);
} else {
    echo json_encode([
        'tiene_horario' => false,
        'texto' => 'Sin horario configurado'
    ]);
}
