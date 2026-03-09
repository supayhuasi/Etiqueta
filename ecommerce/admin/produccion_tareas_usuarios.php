<?php
require 'includes/header.php';

function ensure_produccion_scans_schema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_produccion_scans (
        id INT PRIMARY KEY AUTO_INCREMENT,
        produccion_item_id INT NOT NULL,
        orden_produccion_id INT NOT NULL,
        pedido_id INT NOT NULL,
        usuario_id INT NOT NULL,
        etapa ENUM('corte','armado','terminado') NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_item (produccion_item_id),
        INDEX idx_usuario (usuario_id),
        INDEX idx_orden (orden_produccion_id),
        INDEX idx_fecha (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

function ensure_tareas_usuarios_schema(PDO $pdo): void {
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_tareas_usuarios (
        id INT PRIMARY KEY AUTO_INCREMENT,
        usuario_id INT NOT NULL,
        asignada_por INT NULL,
        titulo VARCHAR(255) NOT NULL,
        descripcion TEXT NULL,
        estado ENUM('pendiente','en_progreso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        fecha_limite DATE NULL,
        fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        fecha_completada DATETIME NULL,
        INDEX idx_usuario (usuario_id),
        INDEX idx_estado (estado),
        INDEX idx_fecha_asignacion (fecha_asignacion),
        INDEX idx_fecha_limite (fecha_limite)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

function admin_get_table_columns(PDO $pdo, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        $cols = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN, 0) : [];
        $cache[$table] = array_map(static fn($c) => (string)$c, $cols ?: []);
    } catch (Throwable $e) {
        $cache[$table] = [];
    }

    return $cache[$table];
}

function admin_pick_column(array $columns, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (in_array($candidate, $columns, true)) {
            return $candidate;
        }
    }
    return null;
}

ensure_produccion_scans_schema($pdo);
ensure_tareas_usuarios_schema($pdo);

$etapas_validas = ['corte', 'armado', 'terminado'];
$usuario_id_filtro = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$etapa_filtro = trim($_GET['etapa'] ?? '');
$fecha_desde = trim($_GET['fecha_desde'] ?? date('Y-m-d'));
$fecha_hasta = trim($_GET['fecha_hasta'] ?? date('Y-m-d'));
$mensaje_tareas = '';
$error_tareas = '';

if (!in_array($etapa_filtro, $etapas_validas, true)) {
    $etapa_filtro = '';
}

if ($fecha_desde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_desde)) {
    $fecha_desde = date('Y-m-d');
}
if ($fecha_hasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_hasta)) {
    $fecha_hasta = date('Y-m-d');
}

if ($fecha_desde !== '' && $fecha_hasta !== '' && $fecha_desde > $fecha_hasta) {
    [$fecha_desde, $fecha_hasta] = [$fecha_hasta, $fecha_desde];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($role ?? '') === 'admin') {
    admin_require_csrf_post();
    $accion_tarea = $_POST['accion_tarea'] ?? '';

    try {
        if ($accion_tarea === 'asignar') {
            $usuario_destino = (int)($_POST['tarea_usuario_id'] ?? 0);
            $titulo_tarea = trim((string)($_POST['tarea_titulo'] ?? ''));
            $descripcion_tarea = trim((string)($_POST['tarea_descripcion'] ?? ''));
            $fecha_limite_tarea = trim((string)($_POST['tarea_fecha_limite'] ?? ''));

            if ($usuario_destino <= 0) {
                throw new Exception('Seleccioná un usuario para asignar la tarea.');
            }
            if ($titulo_tarea === '') {
                throw new Exception('Ingresá un título para la tarea.');
            }

            if ($fecha_limite_tarea !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha_limite_tarea)) {
                $fecha_limite_tarea = '';
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_tareas_usuarios (usuario_id, asignada_por, titulo, descripcion, estado, fecha_limite) VALUES (?, ?, ?, ?, 'pendiente', ?)");
            $stmt->execute([
                $usuario_destino,
                (int)($_SESSION['user']['id'] ?? 0),
                $titulo_tarea,
                $descripcion_tarea !== '' ? $descripcion_tarea : null,
                $fecha_limite_tarea !== '' ? $fecha_limite_tarea : null,
            ]);

            $mensaje_tareas = 'Tarea asignada correctamente.';
        }

        if ($accion_tarea === 'cambiar_estado') {
            $tarea_id = (int)($_POST['tarea_id'] ?? 0);
            $nuevo_estado = trim((string)($_POST['nuevo_estado'] ?? ''));
            $estados_validos = ['pendiente', 'en_progreso', 'completada', 'cancelada'];
            if ($tarea_id <= 0 || !in_array($nuevo_estado, $estados_validos, true)) {
                throw new Exception('Datos inválidos para actualizar la tarea.');
            }

            if ($nuevo_estado === 'completada') {
                $stmt = $pdo->prepare("UPDATE ecommerce_tareas_usuarios SET estado = ?, fecha_completada = NOW() WHERE id = ?");
                $stmt->execute([$nuevo_estado, $tarea_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_tareas_usuarios SET estado = ?, fecha_completada = NULL WHERE id = ?");
                $stmt->execute([$nuevo_estado, $tarea_id]);
            }

            $mensaje_tareas = 'Estado de tarea actualizado.';
        }
    } catch (Throwable $e) {
        $error_tareas = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
$usuarios_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);

$cols_cotizaciones = admin_table_exists($pdo, 'ecommerce_cotizaciones')
    ? admin_get_table_columns($pdo, 'ecommerce_cotizaciones')
    : [];

$col_usuario_cotizacion = admin_pick_column($cols_cotizaciones, ['creado_por', 'usuario_id', 'vendedor_id', 'user_id']);

$col_fecha_cotizacion_actividad = null;
$col_fecha_cotizacion_actividad = admin_pick_column($cols_cotizaciones, ['fecha_creacion', 'created_at', 'fecha_actualizacion']);

$where_latest_sub = [];
$params_latest_sub = [];
$where_latest_outer = [];
$params_latest_outer = [];

if ($usuario_id_filtro > 0) {
    $where_latest_sub[] = "usuario_id = ?";
    $params_latest_sub[] = $usuario_id_filtro;
    $where_latest_outer[] = "u.id = ?";
    $params_latest_outer[] = $usuario_id_filtro;
}
if ($etapa_filtro !== '') {
    $where_latest_sub[] = "etapa = ?";
    $params_latest_sub[] = $etapa_filtro;
    $where_latest_outer[] = "s.etapa = ?";
    $params_latest_outer[] = $etapa_filtro;
}
if ($fecha_desde !== '') {
    $where_latest_sub[] = "created_at >= ?";
    $params_latest_sub[] = $fecha_desde . ' 00:00:00';
    $where_latest_outer[] = "s.created_at >= ?";
    $params_latest_outer[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta !== '') {
    $where_latest_sub[] = "created_at <= ?";
    $params_latest_sub[] = $fecha_hasta . ' 23:59:59';
    $where_latest_outer[] = "s.created_at <= ?";
    $params_latest_outer[] = $fecha_hasta . ' 23:59:59';
}

$sql_actividad = "SELECT
    u.id AS usuario_id,
    u.nombre AS usuario_nombre,
    'produccion' AS origen_tarea,
    s.etapa,
    s.created_at,
    pib.estado AS estado_item,
    pib.numero_item,
    pib.codigo_barcode,
    pr.nombre AS producto_nombre,
    p.numero_pedido,
    NULL AS numero_cotizacion,
    NULL AS cotizacion_estado,
    NULL AS cotizacion_total
FROM ecommerce_produccion_scans s
JOIN (
    SELECT usuario_id, MAX(id) AS max_id
    FROM ecommerce_produccion_scans";

if (!empty($where_latest_sub)) {
    $sql_actividad .= " WHERE " . implode(' AND ', $where_latest_sub);
}

$sql_actividad .= "
    GROUP BY usuario_id
) ult ON ult.max_id = s.id
JOIN usuarios u ON u.id = s.usuario_id
LEFT JOIN ecommerce_produccion_items_barcode pib ON pib.id = s.produccion_item_id
LEFT JOIN ecommerce_pedido_items pi ON pi.id = pib.pedido_item_id
LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
LEFT JOIN ecommerce_ordenes_produccion op ON op.id = s.orden_produccion_id
LEFT JOIN ecommerce_pedidos p ON p.id = op.pedido_id";

if (!empty($where_latest_outer)) {
    $sql_actividad .= " WHERE " . implode(' AND ', $where_latest_outer);
}

$sql_actividad .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql_actividad);
$stmt->execute(array_merge($params_latest_sub, $params_latest_outer));
$actividad_actual = $stmt->fetchAll(PDO::FETCH_ASSOC);

$where_tarea_sub = ["estado NOT IN ('completada','cancelada')"];
$params_tarea_sub = [];
$where_tarea_outer = [];
$params_tarea_outer = [];

if ($usuario_id_filtro > 0) {
    $where_tarea_sub[] = "usuario_id = ?";
    $params_tarea_sub[] = $usuario_id_filtro;
    $where_tarea_outer[] = "u.id = ?";
    $params_tarea_outer[] = $usuario_id_filtro;
}
if ($fecha_desde !== '') {
    $where_tarea_sub[] = "fecha_asignacion >= ?";
    $params_tarea_sub[] = $fecha_desde . ' 00:00:00';
    $where_tarea_outer[] = "t.fecha_asignacion >= ?";
    $params_tarea_outer[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta !== '') {
    $where_tarea_sub[] = "fecha_asignacion <= ?";
    $params_tarea_sub[] = $fecha_hasta . ' 23:59:59';
    $where_tarea_outer[] = "t.fecha_asignacion <= ?";
    $params_tarea_outer[] = $fecha_hasta . ' 23:59:59';
}

$sql_tarea_actividad = "SELECT
    u.id AS usuario_id,
    COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS usuario_nombre,
    'tarea_manual' AS origen_tarea,
    'tarea' AS etapa,
    t.fecha_asignacion AS created_at,
    t.estado AS estado_item,
    NULL AS numero_item,
    NULL AS codigo_barcode,
    t.titulo AS producto_nombre,
    NULL AS numero_pedido,
    NULL AS numero_cotizacion,
    NULL AS cotizacion_estado,
    NULL AS cotizacion_total,
    t.descripcion AS tarea_descripcion,
    t.fecha_limite AS tarea_fecha_limite
FROM ecommerce_tareas_usuarios t
JOIN (
    SELECT usuario_id, MAX(id) AS max_id
    FROM ecommerce_tareas_usuarios";

if (!empty($where_tarea_sub)) {
    $sql_tarea_actividad .= " WHERE " . implode(' AND ', $where_tarea_sub);
}

$sql_tarea_actividad .= "
    GROUP BY usuario_id
) ult ON ult.max_id = t.id
JOIN usuarios u ON u.id = t.usuario_id";

if (!empty($where_tarea_outer)) {
    $sql_tarea_actividad .= " WHERE " . implode(' AND ', $where_tarea_outer);
}

$stmt = $pdo->prepare($sql_tarea_actividad);
$stmt->execute(array_merge($params_tarea_sub, $params_tarea_outer));
$actividad_tareas_manuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!empty($actividad_tareas_manuales)) {
    $actividad_indexada = [];
    foreach ($actividad_actual as $row) {
        $uid = (int)($row['usuario_id'] ?? 0);
        if ($uid > 0) {
            $actividad_indexada[$uid] = $row;
        }
    }

    foreach ($actividad_tareas_manuales as $row) {
        $uid = (int)($row['usuario_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $actual = $actividad_indexada[$uid] ?? null;
        $fecha_nueva = !empty($row['created_at']) ? strtotime((string)$row['created_at']) : 0;
        $fecha_actual = ($actual && !empty($actual['created_at'])) ? strtotime((string)$actual['created_at']) : 0;
        if (!$actual || $fecha_nueva >= $fecha_actual) {
            $actividad_indexada[$uid] = $row;
        }
    }

    $actividad_actual = array_values($actividad_indexada);
    usort($actividad_actual, static function (array $a, array $b): int {
        $ta = !empty($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
        $tb = !empty($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
        return $tb <=> $ta;
    });
}

if ($etapa_filtro === '' && $col_fecha_cotizacion_actividad !== null && $col_usuario_cotizacion !== null) {
    $where_cot_latest = ["c.`{$col_usuario_cotizacion}` IS NOT NULL"];
    $params_cot_latest = [];

    if ($usuario_id_filtro > 0) {
        $where_cot_latest[] = "c.`{$col_usuario_cotizacion}` = ?";
        $params_cot_latest[] = $usuario_id_filtro;
    }
    if ($fecha_desde !== '') {
        $where_cot_latest[] = "c.`{$col_fecha_cotizacion_actividad}` >= ?";
        $params_cot_latest[] = $fecha_desde . ' 00:00:00';
    }
    if ($fecha_hasta !== '') {
        $where_cot_latest[] = "c.`{$col_fecha_cotizacion_actividad}` <= ?";
        $params_cot_latest[] = $fecha_hasta . ' 23:59:59';
    }

    $sql_cot_latest = "SELECT
        c.`{$col_usuario_cotizacion}` AS usuario_id,
        COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(u.usuario), ''), CONCAT('Usuario #', c.`{$col_usuario_cotizacion}`, ' (sin registro)')) AS usuario_nombre,
        'cotizacion' AS origen_tarea,
        'cotizacion' AS etapa,
        c.`{$col_fecha_cotizacion_actividad}` AS created_at,
        NULL AS estado_item,
        NULL AS numero_item,
        NULL AS codigo_barcode,
        NULL AS producto_nombre,
        NULL AS numero_pedido,
        c.numero_cotizacion,
        c.estado AS cotizacion_estado,
        c.total AS cotizacion_total
    FROM ecommerce_cotizaciones c
    LEFT JOIN usuarios u ON u.id = c.`{$col_usuario_cotizacion}`
    INNER JOIN (
        SELECT `{$col_usuario_cotizacion}` AS usuario_ref, MAX(id) AS max_id
        FROM ecommerce_cotizaciones
        WHERE `{$col_usuario_cotizacion}` IS NOT NULL
        GROUP BY `{$col_usuario_cotizacion}`
    ) ultc ON ultc.max_id = c.id
    WHERE " . implode(' AND ', $where_cot_latest) . "
    ORDER BY c.`{$col_fecha_cotizacion_actividad}` DESC";

    $stmt = $pdo->prepare($sql_cot_latest);
    $stmt->execute($params_cot_latest);
    $actividad_cotizaciones_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $actividad_indexada = [];
    foreach ($actividad_actual as $row) {
        $uid = (int)($row['usuario_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $actividad_indexada[$uid] = $row;
    }

    foreach ($actividad_cotizaciones_reciente as $row) {
        $uid = (int)($row['usuario_id'] ?? 0);
        if ($uid <= 0) {
            continue;
        }
        $actual = $actividad_indexada[$uid] ?? null;
        $fecha_nueva = !empty($row['created_at']) ? strtotime((string)$row['created_at']) : 0;
        $fecha_actual = ($actual && !empty($actual['created_at'])) ? strtotime((string)$actual['created_at']) : 0;
        if (!$actual || $fecha_nueva >= $fecha_actual) {
            $actividad_indexada[$uid] = $row;
        }
    }

    $actividad_actual = array_values($actividad_indexada);
    usort($actividad_actual, static function (array $a, array $b): int {
        $ta = !empty($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
        $tb = !empty($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
        return $tb <=> $ta;
    });
}

if ($etapa_filtro === '' && $col_fecha_cotizacion_actividad !== null && $col_usuario_cotizacion === null) {
    $where_cot_latest = ["1=1"];
    $params_cot_latest = [];

    if ($fecha_desde !== '') {
        $where_cot_latest[] = "c.`{$col_fecha_cotizacion_actividad}` >= ?";
        $params_cot_latest[] = $fecha_desde . ' 00:00:00';
    }
    if ($fecha_hasta !== '') {
        $where_cot_latest[] = "c.`{$col_fecha_cotizacion_actividad}` <= ?";
        $params_cot_latest[] = $fecha_hasta . ' 23:59:59';
    }

    $sql_cot_latest = "SELECT
        0 AS usuario_id,
        'Sin vendedor asignado' AS usuario_nombre,
        'cotizacion' AS origen_tarea,
        'cotizacion' AS etapa,
        c.`{$col_fecha_cotizacion_actividad}` AS created_at,
        NULL AS estado_item,
        NULL AS numero_item,
        NULL AS codigo_barcode,
        NULL AS producto_nombre,
        NULL AS numero_pedido,
        c.numero_cotizacion,
        c.estado AS cotizacion_estado,
        c.total AS cotizacion_total
    FROM ecommerce_cotizaciones c
    WHERE " . implode(' AND ', $where_cot_latest) . "
    ORDER BY c.`{$col_fecha_cotizacion_actividad}` DESC
    LIMIT 1";

    $stmt = $pdo->prepare($sql_cot_latest);
    $stmt->execute($params_cot_latest);
    $actividad_cotizaciones_reciente = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($actividad_cotizaciones_reciente)) {
        $actividad_actual[] = $actividad_cotizaciones_reciente[0];
        usort($actividad_actual, static function (array $a, array $b): int {
            $ta = !empty($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
            $tb = !empty($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
            return $tb <=> $ta;
        });
    }
}

$where_resumen = [];
$params_resumen = [];
if ($usuario_id_filtro > 0) {
    $where_resumen[] = "u.id = ?";
    $params_resumen[] = $usuario_id_filtro;
}
if ($etapa_filtro !== '') {
    $where_resumen[] = "s.etapa = ?";
    $params_resumen[] = $etapa_filtro;
}
if ($fecha_desde !== '') {
    $where_resumen[] = "s.created_at >= ?";
    $params_resumen[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta !== '') {
    $where_resumen[] = "s.created_at <= ?";
    $params_resumen[] = $fecha_hasta . ' 23:59:59';
}

$sql_resumen = "SELECT
    u.id AS usuario_id,
    u.nombre AS usuario_nombre,
    SUM(CASE WHEN s.etapa = 'corte' THEN 1 ELSE 0 END) AS cortes,
    SUM(CASE WHEN s.etapa = 'armado' THEN 1 ELSE 0 END) AS armados,
    SUM(CASE WHEN s.etapa = 'terminado' THEN 1 ELSE 0 END) AS terminados,
    COUNT(*) AS total
FROM ecommerce_produccion_scans s
JOIN usuarios u ON u.id = s.usuario_id";

if (!empty($where_resumen)) {
    $sql_resumen .= " WHERE " . implode(' AND ', $where_resumen);
}

$sql_resumen .= " GROUP BY u.id, u.nombre ORDER BY total DESC, u.nombre ASC";

$stmt = $pdo->prepare($sql_resumen);
$stmt->execute($params_resumen);
$resumen_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resumen_vendedores_hoy = [];
try {
    $resumen_indexado = [];

    if (admin_table_exists($pdo, 'ecommerce_cotizaciones')) {
        $col_fecha_cotizacion = admin_pick_column($cols_cotizaciones, ['fecha_creacion', 'created_at', 'fecha_actualizacion']);
        $col_fecha_cierre = admin_pick_column($cols_cotizaciones, ['fecha_actualizacion', 'fecha_envio']) ?: $col_fecha_cotizacion;

        if ($col_fecha_cotizacion && $col_fecha_cierre) {
            $where_cot_hoy = ["1=1"];
            $params_cot_hoy = [];

            if ($col_usuario_cotizacion !== null && $usuario_id_filtro > 0) {
                $where_cot_hoy[] = "c.`{$col_usuario_cotizacion}` = ?";
                $params_cot_hoy[] = $usuario_id_filtro;
            } elseif ($col_usuario_cotizacion === null && $usuario_id_filtro > 0) {
                $where_cot_hoy[] = "1=0";
            }

            $usuario_id_expr = $col_usuario_cotizacion !== null ? "c.`{$col_usuario_cotizacion}`" : "0";
            $usuario_nombre_expr = $col_usuario_cotizacion !== null
                ? "COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(u.usuario), ''), CONCAT('Usuario #', c.`{$col_usuario_cotizacion}`, ' (sin registro)'))"
                : "'Sin vendedor asignado'";
            $join_usuario = $col_usuario_cotizacion !== null
                ? "LEFT JOIN usuarios u ON u.id = c.`{$col_usuario_cotizacion}`"
                : "";
            $group_usuario = $col_usuario_cotizacion !== null
                ? "GROUP BY c.`{$col_usuario_cotizacion}`, u.nombre, u.usuario"
                : "GROUP BY usuario_id, usuario_nombre";

            $sql_cot_hoy = "SELECT
                {$usuario_id_expr} AS usuario_id,
                {$usuario_nombre_expr} AS usuario_nombre,
                SUM(CASE
                    WHEN c.`{$col_fecha_cotizacion}` IS NOT NULL
                     AND DATE(c.`{$col_fecha_cotizacion}`) = CURDATE()
                    THEN 1 ELSE 0 END) AS cotizaciones_hoy,
                SUM(CASE
                    WHEN LOWER(COALESCE(c.estado, '')) IN ('convertida', 'aceptada', 'cerrada', 'cerrado')
                     AND c.`{$col_fecha_cierre}` IS NOT NULL
                     AND DATE(c.`{$col_fecha_cierre}`) = CURDATE()
                    THEN 1 ELSE 0 END) AS pedidos_cerrados_hoy
            FROM ecommerce_cotizaciones c
                    {$join_usuario}
            WHERE " . implode(' AND ', $where_cot_hoy) . "
                    {$group_usuario}";

            $stmt = $pdo->prepare($sql_cot_hoy);
            $stmt->execute($params_cot_hoy);
            $rows_cot_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows_cot_hoy as $row) {
                $uid = (int)($row['usuario_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                $resumen_indexado[$uid] = [
                    'usuario_id' => $uid,
                    'usuario_nombre' => $row['usuario_nombre'] ?? ('Usuario #' . $uid),
                    'cotizaciones_hoy' => (int)($row['cotizaciones_hoy'] ?? 0),
                    'pedidos_hoy' => 0,
                    'pedidos_cerrados_hoy' => (int)($row['pedidos_cerrados_hoy'] ?? 0),
                ];
            }
        }
    }

    if (admin_table_exists($pdo, 'ecommerce_pedidos')) {
        $cols_pedidos = admin_get_table_columns($pdo, 'ecommerce_pedidos');
        $col_usuario_pedido = null;
        foreach (['usuario_id', 'creado_por', 'vendedor_id', 'user_id'] as $candidate_col_usuario) {
            if (in_array($candidate_col_usuario, $cols_pedidos, true)) {
                $col_usuario_pedido = $candidate_col_usuario;
                break;
            }
        }

        $col_fecha_pedido_hoy = null;
        foreach (['fecha_pedido', 'fecha_creacion', 'created_at', 'fecha_actualizacion'] as $candidate_col_fecha) {
            if (in_array($candidate_col_fecha, $cols_pedidos, true)) {
                $col_fecha_pedido_hoy = $candidate_col_fecha;
                break;
            }
        }

        if ($col_usuario_pedido && $col_fecha_pedido_hoy) {
            $where_ped_hoy = ["p.`{$col_usuario_pedido}` IS NOT NULL"];
            $params_ped_hoy = [];

            if ($usuario_id_filtro > 0) {
                $where_ped_hoy[] = "p.`{$col_usuario_pedido}` = ?";
                $params_ped_hoy[] = $usuario_id_filtro;
            }

            $sql_ped_hoy = "SELECT
                p.`{$col_usuario_pedido}` AS usuario_id,
                COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(u.usuario), ''), CONCAT('Usuario #', p.`{$col_usuario_pedido}`, ' (sin registro)')) AS usuario_nombre,
                SUM(CASE
                    WHEN p.`{$col_fecha_pedido_hoy}` IS NOT NULL
                     AND DATE(p.`{$col_fecha_pedido_hoy}`) = CURDATE()
                     AND LOWER(COALESCE(p.estado, '')) NOT IN ('cancelado', 'cancelada')
                    THEN 1 ELSE 0 END) AS pedidos_hoy
            FROM ecommerce_pedidos p
            LEFT JOIN usuarios u ON u.id = p.`{$col_usuario_pedido}`
            WHERE " . implode(' AND ', $where_ped_hoy) . "
            GROUP BY p.`{$col_usuario_pedido}`, u.nombre, u.usuario";

            $stmt = $pdo->prepare($sql_ped_hoy);
            $stmt->execute($params_ped_hoy);
            $rows_ped_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows_ped_hoy as $row) {
                $uid = (int)($row['usuario_id'] ?? 0);
                if ($uid <= 0) {
                    continue;
                }
                if (!isset($resumen_indexado[$uid])) {
                    $resumen_indexado[$uid] = [
                        'usuario_id' => $uid,
                        'usuario_nombre' => $row['usuario_nombre'] ?? ('Usuario #' . $uid),
                        'cotizaciones_hoy' => 0,
                        'pedidos_hoy' => 0,
                        'pedidos_cerrados_hoy' => 0,
                    ];
                }
                $resumen_indexado[$uid]['pedidos_hoy'] = (int)($row['pedidos_hoy'] ?? 0);
                if (!empty($row['usuario_nombre'])) {
                    $resumen_indexado[$uid]['usuario_nombre'] = $row['usuario_nombre'];
                }
            }
        }
    }

    $resumen_vendedores_hoy = array_values(array_filter($resumen_indexado, static function (array $row): bool {
        return (int)($row['cotizaciones_hoy'] ?? 0) > 0
            || (int)($row['pedidos_hoy'] ?? 0) > 0
            || (int)($row['pedidos_cerrados_hoy'] ?? 0) > 0;
    }));

    usort($resumen_vendedores_hoy, static function (array $a, array $b): int {
        $cierre = ((int)($b['pedidos_cerrados_hoy'] ?? 0)) <=> ((int)($a['pedidos_cerrados_hoy'] ?? 0));
        if ($cierre !== 0) {
            return $cierre;
        }
        $pedidos = ((int)($b['pedidos_hoy'] ?? 0)) <=> ((int)($a['pedidos_hoy'] ?? 0));
        if ($pedidos !== 0) {
            return $pedidos;
        }
        $cotizaciones = ((int)($b['cotizaciones_hoy'] ?? 0)) <=> ((int)($a['cotizaciones_hoy'] ?? 0));
        if ($cotizaciones !== 0) {
            return $cotizaciones;
        }
        return strcasecmp((string)($a['usuario_nombre'] ?? ''), (string)($b['usuario_nombre'] ?? ''));
    });
} catch (Throwable $e) {
    $resumen_vendedores_hoy = [];
}

$actividad_cotizaciones = [];
try {
    if (
        admin_table_exists($pdo, 'ecommerce_cotizaciones')
    ) {
        $col_fecha_cot = admin_pick_column($cols_cotizaciones, ['fecha_creacion', 'created_at', 'fecha_actualizacion']);

        if ($col_fecha_cot) {
            $where_cot = ["1=1"];
            $params_cot = [];

            if ($col_usuario_cotizacion !== null && $usuario_id_filtro > 0) {
                $where_cot[] = "c.`{$col_usuario_cotizacion}` = ?";
                $params_cot[] = $usuario_id_filtro;
            } elseif ($col_usuario_cotizacion === null && $usuario_id_filtro > 0) {
                $where_cot[] = "1=0";
            }
            if ($fecha_desde !== '') {
                $where_cot[] = "c.`{$col_fecha_cot}` >= ?";
                $params_cot[] = $fecha_desde . ' 00:00:00';
            }
            if ($fecha_hasta !== '') {
                $where_cot[] = "c.`{$col_fecha_cot}` <= ?";
                $params_cot[] = $fecha_hasta . ' 23:59:59';
            }

            $sql_cotizaciones = "SELECT
                c.id,
                c.numero_cotizacion,
                c.estado,
                c.total,
                c.`{$col_fecha_cot}` AS fecha_cotizacion,
                " . (
                    $col_usuario_cotizacion !== null
                        ? "COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(u.usuario), ''), CONCAT('Usuario #', c.`{$col_usuario_cotizacion}`, ' (sin registro)'))"
                        : "'Sin vendedor asignado'"
                ) . " AS usuario_nombre
            FROM ecommerce_cotizaciones c
            " . (
                $col_usuario_cotizacion !== null
                    ? "LEFT JOIN usuarios u ON u.id = c.`{$col_usuario_cotizacion}`"
                    : ""
            ) . "
            WHERE " . implode(' AND ', $where_cot) . "
            ORDER BY c.`{$col_fecha_cot}` DESC
            LIMIT 80";

            $stmt = $pdo->prepare($sql_cotizaciones);
            $stmt->execute($params_cot);
            $actividad_cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $actividad_cotizaciones = [];
}

$tareas_asignadas = [];
try {
    $where_tareas = ["1=1"];
    $params_tareas = [];

    if ($usuario_id_filtro > 0) {
        $where_tareas[] = "t.usuario_id = ?";
        $params_tareas[] = $usuario_id_filtro;
    }
    if ($fecha_desde !== '') {
        $where_tareas[] = "t.fecha_asignacion >= ?";
        $params_tareas[] = $fecha_desde . ' 00:00:00';
    }
    if ($fecha_hasta !== '') {
        $where_tareas[] = "t.fecha_asignacion <= ?";
        $params_tareas[] = $fecha_hasta . ' 23:59:59';
    }

    $sql_tareas = "SELECT
        t.*,
        COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS usuario_nombre,
        COALESCE(NULLIF(TRIM(a.nombre), ''), a.usuario) AS asignada_por_nombre
    FROM ecommerce_tareas_usuarios t
    JOIN usuarios u ON u.id = t.usuario_id
    LEFT JOIN usuarios a ON a.id = t.asignada_por
    WHERE " . implode(' AND ', $where_tareas) . "
    ORDER BY FIELD(t.estado, 'pendiente', 'en_progreso', 'completada', 'cancelada'), t.fecha_asignacion DESC
    LIMIT 120";

    $stmt = $pdo->prepare($sql_tareas);
    $stmt->execute($params_tareas);
    $tareas_asignadas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $tareas_asignadas = [];
}

$where_informe = [];
$params_informe = [];
if ($usuario_id_filtro > 0) {
    $where_informe[] = "s.usuario_id = ?";
    $params_informe[] = $usuario_id_filtro;
}
if ($etapa_filtro !== '') {
    $where_informe[] = "s.etapa = ?";
    $params_informe[] = $etapa_filtro;
}
if ($fecha_desde !== '') {
    $where_informe[] = "s.created_at >= ?";
    $params_informe[] = $fecha_desde . ' 00:00:00';
}
if ($fecha_hasta !== '') {
    $where_informe[] = "s.created_at <= ?";
    $params_informe[] = $fecha_hasta . ' 23:59:59';
}

$sql_informe = "SELECT
    t.usuario_id,
    t.usuario_nombre,
    t.etapa,
    COUNT(*) AS total_registros,
    SUM(CASE WHEN t.duracion_min IS NOT NULL AND t.duracion_min >= 0 THEN 1 ELSE 0 END) AS con_tiempo,
    AVG(CASE WHEN t.duracion_min IS NOT NULL AND t.duracion_min >= 0 THEN t.duracion_min END) AS promedio_min,
    MIN(CASE WHEN t.duracion_min IS NOT NULL AND t.duracion_min >= 0 THEN t.duracion_min END) AS minimo_min,
    MAX(CASE WHEN t.duracion_min IS NOT NULL AND t.duracion_min >= 0 THEN t.duracion_min END) AS maximo_min
FROM (
    SELECT
        s.usuario_id,
        u.nombre AS usuario_nombre,
        s.etapa,
        s.produccion_item_id,
        s.created_at,
        CASE
            WHEN s.etapa = 'corte' THEN TIMESTAMPDIFF(MINUTE, s.created_at, (
                SELECT MIN(s2.created_at)
                FROM ecommerce_produccion_scans s2
                WHERE s2.produccion_item_id = s.produccion_item_id
                  AND s2.etapa = 'armado'
                  AND s2.created_at > s.created_at
            ))
            WHEN s.etapa = 'armado' THEN TIMESTAMPDIFF(MINUTE, s.created_at, (
                SELECT MIN(s3.created_at)
                FROM ecommerce_produccion_scans s3
                WHERE s3.produccion_item_id = s.produccion_item_id
                  AND s3.etapa = 'terminado'
                  AND s3.created_at > s.created_at
            ))
            ELSE NULL
        END AS duracion_min
    FROM ecommerce_produccion_scans s
    JOIN usuarios u ON u.id = s.usuario_id";

if (!empty($where_informe)) {
    $sql_informe .= " WHERE " . implode(' AND ', $where_informe);
}

$sql_informe .= ") t
GROUP BY t.usuario_id, t.usuario_nombre, t.etapa
ORDER BY t.usuario_nombre ASC, FIELD(t.etapa, 'corte', 'armado', 'terminado')";

$stmt = $pdo->prepare($sql_informe);
$stmt->execute($params_informe);
$informe_tiempos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function format_minutos(?float $minutos): string {
    if ($minutos === null) {
        return '-';
    }
    $minutos = (int)round($minutos);
    if ($minutos < 60) {
        return $minutos . ' min';
    }
    $horas = intdiv($minutos, 60);
    $resto = $minutos % 60;
    if ($resto === 0) {
        return $horas . ' h';
    }
    return $horas . ' h ' . $resto . ' min';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>👷 Dashboard de Tareas por Usuario</h1>
        <p class="text-muted mb-0">Última tarea, volumen y tiempos por usuario</p>
    </div>
    <div>
        <a href="ordenes_produccion.php" class="btn btn-outline-secondary">← Volver a Órdenes</a>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <select name="usuario_id" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios_filtro as $usuario): ?>
                        <option value="<?= (int)$usuario['id'] ?>" <?= $usuario_id_filtro === (int)$usuario['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($usuario['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Etapa</label>
                <select name="etapa" class="form-select">
                    <option value="">Todas</option>
                    <option value="corte" <?= $etapa_filtro === 'corte' ? 'selected' : '' ?>>Corte</option>
                    <option value="armado" <?= $etapa_filtro === 'armado' ? 'selected' : '' ?>>Armado</option>
                    <option value="terminado" <?= $etapa_filtro === 'terminado' ? 'selected' : '' ?>>Terminado</option>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>

            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Aplicar</button>
                <a href="produccion_tareas_usuarios.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($mensaje_tareas)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje_tareas) ?></div>
<?php endif; ?>
<?php if (!empty($error_tareas)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error_tareas) ?></div>
<?php endif; ?>

<?php if (($role ?? '') === 'admin'): ?>
<div class="card mb-4">
    <div class="card-header bg-dark text-white">
        <h5 class="mb-0">Asignar tarea manual</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <input type="hidden" name="accion_tarea" value="asignar">
            <div class="col-md-3">
                <label class="form-label">Usuario</label>
                <select name="tarea_usuario_id" class="form-select" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($usuarios_filtro as $usuario): ?>
                        <option value="<?= (int)$usuario['id'] ?>"><?= htmlspecialchars($usuario['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Título</label>
                <input type="text" name="tarea_titulo" class="form-control" placeholder="Ej: Ir a comprar tornillos" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Descripción</label>
                <input type="text" name="tarea_descripcion" class="form-control" placeholder="Detalle opcional">
            </div>
            <div class="col-md-2">
                <label class="form-label">Fecha límite</label>
                <input type="date" name="tarea_fecha_limite" class="form-control">
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-primary">Asignar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">Tareas manuales asignadas</h5>
    </div>
    <div class="card-body">
        <?php if (empty($tareas_asignadas)): ?>
            <div class="alert alert-info mb-0">No hay tareas manuales en el rango seleccionado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Tarea</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Límite</th>
                            <th>Asignada</th>
                            <th>Por</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tareas_asignadas as $t): ?>
                            <?php
                                $badge_estado = 'secondary';
                                if (($t['estado'] ?? '') === 'pendiente') $badge_estado = 'warning text-dark';
                                if (($t['estado'] ?? '') === 'en_progreso') $badge_estado = 'primary';
                                if (($t['estado'] ?? '') === 'completada') $badge_estado = 'success';
                                if (($t['estado'] ?? '') === 'cancelada') $badge_estado = 'danger';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($t['usuario_nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($t['titulo']) ?></td>
                                <td><?= htmlspecialchars($t['descripcion'] ?? '-') ?></td>
                                <td>
                                    <?php if (($role ?? '') === 'admin'): ?>
                                        <form method="POST" class="d-flex gap-2 align-items-center">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                                            <input type="hidden" name="accion_tarea" value="cambiar_estado">
                                            <input type="hidden" name="tarea_id" value="<?= (int)$t['id'] ?>">
                                            <select name="nuevo_estado" class="form-select form-select-sm" style="min-width: 145px" onchange="this.form.submit()">
                                                <option value="pendiente" <?= ($t['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                                                <option value="en_progreso" <?= ($t['estado'] ?? '') === 'en_progreso' ? 'selected' : '' ?>>En progreso</option>
                                                <option value="completada" <?= ($t['estado'] ?? '') === 'completada' ? 'selected' : '' ?>>Completada</option>
                                                <option value="cancelada" <?= ($t['estado'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                                            </select>
                                        </form>
                                    <?php else: ?>
                                        <span class="badge bg-<?= $badge_estado ?>"><?= strtoupper(htmlspecialchars((string)$t['estado'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($t['fecha_limite']) ? date('d/m/Y', strtotime((string)$t['fecha_limite'])) : '-' ?></td>
                                <td><?= !empty($t['fecha_asignacion']) ? date('d/m/Y H:i', strtotime((string)$t['fecha_asignacion'])) : '-' ?></td>
                                <td><?= htmlspecialchars($t['asignada_por_nombre'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Qué está haciendo cada usuario (último escaneo del rango)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($actividad_actual)): ?>
            <div class="alert alert-info mb-0">Todavía no hay escaneos registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Tarea</th>
                            <th>Producto</th>
                            <th>Orden</th>
                            <th>Item</th>
                            <th>Código</th>
                            <th>Estado item</th>
                            <th>Último escaneo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actividad_actual as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td>
                                    <?php
                                        $badge = 'secondary';
                                        if ($row['etapa'] === 'corte') $badge = 'danger';
                                        if ($row['etapa'] === 'armado') $badge = 'warning text-dark';
                                        if ($row['etapa'] === 'terminado') $badge = 'success';
                                        if (($row['origen_tarea'] ?? '') === 'cotizacion') $badge = 'info';
                                        if (($row['origen_tarea'] ?? '') === 'tarea_manual') $badge = 'dark';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars($row['etapa'])) ?></span>
                                </td>
                                <td>
                                    <?php if (($row['origen_tarea'] ?? '') === 'cotizacion'): ?>
                                        Cotización comercial
                                    <?php elseif (($row['origen_tarea'] ?? '') === 'tarea_manual'): ?>
                                        <?= htmlspecialchars($row['producto_nombre'] ?? 'Tarea manual') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['producto_nombre'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($row['origen_tarea'] ?? '') === 'cotizacion'): ?>
                                        <?= htmlspecialchars($row['numero_cotizacion'] ?? '-') ?>
                                    <?php elseif (($row['origen_tarea'] ?? '') === 'tarea_manual'): ?>
                                        <?= htmlspecialchars($row['tarea_descripcion'] ?? '-') ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['numero_pedido'] ?? '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['numero_item'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($row['codigo_barcode'] ?? '-') ?></code></td>
                                <td>
                                    <?php if (($row['origen_tarea'] ?? '') === 'cotizacion'): ?>
                                        <?= htmlspecialchars(strtoupper((string)($row['cotizacion_estado'] ?? '-'))) ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars(strtoupper(str_replace('_', ' ', (string)($row['estado_item'] ?? '-')))) ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= !empty($row['created_at']) ? date('d/m/Y H:i:s', strtotime($row['created_at'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">Resumen por usuario (rango filtrado)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($resumen_hoy)): ?>
            <div class="alert alert-info mb-0">Sin escaneos registrados para el rango filtrado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Corte</th>
                            <th>Armado</th>
                            <th>Terminado</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen_hoy as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td><?= (int)$row['cortes'] ?></td>
                                <td><?= (int)$row['armados'] ?></td>
                                <td><?= (int)$row['terminados'] ?></td>
                                <td><strong><?= (int)$row['total'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">Cierre del día por usuario</h5>
    </div>
    <div class="card-body">
        <?php if (empty($resumen_vendedores_hoy)): ?>
            <div class="alert alert-info mb-0">Sin cotizaciones, pedidos ni cierres registrados hoy por usuario.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Cotizaciones hoy</th>
                            <th>Pedidos hoy</th>
                            <th>Pedidos cerrados hoy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen_vendedores_hoy as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td><?= (int)$row['cotizaciones_hoy'] ?></td>
                                <td><?= (int)($row['pedidos_hoy'] ?? 0) ?></td>
                                <td><strong><?= (int)$row['pedidos_cerrados_hoy'] ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-secondary text-white">
        <h5 class="mb-0">Actividad de cotizaciones por usuario</h5>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <span class="badge bg-dark">Total cotizaciones en el rango: <?= count($actividad_cotizaciones) ?></span>
        </div>
        <?php if (empty($actividad_cotizaciones)): ?>
            <div class="alert alert-info mb-0">No hay cotizaciones en el rango seleccionado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>N° Cotización</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actividad_cotizaciones as $row): ?>
                            <?php
                                $badge = 'secondary';
                                if (($row['estado'] ?? '') === 'pendiente') $badge = 'warning text-dark';
                                if (($row['estado'] ?? '') === 'enviada') $badge = 'info';
                                if (($row['estado'] ?? '') === 'aceptada') $badge = 'success';
                                if (($row['estado'] ?? '') === 'rechazada') $badge = 'danger';
                                if (($row['estado'] ?? '') === 'convertida') $badge = 'primary';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td>
                                    <a href="cotizacion_detalle.php?id=<?= (int)$row['id'] ?>" class="text-decoration-none fw-semibold">
                                        <?= htmlspecialchars($row['numero_cotizacion'] ?? ('COT-' . (int)$row['id'])) ?>
                                    </a>
                                </td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars((string)($row['estado'] ?? '-'))) ?></span></td>
                                <td>$<?= number_format((float)($row['total'] ?? 0), 2, ',', '.') ?></td>
                                <td><?= !empty($row['fecha_cotizacion']) ? date('d/m/Y H:i', strtotime((string)$row['fecha_cotizacion'])) : '-' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">Informe de tiempos por tarea y usuario</h5>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            Corte: tiempo desde escaneo de corte hasta primer armado del mismo ítem. Armado: tiempo hasta primer terminado. Terminado no tiene etapa siguiente para medir.
        </p>
        <?php if (empty($informe_tiempos)): ?>
            <div class="alert alert-info mb-0">No hay datos suficientes para calcular tiempos en el rango seleccionado.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Etapa</th>
                            <th>Registros</th>
                            <th>Con tiempo medible</th>
                            <th>Promedio</th>
                            <th>Mínimo</th>
                            <th>Máximo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($informe_tiempos as $row): ?>
                            <?php
                                $badge = 'secondary';
                                if ($row['etapa'] === 'corte') $badge = 'danger';
                                if ($row['etapa'] === 'armado') $badge = 'warning text-dark';
                                if ($row['etapa'] === 'terminado') $badge = 'success';
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td><span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars($row['etapa'])) ?></span></td>
                                <td><?= (int)$row['total_registros'] ?></td>
                                <td><?= (int)$row['con_tiempo'] ?></td>
                                <td><strong><?= format_minutos(isset($row['promedio_min']) ? (float)$row['promedio_min'] : null) ?></strong></td>
                                <td><?= format_minutos(isset($row['minimo_min']) ? (float)$row['minimo_min'] : null) ?></td>
                                <td><?= format_minutos(isset($row['maximo_min']) ? (float)$row['maximo_min'] : null) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php';
