<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/funciones_recetas.php';
require_once __DIR__ . '/includes/produccion_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

produccion_asegurar_schema($pdo);

$pedidoId = (int)($_GET['pedido_id'] ?? $_POST['pedido_id'] ?? 0);
$buscar = trim((string)($_GET['q'] ?? ''));
$error = '';
$pedido = null;
$items = [];

if ($pedidoId > 0) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre AS cliente_nombre, c.telefono
        FROM ecommerce_pedidos p
        LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id
        WHERE p.id = ?
    ");
    $stmt->execute([$pedidoId]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) {
        $error = 'Pedido no encontrado.';
        $pedidoId = 0;
    } else {
        $items = produccion_cargar_items($pdo, $pedidoId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pedido) {
    try {
        admin_require_csrf_post();
        $accion = (string)($_POST['accion'] ?? 'crear');

        if (!empty($_POST['eliminar_foto'])) {
            $partes = explode('|', (string)$_POST['eliminar_foto'], 2);
            produccion_eliminar_foto_item($pdo, $pedidoId, (int)($partes[0] ?? 0), (string)($partes[1] ?? ''));
            produccion_guardar_items_desde_post($pdo, $pedidoId, $_POST, $_FILES);
            header('Location: orden_produccion_crear.php?pedido_id=' . $pedidoId . '&ok=guardado');
            exit;
        }

        produccion_guardar_items_desde_post($pdo, $pedidoId, $_POST, $_FILES);

        if ($accion === 'guardar') {
            header('Location: orden_produccion_crear.php?pedido_id=' . $pedidoId . '&ok=guardado');
            exit;
        }

        $ordenId = produccion_crear_orden(
            $pdo,
            $pedidoId,
            (string)($_POST['notas'] ?? ''),
            trim((string)($_POST['fecha_entrega'] ?? '')) ?: null,
            (int)($_SESSION['user']['id'] ?? 0) ?: null
        );
        header('Location: orden_produccion_detalle.php?pedido_id=' . $pedidoId . '&ok=creada&orden_id=' . $ordenId);
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $items = produccion_cargar_items($pdo, $pedidoId);
    }
}

$ok = '';
if (isset($_GET['ok']) && (string)$_GET['ok'] === 'guardado') {
    $ok = 'Se guardaron las medidas, colores y fotos. Todavía falta crear la orden.';
}

$pendientes = $pedido ? [] : produccion_pedidos_sin_orden($pdo, $buscar);
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="mb-1">Nueva orden de producción</h1>
        <p class="text-muted mb-0">Elegí un pedido, corregí medidas o colores y subí las fotos que tienen que salir en el PDF.</p>
    </div>
    <a class="btn btn-outline-secondary" href="ordenes_produccion.php">← Órdenes</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>

<?php if (!$pedido): ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Buscar pedido o cliente</label>
                    <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($buscar) ?>" placeholder="Número de pedido o nombre">
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-primary" type="submit">Buscar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Pedidos sin orden de producción</div>
        <div class="card-body p-0">
            <?php if (empty($pendientes)): ?>
                <div class="p-4 text-muted">No hay pedidos pendientes de orden<?= $buscar !== '' ? ' para esa búsqueda' : '' ?>.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Pedido</th>
                                <th>Cliente</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendientes as $row): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars((string)$row['numero_pedido']) ?></strong></td>
                                    <td><?= htmlspecialchars((string)($row['cliente_nombre'] ?? '-')) ?></td>
                                    <td><?= htmlspecialchars((string)($row['estado'] ?? '')) ?></td>
                                    <td><?= !empty($row['fecha_creacion']) ? date('d/m/Y', strtotime((string)$row['fecha_creacion'])) : '-' ?></td>
                                    <td class="text-end">
                                        <a class="btn btn-sm btn-primary" href="orden_produccion_crear.php?pedido_id=<?= (int)$row['id'] ?>">Cargar orden</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-3">
        <div class="card-body">
            <strong><?= htmlspecialchars((string)$pedido['numero_pedido']) ?></strong>
            · <?= htmlspecialchars((string)($pedido['cliente_nombre'] ?? 'Sin cliente')) ?>
            <?php if (!empty($pedido['telefono'])): ?>
                · <?= htmlspecialchars((string)$pedido['telefono']) ?>
            <?php endif; ?>
            <a class="ms-2" href="pedidos_detalle.php?pedido_id=<?= $pedidoId ?>">Ver pedido</a>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="alert alert-warning">Este pedido no tiene ítems para fabricar.</div>
    <?php else: ?>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <input type="hidden" name="pedido_id" value="<?= $pedidoId ?>">
            <input type="hidden" name="accion" id="accion_op" value="crear">

            <?php produccion_render_editor_items($items); ?>

            <div class="card mb-3">
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Fecha de entrega</label>
                        <input type="date" class="form-control" name="fecha_entrega" value="<?= htmlspecialchars((string)($_POST['fecha_entrega'] ?? '')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Notas de la orden</label>
                        <input type="text" class="form-control" name="notas" value="<?= htmlspecialchars((string)($_POST['notas'] ?? '')) ?>" placeholder="Instrucciones para el taller">
                    </div>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 mb-4">
                <button type="submit" class="btn btn-success" onclick="document.getElementById('accion_op').value='crear'">Crear orden de producción</button>
                <button type="submit" class="btn btn-outline-primary" onclick="document.getElementById('accion_op').value='guardar'">Guardar cambios sin crear</button>
            </div>
        </form>
    <?php endif; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
