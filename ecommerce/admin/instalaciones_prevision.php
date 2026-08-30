<?php
require 'includes/header.php';

function valor_fecha_valido_prevision($valor) {
    if ($valor === '' || $valor === null) {
        return true;
    }
    $dt = DateTime::createFromFormat('Y-m-d', $valor);
    return $dt && $dt->format('Y-m-d') === $valor;
}

$hoy = date('Y-m-d');
$en_13_dias = date('Y-m-d', strtotime('+13 days'));

$instalacion_desde = $_GET['instalacion_desde'] ?? $hoy;
$instalacion_hasta = $_GET['instalacion_hasta'] ?? $en_13_dias;
$incluir_entregados = array_key_exists('incluir_entregados', $_GET) ? !empty($_GET['incluir_entregados']) : true;

if (!valor_fecha_valido_prevision($instalacion_desde)) {
    $instalacion_desde = $hoy;
}
if (!valor_fecha_valido_prevision($instalacion_hasta)) {
    $instalacion_hasta = $en_13_dias;
}
if ($instalacion_desde > $instalacion_hasta) {
    $tmp = $instalacion_desde;
    $instalacion_desde = $instalacion_hasta;
    $instalacion_hasta = $tmp;
}

// Límite razonable de días para no disparar consultas ni tablas gigantes.
$MAX_DIAS_PREVISION = 90;
$span_dias = (int)round((strtotime($instalacion_hasta) - strtotime($instalacion_desde)) / 86400) + 1;
$prevision_truncada = false;
if ($span_dias > $MAX_DIAS_PREVISION) {
    $instalacion_hasta = date('Y-m-d', strtotime($instalacion_desde . ' +' . ($MAX_DIAS_PREVISION - 1) . ' days'));
    $prevision_truncada = true;
}

$tiene_clientes = admin_table_exists($pdo, 'ecommerce_clientes');
$tablas_base_ok = admin_table_exists($pdo, 'ecommerce_ordenes_produccion') && admin_table_exists($pdo, 'ecommerce_pedidos');
$tiene_pagos = admin_table_exists($pdo, 'ecommerce_pedido_pagos');
$tiene_instalaciones_manuales = admin_table_exists($pdo, 'ecommerce_instalaciones_manuales');
$tiene_visitas = admin_table_exists($pdo, 'ecommerce_visitas');

$manuales_con_cliente = $tiene_instalaciones_manuales && admin_column_exists($pdo, 'ecommerce_instalaciones_manuales', 'cliente_id');
$visitas_con_cliente = $tiene_visitas && admin_column_exists($pdo, 'ecommerce_visitas', 'cliente_id');

$items = [];
$error_pagina = '';

try {
    if (!$tablas_base_ok) {
        throw new RuntimeException('Faltan tablas base requeridas (ecommerce_ordenes_produccion / ecommerce_pedidos).');
    }

    // Órdenes de producción con fecha de instalación en el rango.
    $sql_ordenes = "
        SELECT op.id, op.pedido_id, op.fecha_instalacion,
               p.numero_pedido, p.cliente_id, p.envio_nombre
        FROM ecommerce_ordenes_produccion op
        JOIN ecommerce_pedidos p ON op.pedido_id = p.id
        WHERE op.fecha_instalacion BETWEEN ? AND ?
          AND " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'");
    $stmt = $pdo->prepare($sql_ordenes);
    $stmt->execute([$instalacion_desde, $instalacion_hasta]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $items[] = [
            'tipo' => 'Orden',
            'titulo' => 'Pedido ' . ($row['numero_pedido'] ?: ('#' . (int)$row['pedido_id'])),
            'fecha' => $row['fecha_instalacion'],
            'cliente_id' => !empty($row['cliente_id']) ? (int)$row['cliente_id'] : null,
            'cliente_nombre_libre' => trim($row['envio_nombre'] ?? ''),
            'url' => 'orden_produccion_detalle.php?pedido_id=' . (int)$row['pedido_id'],
        ];
    }

    // Instalaciones manuales con fecha en el rango.
    if ($tiene_instalaciones_manuales) {
        $select_cliente_id = $manuales_con_cliente ? 'cliente_id' : 'NULL AS cliente_id';
        $stmt = $pdo->prepare("
            SELECT id, titulo, cliente, fecha_instalacion, {$select_cliente_id}
            FROM ecommerce_instalaciones_manuales
            WHERE fecha_instalacion BETWEEN ? AND ?
        ");
        $stmt->execute([$instalacion_desde, $instalacion_hasta]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'tipo' => 'Manual',
                'titulo' => trim($row['titulo'] ?? 'Instalación manual'),
                'fecha' => $row['fecha_instalacion'],
                'cliente_id' => !empty($row['cliente_id']) ? (int)$row['cliente_id'] : null,
                'cliente_nombre_libre' => trim($row['cliente'] ?? ''),
                'url' => '',
            ];
        }
    }

    // Visitas con fecha en el rango.
    if ($tiene_visitas) {
        $select_cliente_id = $visitas_con_cliente ? 'cliente_id' : 'NULL AS cliente_id';
        $stmt = $pdo->prepare("
            SELECT id, titulo, cliente_nombre, fecha_visita, {$select_cliente_id}
            FROM ecommerce_visitas
            WHERE fecha_visita BETWEEN ? AND ?
        ");
        $stmt->execute([$instalacion_desde, $instalacion_hasta]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'tipo' => 'Visita',
                'titulo' => trim($row['titulo'] ?? 'Visita'),
                'fecha' => $row['fecha_visita'],
                'cliente_id' => !empty($row['cliente_id']) ? (int)$row['cliente_id'] : null,
                'cliente_nombre_libre' => trim($row['cliente_nombre'] ?? ''),
                'url' => '',
            ];
        }
    }
} catch (Exception $e) {
    $error_pagina = $e->getMessage();
}

// Orden cronológico: es clave para no contar dos veces el saldo de un mismo cliente.
usort($items, function ($a, $b) {
    return strcmp($a['fecha'], $b['fecha']);
});

// Saldos actuales de los clientes involucrados (misma fórmula que Facturación por Cliente:
// total de pedidos no cancelados menos total pagado).
$saldos_por_cliente = [];
if ($tiene_clientes) {
    $cliente_ids = array_values(array_unique(array_filter(array_column($items, 'cliente_id'))));
    if (!empty($cliente_ids)) {
        $placeholders = implode(',', array_fill(0, count($cliente_ids), '?'));
        $join_pagos = $tiene_pagos ? "
            LEFT JOIN (
                SELECT p.cliente_id, SUM(pp.monto) AS total_pagado
                FROM ecommerce_pedido_pagos pp
                JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
                WHERE p.estado != 'cancelado'
                GROUP BY p.cliente_id
            ) pag ON pag.cliente_id = c.id
        " : '';
        $select_pagado = $tiene_pagos ? 'COALESCE(pag.total_pagado, 0)' : '0';
        $stmt = $pdo->prepare("
            SELECT c.id, c.nombre,
                   COALESCE(ped.total_pedidos, 0) - {$select_pagado} AS saldo
            FROM ecommerce_clientes c
            LEFT JOIN (
                SELECT cliente_id, SUM(total) AS total_pedidos
                FROM ecommerce_pedidos
                WHERE estado != 'cancelado'
                GROUP BY cliente_id
            ) ped ON ped.cliente_id = c.id
            {$join_pagos}
            WHERE c.id IN ($placeholders)
        ");
        $stmt->execute($cliente_ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $saldos_por_cliente[(int)$row['id']] = [
                'nombre' => $row['nombre'],
                'saldo' => (float)$row['saldo'],
            ];
        }
    }
}

// Agrupar por día y atribuir el saldo de cada cliente una sola vez, en su primera
// aparición dentro del rango, para no duplicar el mismo saldo en varios días.
$dias = [];
$clientes_contabilizados = [];
$total_ingreso = 0.0;
$total_egreso = 0.0;

foreach ($items as $item) {
    $fecha = $item['fecha'];
    if (!isset($dias[$fecha])) {
        $dias[$fecha] = ['items' => [], 'ingreso' => 0.0, 'egreso' => 0.0];
    }

    $detalle = $item;
    $detalle['saldo'] = null;
    $detalle['cliente_display'] = $item['cliente_nombre_libre'];
    $detalle['ya_contabilizado'] = false;

    if ($item['cliente_id'] && isset($saldos_por_cliente[$item['cliente_id']])) {
        $info = $saldos_por_cliente[$item['cliente_id']];
        $detalle['cliente_display'] = $info['nombre'];
        $detalle['saldo'] = $info['saldo'];

        if (in_array($item['cliente_id'], $clientes_contabilizados, true)) {
            $detalle['ya_contabilizado'] = true;
        } else {
            $clientes_contabilizados[] = $item['cliente_id'];
            if ($info['saldo'] > 0) {
                $dias[$fecha]['ingreso'] += $info['saldo'];
                $total_ingreso += $info['saldo'];
            } elseif ($info['saldo'] < 0) {
                $dias[$fecha]['egreso'] += abs($info['saldo']);
                $total_egreso += abs($info['saldo']);
            }
        }
    }

    $dias[$fecha]['items'][] = $detalle;
}

ksort($dias);

$acumulado = 0.0;
$dias_dom = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

$qs_form = http_build_query(array_filter([
    'incluir_entregados' => $incluir_entregados ? '1' : null,
]));
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="mb-1">Previsión de ingresos/egresos</h1>
        <p class="text-muted mb-0">Basada en el saldo actual de los clientes con instalación o visita agendada por día</p>
    </div>
    <a href="instalaciones.php" class="btn btn-outline-secondary">← Volver al tablero</a>
</div>

<div class="alert alert-secondary small">
    Por cada cliente con instalación/visita agendada: si nos debe (saldo positivo), se suma como <strong>ingreso previsto</strong> el día de su próxima cita dentro del rango; si le debemos (saldo negativo, pagó de más), se suma como <strong>egreso previsto</strong>.
    El saldo de un mismo cliente se cuenta <strong>una sola vez</strong> dentro del rango, aunque tenga varias citas. No incluye costos de producción, gastos operativos ni sueldos — para eso usá
    <a href="flujo_caja.php">Flujo de Caja</a> o <a href="gastos.php" target="_blank">Gastos</a>.
</div>

<?php if ($error_pagina !== ''): ?>
    <div class="alert alert-danger"><strong>Error al cargar:</strong> <?= htmlspecialchars($error_pagina) ?></div>
<?php endif; ?>

<?php if (!$tiene_clientes): ?>
    <div class="alert alert-warning">No existe la tabla <code>ecommerce_clientes</code>, no se puede calcular la previsión.</div>
<?php elseif ($tiene_instalaciones_manuales && !$manuales_con_cliente): ?>
    <div class="alert alert-warning">Las instalaciones manuales todavía no tienen la columna <code>cliente_id</code>. Entrá una vez a <a href="instalaciones.php">Instalaciones y visitas</a> para que se cree automáticamente.</div>
<?php elseif ($tiene_visitas && !$visitas_con_cliente): ?>
    <div class="alert alert-warning">Las visitas todavía no tienen la columna <code>cliente_id</code>. Entrá una vez a <a href="instalaciones.php">Instalaciones y visitas</a> para que se cree automáticamente.</div>
<?php endif; ?>

<?php if ($prevision_truncada): ?>
    <div class="alert alert-warning">El rango pedido es muy amplio; se muestran como máximo <?= (int)$MAX_DIAS_PREVISION ?> días desde <?= htmlspecialchars(date('d/m/Y', strtotime($instalacion_desde))) ?>.</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Desde</label>
                <input type="date" name="instalacion_desde" class="form-control form-control-sm" value="<?= htmlspecialchars($instalacion_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Hasta</label>
                <input type="date" name="instalacion_hasta" class="form-control form-control-sm" value="<?= htmlspecialchars($instalacion_hasta) ?>">
            </div>
            <div class="col-md-3">
                <div class="form-check form-check-sm mt-4">
                    <input class="form-check-input" type="checkbox" name="incluir_entregados" value="1" id="incluir_entregados" <?= $incluir_entregados ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="incluir_entregados">Incluir órdenes ya entregadas</label>
                </div>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary">Aplicar</button>
                <a href="instalaciones_prevision.php" class="btn btn-sm btn-outline-secondary">Limpiar</a>
            </div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="?instalacion_desde=<?= $hoy ?>&instalacion_hasta=<?= date('Y-m-d', strtotime('+6 days')) ?>&<?= $qs_form ?>" class="btn btn-sm btn-outline-dark">Próximos 7 días</a>
            <a href="?instalacion_desde=<?= $hoy ?>&instalacion_hasta=<?= date('Y-m-d', strtotime('+13 days')) ?>&<?= $qs_form ?>" class="btn btn-sm btn-outline-dark">Próximos 14 días</a>
            <a href="?instalacion_desde=<?= $hoy ?>&instalacion_hasta=<?= date('Y-m-d', strtotime('+29 days')) ?>&<?= $qs_form ?>" class="btn btn-sm btn-outline-dark">Próximos 30 días</a>
            <a href="?instalacion_desde=<?= date('Y-m-01') ?>&instalacion_hasta=<?= date('Y-m-t') ?>&<?= $qs_form ?>" class="btn btn-sm btn-outline-dark">Este mes</a>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="p-3 rounded" style="background:#d1e7dd;border-left:4px solid #198754;">
            <div class="text-muted small">Ingreso previsto (rango)</div>
            <div class="h4 mb-0 text-success">$<?= number_format($total_ingreso, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 rounded" style="background:#f8d7da;border-left:4px solid #dc3545;">
            <div class="text-muted small">Egreso previsto (rango)</div>
            <div class="h4 mb-0 text-danger">$<?= number_format($total_egreso, 2, ',', '.') ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="p-3 rounded" style="background:#e7f3ff;border-left:4px solid #0066cc;">
            <div class="text-muted small">Neto previsto (rango)</div>
            <div class="h4 mb-0" style="color: <?= ($total_ingreso - $total_egreso) >= 0 ? '#198754' : '#dc3545' ?>">$<?= number_format($total_ingreso - $total_egreso, 2, ',', '.') ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><h5 class="mb-0">Previsión por día</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Día</th>
                    <th>Agenda</th>
                    <th class="text-end">Ingreso previsto</th>
                    <th class="text-end">Egreso previsto</th>
                    <th class="text-end">Neto del día</th>
                    <th class="text-end">Acumulado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($dias)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">No hay instalaciones ni visitas agendadas en este rango.</td></tr>
                <?php else: ?>
                    <?php foreach ($dias as $fecha => $d):
                        $neto_dia = $d['ingreso'] - $d['egreso'];
                        $acumulado += $neto_dia;
                        $ts = strtotime($fecha);
                    ?>
                        <tr>
                            <td colspan="6" class="p-0">
                                <details>
                                    <summary class="d-flex flex-wrap gap-3 align-items-center px-3 py-2" style="cursor:pointer;">
                                        <strong style="min-width:140px;"><?= htmlspecialchars($dias_dom[(int)date('w', $ts)]) ?> <?= htmlspecialchars(date('d/m/Y', $ts)) ?></strong>
                                        <span class="text-muted small" style="min-width:160px;"><?= count($d['items']) ?> ítem(s) agendado(s)</span>
                                        <span class="text-success ms-auto" style="min-width:130px;">+$<?= number_format($d['ingreso'], 2, ',', '.') ?></span>
                                        <span class="text-danger" style="min-width:130px;">-$<?= number_format($d['egreso'], 2, ',', '.') ?></span>
                                        <span style="min-width:130px; color: <?= $neto_dia >= 0 ? '#198754' : '#dc3545' ?>">$<?= number_format($neto_dia, 2, ',', '.') ?></span>
                                        <span style="min-width:130px; color: <?= $acumulado >= 0 ? '#198754' : '#dc3545' ?>">$<?= number_format($acumulado, 2, ',', '.') ?></span>
                                    </summary>
                                    <div class="px-3 pb-3">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Tipo</th>
                                                    <th>Título</th>
                                                    <th>Cliente</th>
                                                    <th class="text-end">Saldo cliente</th>
                                                    <th></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($d['items'] as $it): ?>
                                                    <tr>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($it['tipo']) ?></span></td>
                                                        <td>
                                                            <?php if (!empty($it['url'])): ?>
                                                                <a href="<?= htmlspecialchars($it['url']) ?>" target="_blank"><?= htmlspecialchars($it['titulo']) ?></a>
                                                            <?php else: ?>
                                                                <?= htmlspecialchars($it['titulo']) ?>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($it['cliente_display'] ?: '-') ?></td>
                                                        <td class="text-end">
                                                            <?php if ($it['saldo'] === null): ?>
                                                                <span class="text-muted small">sin vincular</span>
                                                            <?php else: ?>
                                                                <span class="<?= $it['saldo'] > 0 ? 'text-danger' : ($it['saldo'] < 0 ? 'text-success' : 'text-muted') ?>">$<?= number_format($it['saldo'], 2, ',', '.') ?></span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php if ($it['ya_contabilizado']): ?>
                                                                <span class="badge bg-light text-dark border">ya contabilizado antes</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
