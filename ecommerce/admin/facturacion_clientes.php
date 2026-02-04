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

<script>
function actualizarTotal() {
    let total = 0;
    let haySeleccionados = false;
    document.querySelectorAll('input[name="cliente_check"]:checked').forEach(checkbox => {
        const row = checkbox.closest('tr');
        const saldo = parseFloat(row.getAttribute('data-saldo')) || 0;
        total += saldo;
        haySeleccionados = true;
    });
    
    const totalElement = document.getElementById('total-seleccionados');
    if (haySeleccionados) {
        totalElement.textContent = '$' + total.toLocaleString('es-AR', {minimumFractionDigits: 2});
    } else {
        // Mostrar total de todo
        const totalTodo = Array.from(document.querySelectorAll('tr[data-saldo]'))
            .reduce((sum, tr) => sum + (parseFloat(tr.getAttribute('data-saldo')) || 0), 0);
        totalElement.textContent = '$' + totalTodo.toLocaleString('es-AR', {minimumFractionDigits: 2});
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[name="cliente_check"]').forEach(checkbox => {
        checkbox.addEventListener('change', actualizarTotal);
    });
    actualizarTotal();
});
</script>

<div class="card">
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="alert alert-info">No hay clientes.</div>
        <?php else: ?>
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="card bg-light">
                        <div class="card-body">
                            <h6 class="card-title">Total a Facturar</h6>
                            <h4 id="total-seleccionados" class="text-success fw-bold">$0,00</h4>
                            <small class="text-muted">Selecciona clientes para ver el total de sus saldos</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;"></th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th class="text-end">Total pedidos</th>
                            <th class="text-end">Total pagado</th>
                            <th class="text-end">Saldo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): ?>
                            <tr data-saldo="<?= $c['saldo'] ?>">
                                <td>
                                    <input type="checkbox" name="cliente_check" class="form-check-input" value="<?= $c['id'] ?>">
                                </td>
                                <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars($c['email'] ?? '-') ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($c['telefono'] ?? '-') ?></small>
                                </td>
                                <td class="text-end">$<?= number_format($c['total_pedidos'], 2, ',', '.') ?></td>
                                <td class="text-end">$<?= number_format($c['total_pagado'], 2, ',', '.') ?></td>
                                <td class="text-end">
                                    <strong><?php if ($c['saldo'] > 0): ?>
                                        <span class="text-danger">$<?= number_format($c['saldo'], 2, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-success">$<?= number_format($c['saldo'], 2, ',', '.') ?></span>
                                    <?php endif; ?></strong>
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
