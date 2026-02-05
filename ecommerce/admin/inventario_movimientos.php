<?php
require 'includes/header.php';

$tipo_item = $_GET['tipo'] ?? 'material'; // material o producto
$item_id = intval($_GET['id'] ?? 0);

// Obtener información del item
if ($tipo_item === 'material') {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_materiales WHERE id = ?");
} else {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
}
$stmt->execute([$item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die("Item no encontrado");
}

// Obtener movimientos
$stmt = $pdo->prepare("
    SELECT m.*, u.nombre as usuario_nombre
    FROM ecommerce_inventario_movimientos m
    LEFT JOIN usuarios u ON m.usuario_id = u.id
    WHERE m.tipo_item = ? AND m.item_id = ?
    ORDER BY m.fecha_creacion DESC
    LIMIT 100
");
$stmt->execute([$tipo_item, $item_id]);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="inventario.php" class="btn btn-secondary mb-3">← Volver a Inventario</a>
        <h1>📊 Historial de Movimientos</h1>
        <h4><?= htmlspecialchars($item['nombre']) ?></h4>
        <p class="text-muted">
            Tipo: <span class="badge bg-<?= $tipo_item === 'material' ? 'info' : 'success' ?>"><?= ucfirst($tipo_item) ?></span> | 
            Stock Actual: <strong><?= number_format($item['stock'], 2) ?></strong>
        </p>
    </div>
</div>

<!-- Tabla de movimientos -->
<div class="card">
    <div class="card-body">
        <?php if (empty($movimientos)): ?>
            <div class="alert alert-info">No hay movimientos registrados</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo Movimiento</th>
                            <th>Cantidad</th>
                            <th>Stock Anterior</th>
                            <th>Stock Nuevo</th>
                            <th>Referencia</th>
                            <th>Usuario</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <tr>
                                <td>
                                    <?= date('d/m/Y H:i', strtotime($mov['fecha_creacion'])) ?>
                                </td>
                                <td>
                                    <?php 
                                    $badges = [
                                        'entrada' => 'bg-success',
                                        'salida' => 'bg-danger',
                                        'ajuste' => 'bg-warning text-dark',
                                        'produccion' => 'bg-primary',
                                        'venta' => 'bg-info'
                                    ];
                                    $badge_class = $badges[$mov['tipo_movimiento']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $mov['tipo_movimiento'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong class="<?= in_array($mov['tipo_movimiento'], ['entrada', 'ajuste']) && $mov['cantidad'] > 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= in_array($mov['tipo_movimiento'], ['entrada']) ? '+' : '-' ?><?= number_format(abs($mov['cantidad']), 2) ?>
                                    </strong>
                                </td>
                                <td><?= number_format($mov['stock_anterior'], 2) ?></td>
                                <td>
                                    <strong class="<?= $mov['stock_nuevo'] < 0 ? 'text-danger' : '' ?>">
                                        <?= number_format($mov['stock_nuevo'], 2) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($mov['referencia']): ?>
                                        <code><?= htmlspecialchars($mov['referencia']) ?></code>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema') ?></small>
                                </td>
                                <td>
                                    <?php if ($mov['observaciones']): ?>
                                        <small><?= htmlspecialchars($mov['observaciones']) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
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
