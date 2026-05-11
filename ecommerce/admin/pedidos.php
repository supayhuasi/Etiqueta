<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/pedidos_error.log');

set_exception_handler(function($e) {
    error_log('Excepción no capturada en pedidos.php: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
    http_response_code(500);
    echo 'Ocurrió un error interno. Revise el log de errores.';
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP [$errno] $errstr en $errfile:$errline");
    http_response_code(500);
    echo 'Ocurrió un error interno. Revise el log de errores.';
    exit;
});

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        error_log("Error fatal: {$error['message']} en {$error['file']}:{$error['line']}");
        http_response_code(500);
        echo 'Ocurrió un error fatal. Revise el log de errores.';
        exit;
    }
});

require_once __DIR__ . '/includes/header.php';

if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    die('Error: No se pudo establecer la conexión a la base de datos.');
}
require '../includes/funciones_recetas.php';
require_once __DIR__ . '/includes/calidad_helper.php';

$estados = ['pendiente', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado', 'pagado'];
$colores = [
    'pendiente'  => 'warning',
    'confirmado' => 'info',
    'preparando' => 'primary',
    'enviado'    => 'secondary',
    'entregado'  => 'success',
    'cancelado'  => 'danger',
    'pagado'     => 'success',
];

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pedido_id'], $_POST['nuevo_estado'])) {
    $pedido_id    = (int)$_POST['pedido_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    if (!in_array($nuevo_estado, $estados)) {
        $error = 'Estado inválido.';
    } else {
        try {
            $pdo->prepare("UPDATE ecommerce_pedidos SET estado = ? WHERE id = ?")->execute([$nuevo_estado, $pedido_id]);

            if ($nuevo_estado === 'confirmado') {
                $stmtOrden = $pdo->prepare("SELECT id, materiales_descontados FROM ecommerce_ordenes_produccion WHERE pedido_id = ?");
                $stmtOrden->execute([$pedido_id]);
                $orden = $stmtOrden->fetch(PDO::FETCH_ASSOC);
                if (!$orden) {
                    $stmtIns = $pdo->prepare("INSERT INTO ecommerce_ordenes_produccion (pedido_id, estado, materiales_descontados) VALUES (?, 'pendiente', 0)");
                    $stmtIns->execute([$pedido_id]);
                    $orden = ['id' => $pdo->lastInsertId(), 'materiales_descontados' => 0];
                }

                if (empty($orden['materiales_descontados'])) {
                    $stmtIt = $pdo->prepare("
                        SELECT pi.*, p.usa_receta
                        FROM ecommerce_pedido_items pi
                        JOIN ecommerce_productos p ON pi.producto_id = p.id
                        WHERE pi.pedido_id = ?
                    ");
                    $stmtIt->execute([$pedido_id]);
                    $items = $stmtIt->fetchAll(PDO::FETCH_ASSOC);

                    $materiales       = [];
                    $materiales_color = [];
                    $color_cache      = [];

                    foreach ($items as $it) {
                        if (empty($it['usa_receta'])) continue;
                        $producto_id = (int)$it['producto_id'];
                        $ancho_cm    = (float)($it['ancho_cm'] ?? 0);
                        $alto_cm     = (float)($it['alto_cm'] ?? 0);
                        $atributos_seleccionados = [];
                        if (!empty($it['atributos'])) {
                            $arr = json_decode((string)$it['atributos'], true);
                            if (is_array($arr)) $atributos_seleccionados = $arr;
                        }
                        $color_val = null;
                        foreach ($atributos_seleccionados as $attr) {
                            $nombre_attr = strtolower(trim((string)($attr['nombre'] ?? '')));
                            $valor_attr  = trim((string)($attr['valor'] ?? ''));
                            if (stripos($nombre_attr, 'color') !== false && $valor_attr !== '') {
                                $color_val = $valor_attr;
                                break;
                            }
                        }

                        $recetas = obtener_receta_con_condiciones($pdo, $producto_id, $ancho_cm, $alto_cm, $atributos_seleccionados);
                        if (empty($recetas)) continue;

                        $alto_m  = $alto_cm / 100;
                        $ancho_m = $ancho_cm / 100;
                        $area_m2 = $alto_m * $ancho_m;

                        foreach ($recetas as $r) {
                            $factor        = (float)$r['factor'];
                            $merma         = (float)$r['merma_pct'];
                            $cantidad_base = 0;
                            if ($r['tipo_calculo'] === 'fijo')         $cantidad_base = $factor;
                            elseif ($r['tipo_calculo'] === 'por_area')  $cantidad_base = $area_m2 * $factor;
                            elseif ($r['tipo_calculo'] === 'por_ancho') $cantidad_base = $ancho_m * $factor;
                            elseif ($r['tipo_calculo'] === 'por_alto')  $cantidad_base = $alto_m  * $factor;
                            $cantidad_total = $cantidad_base * (1 + ($merma / 100)) * (int)$it['cantidad'];
                            $mat_id = (int)$r['material_producto_id'];

                            $opcion_id = null;
                            if (!empty($color_val)) {
                                $cache_key = $mat_id . '|' . strtolower(trim($color_val));
                                if (array_key_exists($cache_key, $color_cache)) {
                                    $opcion_id = $color_cache[$cache_key];
                                } else {
                                    $stmtOpt = $pdo->prepare("
                                        SELECT o.id FROM ecommerce_atributo_opciones o
                                        JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
                                        WHERE a.producto_id = ? AND a.tipo = 'select'
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
                                $materiales_color[$mat_id][$opcion_id] = ($materiales_color[$mat_id][$opcion_id] ?? 0) + $cantidad_total;
                            } else {
                                $materiales[$mat_id] = ($materiales[$mat_id] ?? 0) + $cantidad_total;
                            }
                        }
                    }

                    foreach ($materiales_color as $mat_id => $opciones) {
                        foreach ($opciones as $opcion_id => $qty) {
                            $pdo->prepare("UPDATE ecommerce_atributo_opciones SET stock = stock - ? WHERE id = ?")->execute([$qty, $opcion_id]);
                        }
                    }
                    foreach ($materiales as $mat_id => $qty) {
                        $pdo->prepare("UPDATE ecommerce_productos SET stock = stock - ? WHERE id = ?")->execute([$qty, $mat_id]);
                    }
                    $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET materiales_descontados = 1 WHERE id = ?")->execute([$orden['id']]);
                }
            }

            header("Location: pedidos.php");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Filtros
$estado_filter              = $_GET['estado'] ?? '';
$fecha_desde                = $_GET['fecha_desde'] ?? '';
$fecha_hasta                = $_GET['fecha_hasta'] ?? '';
$cliente_busqueda           = $_GET['cliente'] ?? '';
$calidad_revision_filter    = $_GET['calidad_revision'] ?? '';
$calidad_observacion_filter = $_GET['calidad_observacion'] ?? '';
$per_page = 50;
$page     = max(1, intval($_GET['pagina'] ?? 1));

$query_from = "
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    LEFT JOIN ecommerce_calidad_inspecciones ci ON ci.pedido_id = p.id
    WHERE p.estado != 'cancelado'
";
$params = [];

if (!empty($estado_filter)) {
    $query_from .= " AND p.estado = ?";
    $params[] = $estado_filter;
}
if (!empty($fecha_desde)) {
    $query_from .= " AND DATE(p.fecha_pedido) >= ?";
    $params[] = $fecha_desde;
}
if (!empty($fecha_hasta)) {
    $query_from .= " AND DATE(p.fecha_pedido) <= ?";
    $params[] = $fecha_hasta;
}
if (!empty($cliente_busqueda)) {
    $query_from .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR p.numero_pedido LIKE ?)";
    $busqueda_wildcard = '%' . $cliente_busqueda . '%';
    $params[] = $busqueda_wildcard;
    $params[] = $busqueda_wildcard;
    $params[] = $busqueda_wildcard;
}
if ($calidad_revision_filter === 'chequeados') {
    $query_from .= " AND ci.id IS NOT NULL";
} elseif ($calidad_revision_filter === 'sin_chequear') {
    $query_from .= " AND ci.id IS NULL";
}
if ($calidad_observacion_filter === 'con_observacion') {
    $query_from .= " AND (
        LOWER(COALESCE(ci.estado_calidad, '')) IN ('observado', 'rechazado')
        OR NULLIF(TRIM(COALESCE(ci.observaciones, '')), '') IS NOT NULL
        OR NULLIF(TRIM(COALESCE(ci.detalle_revision, '')), '') IS NOT NULL
    )";
} elseif ($calidad_observacion_filter === 'sin_observacion') {
    $query_from .= " AND (
        ci.id IS NULL OR (
            LOWER(COALESCE(ci.estado_calidad, '')) NOT IN ('observado', 'rechazado')
            AND NULLIF(TRIM(COALESCE(ci.observaciones, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(ci.detalle_revision, '')), '') IS NULL
        )
    )";
}

try {
    $stmt_count = $pdo->prepare("SELECT COUNT(*) " . $query_from);
    $stmt_count->execute($params);
    $total_pedidos = (int)$stmt_count->fetchColumn();
    $total_paginas = max(1, (int)ceil($total_pedidos / $per_page));
    $page   = min($page, $total_paginas);
    $offset = ($page - 1) * $per_page;
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error SQL: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log('Error SQL pedidos.php: ' . $e->getMessage());
    exit;
}

$query = "
SELECT
    p.id,
    p.numero_pedido,
    p.fecha_pedido,
    p.total,
    p.estado,
    p.public_token,
    c.nombre  AS cliente_nombre,
    c.email   AS cliente_email,
    COALESCE(pagos.total_pagado, 0) AS total_pagado,
    ci.id              AS calidad_id,
    ci.estado_calidad,
    ci.prueba_aprobada,
    ci.detalle_revision,
    ci.observaciones,
    ci.fecha_revision
FROM ecommerce_pedidos p
LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
LEFT JOIN (
    SELECT pedido_id, SUM(monto) AS total_pagado
    FROM ecommerce_pedido_pagos
    GROUP BY pedido_id
) pagos ON pagos.pedido_id = p.id
LEFT JOIN ecommerce_calidad_inspecciones ci ON ci.pedido_id = p.id
WHERE p.estado != 'cancelado'";
if (!empty($estado_filter))    $query .= " AND p.estado = ?";
if (!empty($fecha_desde))      $query .= " AND DATE(p.fecha_pedido) >= ?";
if (!empty($fecha_hasta))      $query .= " AND DATE(p.fecha_pedido) <= ?";
if (!empty($cliente_busqueda)) $query .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR p.numero_pedido LIKE ?)";
if ($calidad_revision_filter === 'chequeados') {
    $query .= " AND ci.id IS NOT NULL";
} elseif ($calidad_revision_filter === 'sin_chequear') {
    $query .= " AND ci.id IS NULL";
}
if ($calidad_observacion_filter === 'con_observacion') {
    $query .= " AND (
        LOWER(COALESCE(ci.estado_calidad, '')) IN ('observado', 'rechazado')
        OR NULLIF(TRIM(COALESCE(ci.observaciones, '')), '') IS NOT NULL
        OR NULLIF(TRIM(COALESCE(ci.detalle_revision, '')), '') IS NOT NULL
    )";
} elseif ($calidad_observacion_filter === 'sin_observacion') {
    $query .= " AND (
        ci.id IS NULL OR (
            LOWER(COALESCE(ci.estado_calidad, '')) NOT IN ('observado', 'rechazado')
            AND NULLIF(TRIM(COALESCE(ci.observaciones, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(ci.detalle_revision, '')), '') IS NULL
        )
    )";
}
$query .= " ORDER BY p.fecha_pedido DESC LIMIT ? OFFSET ?";

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute(array_merge($params, [$per_page, $offset]));
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">Error SQL: ' . htmlspecialchars($e->getMessage()) . '</div>';
    error_log('Error SQL pedidos.php: ' . $e->getMessage());
    exit;
}

$request_scheme = 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $request_scheme = 'https';
}
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $request_scheme . '://' . $host . ($public_base ?? '');

foreach ($pedidos as &$pedido) {
    $pid          = $pedido['id'];
    $total_pagado = (float)($pedido['total_pagado'] ?? 0);
    $total_pedido = (float)($pedido['total'] ?? 0);
    if ($total_pedido > 0 && $total_pagado >= $total_pedido && $pedido['estado'] !== 'pagado') {
        $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'pagado' WHERE id = ?")->execute([$pid]);
        $pedido['estado'] = 'pagado';
    }
    if (empty($pedido['public_token'])) {
        $nuevo_token = bin2hex(random_bytes(16));
        try {
            $pdo->prepare("UPDATE ecommerce_pedidos SET public_token = ? WHERE id = ?")->execute([$nuevo_token, $pid]);
            $pedido['public_token'] = $nuevo_token;
        } catch (Exception $e) { /* continuar */ }
    }
    $pedido['calidad'] = [
        'id'              => $pedido['calidad_id']        ?? null,
        'estado_calidad'  => $pedido['estado_calidad']    ?? null,
        'prueba_aprobada' => $pedido['prueba_aprobada']   ?? null,
        'detalle_revision'=> $pedido['detalle_revision']  ?? null,
        'observaciones'   => $pedido['observaciones']     ?? null,
        'fecha_revision'  => $pedido['fecha_revision']    ?? null,
    ];
}
unset($pedido);

$pedido_items_map      = [];
$pedido_remito_resumen = [];

if (!empty($pedidos)) {
    $tabla_items_existe = false;
    try {
        $pdo->query("SELECT 1 FROM `ecommerce_pedido_items` LIMIT 1");
        $tabla_items_existe = true;
    } catch (PDOException $e) { /* tabla no existe */ }

    if ($tabla_items_existe) {
        $pedido_ids   = array_values(array_unique(array_map('intval', array_column($pedidos, 'id'))));
        $placeholders = implode(', ', array_fill(0, count($pedido_ids), '?'));
        $sql_items = "
            SELECT pi.id, pi.pedido_id, pi.cantidad, pi.ancho_cm, pi.alto_cm, pi.atributos,
                   COALESCE(pr.nombre, 'Producto') AS producto_nombre,
                   COALESCE(rem.cantidad_remitida, 0) AS cantidad_remitida
            FROM ecommerce_pedido_items pi
            LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
            LEFT JOIN (
                SELECT pedido_item_id, SUM(cantidad) AS cantidad_remitida
                FROM ecommerce_remito_items
                GROUP BY pedido_item_id
            ) rem ON rem.pedido_item_id = pi.id
            WHERE pi.pedido_id IN ($placeholders)
            ORDER BY pi.pedido_id ASC, pi.id ASC
        ";
        $stmtIt = $pdo->prepare($sql_items);
        $stmtIt->execute($pedido_ids);

        foreach ($stmtIt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $pedidoId          = (int)$item['pedido_id'];
            $cantidadTotal     = (float)($item['cantidad'] ?? 0);
            $cantidadRemitida  = min($cantidadTotal, (float)($item['cantidad_remitida'] ?? 0));
            $cantidadPendiente = max(0, $cantidadTotal - $cantidadRemitida);

            $medidas = '';
            if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
                $medidas = ($item['ancho_cm'] !== null && $item['ancho_cm'] !== '' ? $item['ancho_cm'] : '-')
                    . 'x'
                    . ($item['alto_cm'] !== null && $item['alto_cm'] !== '' ? $item['alto_cm'] : '-')
                    . ' cm';
            }

            $atributosTexto = '';
            if (!empty($item['atributos'])) {
                $atrs = json_decode((string)$item['atributos'], true);
                if (is_array($atrs)) {
                    $partes = [];
                    foreach ($atrs as $attr) {
                        $n = trim((string)($attr['nombre'] ?? ''));
                        $v = trim((string)($attr['valor'] ?? ''));
                        if ($n !== '' || $v !== '') $partes[] = trim($n . ': ' . $v, ': ');
                    }
                    $atributosTexto = implode(' · ', $partes);
                }
            }

            $pedido_items_map[$pedidoId][] = [
                'id'       => (int)$item['id'],
                'producto' => (string)$item['producto_nombre'],
                'cantidad' => $cantidadTotal,
                'remitida' => $cantidadRemitida,
                'pendiente'=> $cantidadPendiente,
                'medidas'  => $medidas,
                'atributos'=> $atributosTexto,
            ];

            if (!isset($pedido_remito_resumen[$pedidoId])) {
                $pedido_remito_resumen[$pedidoId] = ['cantidad_total' => 0, 'cantidad_remitida' => 0, 'cantidad_pendiente' => 0];
            }
            $pedido_remito_resumen[$pedidoId]['cantidad_total']     += $cantidadTotal;
            $pedido_remito_resumen[$pedidoId]['cantidad_remitida']  += $cantidadRemitida;
            $pedido_remito_resumen[$pedidoId]['cantidad_pendiente'] += $cantidadPendiente;
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Pedidos</h1>
        <p class="text-muted mb-0">Gestión de pedidos y seguimiento de estados</p>
    </div>
    <div class="badge bg-primary-subtle text-primary-emphasis fs-6">Total: <?= $total_pedidos ?></div>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row align-items-end g-3">
            <div class="col-12 col-md-2">
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
            <div class="col-12 col-md-3">
                <label for="cliente" class="form-label">Cliente:</label>
                <input type="text" name="cliente" id="cliente" class="form-control" placeholder="Nombre, email o #pedido" value="<?= htmlspecialchars($cliente_busqueda) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="calidad_revision" class="form-label">Control calidad:</label>
                <select name="calidad_revision" id="calidad_revision" class="form-select">
                    <option value="">Todos</option>
                    <option value="chequeados" <?= $calidad_revision_filter === 'chequeados' ? 'selected' : '' ?>>Chequeados</option>
                    <option value="sin_chequear" <?= $calidad_revision_filter === 'sin_chequear' ? 'selected' : '' ?>>Sin chequear</option>
                </select>
            </div>
            <div class="col-6 col-md-3">
                <label for="calidad_observacion" class="form-label">Observación calidad:</label>
                <select name="calidad_observacion" id="calidad_observacion" class="form-select">
                    <option value="">Todas</option>
                    <option value="con_observacion" <?= $calidad_observacion_filter === 'con_observacion' ? 'selected' : '' ?>>Con observación</option>
                    <option value="sin_observacion" <?= $calidad_observacion_filter === 'sin_observacion' ? 'selected' : '' ?>>Sin observación</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="fecha_desde" class="form-label">Desde:</label>
                <input type="date" name="fecha_desde" id="fecha_desde" class="form-control" value="<?= $fecha_desde ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="fecha_hasta" class="form-label">Hasta:</label>
                <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control" value="<?= $fecha_hasta ?>">
            </div>
            <div class="col-12 col-md-3 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <?php if (!empty($estado_filter) || !empty($fecha_desde) || !empty($fecha_hasta) || !empty($cliente_busqueda) || !empty($calidad_revision_filter) || !empty($calidad_observacion_filter)): ?>
                    <a href="pedidos.php" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">No hay pedidos</div>
<?php else: ?>
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
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
                            <?php
                                $remito_resumen = $pedido_remito_resumen[(int)$pedido['id']] ?? ['cantidad_total' => 0, 'cantidad_remitida' => 0, 'cantidad_pendiente' => 0];
                                $calidad = $pedido['calidad'] ?? [];
                                $calidadEstado = strtolower(trim((string)($calidad['estado_calidad'] ?? '')));
                                $calidadBadge = 'secondary';
                                $calidadTexto = 'Sin control';
                                if ($calidadEstado === 'aprobado') {
                                    $calidadBadge = 'success';
                                    $calidadTexto = ((int)($calidad['prueba_aprobada'] ?? 0) === 1) ? 'Calidad OK' : 'Aprobado';
                                } elseif ($calidadEstado === 'observado') {
                                    $calidadBadge = 'warning text-dark';
                                    $calidadTexto = 'Observado';
                                } elseif ($calidadEstado === 'rechazado') {
                                    $calidadBadge = 'danger';
                                    $calidadTexto = 'Rechazado';
                                } elseif ($calidadEstado === 'pendiente') {
                                    $calidadBadge = 'secondary';
                                    $calidadTexto = 'Pendiente';
                                }
                            ?>
                            <div class="d-flex flex-wrap gap-1">
                            <a class="btn btn-sm btn-outline-primary" href="pedidos_detalle.php?pedido_id=<?= $pedido['id'] ?>">Ver detalle</a>
                            <a class="btn btn-sm btn-outline-success" href="pedidos_detalle.php?pedido_id=<?= $pedido['id'] ?>#pagos">Pagos</a>
                            <a class="btn btn-sm btn-outline-warning" href="calidad.php?pedido_id=<?= $pedido['id'] ?>">Calidad</a>
                            <a class="btn btn-sm btn-outline-dark" href="pedido_imprimir.php?id=<?= $pedido['id'] ?>" target="_blank">Imprimir</a>
                            <?php if ($calidadEstado !== ''): ?>
                                <a class="btn btn-sm btn-outline-secondary" href="calidad_inspeccion_pdf.php?pedido_id=<?= $pedido['id'] ?>" target="_blank">PDF calidad</a>
                            <?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary btn-remito-parcial" href="pedido_remito.php?id=<?= $pedido['id'] ?>" data-pedido-id="<?= (int)$pedido['id'] ?>" data-pedido-numero="<?= htmlspecialchars($pedido['numero_pedido']) ?>">
                                <?= ($remito_resumen['cantidad_remitida'] > 0 && $remito_resumen['cantidad_pendiente'] > 0) ? 'Remito parcial' : 'Remito' ?>
                            </a>
                            <?php if (!empty($pedido['public_token'])): ?>
                                <a class="btn btn-sm btn-outline-info" href="<?= htmlspecialchars($base_url . '/pedido_publico.php?token=' . urlencode($pedido['public_token'])) ?>" target="_blank" rel="noopener">Link público</a>
                            <?php endif; ?>
                            </div>
                            <div class="mt-1">
                                <span class="badge bg-<?= $calidadBadge ?>"><?= htmlspecialchars($calidadTexto) ?></span>
                                <?php if (!empty($calidad['fecha_revision'])): ?>
                                    <small class="text-muted ms-1">Revisado: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$calidad['fecha_revision']))) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($pedido_items_map[(int)$pedido['id']])): ?>
                                <small class="text-muted d-block mt-1">
                                    Pendiente remito: <?= number_format((float)$remito_resumen['cantidad_pendiente'], 2, ',', '.') ?>
                                    · Ya remitido: <?= number_format((float)$remito_resumen['cantidad_remitida'], 2, ',', '.') ?>
                                </small>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php if ($total_paginas > 1): ?>
        <?php
            $filtros_url = http_build_query(array_filter([
                'estado'              => $estado_filter,
                'fecha_desde'         => $fecha_desde,
                'fecha_hasta'         => $fecha_hasta,
                'cliente'             => $cliente_busqueda,
                'calidad_revision'    => $calidad_revision_filter,
                'calidad_observacion' => $calidad_observacion_filter,
            ]));
            $url_base = 'pedidos.php?' . ($filtros_url ? $filtros_url . '&' : '');
        ?>
        <nav class="mt-3" aria-label="Paginación de pedidos">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $url_base ?>pagina=<?= $page - 1 ?>">‹ Anterior</a>
                </li>
                <?php for ($p = 1; $p <= $total_paginas; $p++): ?>
                    <?php if ($p === 1 || $p === $total_paginas || abs($p - $page) <= 2): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= $url_base ?>pagina=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php elseif (abs($p - $page) === 3): ?>
                        <li class="page-item disabled"><span class="page-link">…</span></li>
                    <?php endif; ?>
                <?php endfor; ?>
                <li class="page-item <?= $page >= $total_paginas ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $url_base ?>pagina=<?= $page + 1 ?>">Siguiente ›</a>
                </li>
            </ul>
            <p class="text-center text-muted small">Página <?= $page ?> de <?= $total_paginas ?> · <?= $total_pedidos ?> pedidos en total</p>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<style>
    #modalRemitoParcial .modal-dialog {
        max-width: min(980px, calc(100vw - 1rem));
        margin: 0.75rem auto;
    }
    #modalRemitoParcial .modal-content {
        max-height: calc(100vh - 1.5rem);
        overflow: hidden;
    }
    #modalRemitoParcial .remito-modal-form {
        display: flex;
        flex-direction: column;
        min-height: 0;
        height: 100%;
    }
    #modalRemitoParcial .modal-header,
    #modalRemitoParcial .modal-footer {
        flex: 0 0 auto;
        background: #fff;
        z-index: 2;
    }
    #modalRemitoParcial .modal-body {
        flex: 1 1 auto;
        overflow-y: auto;
        overscroll-behavior: contain;
        max-height: calc(100vh - 220px);
    }
    #remitoItemsContainer {
        max-height: 46vh;
        overflow-y: auto;
        padding-right: .25rem;
    }
    #remitoItemsContainer::-webkit-scrollbar,
    #modalRemitoParcial .modal-body::-webkit-scrollbar {
        width: 10px;
    }
    #remitoItemsContainer::-webkit-scrollbar-thumb,
    #modalRemitoParcial .modal-body::-webkit-scrollbar-thumb {
        background: #c8d4ea;
        border-radius: 999px;
    }
</style>

<div class="modal fade" id="modalRemitoParcial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form method="POST" action="pedido_remito.php" target="_blank" id="formRemitoParcial" class="remito-modal-form">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title mb-1">📦 Generar remito parcial</h5>
                        <div class="small text-muted">Pedido <span id="remitoPedidoNumero">-</span></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="remitoPedidoId" value="0">
                    <div id="remitoResumen" class="mb-3"></div>
                    <div class="d-flex justify-content-end gap-2 mb-3">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="limpiarCantidadesBtn">Limpiar</button>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="completarPendientesBtn">Cargar pendientes</button>
                    </div>
                    <div id="remitoItemsContainer"></div>
                    <div class="mt-3">
                        <label for="remitoObservaciones" class="form-label">Observaciones del remito</label>
                        <textarea class="form-control" id="remitoObservaciones" name="observaciones" rows="2" placeholder="Ej: Primera entrega / entrega parcial"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="remitoSubmitBtn">Imprimir remito</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const remitoPedidos = <?= json_encode($pedido_items_map, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const remitoModalElement = document.getElementById('modalRemitoParcial');
const remitoItemsContainer = document.getElementById('remitoItemsContainer');
const remitoResumen = document.getElementById('remitoResumen');
const remitoPedidoId = document.getElementById('remitoPedidoId');
const remitoPedidoNumero = document.getElementById('remitoPedidoNumero');
const remitoSubmitBtn = document.getElementById('remitoSubmitBtn');

function obtenerRemitoModal() {
    if (!remitoModalElement || !window.bootstrap || !window.bootstrap.Modal) {
        return null;
    }
    return window.bootstrap.Modal.getOrCreateInstance(remitoModalElement);
}
function formatCantidadRemito(valor) {
    const numero = Number(valor || 0);
    return numero.toLocaleString('es-AR', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

function renderRemitoModal(pedidoId, numeroPedido) {
    const items = remitoPedidos[String(pedidoId)] || remitoPedidos[pedidoId] || [];
    if (remitoPedidoId) remitoPedidoId.value = pedidoId;
    if (remitoPedidoNumero) remitoPedidoNumero.textContent = numeroPedido || ('#' + pedidoId);

    if (!items.length) {
        remitoItemsContainer.innerHTML = '<div class="alert alert-warning mb-0">Este pedido no tiene ítems para remitir.</div>';
        remitoResumen.innerHTML = '';
        if (remitoSubmitBtn) remitoSubmitBtn.disabled = true;
        return;
    }

    let pendienteTotal = 0;
    let remitidoTotal = 0;
    let html = '';

    items.forEach((item) => {
        const pendiente = Number(item.pendiente || 0);
        const remitida = Number(item.remitida || 0);
        const cantidad = Number(item.cantidad || 0);
        pendienteTotal += pendiente;
        remitidoTotal += remitida;

        html += `
            <div class="border rounded p-3 mb-2 ${pendiente <= 0 ? 'bg-light' : ''}">
                <div class="d-flex justify-content-between gap-2 flex-wrap">
                    <div>
                        <div class="fw-semibold">${item.producto || 'Producto'}</div>
                        ${item.medidas ? `<div class="small text-muted">Medidas: ${item.medidas}</div>` : ''}
                        ${item.atributos ? `<div class="small text-muted">${item.atributos}</div>` : ''}
                    </div>
                    <div class="text-md-end small">
                        <div>Total: <strong>${formatCantidadRemito(cantidad)}</strong></div>
                        <div>Ya remitido: <strong>${formatCantidadRemito(remitida)}</strong></div>
                        <div>Pendiente: <strong>${formatCantidadRemito(pendiente)}</strong></div>
                    </div>
                </div>
                <div class="mt-2">
                    <label class="form-label mb-1">Cantidad a incluir en este remito</label>
                    <input
                        type="number"
                        class="form-control"
                        name="cantidades[${item.id}]"
                        value="${pendiente > 0 ? pendiente : 0}"
                        min="0"
                        max="${pendiente}"
                        step="0.01"
                        data-remito-cantidad="1"
                        data-pendiente="${pendiente}"
                        ${pendiente <= 0 ? 'disabled' : ''}
                    >
                </div>
            </div>`;
    });

    remitoItemsContainer.innerHTML = html;

    if (pendienteTotal <= 0) {
        remitoResumen.innerHTML = '<div class="alert alert-success mb-0">Este pedido ya fue remitido completamente.</div>';
        if (remitoSubmitBtn) remitoSubmitBtn.disabled = true;
    } else {
        remitoResumen.innerHTML = `<div class="alert alert-info mb-0">Pendiente a remitir: <strong>${formatCantidadRemito(pendienteTotal)}</strong> · Ya remitido: <strong>${formatCantidadRemito(remitidoTotal)}</strong></div>`;
        if (remitoSubmitBtn) remitoSubmitBtn.disabled = false;
    }
}

document.querySelectorAll('.btn-remito-parcial').forEach((btn) => {
    btn.addEventListener('click', function(e) {
        const remitoModal = obtenerRemitoModal();
        if (!remitoModal) {
            return;
        }
        e.preventDefault();
        renderRemitoModal(this.dataset.pedidoId, this.dataset.pedidoNumero);
        remitoModal.show();
    });
});

document.getElementById('completarPendientesBtn')?.addEventListener('click', function() {
    remitoItemsContainer.querySelectorAll('input[data-remito-cantidad]').forEach((input) => {
        if (!input.disabled) {
            input.value = input.dataset.pendiente || 0;
        }
    });
});

document.getElementById('limpiarCantidadesBtn')?.addEventListener('click', function() {
    remitoItemsContainer.querySelectorAll('input[data-remito-cantidad]').forEach((input) => {
        if (!input.disabled) {
            input.value = 0;
        }
    });
});

remitoItemsContainer?.addEventListener('input', function(e) {
    const input = e.target;
    if (!(input instanceof HTMLInputElement) || !input.matches('input[data-remito-cantidad]')) {
        return;
    }
    const max = Number(input.dataset.pendiente || 0);
    let valor = Number(input.value || 0);
    if (Number.isNaN(valor) || valor < 0) {
        valor = 0;
    }
    if (valor > max) {
        valor = max;
    }
    input.value = valor;
});

document.getElementById('formRemitoParcial')?.addEventListener('submit', function(e) {
    const hayCantidad = Array.from(this.querySelectorAll('input[data-remito-cantidad]')).some((input) => Number(input.value || 0) > 0);
    if (!hayCantidad) {
        e.preventDefault();
        alert('Indicá al menos una cantidad a incluir en el remito.');
    }
});
</script>

<?php require 'includes/footer.php'; ?>
