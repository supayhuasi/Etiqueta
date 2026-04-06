<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/compras_workflow.php';
ensureComprasWorkflowSchema($pdo);

$mensaje = $_GET['mensaje'] ?? '';
$mensajeTexto = '';
if ($mensaje === 'eliminada') {
    $mensajeTexto = 'Registro eliminado correctamente.';
} elseif ($mensaje === 'creada' || $mensaje === 'orden_creada') {
    $mensajeTexto = 'Orden de compra creada correctamente.';
} elseif ($mensaje === 'editada') {
    $mensajeTexto = 'Registro actualizado correctamente.';
} elseif ($mensaje === 'aprobada') {
    $mensajeTexto = 'La orden fue aprobada y pasó a compra.';
} elseif ($mensaje === 'recepcion_actualizada') {
    $mensajeTexto = 'La recepción se actualizó y el stock fue ajustado.';
}

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
        <h1>🧾 Órdenes de Compra</h1>
        <p class="text-muted">Gestioná órdenes, aprobación de compra y recepción con impacto en stock</p>
    </div>
    <a href="compras_crear.php" class="btn btn-primary">+ Nueva Orden</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($mensajeTexto): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensajeTexto) ?></div>
        <?php endif; ?>

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
                            <th>Estado</th>
                            <th>Recepción</th>
                            <th>Total</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($compras as $compra): ?>
                            <?php
                            $estadoMeta = compraEstadoMeta($compra['estado'] ?? 'orden_pendiente');
                            $recepcionMeta = compraRecepcionMeta($compra['recepcion_estado'] ?? 'pendiente');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($compra['numero_compra']) ?></strong></td>
                                <td><?= htmlspecialchars($compra['proveedor_nombre'] ?? 'N/A') ?></td>
                                <td><?= date('d/m/Y', strtotime($compra['fecha_compra'])) ?></td>
                                <td><span class="badge bg-<?= $estadoMeta['class'] ?>"><?= htmlspecialchars($estadoMeta['label']) ?></span></td>
                                <td><span class="badge bg-<?= $recepcionMeta['class'] ?>"><?= htmlspecialchars($recepcionMeta['label']) ?></span></td>
                                <td>$<?= number_format($compra['total'], 2) ?></td>
                                <td>
                                    <a href="compras_detalle.php?id=<?= $compra['id'] ?>" class="btn btn-sm btn-primary">👁️ Ver</a>
                                    <a href="compras_editar.php?id=<?= $compra['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                    <a href="compras_eliminar.php?id=<?= $compra['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar este registro? Si tiene recepción cargada se descontará el stock ya ingresado.')">🗑️ Eliminar</a>
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
