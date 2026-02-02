<?php
require 'includes/header.php';

$estado = $_GET['estado'] ?? '';

$sql = "
    SELECT op.*, p.numero_pedido, c.nombre AS cliente_nombre
    FROM ecommerce_ordenes_produccion op
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE 1=1
";
$params = [];

if ($estado) {
    $sql .= " AND op.estado = ?";
    $params[] = $estado;
}

$sql .= " ORDER BY op.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🏭 Órdenes de Producción</h1>
        <p class="text-muted">Seguimiento de producción por pedido</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="en_produccion" <?= $estado === 'en_produccion' ? 'selected' : '' ?>>En producción</option>
                    <option value="terminado" <?= $estado === 'terminado' ? 'selected' : '' ?>>Terminado</option>
                    <option value="entregado" <?= $estado === 'entregado' ? 'selected' : '' ?>>Entregado</option>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
                <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($ordenes)): ?>
            <div class="alert alert-info">No hay órdenes de producción.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Estado</th>
                            <th>Entrega</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $op): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($op['numero_pedido']) ?></strong></td>
                                <td><?= htmlspecialchars($op['cliente_nombre']) ?></td>
                                <td><?= htmlspecialchars(str_replace('_',' ', $op['estado'])) ?></td>
                                <td><?= !empty($op['fecha_entrega']) ? date('d/m/Y', strtotime($op['fecha_entrega'])) : '-' ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($op['fecha_creacion'])) ?></td>
                                <td><a href="pedidos_detalle.php?id=<?= $op['pedido_id'] ?>" class="btn btn-sm btn-primary">Ver pedido</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
