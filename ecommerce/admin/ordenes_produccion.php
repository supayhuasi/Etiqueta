<?php
require 'includes/header.php';

$estado = trim((string)($_GET['estado'] ?? ''));
$estados_validos = ['', 'pendiente', 'en_produccion', 'terminado', 'entregado', 'cancelado', 'no_entregadas'];
if (!in_array($estado, $estados_validos, true)) {
    $estado = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar') {
    try {
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        if ($pedido_id <= 0) {
            throw new Exception('ID de pedido inválido');
        }

        $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET estado = 'cancelado' WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);

        $mensaje = 'Orden de producción cancelada';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'scan_estado') {
    try {
        $codigo = trim($_POST['codigo'] ?? '');
        $secuencia = ['pendiente','en_produccion','terminado','entregado'];

        if ($codigo === '') {
            throw new Exception('Código inválido');
        }

        $stmt = $pdo->prepare("SELECT id FROM ecommerce_pedidos WHERE numero_pedido = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) {
            throw new Exception('Pedido no encontrado');
        }

        $stmt = $pdo->prepare("SELECT estado FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
        $stmt->execute([$pedido['id']]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$orden) {
            throw new Exception('Orden de producción no encontrada');
        }

        $idx = array_search($orden['estado'], $secuencia, true);
        if ($idx === false || $idx >= count($secuencia) - 1) {
            throw new Exception('La orden ya está en el último estado');
        }
        $nuevo_estado = $secuencia[$idx + 1];

        $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET estado = ? WHERE pedido_id = ?");
        $stmt->execute([$nuevo_estado, $pedido['id']]);

        $mensaje = 'Estado actualizado';
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$sql = "
    SELECT op.*, p.numero_pedido, p.observaciones AS pedido_observaciones, c.nombre AS cliente_nombre
    FROM ecommerce_ordenes_produccion op
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE 1=1
";
$params = [];

if ($estado === 'no_entregadas') {
    $sql .= " AND op.estado IN ('pendiente','en_produccion','terminado')";
} elseif ($estado !== '') {
    $sql .= " AND op.estado = ?";
    $params[] = $estado;
} else {
    $sql .= " AND op.estado != 'cancelado'";
}

$sql .= " ORDER BY op.fecha_creacion DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

function orden_esta_vencida(array $orden): bool {
    $estado = strtolower((string)($orden['estado'] ?? ''));
    if (in_array($estado, ['terminado', 'entregado', 'cancelado'], true)) {
        return false;
    }
    if (empty($orden['fecha_entrega'])) {
        return false;
    }

    $ts_entrega = strtotime((string)$orden['fecha_entrega']);
    if ($ts_entrega === false) {
        return false;
    }

    $fecha_entrega = date('Y-m-d', $ts_entrega);
    return $fecha_entrega <= date('Y-m-d');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🏭 Órdenes de Producción</h1>
        <p class="text-muted">Seguimiento de producción por pedido</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="orden_produccion_crear.php" class="btn btn-primary btn-lg">
            + Crear orden
        </a>
        <a href="produccion_tareas_usuarios.php" class="btn btn-outline-primary btn-lg">
            👷 Dashboard por Usuario
        </a>
        <a href="/scan.php" class="btn btn-success btn-lg">
            📱 Control de Producción (Escaneo)
        </a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end" id="scanForm">
            <input type="hidden" name="accion" value="scan_estado">
            <div class="col-md-4">
                <label class="form-label">Código de pedido (scanner)</label>
                <input type="text" name="codigo" class="form-control" autofocus>
            </div>
            <div class="col-md-3">
                <button class="btn btn-primary" type="submit">Actualizar por scanner</button>
            </div>
        </form>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos (sin canceladas)</option>
                    <option value="no_entregadas" <?= $estado === 'no_entregadas' ? 'selected' : '' ?>>No entregadas</option>
                    <option value="pendiente" <?= $estado === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="en_produccion" <?= $estado === 'en_produccion' ? 'selected' : '' ?>>En producción</option>
                    <option value="terminado" <?= $estado === 'terminado' ? 'selected' : '' ?>>Terminado</option>
                    <option value="entregado" <?= $estado === 'entregado' ? 'selected' : '' ?>>Entregado</option>
                    <option value="cancelado" <?= $estado === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div class="col-md-4">
                <button class="btn btn-outline-secondary" type="submit">Filtrar</button>
                <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php
$entregadas = 0;
$no_entregadas = 0;
$total_ordenes = 0;
try {
    $stmt_counts = $pdo->query("
        SELECT estado, COUNT(*) AS c
        FROM ecommerce_ordenes_produccion
        GROUP BY estado
    ");
    foreach ($stmt_counts->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cant = (int)$row['c'];
        $est = (string)$row['estado'];
        if ($est === 'entregado') {
            $entregadas = $cant;
        } elseif ($est !== 'cancelado') {
            $no_entregadas += $cant;
        }
        if ($est !== 'cancelado') {
            $total_ordenes += $cant;
        }
    }
} catch (Exception $e) {
    $total_ordenes = count($ordenes);
}
?>

<div class="row mb-3">
    <div class="col-md-4">
        <a href="ordenes_produccion.php?estado=entregado" class="text-decoration-none">
            <div class="card bg-light border-success mb-2 <?= $estado === 'entregado' ? 'border-3' : '' ?>">
                <div class="card-body text-success">
                    <h6 class="card-title mb-1">Órdenes Entregadas</h6>
                    <h3 class="fw-bold mb-0"><?= $entregadas ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="ordenes_produccion.php?estado=no_entregadas" class="text-decoration-none">
            <div class="card bg-light border-warning mb-2 <?= $estado === 'no_entregadas' ? 'border-3' : '' ?>">
                <div class="card-body text-warning">
                    <h6 class="card-title mb-1">Órdenes No Entregadas</h6>
                    <h3 class="fw-bold mb-0"><?= $no_entregadas ?></h3>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="ordenes_produccion.php" class="text-decoration-none">
            <div class="card bg-light border-secondary mb-2 <?= $estado === '' ? 'border-3' : '' ?>">
                <div class="card-body text-secondary">
                    <h6 class="card-title mb-1">Total Órdenes</h6>
                    <h3 class="fw-bold mb-0"><?= $total_ordenes ?></h3>
                </div>
            </div>
        </a>
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
                            <?php $vencida = orden_esta_vencida($op); ?>
                            <tr class="<?= $vencida ? 'table-danger' : '' ?>">
                                <td>
                                    <strong><?= htmlspecialchars($op['numero_pedido']) ?></strong>
                                    <?php if (!empty(trim((string)($op['pedido_observaciones'] ?? '')))): ?>
                                        <span class="badge bg-info ms-1" title="<?= htmlspecialchars(trim((string)$op['pedido_observaciones'])) ?>">📝 obs.</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($op['cliente_nombre']) ?></td>
                                <td>
                                    <?= htmlspecialchars(str_replace('_',' ', $op['estado'])) ?>
                                    <?php if ($vencida): ?>
                                        <span class="badge bg-danger ms-2">Vencida</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($op['fecha_entrega']) ? date('d/m/Y', strtotime($op['fecha_entrega'])) : '-' ?>
                                    <?php if ($vencida && !empty($op['fecha_entrega'])): ?>
                                        <?php $dias = (int)floor((strtotime(date('Y-m-d')) - strtotime(date('Y-m-d', strtotime($op['fecha_entrega'])))) / 86400); ?>
                                        <div class="small text-danger fw-semibold">
                                            <?= $dias > 0 ? ($dias . ' día(s) de atraso') : 'Vence hoy' ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d/m/Y H:i', strtotime($op['fecha_creacion'])) ?></td>
                                <td>
                                    <a href="orden_produccion_detalle.php?pedido_id=<?= $op['pedido_id'] ?>" class="btn btn-sm btn-primary">Ver orden</a>
                                    <?php if ($op['estado'] !== 'cancelado' && $op['estado'] !== 'entregado'): ?>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar esta orden de producción?');">
                                            <input type="hidden" name="accion" value="cancelar">
                                            <input type="hidden" name="pedido_id" value="<?= $op['pedido_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger">Cancelar</button>
                                        </form>
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
