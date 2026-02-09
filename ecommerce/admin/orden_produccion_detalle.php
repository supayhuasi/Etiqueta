<?php
require 'includes/header.php';
require '../includes/funciones_recetas.php';

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

$pedido_id = $_GET['pedido_id'] ?? 0;
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cancelar') {
    try {
        $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET estado = 'cancelado' WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        header("Location: ordenes_produccion.php?mensaje=cancelada");
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Descontar materiales manualmente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'descontar_materiales') {
    try {
        $pdo->beginTransaction();
        
        // Obtener orden
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        $orden_actual = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$orden_actual) {
            throw new Exception('Orden de producción no encontrada');
        }
        
        if ($orden_actual['materiales_descontados']) {
            throw new Exception('Los materiales ya fueron descontados');
        }
        
        // Obtener items del pedido
        $stmt_items = $pdo->prepare("
            SELECT pi.*, p.usa_receta
            FROM ecommerce_pedido_items pi
            JOIN ecommerce_productos p ON pi.producto_id = p.id
            WHERE pi.pedido_id = ?
        ");
        $stmt_items->execute([$pedido_id]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $materiales_descontados = 0;
        
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
                    'referencia' => 'Orden-' . $orden_actual['id'],
                    'usuario_id' => $_SESSION['user']['id'] ?? null,
                    'pedido_id' => $pedido_id,
                    'orden_produccion_id' => $orden_actual['id']
                ]);
                
                $materiales_descontados++;
            }
        }
        
        // Marcar materiales como descontados
        $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET materiales_descontados = 1 WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        
        $pdo->commit();
        $mensaje = "✓ Materiales descontados correctamente ($materiales_descontados items actualizados)";
        
        // Recargar la orden actualizada
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
        $stmt->execute([$pedido_id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
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
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

// Items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recetas por producto con evaluación de condiciones
$recetas_map = [];
if (!empty($items)) {
    foreach ($items as $item) {
        $producto_id = (int)$item['producto_id'];
        
        // Obtener atributos del item
        $atributos_seleccionados = [];
        if (!empty($item['atributos'])) {
            $atributos_seleccionados = json_decode($item['atributos'], true) ?: [];
        }
        
        // Obtener receta con condiciones evaluadas
        $ancho = floatval($item['ancho'] ?? 0);
        $alto = floatval($item['alto'] ?? 0);
        
        $recetas_map[$producto_id] = obtener_receta_con_condiciones(
            $pdo,
            $producto_id,
            $ancho,
            $alto,
            $atributos_seleccionados
        );
    }
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <a href="ordenes_produccion.php" class="btn btn-outline-secondary">← Volver a Órdenes</a>
        <a href="orden_produccion_imprimir.php?pedido_id=<?= $pedido_id ?>" class="btn btn-outline-primary" target="_blank">🖨️ Imprimir</a>
        <?php if ($orden && $orden['estado'] !== 'cancelado' && $orden['estado'] !== 'entregado'): ?>
            <form method="POST" style="display:inline;" onsubmit="return confirm('¿Cancelar esta orden de producción?');">
                <input type="hidden" name="accion" value="cancelar">
                <button type="submit" class="btn btn-danger">❌ Cancelar Orden</button>
            </form>
        <?php endif; ?>
        <h1 class="mt-3">🏭 Orden de Producción</h1>
        <p class="text-muted mb-0">Pedido: <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">Estado de Producción</h5>
            </div>
            <div class="card-body">
                <p><strong>Estado:</strong> <?= htmlspecialchars(str_replace('_', ' ', $orden['estado'] ?? 'pendiente')) ?></p>
                <p><strong>Entrega:</strong> <?= !empty($orden['fecha_entrega']) ? date('d/m/Y', strtotime($orden['fecha_entrega'])) : '-' ?></p>
                
                <?php if (isset($orden['materiales_descontados'])): ?>
                    <p><strong>Materiales:</strong> 
                        <?php if ($orden['materiales_descontados']): ?>
                            <span class="badge bg-success">✓ Descontados del inventario</span>
                        <?php else: ?>
                            <span class="badge bg-danger">⚠ Pendientes de descuento</span>
                            <br>
                            <form method="POST" class="mt-2" onsubmit="return confirm('¿Descontar los materiales del inventario ahora?');">
                                <input type="hidden" name="accion" value="descontar_materiales">
                                <button type="submit" class="btn btn-sm btn-warning">
                                    🔽 Descontar Materiales Ahora
                                </button>
                            </form>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
                
                <?php if (!empty($orden['notas'])): ?>
                    <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars($orden['notas'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Datos del Cliente</h5>
            </div>
            <div class="card-body">
                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['nombre']) ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['direccion'] ?? 'N/A') ?></p>
                <p><strong>Ciudad:</strong> <?= htmlspecialchars($pedido['ciudad'] ?? 'N/A') ?></p>
                <p><strong>Provincia:</strong> <?= htmlspecialchars($pedido['provincia'] ?? 'N/A') ?></p>
                <p><strong>Código Postal:</strong> <?= htmlspecialchars($pedido['codigo_postal'] ?? 'N/A') ?></p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h5>🧵 Productos a fabricar</h5>
    </div>
    <div class="table-responsive">
        <table class="table mb-0">
            <thead class="table-light">
                <tr>
                    <th>Producto</th>
                    <th>Medidas</th>
                    <th>Atributos</th>
                    <th>Cantidad</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): 
                    $atributos = !empty($item['atributos']) ? json_decode($item['atributos'], true) : [];
                    $alto_m = !empty($item['alto_cm']) ? ((float)$item['alto_cm'] / 100) : 0;
                    $ancho_m = !empty($item['ancho_cm']) ? ((float)$item['ancho_cm'] / 100) : 0;
                    $area_m2 = $alto_m * $ancho_m;
                    $recetas = $recetas_map[$item['producto_id']] ?? [];
                ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($item['producto_nombre'] ?? 'Producto eliminado') ?></strong></td>
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
                                    <?php foreach ($atributos as $attr): 
                                        $nombre = strtolower($attr['nombre'] ?? 'attr');
                                        $valor = $attr['valor'] ?? '';
                                        // Destacar el color de forma especial
                                        if (strpos($nombre, 'color') !== false): ?>
                                            <div>
                                                <strong><?= htmlspecialchars($attr['nombre']) ?>:</strong> 
                                                <span class="badge" style="background-color: <?= htmlspecialchars($valor) ?>; color: #fff; padding: 5px 10px;">
                                                    <?= htmlspecialchars($valor) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div><?= htmlspecialchars($attr['nombre']) ?>: <?= htmlspecialchars($valor) ?></div>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted">-</small>
                            <?php endif; ?>
                        </td>
                        <td><?= $item['cantidad'] ?></td>
                    </tr>
                    <?php if (!empty($recetas)): ?>
                        <tr class="table-light">
                            <td colspan="4">
                                <small class="text-muted"><strong>Receta:</strong></small>
                                <div class="table-responsive mt-2">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th>Consumo</th>
                                                <th>Detalle</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recetas as $r):
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
                                                $cantidad_total = $cantidad_total * (int)$item['cantidad'];
                                            ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($r['material_nombre']) ?></td>
                                                    <td><?= number_format($cantidad_total, 4, ',', '.') ?></td>
                                                    <td>
                                                        <?= htmlspecialchars($r['tipo_calculo']) ?>
                                                        <?= $merma > 0 ? ' + ' . $merma . '%' : '' ?>
                                                        <?php if ($orden['materiales_descontados'] ?? 0): ?>
                                                            <br>
                                                            <a href="inventario_movimientos.php?tipo=producto&id=<?= $r['material_producto_id'] ?>" 
                                                               class="btn btn-xs btn-outline-info btn-sm mt-1" 
                                                               target="_blank">
                                                                📊 Ver movimientos
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
