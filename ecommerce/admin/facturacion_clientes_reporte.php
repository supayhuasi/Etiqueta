<?php
require 'includes/header.php';

$ids_raw = trim((string)($_GET['cliente_ids'] ?? ''));
$ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $ids_raw)), function ($id) {
    return $id > 0;
})));

$clientes = [];
$total_pedidos = 0.0;
$total_pagado = 0.0;
$total_saldo = 0.0;

if (!empty($ids)) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "
        SELECT c.id, c.nombre, c.email, c.telefono,
               COALESCE(ped.total_pedidos, 0) AS total_pedidos,
               COALESCE(pag.total_pagado, 0) AS total_pagado,
               COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0) AS saldo,
               p_last.id AS ultimo_pedido_id,
               p_last.numero_pedido AS ultimo_numero_pedido,
               op.id AS orden_id,
               op.fecha_creacion AS orden_fecha_creacion,
               op.fecha_entrega AS orden_fecha_entrega
        FROM ecommerce_clientes c
        LEFT JOIN (
            SELECT cliente_id, SUM(total) AS total_pedidos, MAX(id) AS ultimo_pedido_id
            FROM ecommerce_pedidos
            WHERE estado != 'cancelado'
            GROUP BY cliente_id
        ) ped ON ped.cliente_id = c.id
        LEFT JOIN (
            SELECT p.cliente_id, SUM(pp.monto) AS total_pagado
            FROM ecommerce_pedido_pagos pp
            JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
            WHERE p.estado != 'cancelado'
            GROUP BY p.cliente_id
        ) pag ON pag.cliente_id = c.id
        LEFT JOIN ecommerce_pedidos p_last ON p_last.id = ped.ultimo_pedido_id
        LEFT JOIN ecommerce_ordenes_produccion op ON op.pedido_id = p_last.id
        WHERE c.id IN ($placeholders)
        ORDER BY c.nombre
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($ids);
    $clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($clientes as $c) {
        $total_pedidos += (float)$c['total_pedidos'];
        $total_pagado += (float)$c['total_pagado'];
        $total_saldo += (float)$c['saldo'];
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📄 Reporte de Facturación Seleccionada</h1>
        <p class="text-muted mb-0">Montos y orden de producción de los clientes seleccionados</p>
    </div>
    <button onclick="window.print()" class="btn btn-outline-secondary">Imprimir</button>
</div>

<?php if (empty($ids)): ?>
    <div class="alert alert-warning">No se recibieron clientes seleccionados.</div>
<?php elseif (empty($clientes)): ?>
    <div class="alert alert-info">No hay datos para los clientes seleccionados.</div>
<?php else: ?>
    <div class="row mb-3">
        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Total pedidos</h6>
                    <h4 class="mb-0">$<?= number_format($total_pedidos, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Total pagado</h6>
                    <h4 class="mb-0">$<?= number_format($total_pagado, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-4 mt-3 mt-md-0">
            <div class="card bg-light">
                <div class="card-body">
                    <h6 class="mb-1">Saldo total</h6>
                    <h4 class="mb-0 <?= $total_saldo > 0 ? 'text-danger' : 'text-success' ?>">$<?= number_format($total_saldo, 2, ',', '.') ?></h4>
                </div>
            </div>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead class="table-light">
                <tr>
                    <th>Cliente</th>
                    <th>Contacto</th>
                    <th>Pedido</th>
                    <th>Orden de Producción</th>
                    <th class="text-end">Total pedidos</th>
                    <th class="text-end">Total pagado</th>
                    <th class="text-end">Saldo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $c): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                        <td>
                            <?= htmlspecialchars($c['email'] ?? '-') ?><br>
                            <small class="text-muted"><?= htmlspecialchars($c['telefono'] ?? '-') ?></small>
                        </td>
                        <td>
                            <?php if (!empty($c['ultimo_pedido_id'])): ?>
                                <a href="pedidos_detalle.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>" target="_blank">#<?= htmlspecialchars((string)$c['ultimo_numero_pedido']) ?></a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($c['orden_id']) && !empty($c['ultimo_pedido_id'])): ?>
                                <a href="orden_produccion_detalle.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>" target="_blank">Orden #<?= (int)$c['orden_id'] ?></a>
                                <?php if (!empty($c['orden_fecha_creacion'])): ?>
                                    <div class="small text-muted">Creación: <?= htmlspecialchars(date('d/m/Y', strtotime($c['orden_fecha_creacion']))) ?></div>
                                <?php elseif (!empty($c['orden_fecha_entrega'])): ?>
                                    <div class="small text-muted">Entrega: <?= htmlspecialchars(date('d/m/Y', strtotime($c['orden_fecha_entrega']))) ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">$<?= number_format((float)$c['total_pedidos'], 2, ',', '.') ?></td>
                        <td class="text-end">$<?= number_format((float)$c['total_pagado'], 2, ',', '.') ?></td>
                        <td class="text-end">
                            <strong class="<?= ((float)$c['saldo']) > 0 ? 'text-danger' : 'text-success' ?>">
                                $<?= number_format((float)$c['saldo'], 2, ',', '.') ?>
                            </strong>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
