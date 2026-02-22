<?php
require 'includes/header.php';

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$incluir_entregados = !empty($_GET['incluir_entregados']);
$pedidos = [];
$error_pagina = '';

try {
    $sql = "
        SELECT op.id AS orden_id, op.pedido_id, op.estado AS estado_produccion, op.fecha_entrega,
               p.numero_pedido, p.envio_nombre, p.envio_telefono, p.envio_direccion,
               p.envio_localidad, p.envio_provincia, p.envio_codigo_postal, p.fecha_creacion,
               c.nombre AS cliente_nombre
        FROM ecommerce_ordenes_produccion op
        JOIN ecommerce_pedidos p ON op.pedido_id = p.id
        LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
        WHERE " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'") . "
    ";
    $params = [];
    if ($fecha_desde !== '') {
        $sql .= " AND DATE(p.fecha_creacion) >= ?";
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $sql .= " AND DATE(p.fecha_creacion) <= ?";
        $params[] = $fecha_hasta;
    }
    $sql .= " ORDER BY p.fecha_creacion DESC, op.pedido_id ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error_pagina = $e->getMessage();
}

$qs = http_build_query(array_filter([
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'incluir_entregados' => $incluir_entregados ? '1' : null,
]));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Instalaciones</h1>
        <p class="text-muted">Pedidos terminados de produccion - armar listas para instalar</p>
    </div>
    <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Ordenes de Produccion</a>
</div>

<?php if ($error_pagina !== ''): ?>
<div class="alert alert-danger">
    <strong>Error al cargar:</strong> <?= htmlspecialchars($error_pagina) ?>
    <p class="mb-0 mt-2 small">Comprobá que existan las tablas <code>ecommerce_ordenes_produccion</code>, <code>ecommerce_pedidos</code> y <code>ecommerce_clientes</code>.</p>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="incluir_entregados" value="1" id="incluir_entregados" <?= $incluir_entregados ? 'checked' : '' ?>>
                    <label class="form-check-label" for="incluir_entregados">Incluir ya entregados</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">No hay pedidos con produccion terminada para los filtros elegidos.</div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= count($pedidos) ?> pedido(s) terminado(s)</h5>
            <div class="d-flex gap-2">
                <a href="instalaciones_reporte_direcciones.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por direcciones y cortinas</a>
                <a href="instalaciones_reporte_productos.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por productos y tiempos</a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente / Envio</th>
                            <th>Direccion</th>
                            <th>Localidad</th>
                            <th>Fecha pedido</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedidos as $row): ?>
                            <?php
                            $nombre = trim($row['envio_nombre'] ?? '') ?: $row['cliente_nombre'] ?? 'Sin nombre';
                            $dir = trim($row['envio_direccion'] ?? '');
                            $loc = trim($row['envio_localidad'] ?? '') . (!empty($row['envio_provincia']) ? ', ' . $row['envio_provincia'] : '');
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['numero_pedido']) ?></strong></td>
                                <td><?= htmlspecialchars($nombre) ?></td>
                                <td><?= htmlspecialchars($dir) ?></td>
                                <td><?= htmlspecialchars($loc) ?></td>
                                <td><?= date('d/m/Y', strtotime($row['fecha_creacion'])) ?></td>
                                <td>
                                    <a href="orden_produccion_detalle.php?pedido_id=<?= (int)$row['pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
