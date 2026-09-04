<?php
require '../config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();
$apiKey = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$nombre = trim($_GET['nombre'] ?? '');
// mes opcional; por defecto utilizar mes actual
$mes = trim($_GET['mes'] ?? date('Y-m'));

if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['success'=>false,'message'=>'Parámetro nombre requerido']);
    exit;
}

try {
    $sql = "SELECT ps.empleado_id, e.nombre as empleado_nombre, ps.mes_pago, 
                ps.sueldo_total, ps.monto_pagado, ps.fecha_pago, 
                (ps.sueldo_total - ps.monto_pagado) as faltante
         FROM pagos_sueldos ps
         JOIN empleados e ON ps.empleado_id = e.id
         WHERE e.nombre LIKE ?";
    $params = ["%$nombre%"];  // primer parámetro

    if ($mes !== '') {
        $sql .= " AND ps.mes_pago = ?";
        $params[] = $mes;
    }

    $sql .= " ORDER BY ps.mes_pago DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success'=>true,'nombre'=>$nombre,'registros'=>$rows]);
} catch (Exception $e) {
    // registrar en log para diagnóstico
    error_log('sueldos_faltantes error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false,'message'=>'Error interno']);
}
