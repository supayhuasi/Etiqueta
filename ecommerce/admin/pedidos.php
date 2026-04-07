<?php
require 'includes/header.php';
require '../includes/funciones_recetas.php';
require_once __DIR__ . '/includes/calidad_helper.php';

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

function pedidos_tabla_existe(PDO $pdo, string $tabla): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tabla]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function pedidos_asegurar_tablas_remitos(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_remitos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        numero_remito VARCHAR(50) NOT NULL,
        tipo ENUM('completo','parcial') NOT NULL DEFAULT 'completo',
        observaciones TEXT NULL,
        creado_por INT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_numero_remito (numero_remito),
        KEY idx_pedido (pedido_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_remito_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remito_id INT NOT NULL,
        pedido_item_id INT NOT NULL,
        cantidad DECIMAL(10,2) NOT NULL DEFAULT 0,
        KEY idx_remito_id (remito_id),
        KEY idx_pedido_item_id (pedido_item_id),
        UNIQUE KEY uniq_remito_item (remito_id, pedido_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

pedidos_asegurar_tablas_remitos($pdo);
try {
    ensureCalidadSchema($pdo);
} catch (Throwable $e) {
    // Continuar aunque la tabla de calidad no pueda inicializarse.
}

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
                $opcion_color_cache = [];
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
                            $nombre_attr = (string)($attr['nombre'] ?? '');
                            $valor_attr = (string)($attr['valor'] ?? '');
                            $opcion_id_attr = isset($attr['opcion_id']) && $attr['opcion_id'] !== '' ? (int)$attr['opcion_id'] : null;

                            // 1) Si el nombre del atributo menciona color, usar el valor
                            if ($nombre_attr !== '' && stripos($nombre_attr, 'color') !== false && $valor_attr !== '') {
                                $color_val = $valor_attr;
                                break;
                            }

                            // 2) Si el valor parece un color HEX, usarlo
                            if ($valor_attr !== '' && preg_match('/^#[0-9a-f]{6}$/i', $valor_attr)) {
                                $color_val = $valor_attr;
                                break;
                            }

                            // 3) Si hay opción y tiene color definido, usar ese color
                            if (!empty($opcion_id_attr)) {
                                if (!array_key_exists($opcion_id_attr, $opcion_color_cache)) {
                                    $stmtOptColor = $pdo->prepare("SELECT color, nombre FROM ecommerce_atributo_opciones WHERE id = ? LIMIT 1");
                                    $stmtOptColor->execute([$opcion_id_attr]);
                                    $opcion_color_cache[$opcion_id_attr] = $stmtOptColor->fetch(PDO::FETCH_ASSOC) ?: null;
                                }
                                $op = $opcion_color_cache[$opcion_id_attr];
                                if (!empty($op['color']) && preg_match('/^#[0-9a-f]{6}$/i', $op['color'])) {
                                    $color_val = $op['color'];
                                    break;
                                }
                                if ($nombre_attr !== '' && stripos($nombre_attr, 'color') !== false && !empty($op['nombre'])) {
                                    $color_val = $op['nombre'];
                                    break;
                                }
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
$cliente_busqueda = $_GET['cliente'] ?? '';
$calidad_revision_filter = $_GET['calidad_revision'] ?? '';
$calidad_observacion_filter = $_GET['calidad_observacion'] ?? '';

$query = "
    SELECT p.*, c.nombre as cliente_nombre, c.email as cliente_email,
           COALESCE(pp.total_pagado, 0) AS total_pagado,
           ci.id AS calidad_inspeccion_id,
           ci.estado_calidad,
           ci.prueba_aprobada,
           ci.detalle_revision,
           ci.observaciones,
           ci.fecha_revision AS calidad_fecha_revision
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    LEFT JOIN (
        SELECT pedido_id, SUM(monto) AS total_pagado
        FROM ecommerce_pedido_pagos
        GROUP BY pedido_id
    ) pp ON pp.pedido_id = p.id
    LEFT JOIN ecommerce_calidad_inspecciones ci ON ci.pedido_id = p.id
    WHERE p.estado != 'cancelado'
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

if (!empty($cliente_busqueda)) {
    $query .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR p.numero_pedido LIKE ?)";
    $busqueda_wildcard = '%' . $cliente_busqueda . '%';
    $params[] = $busqueda_wildcard;
    $params[] = $busqueda_wildcard;
    $params[] = $busqueda_wildcard;
}

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
        ci.id IS NULL
        OR (
            LOWER(COALESCE(ci.estado_calidad, '')) NOT IN ('observado', 'rechazado')
            AND NULLIF(TRIM(COALESCE(ci.observaciones, '')), '') IS NULL
            AND NULLIF(TRIM(COALESCE(ci.detalle_revision, '')), '') IS NULL
        )
    )";
}

$query .= " ORDER BY p.fecha_pedido DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$request_scheme = 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $request_scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $request_scheme . '://' . $host . $public_base;

// Marcar pedidos como pagados si corresponde
foreach ($pedidos as &$pedido) {
    $total_pagado = (float)($pedido['total_pagado'] ?? 0);
    $total_pedido = (float)($pedido['total'] ?? 0);
    if ($total_pedido > 0 && $total_pagado >= $total_pedido && $pedido['estado'] !== 'pagado') {
        $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'pagado' WHERE id = ?");
        $stmt->execute([$pedido['id']]);
        $pedido['estado'] = 'pagado';
    }

    if (empty($pedido['public_token'])) {
        $nuevo_token = bin2hex(random_bytes(16));
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET public_token = ? WHERE id = ?");
            $stmt->execute([$nuevo_token, $pedido['id']]);
            $pedido['public_token'] = $nuevo_token;
        } catch (Exception $e) {
            // Si falla, continuar sin token
        }
    }
}
unset($pedido);

$pedido_items_map = [];
$pedido_remito_resumen = [];

if (!empty($pedidos) && pedidos_tabla_existe($pdo, 'ecommerce_pedido_items')) {
    $pedido_ids = array_values(array_unique(array_map('intval', array_column($pedidos, 'id'))));
    if (!empty($pedido_ids)) {
        $placeholders = implode(', ', array_fill(0, count($pedido_ids), '?'));
        $sql_items_remito = "
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
        $stmt = $pdo->prepare($sql_items_remito);
        $stmt->execute($pedido_ids);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
            $pedidoId = (int)$item['pedido_id'];
            $cantidadTotal = (float)($item['cantidad'] ?? 0);
            $cantidadRemitida = min($cantidadTotal, (float)($item['cantidad_remitida'] ?? 0));
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
                $atributos = json_decode((string)$item['atributos'], true);
                if (is_array($atributos)) {
                    $partes = [];
                    foreach ($atributos as $attr) {
                        $nombreAttr = trim((string)($attr['nombre'] ?? ''));
                        $valorAttr = trim((string)($attr['valor'] ?? ''));
                        if ($nombreAttr !== '' || $valorAttr !== '') {
                            $partes[] = trim($nombreAttr . ': ' . $valorAttr, ': ');
                        }
                    }
                    $atributosTexto = implode(' · ', $partes);
                }
            }

            $pedido_items_map[$pedidoId][] = [
                'id' => (int)$item['id'],
                'producto' => (string)$item['producto_nombre'],
                'cantidad' => $cantidadTotal,
                'remitida' => $cantidadRemitida,
                'pendiente' => $cantidadPendiente,
                'medidas' => $medidas,
                'atributos' => $atributosTexto,
            ];

            if (!isset($pedido_remito_resumen[$pedidoId])) {
                $pedido_remito_resumen[$pedidoId] = [
                    'cantidad_total' => 0,
                    'cantidad_remitida' => 0,
                    'cantidad_pendiente' => 0,
                ];
            }

            $pedido_remito_resumen[$pedidoId]['cantidad_total'] += $cantidadTotal;
            $pedido_remito_resumen[$pedidoId]['cantidad_remitida'] += $cantidadRemitida;
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
    <div class="badge bg-primary-subtle text-primary-emphasis fs-6">Total: <?= count($pedidos) ?></div>
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
                                $calidadEstado = strtolower(trim((string)($pedido['estado_calidad'] ?? '')));
                                $calidadBadge = 'secondary';
                                $calidadTexto = 'Sin control';
                                if ($calidadEstado === 'aprobado') {
                                    $calidadBadge = 'success';
                                    $calidadTexto = ((int)($pedido['prueba_aprobada'] ?? 0) === 1) ? 'Calidad OK' : 'Aprobado';
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
                                <?php if (!empty($pedido['calidad_fecha_revision'])): ?>
                                    <small class="text-muted ms-1">Revisado: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$pedido['calidad_fecha_revision']))) ?></small>
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
