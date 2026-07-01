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

$fechaDesde = $_GET['fecha_desde'] ?? '';
$fechaHasta = $_GET['fecha_hasta'] ?? '';

$whereClauses = [];
$params = [];

if ($fechaDesde !== '') {
    $whereClauses[] = 'DATE(c.fecha_compra) >= ?';
    $params[] = $fechaDesde;
}

if ($fechaHasta !== '') {
    $whereClauses[] = 'DATE(c.fecha_compra) <= ?';
    $params[] = $fechaHasta;
}

$sql = "
    SELECT c.*, p.nombre as proveedor_nombre
    FROM ecommerce_compras c
    LEFT JOIN ecommerce_proveedores p ON c.proveedor_id = p.id
";

if (!empty($whereClauses)) {
    $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
}

$sql .= ' ORDER BY c.fecha_compra DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalOrdenes = count($compras);
$totalImporte = 0;
$totalAprobadas = 0;
$totalRecepcionTotal = 0;

foreach ($compras as $compra) {
    $totalImporte += (float)($compra['total'] ?? 0);

    if (($compra['estado'] ?? '') === 'aprobada') {
        $totalAprobadas++;
    }

    if (($compra['recepcion_estado'] ?? '') === 'total') {
        $totalRecepcionTotal++;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧾 Órdenes de Compra</h1>
        <p class="text-muted">Gestioná órdenes, aprobación de compra y recepción con impacto en stock</p>
    </div>
    <a href="compras_crear.php" class="btn btn-primary">+ Nueva Orden</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="fecha_desde" class="form-label">Fecha desde</label>
                <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($fechaDesde) ?>">
            </div>
            <div class="col-md-3">
                <label for="fecha_hasta" class="form-label">Fecha hasta</label>
                <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($fechaHasta) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">Filtrar</button>
            </div>
            <div class="col-md-2">
                <a href="compras.php" class="btn btn-outline-secondary w-100">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">Total órdenes</h6>
                <h3 class="mb-0"><?= $totalOrdenes ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">Monto total</h6>
                <h3 class="mb-0">$<?= number_format($totalImporte, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">Aprobadas</h6>
                <h3 class="mb-0"><?= $totalAprobadas ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body">
                <h6 class="card-title text-muted mb-2">Recepción total</h6>
                <h3 class="mb-0"><?= $totalRecepcionTotal ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if ($mensajeTexto): ?>
            <div class="alert alert-success"><?= htmlspecialchars($mensajeTexto) ?></div>
        <?php endif; ?>

        <?php if (empty($compras)): ?>
            <div class="alert alert-info">No hay compras registradas para el rango seleccionado.</div>
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
