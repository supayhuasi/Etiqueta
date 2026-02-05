<?php
// Asegurar que se muestra cualquier error
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require 'includes/header.php';
} catch (Exception $e) {
    // Si header.php falla, mostrar error y continuar
    echo "<!DOCTYPE html>";
    echo "<html><body>";
    echo "<div style='padding: 20px; background: #fee; color: #c00; border: 1px solid #c00;'>";
    echo "<strong>Error en header.php:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
    
    // Cargar config manualmente
    require '../config.php';
}

$tipo_item = $_GET['tipo'] ?? 'material'; // material o producto
$item_id = intval($_GET['id'] ?? 0);

$error_item = '';

// Validar que el ID sea válido
if ($item_id <= 0) {
    $error_item = "ID de item inválido";
} else {
    // Obtener información del item
    if ($tipo_item === 'material') {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_materiales WHERE id = ?");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
    }
    $stmt->execute([$item_id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$item) {
        $error_item = "Item no encontrado (ID: $item_id, Tipo: $tipo_item)";
    }
}

// Inicializar variables
$movimientos = [];
$columnas_tabla = [];

// Si el item existe, obtener movimientos
if (empty($error_item) && !empty($item)) {
    // Obtener movimientos - usando producto_id (schema actual)
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM ecommerce_inventario_movimientos m
        WHERE m.producto_id = ?
        ORDER BY m.fecha_creacion DESC
        LIMIT 100
    ");
    $stmt->execute([$item_id]);
    $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="inventario.php" class="btn btn-secondary mb-3">← Volver a Inventario</a>
        <h1>📊 Historial de Movimientos</h1>
        
        <?php if (!empty($error_item)): ?>
            <div class="alert alert-danger">
                <strong>Error:</strong> <?= htmlspecialchars($error_item) ?>
            </div>
        <?php else: ?>
            <h4><?= htmlspecialchars($item['nombre']) ?></h4>
            <p class="text-muted">
                Tipo: <span class="badge bg-<?= $tipo_item === 'material' ? 'info' : 'success' ?>"><?= ucfirst($tipo_item) ?></span> | 
                Stock Actual: <strong><?= number_format($item['stock'], 2) ?></strong>
            </p>
        <?php endif; ?>
        

    </div>
</div>

<!-- Tabla de movimientos -->
<?php if (empty($error_item) && !empty($item)): ?>
<div class="card">
    <div class="card-body">
        <?php if (empty($movimientos)): ?>
            <div class="alert alert-info">
                <strong>No hay movimientos registrados</strong><br>
                <small>Este producto aún no tiene ningún movimiento en el historial. Los movimientos se registran cuando:</small>
                <ul style="margin-top: 10px;">
                    <li>Se crea una orden de producción (se descuentan materiales)</li>
                    <li>Se realiza un ajuste manual de inventario</li>
                    <li>Se procesa un pedido (descuento de stock)</li>
                </ul>
            </div>
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
                                    $tipo_mov = $mov['tipo'] ?? 'desconocido';
                                    $badge_class = $badges[$tipo_mov] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $badge_class ?>">
                                        <?= ucfirst(str_replace('_', ' ', $tipo_mov)) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $cantidad = $mov['cantidad'] ?? 0;
                                    $es_positivo = $cantidad >= 0;
                                    ?>
                                    <strong class="<?= $es_positivo ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($cantidad, 2) ?>
                                    </strong>
                                </td>
                                <td><?= number_format($mov['stock_anterior'] ?? 0, 2) ?></td>
                                <td>
                                    <strong class="<?= ($mov['stock_nuevo'] ?? 0) < 0 ? 'text-danger' : '' ?>">
                                        <?= number_format($mov['stock_nuevo'] ?? 0, 2) ?>
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
                                    <small class="text-muted">-</small>
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
<?php endif; ?>

<?php 
try {
    require 'includes/footer.php';
} catch (Exception $e) {
    // Si footer.php falla, cerrar HTML
    if (!defined('FOOTER_LOADED')) {
        echo "</body></html>";
    }
}
?>
