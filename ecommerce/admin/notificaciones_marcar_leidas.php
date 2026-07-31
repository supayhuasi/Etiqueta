<?php
require 'includes/header.php';
admin_require_csrf_post();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
    exit;
}

$usuarioId = (int)($_SESSION['user']['id'] ?? 0);
if ($usuarioId <= 0) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'No autenticado']);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_notif_leidas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    usuario_id INT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    item_id VARCHAR(50) NOT NULL,
    leido_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_usuario_categoria_item (usuario_id, categoria, item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$stmtIns = $pdo->prepare("INSERT IGNORE INTO ecommerce_notif_leidas (usuario_id, categoria, item_id) VALUES (?, ?, ?)");

$marcados = 0;

// Categorías "persistentes" que no se autolimpian con el tiempo. Se vuelven a
// consultar acá (sin el filtro de leídos) para marcar TODO lo pendiente en este
// momento, no solo lo que se alcanza a mostrar en el dropdown (limitado a 8/20).
try {
    if (
        admin_table_exists($pdo, 'ecommerce_tareas_usuarios')
        && admin_column_exists($pdo, 'ecommerce_tareas_usuarios', 'fecha_limite')
        && admin_column_exists($pdo, 'ecommerce_tareas_usuarios', 'estado')
    ) {
        $ids = $pdo->query("
            SELECT id FROM ecommerce_tareas_usuarios
            WHERE fecha_limite IS NOT NULL
              AND fecha_limite < CURDATE()
              AND LOWER(COALESCE(estado, '')) IN ('pendiente', 'en_progreso')
        ")->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($ids as $id) {
            $marcados += $stmtIns->execute([$usuarioId, 'tareas_vencidas', $id]) ? $stmtIns->rowCount() : 0;
        }
    }

    if (
        admin_table_exists($pdo, 'usuarios')
        && admin_table_exists($pdo, 'roles')
        && admin_table_exists($pdo, 'ecommerce_produccion_items_barcode')
        && admin_column_exists($pdo, 'usuarios', 'rol_id')
        && admin_column_exists($pdo, 'usuarios', 'activo')
    ) {
        $ids = $pdo->query("
            SELECT u.id
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            WHERE COALESCE(u.activo, 1) = 1
              AND LOWER(COALESCE(r.nombre, '')) IN ('operario', 'ventas')
              AND NOT EXISTS (
                  SELECT 1 FROM ecommerce_produccion_items_barcode pib
                  WHERE pib.usuario_inicio = u.id
                    AND LOWER(COALESCE(pib.estado, '')) IN ('armado', 'en_armado', 'en_produccion')
              )
        ")->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($ids as $id) {
            $marcados += $stmtIns->execute([$usuarioId, 'sin_tareas', $id]) ? $stmtIns->rowCount() : 0;
        }
    }

    if (admin_table_exists($pdo, 'gastos') && admin_column_exists($pdo, 'gastos', 'fecha_vencimiento')) {
        $stmtG = $pdo->prepare("
            SELECT g.id FROM gastos g
            LEFT JOIN estados_gastos e ON e.id = g.estado_gasto_id
            WHERE g.fecha_vencimiento IS NOT NULL
              AND g.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
              AND LOWER(COALESCE(e.nombre, '')) <> 'pagado'
        ");
        $stmtG->execute();
        foreach ($stmtG->fetchAll(PDO::FETCH_COLUMN, 0) as $id) {
            $marcados += $stmtIns->execute([$usuarioId, 'gastos_vencer', $id]) ? $stmtIns->rowCount() : 0;
        }
    }

    if (
        admin_table_exists($pdo, 'ecommerce_ordenes_produccion')
        && admin_table_exists($pdo, 'ecommerce_pedidos')
        && admin_column_exists($pdo, 'ecommerce_ordenes_produccion', 'pedido_id')
        && admin_column_exists($pdo, 'ecommerce_ordenes_produccion', 'fecha_entrega')
    ) {
        $ids = $pdo->query("
            SELECT op.id
            FROM ecommerce_ordenes_produccion op
            JOIN ecommerce_pedidos p ON p.id = op.pedido_id
            WHERE op.fecha_entrega IS NOT NULL
              AND DATE(op.fecha_entrega) <= CURDATE()
              AND LOWER(COALESCE(op.estado, '')) NOT IN ('terminado', 'entregado', 'cancelado')
              AND LOWER(COALESCE(p.estado, '')) <> 'cancelado'
        ")->fetchAll(PDO::FETCH_COLUMN, 0);
        foreach ($ids as $id) {
            $marcados += $stmtIns->execute([$usuarioId, 'atrasos', $id]) ? $stmtIns->rowCount() : 0;
        }
    }

    echo json_encode(['ok' => true, 'marcados' => $marcados]);
} catch (Throwable $e) {
    error_log('notificaciones_marcar_leidas error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'Error al marcar como leídas']);
}
