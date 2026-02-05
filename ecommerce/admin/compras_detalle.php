<?php
require 'includes/header.php';

$compra_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT c.*, p.nombre as proveedor_nombre, p.email as proveedor_email, p.telefono as proveedor_telefono
    FROM ecommerce_compras c
    LEFT JOIN ecommerce_proveedores p ON c.proveedor_id = p.id
    WHERE c.id = ?
");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die("Compra no encontrada");
}

$stmt = $pdo->prepare("
    SELECT ci.*, pr.nombre as producto_nombre
    FROM ecommerce_compra_items ci
    LEFT JOIN ecommerce_productos pr ON ci.producto_id = pr.id
    WHERE ci.compra_id = ?
    ORDER BY ci.id
");
$stmt->execute([$compra_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧾 Compra <?= htmlspecialchars($compra['numero_compra']) ?></h1>
        <p class="text-muted">Detalle de compra y actualización de inventario</p>
    </div>
    <a href="compras.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏭 Proveedor</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A') ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($compra['proveedor_email'] ?? '-') ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($compra['proveedor_telefono'] ?? '-') ?></p>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">📋 Datos de la Compra</h5>
            </div>
            <div class="card-body">
                <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($compra['fecha_compra'])) ?></p>
                <p><strong>Subtotal:</strong> $<?= number_format($compra['subtotal'], 2) ?></p>
                <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format($compra['total'], 2) ?></span></p>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($compra['observaciones'])): ?>
    <div class="card mb-4">
        <div class="card-header bg-light">📝 Observaciones</div>
        <div class="card-body">
            <?= nl2br(htmlspecialchars($compra['observaciones'])) ?>
        </div>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">📦 Items</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">Costo Unit.</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong></td>
                        <td>
                            <?php if ($item['alto_cm'] && $item['ancho_cm']): ?>
                                <?= $item['ancho_cm'] ?>cm × <?= $item['alto_cm'] ?>cm
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int)$item['cantidad'] ?></td>
                        <td class="text-end">$<?= number_format($item['costo_unitario'], 2) ?></td>
                        <td class="text-end"><strong>$<?= number_format($item['subtotal'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
