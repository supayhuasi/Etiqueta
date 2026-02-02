<?php
require 'includes/header.php';

$sql = "
    SELECT c.id, c.nombre, c.email, c.telefono,
           COALESCE(ped.total_pedidos, 0) AS total_pedidos,
           COALESCE(pag.total_pagado, 0) AS total_pagado,
           COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0) AS saldo
    FROM ecommerce_clientes c
    LEFT JOIN (
        SELECT cliente_id, SUM(total) AS total_pedidos
        FROM ecommerce_pedidos
        GROUP BY cliente_id
    ) ped ON ped.cliente_id = c.id
    LEFT JOIN (
        SELECT p.cliente_id, SUM(pp.monto) AS total_pagado
        FROM ecommerce_pedido_pagos pp
        JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
        GROUP BY p.cliente_id
    ) pag ON pag.cliente_id = c.id
    ORDER BY c.nombre
";

$stmt = $pdo->query($sql);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💳 Facturación por Cliente</h1>
        <p class="text-muted">Saldos y pagos acumulados</p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="alert alert-info">No hay clientes.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Contacto</th>
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
                                <td class="text-end">$<?= number_format($c['total_pedidos'], 2, ',', '.') ?></td>
                                <td class="text-end">$<?= number_format($c['total_pagado'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <strong>$<?= number_format($c['saldo'], 2, ',', '.') ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
