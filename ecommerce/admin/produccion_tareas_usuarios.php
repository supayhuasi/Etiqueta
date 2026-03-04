<?php
require 'includes/header.php';

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

ensure_produccion_scans_schema($pdo);

$stmt = $pdo->query("SELECT
    u.id AS usuario_id,
    u.nombre AS usuario_nombre,
    s.etapa,
    s.created_at,
    pib.estado AS estado_item,
    pib.numero_item,
    pib.codigo_barcode,
    pr.nombre AS producto_nombre,
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
ORDER BY s.created_at DESC");
$actividad_actual = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT
    u.id AS usuario_id,
    u.nombre AS usuario_nombre,
    SUM(CASE WHEN s.etapa = 'corte' THEN 1 ELSE 0 END) AS cortes,
    SUM(CASE WHEN s.etapa = 'armado' THEN 1 ELSE 0 END) AS armados,
    SUM(CASE WHEN s.etapa = 'terminado' THEN 1 ELSE 0 END) AS terminados,
    COUNT(*) AS total
FROM ecommerce_produccion_scans s
JOIN usuarios u ON u.id = s.usuario_id
WHERE DATE(s.created_at) = CURDATE()
GROUP BY u.id, u.nombre
ORDER BY total DESC, u.nombre ASC");
$resumen_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>👷 Dashboard de Tareas por Usuario</h1>
        <p class="text-muted mb-0">Última tarea registrada y resumen de escaneos del día</p>
    </div>
    <div>
        <a href="ordenes_produccion.php" class="btn btn-outline-secondary">← Volver a Órdenes</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Qué está haciendo cada usuario (último escaneo)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($actividad_actual)): ?>
            <div class="alert alert-info mb-0">Todavía no hay escaneos registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Tarea</th>
                            <th>Producto</th>
                            <th>Orden</th>
                            <th>Item</th>
                            <th>Código</th>
                            <th>Estado item</th>
                            <th>Último escaneo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actividad_actual as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td>
                                    <?php
                                        $badge = 'secondary';
                                        if ($row['etapa'] === 'corte') $badge = 'danger';
                                        if ($row['etapa'] === 'armado') $badge = 'warning text-dark';
                                        if ($row['etapa'] === 'terminado') $badge = 'success';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars($row['etapa'])) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['producto_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['numero_pedido'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['numero_item'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($row['codigo_barcode'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars(strtoupper(str_replace('_', ' ', (string)($row['estado_item'] ?? '-')))) ?></td>
                                <td><?= !empty($row['created_at']) ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Resumen de hoy por usuario</h5>
    </div>
    <div class="card-body">
        <?php if (empty($resumen_hoy)): ?>
            <div class="alert alert-info mb-0">Sin escaneos registrados hoy.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Corte</th>
                            <th>Armado</th>
                            <th>Terminado</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen_hoy as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td><?= (int)$row['cortes'] ?></td>
                                <td><?= (int)$row['armados'] ?></td>
                                <td><?= (int)$row['terminados'] ?></td>
                                <td><strong><?= (int)$row['total'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php';
