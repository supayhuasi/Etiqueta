<?php
require 'includes/header.php';

$stmt = $pdo->query("
    SELECT c.*, p.nombre as proveedor_nombre
    FROM ecommerce_compras c
    LEFT JOIN ecommerce_proveedores p ON c.proveedor_id = p.id
    ORDER BY c.fecha_compra DESC
");
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧾 Compras</h1>
        <p class="text-muted">Registro de compras e inventario</p>
    </div>
    <a href="compras_crear.php" class="btn btn-primary">+ Nueva Compra</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($compras)): ?>
            <div class="alert alert-info">No hay compras registradas.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Número</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($compra['numero_compra']) ?></strong></td>
                                <td><?= htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y', strtotime($compra['fecha_compra'])) ?></td>
                                <td>$<?= number_format($compra['total'], 2) ?></td>
                                <td>
                                    <a href="compras_detalle.php?id=<?= $compra['id'] ?>" class="btn btn-sm btn-primary">👁️ Ver</a>
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
