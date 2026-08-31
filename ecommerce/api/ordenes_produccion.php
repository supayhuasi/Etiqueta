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

$estado = isset($_GET['estado']) ? trim($_GET['estado']) : null;
$pedido_id = isset($_GET['pedido_id']) ? intval($_GET['pedido_id']) : null;

try {
    $sql = "SELECT op.*, p.numero_pedido FROM ecommerce_ordenes_produccion op ";
    $sql .= "LEFT JOIN ecommerce_pedidos p ON p.id = op.pedido_id ";
    $where = [];
    $params = [];

    if ($estado !== null && $estado !== '') {
        $where[] = 'op.estado = ?';
        $params[] = $estado;
    }
    if ($pedido_id) {
        $where[] = 'op.pedido_id = ?';
        $params[] = $pedido_id;
    }
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY op.id DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'ordenes' => $ordenes]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
