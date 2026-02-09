<?php
require 'includes/header.php';

function registrarMovimientoInventario(PDO $pdo, array $payload): void
{
    static $columnas = null;
    static $tipoEnum = null;
    if ($columnas === null) {
        $columnas = $pdo->query("SHOW COLUMNS FROM ecommerce_inventario_movimientos")->fetchAll(PDO::FETCH_COLUMN, 0);
        if (in_array('tipo', $columnas, true)) {
            $stmtTipo = $pdo->query("SHOW COLUMNS FROM ecommerce_inventario_movimientos LIKE 'tipo'");
            $col = $stmtTipo->fetch(PDO::FETCH_ASSOC);
            if (!empty($col['Type']) && stripos($col['Type'], 'enum(') === 0) {
                $raw = substr($col['Type'], 5, -1);
                $vals = array_map(function ($v) {
                    return trim($v, "' ");
                }, explode(',', $raw));
                $tipoEnum = array_filter($vals, fn($v) => $v !== '');
            }
        }
    }

    $cols = array_flip($columnas);
    $campos = [];
    $valores = [];

    if (isset($cols['producto_id'])) {
        $tipoRaw = $payload['tipo'] ?? null;
        $tipo = $tipoRaw;
        if ($tipoRaw) {
            $tipoRaw = strtolower((string)$tipoRaw);
            if ($tipoRaw === 'produccion' || $tipoRaw === 'venta') {
                $tipo = 'salida';
            } elseif ($tipoRaw === 'compra') {
                $tipo = 'entrada';
            } else {
                $tipo = $tipoRaw;
            }
        }

        if (is_array($tipoEnum) && $tipoEnum) {
            if (!in_array($tipo, $tipoEnum, true)) {
                if (in_array('ajuste', $tipoEnum, true)) {
                    $tipo = 'ajuste';
                } else {
                    $tipo = $tipoEnum[0];
                }
            }
        }

        $campos = ['producto_id', 'tipo', 'cantidad', 'referencia'];
        $valores = [
            $payload['producto_id'] ?? null,
            $tipo,
            $payload['cantidad'] ?? 0,
            $payload['referencia'] ?? null,
        ];

        if (isset($cols['usuario_id'])) {
            $campos[] = 'usuario_id';
            $valores[] = $payload['usuario_id'] ?? null;
        }

        if (isset($cols['stock_anterior'])) {
            $campos[] = 'stock_anterior';
            $valores[] = $payload['stock_anterior'] ?? null;
        }
        if (isset($cols['stock_nuevo'])) {
            $campos[] = 'stock_nuevo';
            $valores[] = $payload['stock_nuevo'] ?? null;
        }
        if (isset($cols['pedido_id'])) {
            $campos[] = 'pedido_id';
            $valores[] = $payload['pedido_id'] ?? null;
        }
        if (isset($cols['orden_produccion_id'])) {
            $campos[] = 'orden_produccion_id';
            $valores[] = $payload['orden_produccion_id'] ?? null;
        }
    } else {
        $campos = ['tipo_item', 'item_id', 'tipo_movimiento', 'cantidad', 'referencia'];
        $valores = [
            $payload['tipo_item'] ?? 'producto',
            $payload['item_id'] ?? null,
            $payload['tipo_movimiento'] ?? null,
            $payload['cantidad'] ?? 0,
            $payload['referencia'] ?? null,
        ];

        if (isset($cols['usuario_id'])) {
            $campos[] = 'usuario_id';
            $valores[] = $payload['usuario_id'] ?? null;
        }

        if (isset($cols['stock_anterior'])) {
            $campos[] = 'stock_anterior';
            $valores[] = $payload['stock_anterior'] ?? null;
        }
        if (isset($cols['stock_nuevo'])) {
            $campos[] = 'stock_nuevo';
            $valores[] = $payload['stock_nuevo'] ?? null;
        }
        if (isset($cols['pedido_id'])) {
            $campos[] = 'pedido_id';
            $valores[] = $payload['pedido_id'] ?? null;
        }
        if (isset($cols['orden_produccion_id'])) {
            $campos[] = 'orden_produccion_id';
            $valores[] = $payload['orden_produccion_id'] ?? null;
        }
    }

    $placeholders = implode(', ', array_fill(0, count($campos), '?'));
    $sql = "INSERT INTO ecommerce_inventario_movimientos (" . implode(', ', $campos) . ") VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($valores);
}

$pedido_id = $_GET['id'] ?? 0;

// Obtener pedido
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.email, c.telefono, c.direccion as dir_cliente, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

if (empty($pedido['public_token'])) {
    $nuevo_token = bin2hex(random_bytes(16));
    try {
        $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET public_token = ? WHERE id = ?");
        $stmt->execute([$nuevo_token, $pedido_id]);
        $pedido['public_token'] = $nuevo_token;
    } catch (Exception $e) {
        // Si falla, continuar sin token
    }
}

// Si no hay dirección del cliente, usar la del pedido
if (empty($pedido['dir_cliente'])) {
    $pedido['dir_cliente'] = $pedido['direccion'] ?? 'N/A';
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

$estados_pagados = ['pagado', 'pago_autorizado', 'confirmado', 'esperando_envio', 'preparando', 'enviado', 'entregado'];
if ($total_pagado <= 0 && in_array($pedido['estado'], $estados_pagados, true)) {
    $total_pagado = (float)$pedido['total'];
}

$saldo = (float)$pedido['total'] - $total_pagado;

$error = '';

// Procesar acciones de producción y pagos ANTES de incluir header.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear_orden' && !$orden_produccion) {
            $pdo->beginTransaction();
            
            $fecha_entrega = !empty($_POST['fecha_entrega']) ? $_POST['fecha_entrega'] : null;
            
            // Crear orden de producción
            $stmt = $pdo->prepare("INSERT INTO ecommerce_ordenes_produccion (pedido_id, estado, notas, fecha_entrega, materiales_descontados) VALUES (?, 'pendiente', ?, ?, 0)");
            $stmt->execute([$pedido_id, $_POST['notas'] ?? null, $fecha_entrega]);
            $orden_id = $pdo->lastInsertId();
            
            // Descontar materiales inmediatamente
            require '../includes/funciones_recetas.php';
            
            $stmt_items = $pdo->prepare("
                SELECT pi.*, p.usa_receta
                FROM ecommerce_pedido_items pi
                JOIN ecommerce_productos p ON pi.producto_id = p.id
                WHERE pi.pedido_id = ?
            ");
            $stmt_items->execute([$pedido_id]);
            $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $it) {
                if (empty($it['usa_receta'])) continue;
                
                $atributos_seleccionados = [];
                if (!empty($it['atributos'])) {
                    $atributos_seleccionados = json_decode($it['atributos'], true) ?: [];
                }
                
                $producto_id = (int)$it['producto_id'];
                $ancho_cm = floatval($it['ancho_cm'] ?? 0);
                $alto_cm = floatval($it['alto_cm'] ?? 0);
                
                $recetas = obtener_receta_con_condiciones($pdo, $producto_id, $ancho_cm, $alto_cm, $atributos_seleccionados);
                if (empty($recetas)) continue;
                
                $alto_m = $alto_cm / 100;
                $ancho_m = $ancho_cm / 100;
                $area_m2 = $alto_m * $ancho_m;
                
                foreach ($recetas as $r) {
                    $factor = (float)$r['factor'];
                    $merma = (float)$r['merma_pct'];
                    $cantidad_base = 0;
                    
                    if ($r['tipo_calculo'] === 'fijo') {
                        $cantidad_base = $factor;
                    } elseif ($r['tipo_calculo'] === 'por_area') {
                        $cantidad_base = $area_m2 * $factor;
                    } elseif ($r['tipo_calculo'] === 'por_ancho') {
                        $cantidad_base = $ancho_m * $factor;
                    } elseif ($r['tipo_calculo'] === 'por_alto') {
                        $cantidad_base = $alto_m * $factor;
                    }
                    
                    $cantidad_total = $cantidad_base * (1 + ($merma / 100));
                    $cantidad_total = $cantidad_total * (int)$it['cantidad'];
                    $mat_id = (int)$r['material_producto_id'];
                    
                    // Obtener stock actual del producto
                    $stmt_stock = $pdo->prepare("SELECT stock FROM ecommerce_productos WHERE id = ?");
                    $stmt_stock->execute([$mat_id]);
                    $stock_anterior = (float)($stmt_stock->fetchColumn() ?: 0);
                    $stock_nuevo = $stock_anterior - $cantidad_total;
                    
                    // Descontar stock
                    $stmt_update = $pdo->prepare("UPDATE ecommerce_productos SET stock = stock - ? WHERE id = ?");
                    $stmt_update->execute([$cantidad_total, $mat_id]);
                    
                    // Registrar movimiento (compatible con distintos esquemas)
                    registrarMovimientoInventario($pdo, [
                        'producto_id' => $mat_id,
                        'tipo' => 'produccion',
                        'tipo_item' => 'producto',
                        'item_id' => $mat_id,
                        'tipo_movimiento' => 'produccion',
                        'cantidad' => $cantidad_total,
                        'stock_anterior' => $stock_anterior,
                        'stock_nuevo' => $stock_nuevo,
                        'referencia' => 'Orden-' . $orden_id,
                        'usuario_id' => $_SESSION['user']['id'] ?? null,
                        'pedido_id' => $pedido_id,
                        'orden_produccion_id' => $orden_id
                    ]);
                }
            }
            
            // Marcar materiales como descontados
            $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET materiales_descontados = 1 WHERE id = ?");
            $stmt->execute([$orden_id]);
            
            $pdo->commit();
            
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

            $pago_id = $pdo->lastInsertId();

            // Registrar ingreso en flujo de caja
            try {
                $stmt_fc_check = $pdo->prepare("
                    SELECT id FROM flujo_caja
                    WHERE id_referencia = ? AND categoria = 'Pago Pedido'
                    LIMIT 1
                ");
                $stmt_fc_check->execute([$pago_id]);
                $fc_existe = $stmt_fc_check->fetch(PDO::FETCH_ASSOC);

                if (!$fc_existe) {
                    $descripcion_fc = 'Pago pedido ' . $pedido['numero_pedido'] . ' (' . $metodo . ')';
                    $referencia_fc = $referencia ?: $pedido['numero_pedido'];

                    $stmt_fc = $pdo->prepare("
                        INSERT INTO flujo_caja
                        (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                        VALUES (?, 'ingreso', 'Pago Pedido', ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt_fc->execute([
                        date('Y-m-d'),
                        $descripcion_fc,
                        $monto,
                        $referencia_fc,
                        $pago_id,
                        $_SESSION['user']['id'] ?? null,
                        $notas ?: 'Registrado desde pedido'
                    ]);
                }
            } catch (Exception $e) {
                // Si falla el flujo de caja, no afecta el registro del pago
                $_SESSION['flujo_error_pago'] = $e->getMessage();
            }

            header("Location: pedidos_detalle.php?id=" . $pedido_id);
            exit;
        } elseif ($accion === 'cancelar_pedido') {
            if ($pedido['estado'] === 'cancelado') {
                throw new Exception('El pedido ya está cancelado');
            }
            $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'cancelado' WHERE id = ?");
            $stmt->execute([$pedido_id]);
            header("Location: pedidos_detalle.php?id=" . $pedido_id);
            exit;
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error al procesar la acción: " . $e->getMessage();
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
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['dir_cliente'] ?? 'N/A') ?></p>
                <p><strong>Ciudad:</strong> <?= htmlspecialchars($pedido['ciudad'] ?? 'N/A') ?></p>
                <p><strong>Provincia:</strong> <?= htmlspecialchars($pedido['provincia'] ?? 'N/A') ?></p>
                <p><strong>Código Postal:</strong> <?= htmlspecialchars($pedido['codigo_postal'] ?? 'N/A') ?></p>
            </div>
        </div>
    </div>

    <!-- Datos del pedido -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">📋 Datos del Pedido</h5>
                <?php if ($pedido['estado'] !== 'cancelado'): ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Cancelar este pedido?')">
                        <input type="hidden" name="accion" value="cancelar_pedido">
                        <button type="submit" class="btn btn-sm btn-danger">Cancelar Pedido</button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-danger">Cancelado</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <p><strong>Número:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                <p><strong>Fecha:</strong> <?= date('d/m/Y H:i:s', strtotime($pedido['fecha_creacion'])) ?></p>
                <p><strong>Método de Pago:</strong> <span class="badge bg-info"><?= htmlspecialchars($pedido['metodo_pago']) ?></span></p>
                <p><strong>Total:</strong> <span class="text-success fw-bold">$<?= number_format($pedido['total'], 2, ',', '.') ?></span></p>
                <?php if (!empty($pedido['public_token'])): ?>
                    <p><strong>Link público:</strong>
                        <a href="../pedido_publico.php?token=<?= urlencode($pedido['public_token']) ?>" target="_blank" rel="noopener">
                            Ver estado del pedido
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (!empty($_SESSION['flujo_error_pago'])): ?>
    <div class="alert alert-warning">
        El pago se registró, pero no se pudo impactar en flujo de caja: <?= htmlspecialchars($_SESSION['flujo_error_pago']) ?>
    </div>
    <?php unset($_SESSION['flujo_error_pago']); ?>
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
            
            <!-- Información de descuento de materiales -->
            <?php if ($orden_produccion['materiales_descontados'] ?? 0): ?>
                <?php
                // Obtener movimientos de inventario relacionados con esta orden
                $stmt_mov = $pdo->prepare("
                    SELECT m.*, p.nombre as item_nombre
                    FROM ecommerce_inventario_movimientos m
                    LEFT JOIN ecommerce_productos p ON m.producto_id = p.id
                    WHERE m.referencia LIKE ?
                    ORDER BY m.fecha_creacion DESC
                ");
                $stmt_mov->execute(['%Orden-' . $orden_produccion['id'] . '%']);
                $movimientos = $stmt_mov->fetchAll(PDO::FETCH_ASSOC);
                ?>
                
                <hr class="my-3">
                
                <div class="alert alert-success">
                    <strong>✓ Materiales descontados del inventario</strong>
                    <br>
                    <small>Los materiales necesarios fueron descontados automáticamente al crear la orden de producción.</small>
                </div>
                
                <?php if (!empty($movimientos)): ?>
                    <h6 class="mt-3">Movimientos de Inventario:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Material/Producto</th>
                                    <th>Tipo</th>
                                    <th>Cantidad</th>
                                    <th>Stock Anterior</th>
                                    <th>Stock Nuevo</th>
                                    <th>Fecha</th>
                                    <th>Ver Historial</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movimientos as $mov): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mov['item_nombre'] ?? 'Desconocido') ?></td>
                                        <td>
                                            <span class="badge bg-success">Producto</span>
                                        </td>
                                        <td class="text-danger"><strong>-<?= number_format($mov['cantidad'], 2) ?></strong></td>
                                        <td><?= number_format($mov['stock_anterior'] ?? 0, 2) ?></td>
                                        <td class="<?= ($mov['stock_nuevo'] ?? 0) < 0 ? 'text-danger' : '' ?>">
                                            <?= number_format($mov['stock_nuevo'] ?? 0, 2) ?>
                                            <?= ($mov['stock_nuevo'] ?? 0) < 0 ? ' ⚠️' : '' ?>
                                        </td>
                                        <td><small><?= date('d/m/Y H:i', strtotime($mov['fecha_creacion'])) ?></small></td>
                                        <td>
                                            <a href="inventario_movimientos.php?tipo=producto&id=<?= $mov['producto_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary" 
                                               target="_blank">
                                                📊 Ver
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <hr class="my-3">
                <div class="alert alert-warning">
                    <strong>⚠️ Los materiales aún no han sido descontados</strong>
                    <br>
                    <small>Los materiales se descuentan automáticamente al crear la orden de producción.</small>
                </div>
            <?php endif; ?>
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
                                <small><?= $item['ancho_cm'] ?>cm × <?= $item['alto_cm'] ?>cm</small>
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
