<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function ensure_produccion_scans_schema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_produccion_scans (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produccion_item_id INT NOT NULL,
        orden_produccion_id INT NOT NULL,
        pedido_id INT NOT NULL,
        usuario_id INT NOT NULL,
        etapa ENUM('corte','armado','terminado') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (produccion_item_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_orden (orden_produccion_id),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

session_start();
$apiKey = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']) || !empty($_SESSION['user_id']) || !empty($_SESSION['usuario_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    ensure_produccion_scans_schema($pdo);

    $usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
    $solo_activos = isset($_GET['solo_activos']) ? (int)$_GET['solo_activos'] : 0;

    $sql = "SELECT
        u.id AS usuario_id,
        u.nombre AS usuario_nombre,
        s.etapa,
        s.created_at,
        pib.estado AS estado_item,
        pib.numero_item,
        pib.codigo_barcode,
        pr.nombre AS producto_nombre,
        p.id AS pedido_id,
        p.numero_pedido
    FROM ecommerce_produccion_scans s
    JOIN (
        SELECT usuario_id, MAX(id) AS max_id
        FROM ecommerce_produccion_scans
        GROUP BY usuario_id
    ) ult ON ult.max_id = s.id
    JOIN usuarios u ON u.id = s.usuario_id
    LEFT JOIN ecommerce_produccion_items_barcode pib ON pib.id = s.produccion_item_id
    LEFT JOIN ecommerce_pedido_items pi ON pi.id = pib.pedido_item_id
    LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
    LEFT JOIN ecommerce_ordenes_produccion op ON op.id = s.orden_produccion_id
    LEFT JOIN ecommerce_pedidos p ON p.id = op.pedido_id
    WHERE 1=1";

    $params = [];
    if ($usuario_id > 0) {
        $sql .= " AND u.id = ?";
        $params[] = $usuario_id;
    }

    if ($solo_activos === 1) {
        $sql .= " AND s.etapa <> 'terminado'";
    }

    $sql .= " ORDER BY s.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'total' => count($rows),
        'usuarios' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar tareas de producción',
        'error' => $e->getMessage()
    ]);
}
