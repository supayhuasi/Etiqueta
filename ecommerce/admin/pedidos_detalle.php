<?php
require 'includes/header.php';

$pedido_id = $_GET['id'] ?? 0;

// Obtener pedido
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.email, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

// Orden de producción
$stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$orden_produccion = $stmt->fetch(PDO::FETCH_ASSOC);

// Pagos del pedido
$stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_pagos WHERE pedido_id = ? ORDER BY fecha_pago DESC");
$stmt->execute([$pedido_id]);
$pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT SUM(monto) AS total_pagado FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
$stmt->execute([$pedido_id]);
$total_pagado = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);
$saldo = (float)$pedido['total'] - $total_pagado;

// Procesar acciones de producción y pagos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear_orden' && !$orden_produccion) {
            $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
            $stmt = $pdo->prepare("INSERT INTO ecommerce_ordenes_produccion (pedido_id, estado, notas, fecha_entrega) VALUES (?, 'pendiente', ?, ?)");
            $stmt->execute([$pedido_id, $_POST['notas'] ?? null, $fecha_entrega]);
            header("Location: pedidos_detalle.php?id=" . $pedido_id);
            exit;
        } elseif ($accion === 'actualizar_orden' && $orden_produccion) {
            $estado = $_POST['estado'] ?? 'pendiente';
            $notas = $_POST['notas'] ?? null;
            $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
            $estados_validos = ['pendiente','en_produccion','terminado','entregado'];
            if (!in_array($estado, $estados_validos, true)) {
                throw new Exception('Estado inválido');
            }
            $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET estado = ?, notas = ?, fecha_entrega = ? WHERE id = ?");
            $stmt->execute([$estado, $notas, $fecha_entrega, $orden_produccion['id']]);
            header("Location: pedidos_detalle.php?id=" . $pedido_id);
            exit;
        } elseif ($accion === 'registrar_pago') {
            $monto = (float)($_POST['monto'] ?? 0);
            $metodo = trim($_POST['metodo'] ?? '');
            $referencia = trim($_POST['referencia'] ?? '');
            $notas = trim($_POST['notas'] ?? '');

            if ($monto <= 0) {
                throw new Exception('El monto debe ser mayor a 0');
            }
            if ($monto > $saldo) {
                throw new Exception('El monto excede el saldo');
            }
            if ($metodo === '') {
                throw new Exception('El método de pago es obligatorio');
            }

            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_pedido_pagos (pedido_id, monto, metodo, referencia, notas, creado_por)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $pedido_id,
                $monto,
                $metodo,
                $referencia ?: null,
                $notas ?: null,
                $_SESSION['user']['id'] ?? null
            ]);

            header("Location: pedidos_detalle.php?id=" . $pedido_id);
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Obtener items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre, pr.imagen
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="pedidos.php" class="btn btn-outline-secondary">← Volver a Pedidos</a>
        <h1 class="mt-3">📦 Pedido: <?= htmlspecialchars($pedido['numero_pedido']) ?></h1>
    </div>
</div>

<div class="row">
    <!-- Datos del cliente -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5>👤 Datos del Cliente</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['nombre']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($pedido['email']) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? 'N/A') ?></p>
                <p><strong>Ciudad:</strong> <?= htmlspecialchars($pedido['ciudad'] ?? 'N/A') ?></p>
                <p><strong>Provincia:</strong> <?= htmlspecialchars($pedido['provincia'] ?? 'N/A') ?></p>
                <p><strong>Código Postal:</strong> <?= htmlspecialchars($pedido['codigo_postal'] ?? 'N/A') ?></p>
            </div>
        </div>
    </div>

    <!-- Datos del pedido -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5>📋 Datos del Pedido</h5>
            </div>
            <div class="card-body">
                <p><strong>Número:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i:s', strtotime($pedido['fecha_creacion'])) ?></p>
                <p><strong>Método de Pago:</strong> <span class="badge bg-info"><?= htmlspecialchars($pedido['metodo_pago']) ?></span></p>
                <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format($pedido['total'], 2, ',', '.') ?></span></p>
            </div>
        </div>
    </div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4" id="pagos">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">💳 Pagos y Saldo</h5>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-4"><strong>Total:</strong> $<?= number_format($pedido['total'], 2, ',', '.') ?></div>
            <div class="col-md-4"><strong>Pagado:</strong> $<?= number_format($total_pagado, 2, ',', '.') ?></div>
            <div class="col-md-4"><strong>Saldo:</strong> $<?= number_format($saldo, 2, ',', '.') ?></div>
        </div>

        <form method="POST" class="row g-3">
            <input type="hidden" name="accion" value="registrar_pago">
            <div class="col-md-3">
                <label class="form-label">Monto</label>
                <input type="number" step="0.01" min="0" class="form-control" name="monto" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Método</label>
                <input type="text" class="form-control" name="metodo" placeholder="Efectivo, Transferencia..." required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Referencia</label>
                <input type="text" class="form-control" name="referencia">
            </div>
            <div class="col-md-3">
                <label class="form-label">Notas</label>
                <input type="text" class="form-control" name="notas">
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary">Registrar Pago</button>
            </div>
        </form>

        <hr>

        <?php if (empty($pagos)): ?>
            <div class="alert alert-info">No hay pagos registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pagos as $pago): ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                <td>$<?= number_format($pago['monto'], 2, ',', '.') ?></td>
                                <td><?= htmlspecialchars($pago['metodo']) ?></td>
                                <td><?= htmlspecialchars($pago['referencia'] ?? '-') ?></td>
                                <td>
                                    <a href="pedido_pago_recibo.php?pago_id=<?= $pago['id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">Recibo</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">🏭 Orden de Producción</h5>
    </div>
    <div class="card-body">
        <?php if (!$orden_produccion): ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="accion" value="crear_orden">
                <div class="col-md-4">
                    <label class="form-label">Fecha de entrega</label>
                    <input type="date" class="form-control" name="fecha_entrega">
                </div>
                <div class="col-md-12">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notas" rows="3" placeholder="Instrucciones de producción..."></textarea>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-success">Crear Orden de Producción</button>
                </div>
            </form>
        <?php else: ?>
            <form method="POST" class="row g-3">
                <input type="hidden" name="accion" value="actualizar_orden">
                <div class="col-md-4">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="pendiente" <?= $orden_produccion['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="en_produccion" <?= $orden_produccion['estado'] === 'en_produccion' ? 'selected' : '' ?>>En producción</option>
                        <option value="terminado" <?= $orden_produccion['estado'] === 'terminado' ? 'selected' : '' ?>>Terminado</option>
                        <option value="entregado" <?= $orden_produccion['estado'] === 'entregado' ? 'selected' : '' ?>>Entregado</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Fecha de entrega</label>
                    <input type="date" class="form-control" name="fecha_entrega" value="<?= htmlspecialchars($orden_produccion['fecha_entrega'] ?? '') ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label">Notas</label>
                    <textarea class="form-control" name="notas" rows="3"><?= htmlspecialchars($orden_produccion['notas'] ?? '') ?></textarea>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Actualizar Orden</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Items del pedido -->
<div class="card">
    <div class="card-header bg-light">
        <h5>🛒 Items del Pedido</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th>Atributos</th>
                    <th>Precio Unitario</th>
                    <th>Cantidad</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $atributos = !empty($item['atributos']) ? json_decode($item['atributos'], true) : [];
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong>
                        </td>
                        <td>
                            <?php if ($item['alto_cm'] && $item['ancho_cm']): ?>
                                <small><?= $item['alto_cm'] ?>cm × <?= $item['ancho_cm'] ?>cm</small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (is_array($atributos) && count($atributos) > 0): ?>
                                <small>
                                    <?php foreach ($atributos as $attr): ?>
                                        <div><?= htmlspecialchars($attr['nombre'] ?? 'Attr') ?>: <?= htmlspecialchars($attr['valor'] ?? '') ?>
                                            <?php if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0): ?>
                                                <span class="badge bg-success">+$<?= number_format($attr['costo_adicional'], 2, ',', '.') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td>$<?= number_format($item['precio_unitario'], 2, ',', '.') ?></td>
                        <td><?= $item['cantidad'] ?></td>
                        <td><strong>$<?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
