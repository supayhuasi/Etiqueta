<?php
require 'includes/header.php';
require '../includes/funciones_recetas.php';

$estados = ['pendiente', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado', 'pagado'];
$colores = [
    'pendiente' => 'warning',
    'confirmado' => 'info',
    'preparando' => 'primary',
    'enviado' => 'secondary',
    'entregado' => 'success',
    'cancelado' => 'danger',
    'pagado' => 'success'
];

// Procesar cambio de estado ANTES de consultar pedidos
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'cambiar_estado') {
    try {
        $pedido_id = intval($_POST['pedido_id']);
        $nuevo_estado = $_POST['nuevo_estado'];
        
        if (!in_array($nuevo_estado, $estados)) die("Estado inválido");
        
        $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $pedido_id]);

        if ($nuevo_estado === 'confirmado') {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            $orden = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$orden) {
                $stmt = $pdo->prepare("INSERT INTO ecommerce_ordenes_produccion (pedido_id, estado, materiales_descontados) VALUES (?, 'pendiente', 0)");
                $stmt->execute([$pedido_id]);
                $orden_id = $pdo->lastInsertId();
                $orden = ['id' => $orden_id, 'materiales_descontados' => 0];
            }

            if (empty($orden['materiales_descontados'])) {
                // Descontar materiales según receta
                $stmt = $pdo->prepare("
                    SELECT pi.*, p.usa_receta
                    FROM ecommerce_pedido_items pi
                    JOIN ecommerce_productos p ON pi.producto_id = p.id
                    WHERE pi.pedido_id = ?
                ");
                $stmt->execute([$pedido_id]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $materiales = [];
                $materiales_color = [];
                $color_cache = [];
                foreach ($items as $it) {
                    if (empty($it['usa_receta'])) {
                        continue;
                    }
                    
                    // Obtener atributos del item
                    $atributos_seleccionados = [];
                    if (!empty($it['atributos'])) {
                        $atributos_seleccionados = json_decode($it['atributos'], true) ?: [];
                    }
                    
                    $producto_id = (int)$it['producto_id'];
                    $ancho_cm = floatval($it['ancho_cm'] ?? 0);
                    $alto_cm = floatval($it['alto_cm'] ?? 0);

                    // Buscar atributo de color seleccionado (si existe)
                    $color_val = null;
                    if (is_array($atributos_seleccionados)) {
                        foreach ($atributos_seleccionados as $attr) {
                            if (!empty($attr['nombre']) && stripos($attr['nombre'], 'color') !== false) {
                                $color_val = $attr['valor'] ?? null;
                                break;
                            }
                        }
                    }
                    
                    // Obtener receta con condiciones evaluadas
                    $recetas = obtener_receta_con_condiciones(
                        $pdo,
                        $producto_id,
                        $ancho_cm,
                        $alto_cm,
                        $atributos_seleccionados
                    );
                    
                    if (empty($recetas)) {
                        continue;
                    }

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

                        // Si hay color seleccionado, intentar descontar stock por opción de color del material
                        $opcion_id = null;
                        if (!empty($color_val)) {
                            $cache_key = $mat_id . '|' . strtolower(trim($color_val));
                            if (array_key_exists($cache_key, $color_cache)) {
                                $opcion_id = $color_cache[$cache_key];
                            } else {
                                $stmtOpt = $pdo->prepare("
                                    SELECT o.id
                                    FROM ecommerce_atributo_opciones o
                                    JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
                                    WHERE a.producto_id = ?
                                      AND a.tipo = 'select'
                                      AND LOWER(a.nombre) LIKE '%color%'
                                      AND (LOWER(o.nombre) = LOWER(?) OR o.color = ?)
                                    LIMIT 1
                                ");
                                $stmtOpt->execute([$mat_id, $color_val, $color_val]);
                                $opcion_id = $stmtOpt->fetchColumn() ?: null;
                                $color_cache[$cache_key] = $opcion_id;
                            }
                        }

                        if (!empty($opcion_id)) {
                            if (!isset($materiales_color[$mat_id])) {
                                $materiales_color[$mat_id] = [];
                            }
                            if (!isset($materiales_color[$mat_id][$opcion_id])) {
                                $materiales_color[$mat_id][$opcion_id] = 0;
                            }
                            $materiales_color[$mat_id][$opcion_id] += $cantidad_total;
                        } else {
                            if (!isset($materiales[$mat_id])) {
                                $materiales[$mat_id] = 0;
                            }
                            $materiales[$mat_id] += $cantidad_total;
                        }
                    }
                }

                foreach ($materiales_color as $mat_id => $opciones) {
                    foreach ($opciones as $opcion_id => $qty) {
                        $stmt = $pdo->prepare("UPDATE ecommerce_atributo_opciones SET stock = stock - ? WHERE id = ?");
                        $stmt->execute([$qty, $opcion_id]);
                    }
                }

                foreach ($materiales as $mat_id => $qty) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_productos SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$qty, $mat_id]);
                }

                $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET materiales_descontados = 1 WHERE id = ?");
                $stmt->execute([$orden['id']]);
            }
        }
        
        // Recargar
        header("Location: pedidos.php");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Consultar pedidos DESPUÉS de procesar POST
$estado_filter = $_GET['estado'] ?? '';
$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';

$query = "
    SELECT p.*, c.nombre as cliente_nombre, c.email as cliente_email,
           COALESCE(pp.total_pagado, 0) AS total_pagado
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    LEFT JOIN (
        SELECT pedido_id, SUM(monto) AS total_pagado
        FROM ecommerce_pedido_pagos
        GROUP BY pedido_id
    ) pp ON pp.pedido_id = p.id
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

// Marcar pedidos como pagados si corresponde
foreach ($pedidos as &$pedido) {
    $total_pagado = (float)($pedido['total_pagado'] ?? 0);
    $total_pedido = (float)($pedido['total'] ?? 0);
    if ($total_pedido > 0 && $total_pagado >= $total_pedido && $pedido['estado'] !== 'pagado') {
        $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'pagado' WHERE id = ?");
        $stmt->execute([$pedido['id']]);
        $pedido['estado'] = 'pagado';
    }
}
unset($pedido);
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
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>Número</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>Importe</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $pedido): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($pedido['numero_pedido']) ?></strong></td>
                        <td>
                            <div><?= htmlspecialchars($pedido['cliente_nombre'] ?? 'Sin cliente') ?></div>
                            <?php if (!empty($pedido['cliente_email'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($pedido['cliente_email']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                        <td class="fw-semibold">$<?= number_format($pedido['total'], 2, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-<?= $colores[$pedido['estado']] ?>">
                                <?= ucfirst($pedido['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="pedidos_detalle.php?id=<?= $pedido['id'] ?>">Ver detalle</a>
                            <a class="btn btn-sm btn-outline-success" href="pedidos_detalle.php?id=<?= $pedido['id'] ?>#pagos">Pagos</a>
                            <a class="btn btn-sm btn-outline-dark" href="pedido_imprimir.php?id=<?= $pedido['id'] ?>" target="_blank">Imprimir</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
