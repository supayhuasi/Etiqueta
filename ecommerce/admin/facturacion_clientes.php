<?php
require 'includes/header.php';

$estadoPago = $_GET['estado_pago'] ?? 'todos';
$busqueda = trim($_GET['busqueda'] ?? '');
$orden = $_GET['orden'] ?? 'nombre';
$estadosPermitidos = ['todos', 'por_pagar', 'sin_pagar'];
if (!in_array($estadoPago, $estadosPermitidos, true)) {
    $estadoPago = 'todos';
}

$ordenesPermitidos = ['nombre', 'saldo_desc', 'saldo_asc'];
if (!in_array($orden, $ordenesPermitidos, true)) {
    $orden = 'nombre';
}

if (isset($_GET['toggle_saldo']) && $_GET['toggle_saldo'] === '1') {
    $orden = $orden === 'saldo_desc' ? 'saldo_asc' : 'saldo_desc';
}

$whereAdicional = '';
if ($estadoPago === 'por_pagar') {
    $whereAdicional = ' AND (COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0)) > 0';
} elseif ($estadoPago === 'sin_pagar') {
    $whereAdicional = ' AND (COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0)) <= 0';
}

$params = [];
if ($busqueda !== '') {
    $whereAdicional .= ' AND (c.nombre LIKE :busqueda OR c.email LIKE :busqueda)';
    $params[':busqueda'] = '%' . $busqueda . '%';
}

$saldoExpr = '(COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0))';
$orderBy = 'c.nombre ASC';
if ($orden === 'saldo_desc') {
    $orderBy = $saldoExpr . ' DESC, c.nombre ASC';
} elseif ($orden === 'saldo_asc') {
    $orderBy = $saldoExpr . ' ASC, c.nombre ASC';
}

$sql = "
    SELECT c.id, c.nombre, c.email, c.telefono,
           COALESCE(ped.total_pedidos, 0) AS total_pedidos,
           COALESCE(pag.total_pagado, 0) AS total_pagado,
           COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0) AS saldo,
           p_last.id AS ultimo_pedido_id,
           p_last.numero_pedido AS ultimo_numero_pedido,
           p_last.tipo_factura AS ultimo_tipo_factura,
           p_last.numero_factura AS ultimo_numero_factura,
           p_last.fecha_facturacion AS ultima_fecha_facturacion,
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
    WHERE COALESCE(ped.total_pedidos, 0) > 0
    {$whereAdicional}
    ORDER BY {$orderBy}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
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
    const checksClientes = Array.from(document.querySelectorAll('input[name="cliente_check"]'));
    const checkTodos = document.getElementById('seleccionar-todos');

    function actualizarCheckTodos() {
        if (!checkTodos) return;
        const seleccionados = checksClientes.filter(ch => ch.checked).length;
        checkTodos.checked = checksClientes.length > 0 && seleccionados === checksClientes.length;
        checkTodos.indeterminate = seleccionados > 0 && seleccionados < checksClientes.length;
    }

    checksClientes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            actualizarTotal();
            actualizarCheckTodos();
        });
    });

    if (checkTodos) {
        checkTodos.addEventListener('change', function() {
            checksClientes.forEach(ch => {
                ch.checked = checkTodos.checked;
            });
            actualizarTotal();
            actualizarCheckTodos();
        });
    }

    const btnReporte = document.getElementById('btn-generar-reporte');
    if (btnReporte) {
        btnReporte.addEventListener('click', function() {
            const seleccionados = Array.from(document.querySelectorAll('input[name="cliente_check"]:checked'))
                .map(el => el.value)
                .filter(Boolean);

            if (seleccionados.length === 0) {
                alert('Seleccioná al menos un cliente para generar el reporte.');
                return;
            }

            document.getElementById('reporte_cliente_ids').value = seleccionados.join(',');
            document.getElementById('form-reporte-clientes').submit();
        });
    }

    actualizarTotal();
    actualizarCheckTodos();
});
</script>

<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end mb-3">
            <div class="col-md-4">
                <label for="estado_pago" class="form-label">Estado de pago</label>
                <select name="estado_pago" id="estado_pago" class="form-select" onchange="this.form.submit()">
                    <option value="todos" <?= $estadoPago === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="por_pagar" <?= $estadoPago === 'por_pagar' ? 'selected' : '' ?>>Por pagar (saldo pendiente)</option>
                    <option value="sin_pagar" <?= $estadoPago === 'sin_pagar' ? 'selected' : '' ?>>Sin pagar pendiente</option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="busqueda" class="form-label">Buscar cliente</label>
                <input
                    type="text"
                    name="busqueda"
                    id="busqueda"
                    class="form-control"
                    value="<?= htmlspecialchars($busqueda) ?>"
                    placeholder="Nombre o email"
                >
            </div>
            <div class="col-md-4">
                <input type="hidden" name="orden" value="<?= htmlspecialchars($orden) ?>">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="facturacion_clientes.php" class="btn btn-outline-secondary">Limpiar</a>
                <button type="submit" name="toggle_saldo" value="1" class="btn btn-outline-dark">
                    Ordenar saldo pendiente: <?= $orden === 'saldo_asc' ? 'Menor a mayor' : 'Mayor a menor' ?>
                </button>
            </div>
        </form>

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
                <div class="col-md-6 d-flex align-items-end justify-content-md-end mt-3 mt-md-0">
                    <form id="form-reporte-clientes" method="GET" action="facturacion_clientes_reporte.php" target="_blank" class="d-inline">
                        <input type="hidden" name="cliente_ids" id="reporte_cliente_ids" value="">
                        <button type="button" id="btn-generar-reporte" class="btn btn-primary">Generar reporte</button>
                    </form>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="seleccionar-todos" class="form-check-input" title="Seleccionar todos"></th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Pedido / Orden</th>
                            <th class="text-end">Total pedidos</th>
                            <th class="text-end">Total pagado</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-center">Acción</th>
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
                                <td>
                                    <?php if (!empty($c['ultimo_pedido_id'])): ?>
                                        <a href="pedidos_detalle.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver pedido #<?= htmlspecialchars($c['ultimo_numero_pedido']) ?></a>
                                        <a href="orden_produccion_detalle.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>" class="btn btn-sm btn-outline-secondary">Ver orden</a>
                                        <?php if (!empty($c['orden_fecha_creacion'])): ?>
                                            <div class="small text-muted mt-1">Orden: <?= htmlspecialchars(date('d/m/Y', strtotime($c['orden_fecha_creacion']))) ?></div>
                                        <?php elseif (!empty($c['orden_fecha_entrega'])): ?>
                                            <div class="small text-muted mt-1">Entrega: <?= htmlspecialchars(date('d/m/Y', strtotime($c['orden_fecha_entrega']))) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($c['ultimo_numero_factura'])): ?>
                                            <div class="small text-success mt-1">Factura <?= htmlspecialchars((string)($c['ultimo_tipo_factura'] ?? '')) ?> <?= htmlspecialchars((string)$c['ultimo_numero_factura']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
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
                                <td class="text-center">
                                    <?php if (!empty($c['ultimo_pedido_id'])): ?>
                                        <div class="d-flex flex-column gap-1">
                                            <?php if ($c['saldo'] > 0): ?>
                                                <a href="pedidos_detalle.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>#pagos" class="btn btn-sm btn-success">Pagar</a>
                                            <?php endif; ?>
                                            <a href="pedido_factura_pdf.php?pedido_id=<?= (int)$c['ultimo_pedido_id'] ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">Factura</a>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
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
