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

ensure_produccion_scans_schema($pdo);

$etapas_validas = ['corte', 'armado', 'terminado'];
$usuario_id_filtro = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;
$etapa_filtro = trim($_GET['etapa'] ?? '');
$fecha_desde = trim($_GET['fecha_desde'] ?? date('Y-m-d'));
$fecha_hasta = trim($_GET['fecha_hasta'] ?? date('Y-m-d'));

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

$stmt = $pdo->query("SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC");
$usuarios_filtro = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    s.etapa,
    s.created_at,
    pib.estado AS estado_item,
    pib.numero_item,
    pib.codigo_barcode,
    pr.nombre AS producto_nombre,
    p.numero_pedido
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
    if (
        admin_table_exists($pdo, 'usuarios')
        && admin_table_exists($pdo, 'roles')
        && admin_table_exists($pdo, 'ecommerce_cotizaciones')
        && admin_column_exists($pdo, 'usuarios', 'rol_id')
        && admin_column_exists($pdo, 'usuarios', 'activo')
        && admin_column_exists($pdo, 'ecommerce_cotizaciones', 'creado_por')
    ) {
        $col_fecha_cotizacion = admin_column_exists($pdo, 'ecommerce_cotizaciones', 'fecha_creacion')
            ? 'fecha_creacion'
            : null;

        $col_fecha_cierre = admin_column_exists($pdo, 'ecommerce_cotizaciones', 'fecha_actualizacion')
            ? 'fecha_actualizacion'
            : (
                admin_column_exists($pdo, 'ecommerce_cotizaciones', 'fecha_envio')
                    ? 'fecha_envio'
                    : $col_fecha_cotizacion
            );

        if ($col_fecha_cotizacion && $col_fecha_cierre) {
            $where_vendedores = [
                "COALESCE(u.activo, 1) = 1",
                "LOWER(COALESCE(r.nombre, '')) IN ('ventas', 'vendedor', 'vendedores')"
            ];
            $params_vendedores = [];

            if ($usuario_id_filtro > 0) {
                $where_vendedores[] = "u.id = ?";
                $params_vendedores[] = $usuario_id_filtro;
            }

            $sql_vendedores_hoy = "SELECT
                u.id AS usuario_id,
                COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS usuario_nombre,
                SUM(CASE
                    WHEN c.`{$col_fecha_cotizacion}` IS NOT NULL
                     AND DATE(c.`{$col_fecha_cotizacion}`) = CURDATE()
                    THEN 1 ELSE 0 END) AS cotizaciones_hoy,
                SUM(CASE
                    WHEN LOWER(COALESCE(c.estado, '')) = 'convertida'
                     AND c.`{$col_fecha_cierre}` IS NOT NULL
                     AND DATE(c.`{$col_fecha_cierre}`) = CURDATE()
                    THEN 1 ELSE 0 END) AS pedidos_cerrados_hoy
            FROM usuarios u
            INNER JOIN roles r ON r.id = u.rol_id
            LEFT JOIN ecommerce_cotizaciones c ON c.creado_por = u.id
            WHERE " . implode(' AND ', $where_vendedores) . "
            GROUP BY u.id, u.nombre, u.usuario
            HAVING cotizaciones_hoy > 0 OR pedidos_cerrados_hoy > 0
            ORDER BY pedidos_cerrados_hoy DESC, cotizaciones_hoy DESC, usuario_nombre ASC";

            $stmt = $pdo->prepare($sql_vendedores_hoy);
            $stmt->execute($params_vendedores);
            $resumen_vendedores_hoy = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }
} catch (Throwable $e) {
    $resumen_vendedores_hoy = [];
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
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= strtoupper(htmlspecialchars($row['etapa'])) ?></span>
                                </td>
                                <td><?= htmlspecialchars($row['producto_nombre'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['numero_pedido'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['numero_item'] ?? '-') ?></td>
                                <td><code><?= htmlspecialchars($row['codigo_barcode'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars(strtoupper(str_replace('_', ' ', (string)($row['estado_item'] ?? '-')))) ?></td>
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
        <h5 class="mb-0">Cierre del día por vendedor</h5>
    </div>
    <div class="card-body">
        <?php if (empty($resumen_vendedores_hoy)): ?>
            <div class="alert alert-info mb-0">Sin cierres registrados hoy para vendedores.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Vendedor</th>
                            <th>Cotizaciones hoy</th>
                            <th>Pedidos cerrados hoy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen_vendedores_hoy as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($row['usuario_nombre']) ?></strong></td>
                                <td><?= (int)$row['cotizaciones_hoy'] ?></td>
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
