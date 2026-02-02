<?php
require 'includes/header.php';

$estado_filter = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "
    SELECT p.*, c.nombre as cliente_nombre, c.email as cliente_email 
    FROM ecommerce_pedidos p
    JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE 1=1
";
$params = [];

if (!empty($estado_filter)) {
    $query .= " AND p.estado = ?";
    $params[] = $estado_filter;
}

if (!empty($fecha_desde)) {
    $query .= " AND DATE(p.fecha_pedido) >= ?";
    $params[] = $fecha_desde;
}

if (!empty($fecha_hasta)) {
    $query .= " AND DATE(p.fecha_pedido) <= ?";
    $params[] = $fecha_hasta;
}

$query .= " ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$estados = ['pendiente', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado'];
$colores = [
    'pendiente' => 'warning',
    'confirmado' => 'info',
    'preparando' => 'primary',
    'enviado' => 'secondary',
    'entregado' => 'success',
    'cancelado' => 'danger'
];

// Procesar cambio de estado
if ($_POST['accion'] === 'cambiar_estado') {
    try {
        $pedido_id = intval($_POST['pedido_id']);
        $nuevo_estado = $_POST['nuevo_estado'];
        
        if (!in_array($nuevo_estado, $estados)) die("Estado inválido");
        
        $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $pedido_id]);
        
        // Recargar
        header("Location: pedidos.php");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Pedidos</h1>
    <div class="text-muted">Total: <?= count($pedidos) ?> pedidos</div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-auto">
                <label for="estado" class="form-label">Estado:</label>
                <select name="estado" id="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($estados as $est): ?>
                        <option value="<?= $est ?>" <?= $estado_filter === $est ? 'selected' : '' ?>>
                            <?= ucfirst($est) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <label for="fecha_desde" class="form-label">Desde:</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
            </div>
            <div class="col-auto">
                <label for="fecha_hasta" class="form-label">Hasta:</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-outline-secondary">Filtrar</button>
                <?php if (!empty($estado_filter) || !empty($fecha_desde) || !empty($fecha_hasta)): ?>
                    <a href="pedidos.php" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">No hay pedidos</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pedido['numero_pedido']) ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($pedido['cliente_email']) ?></small>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                        <td>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) as cantidad FROM ecommerce_pedido_items WHERE pedido_id = ?");
                            $stmt->execute([$pedido['id']]);
                            $items = $stmt->fetch(PDO::FETCH_ASSOC);
                            echo $items['cantidad'];
                            ?>
                        </td>
                        <td>$<?= number_format($pedido['total'], 2) ?></td>
                        <td>
                            <span class="badge bg-<?= $colores[$pedido['estado']] ?>">
                                <?= ucfirst($pedido['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detalle<?= $pedido['id'] ?>">Ver Detalle</button>
                            <a class="btn btn-sm btn-success" href="pedidos_detalle.php?id=<?= $pedido['id'] ?>#pagos">Pagos</a>
                            <a class="btn btn-sm btn-outline-dark" href="pedido_imprimir.php?id=<?= $pedido['id'] ?>" target="_blank">Imprimir</a>
                            <a class="btn btn-sm btn-outline-primary" href="pedido_remito.php?id=<?= $pedido['id'] ?>" target="_blank">Remito</a>
                            <div class="dropdown d-inline-block">
                                <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    Cambiar Estado
                                </button>
                                <ul class="dropdown-menu">
                                    <?php foreach ($estados as $est): ?>
                                        <?php if ($est !== $pedido['estado']): ?>
                                            <li>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="accion" value="cambiar_estado">
                                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                    <input type="hidden" name="nuevo_estado" value="<?= $est ?>">
                                                    <button type="submit" class="dropdown-item">
                                                        → <?= ucfirst($est) ?>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal Detalle -->
                    <div class="modal fade" id="detalle<?= $pedido['id'] ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Detalle - <?= htmlspecialchars($pedido['numero_pedido']) ?></h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <h6>Datos del Cliente</h6>
                                            <p><strong><?= htmlspecialchars($pedido['cliente_nombre']) ?></strong><br>
                                            <?= htmlspecialchars($pedido['cliente_email']) ?><br>
                                            <?php if (!empty($pedido['cliente_telefono'])): ?>
                                                <?= htmlspecialchars($pedido['cliente_telefono']) ?>
                                            <?php endif; ?></p>
                                        </div>
                                        <div class="col-md-6">
                                            <h6>Datos del Pedido</h6>
                                            <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?><br>
                                            <strong>Método de Pago:</strong> <?= ucfirst($pedido['metodo_pago']) ?><br>
                                            <strong>Estado:</strong> <span class="badge bg-<?= $colores[$pedido['estado']] ?>"><?= ucfirst($pedido['estado']) ?></span></p>
                                        </div>
                                    </div>

                                    <h6>Items</h6>
                                    <div class="table-responsive mb-3">
                                        <table class="table table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Producto</th>
                                                    <th>Medidas</th>
                                                    <th>Precio Unit.</th>
                                                    <th>Cantidad</th>
                                                    <th>Subtotal</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php
                                                $stmt = $pdo->prepare("
                                                    SELECT pi.*, p.nombre as producto_nombre 
                                                    FROM ecommerce_pedido_items pi
                                                    JOIN ecommerce_productos p ON pi.producto_id = p.id
                                                    WHERE pi.pedido_id = ?
                                                ");
                                                $stmt->execute([$pedido['id']]);
                                                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($items as $item):
                                                    $medidas = !empty($item['medidas']) ? json_decode($item['medidas'], true) : null;
                                                ?>
                                                    <tr>
                                                        <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                                                        <td>
                                                            <?php if ($medidas): ?>
                                                                <?= $medidas['alto'] ?? '' ?> × <?= $medidas['ancho'] ?? '' ?> cm
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>$<?= number_format($item['precio_unitario'], 2) ?></td>
                                                        <td><?= $item['cantidad'] ?></td>
                                                        <td>$<?= number_format($item['precio_unitario'] * $item['cantidad'], 2) ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="text-end">
                                        <h6>Subtotal: $<?= number_format($pedido['subtotal'], 2) ?></h6>
                                        <h6>Envío: $<?= number_format($pedido['costo_envio'], 2) ?></h6>
                                        <h5>Total: <strong>$<?= number_format($pedido['total'], 2) ?></strong></h5>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
