<?php
require 'config.php';
header('Content-Type: application/json');

$empleado_id = $_GET['empleado_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT hora_entrada, hora_salida, tolerancia_minutos 
    FROM empleados_horarios 
    WHERE empleado_id = ? AND activo = 1
");
$stmt->execute([$empleado_id]);
$horario = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'horario' => $horario ?: null
]);
