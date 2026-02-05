<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '../config.php';

$tipo_item = $_GET['tipo'] ?? 'producto';
$item_id = intval($_GET['id'] ?? 0);

$error_item = '';
$item = null;
$movimientos = [];

// Obtener información del item
if ($item_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($item) {
        // Obtener movimientos
        $stmt = $pdo->prepare("
            SELECT m.*, u.nombre as usuario_nombre
            FROM ecommerce_inventario_movimientos m
            LEFT JOIN usuarios u ON m.usuario_id = u.id
            WHERE m.producto_id = ?
            ORDER BY m.fecha_creacion DESC
            LIMIT 100
        ");
        $stmt->execute([$item_id]);
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $error_item = "Producto ID $item_id no encontrado";
    }
} else {
    $error_item = "ID inválido";
}

// Contar total de registros
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos");
$total_registros = $stmt->fetch()['total'];

// Contar para este producto
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM ecommerce_inventario_movimientos WHERE producto_id = ?");
$stmt->execute([$item_id]);
$total_para_este = $stmt->fetch()['total'];

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Movimientos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-12">
            <a href="inventario.php" class="btn btn-secondary mb-3">← Volver a Inventario</a>
            <h1>📊 Historial de Movimientos</h1>
            
            <?php if (!empty($error_item)): ?>
                <div class="alert alert-danger">
                    <strong>Error:</strong> <?= htmlspecialchars($error_item) ?>
                </div>
            <?php elseif (!$item): ?>
                <div class="alert alert-warning">
                    <strong>No encontrado</strong>
                </div>
            <?php else: ?>
                <h4><?= htmlspecialchars($item['nombre']) ?></h4>
                <p class="text-muted">
                    Stock Actual: <strong><?= number_format($item['stock'], 2) ?></strong>
                </p>
                
                <div class="alert alert-info">
                    <strong>📊 DEBUG:</strong><br>
                    - Producto ID: <?= $item_id ?><br>
                    - Movimientos para este producto: <strong><?= $total_para_este ?></strong><br>
                    - Total de movimientos en tabla: <strong><?= $total_registros ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($item && !empty($error_item) === false): ?>
    <div class="card">
        <div class="card-body">
            <?php if (empty($movimientos)): ?>
                <div class="alert alert-info">
                    <strong>No hay movimientos registrados</strong><br>
                    <small>Este producto aún no tiene historial de movimientos</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Cantidad</th>
                                <th>Stock Anterior</th>
                                <th>Stock Nuevo</th>
                                <th>Referencia</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientos as $mov): ?>
                                <tr>
                                    <td><?= date('d/m/Y H:i', strtotime($mov['fecha_creacion'] ?? 'now')) ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= htmlspecialchars($mov['tipo']) ?></span>
                                    </td>
                                    <td class="text-danger">
                                        <strong>-<?= number_format($mov['cantidad'], 2) ?></strong>
                                    </td>
                                    <td><?= number_format($mov['stock_anterior'] ?? 0, 2) ?></td>
                                    <td class="<?= ($mov['stock_nuevo'] ?? 0) < 0 ? 'text-danger' : '' ?>">
                                        <?= number_format($mov['stock_nuevo'] ?? 0, 2) ?>
                                    </td>
                                    <td>
                                        <?php if ($mov['referencia']): ?>
                                            <code><?= htmlspecialchars($mov['referencia']) ?></code>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema') ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
