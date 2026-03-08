<?php
require 'includes/header.php';

function tabla_existe($pdo, $tabla) {
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$tabla]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Exception $e) {
        // fallback abajo
    }

    try {
        $pdo->query("SELECT 1 FROM {$tabla} LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function columna_existe($pdo, $tabla, $columna) {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tabla} LIKE ?");
        $stmt->execute([$columna]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Exception $e) {
        // fallback abajo
    }

    try {
        $pdo->query("SELECT {$columna} FROM {$tabla} LIMIT 1");
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function asegurar_estructura_minima_instalaciones($pdo) {
    $mensajes = [];

    try {
        if (!tabla_existe($pdo, 'ecommerce_clientes')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_clientes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                telefono VARCHAR(50) NULL,
                activo TINYINT(1) DEFAULT 1,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_clientes (mínima).';
        }

        if (!tabla_existe($pdo, 'ecommerce_pedidos')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_pedidos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero_pedido VARCHAR(50) NULL,
                cliente_id INT NULL,
                envio_nombre VARCHAR(150) NULL,
                envio_telefono VARCHAR(50) NULL,
                envio_direccion VARCHAR(255) NULL,
                envio_localidad VARCHAR(120) NULL,
                envio_provincia VARCHAR(120) NULL,
                envio_codigo_postal VARCHAR(20) NULL,
                estado VARCHAR(30) DEFAULT 'pendiente',
                total DECIMAL(12,2) DEFAULT 0,
                fecha_pedido DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_cliente_id (cliente_id),
                INDEX idx_fecha_pedido (fecha_pedido)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_pedidos (mínima).';
        }

        if (!tabla_existe($pdo, 'ecommerce_ordenes_produccion')) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_ordenes_produccion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                estado VARCHAR(30) DEFAULT 'pendiente',
                notas TEXT NULL,
                fecha_entrega DATE NULL,
                fecha_instalacion DATE NULL,
                materiales_descontados TINYINT DEFAULT 0,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion DATETIME NULL,
                INDEX idx_pedido_id (pedido_id),
                INDEX idx_estado (estado)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $mensajes[] = 'Se creó tabla ecommerce_ordenes_produccion (mínima).';
        }

        if (tabla_existe($pdo, 'ecommerce_ordenes_produccion') && !columna_existe($pdo, 'ecommerce_ordenes_produccion', 'fecha_instalacion')) {
            $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN fecha_instalacion DATE NULL AFTER fecha_entrega");
            $mensajes[] = 'Se agregó columna fecha_instalacion.';
        }
    } catch (Exception $e) {
        error_log('asegurar_estructura_minima_instalaciones: ' . $e->getMessage());
        $mensajes[] = 'No se pudo completar auto-reparación de estructura: ' . $e->getMessage();
    }

    return $mensajes;
}

$fecha_desde = $_GET['fecha_desde'] ?? '';
$fecha_hasta = $_GET['fecha_hasta'] ?? '';
$instalacion_desde = $_GET['instalacion_desde'] ?? '';
$instalacion_hasta = $_GET['instalacion_hasta'] ?? '';
$incluir_entregados = !empty($_GET['incluir_entregados']);

$pedidos = [];
$pedidos_por_fecha = [];
$pedidos_sin_fecha = [];
$error_pagina = '';
$auto_setup_msg = '';

$mensajes_setup = asegurar_estructura_minima_instalaciones($pdo);
if (!empty($mensajes_setup)) {
    $auto_setup_msg = implode(' ', $mensajes_setup);
}

$tablas_base_ok = tabla_existe($pdo, 'ecommerce_ordenes_produccion') && tabla_existe($pdo, 'ecommerce_pedidos');
$tiene_clientes = tabla_existe($pdo, 'ecommerce_clientes');
$tiene_fecha_instalacion = false;

if ($tablas_base_ok) {
    $tiene_fecha_instalacion = columna_existe($pdo, 'ecommerce_ordenes_produccion', 'fecha_instalacion');

    if (!$tiene_fecha_instalacion) {
        try {
            $pdo->exec("ALTER TABLE ecommerce_ordenes_produccion ADD COLUMN fecha_instalacion DATE NULL AFTER fecha_entrega");
            $tiene_fecha_instalacion = columna_existe($pdo, 'ecommerce_ordenes_produccion', 'fecha_instalacion');
            if ($tiene_fecha_instalacion) {
                $auto_setup_msg = 'Se creó automáticamente la columna fecha_instalacion.';
            }
        } catch (Exception $e) {
            $auto_setup_msg = 'No se pudo crear automáticamente fecha_instalacion. Podés ejecutar ecommerce/setup_ordenes_produccion.php';
            error_log('instalaciones auto-setup: ' . $e->getMessage());
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'programar_instalacion') {
    $orden_ids = $_POST['orden_ids'] ?? [];
    $fecha_instalacion = trim($_POST['fecha_instalacion'] ?? '');

    if ($tablas_base_ok && $tiene_fecha_instalacion && !empty($orden_ids) && $fecha_instalacion !== '') {
        try {
            $ids = array_map('intval', $orden_ids);
            $ids = array_filter($ids, function ($v) { return $v > 0; });

            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $params = array_merge([$fecha_instalacion], $ids);
                $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET fecha_instalacion = ? WHERE id IN ($placeholders)");
                $stmt->execute($params);
            }
        } catch (Exception $e) {
            error_log('Error programando instalaciones: ' . $e->getMessage());
        }
    }

    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: instalaciones.php' . ($qs ? ('?' . $qs) : ''));
    exit;
}

try {
    if (!$tablas_base_ok) {
        throw new RuntimeException('Faltan tablas base requeridas (ecommerce_ordenes_produccion / ecommerce_pedidos).');
    }

    $select_fecha_inst = $tiene_fecha_instalacion ? 'op.fecha_instalacion AS fecha_instalacion' : 'NULL AS fecha_instalacion';
    $select_cliente = $tiene_clientes ? 'c.nombre AS cliente_nombre' : 'NULL AS cliente_nombre';
    $join_cliente = $tiene_clientes ? 'LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id' : '';

    $sql = "
        SELECT
            op.id AS orden_id,
            op.pedido_id,
            op.estado AS estado_produccion,
            op.fecha_entrega,
            {$select_fecha_inst},
            p.numero_pedido,
            p.envio_nombre,
            p.envio_telefono,
            p.envio_direccion,
            p.envio_localidad,
            p.envio_provincia,
            p.envio_codigo_postal,
            p.fecha_pedido AS fecha_creacion,
            {$select_cliente}
        FROM ecommerce_ordenes_produccion op
        JOIN ecommerce_pedidos p ON op.pedido_id = p.id
        {$join_cliente}
        WHERE " . ($incluir_entregados ? "op.estado IN ('terminado','entregado')" : "op.estado = 'terminado'") . "
    ";

    $params = [];

    if ($fecha_desde !== '') {
        $sql .= " AND DATE(p.fecha_pedido) >= ?";
        $params[] = $fecha_desde;
    }
    if ($fecha_hasta !== '') {
        $sql .= " AND DATE(p.fecha_pedido) <= ?";
        $params[] = $fecha_hasta;
    }

    if ($tiene_fecha_instalacion && $instalacion_desde !== '') {
        $sql .= " AND op.fecha_instalacion >= ?";
        $params[] = $instalacion_desde;
    }
    if ($tiene_fecha_instalacion && $instalacion_hasta !== '') {
        $sql .= " AND op.fecha_instalacion <= ?";
        $params[] = $instalacion_hasta;
    }

    if ($tiene_fecha_instalacion) {
        $sql .= " ORDER BY op.fecha_instalacion IS NULL, op.fecha_instalacion ASC, p.fecha_pedido DESC, op.pedido_id ASC";
    } else {
        $sql .= " ORDER BY p.fecha_pedido DESC, op.pedido_id ASC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pedidos as $row) {
        if (!empty($row['fecha_instalacion'])) {
            $pedidos_por_fecha[$row['fecha_instalacion']][] = $row;
        } else {
            $pedidos_sin_fecha[] = $row;
        }
    }

    if (!empty($pedidos_por_fecha)) {
        ksort($pedidos_por_fecha);
    }
} catch (Exception $e) {
    $error_pagina = $e->getMessage();
}

$qs = http_build_query(array_filter([
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'instalacion_desde' => $instalacion_desde,
    'instalacion_hasta' => $instalacion_hasta,
    'incluir_entregados' => $incluir_entregados ? '1' : null,
]));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">Instalaciones</h1>
        <p class="text-muted mb-0">Agenda por fecha para planificar qué se instala cada día</p>
    </div>
    <a href="ordenes_produccion.php" class="btn btn-outline-secondary">Órdenes de Producción</a>
</div>

<?php if ($error_pagina !== ''): ?>
    <div class="alert alert-danger">
        <strong>Error al cargar:</strong> <?= htmlspecialchars($error_pagina) ?>
        <div class="small mt-2">Verificá tablas base: <code>ecommerce_ordenes_produccion</code> y <code>ecommerce_pedidos</code>.</div>
    </div>
<?php endif; ?>

<?php if ($auto_setup_msg !== ''): ?>
    <div class="alert alert-info"><?= htmlspecialchars($auto_setup_msg) ?></div>
<?php endif; ?>

<?php if (!$tiene_clientes): ?>
    <div class="alert alert-warning">No existe <code>ecommerce_clientes</code>. Se muestra el listado usando datos de envío.</div>
<?php endif; ?>

<?php if (!$tiene_fecha_instalacion): ?>
    <div class="alert alert-warning">No está disponible <code>fecha_instalacion</code>. Podés ver pedidos, pero no programar fecha hasta crear esa columna.</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><h5 class="mb-0">Filtros</h5></div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Pedido desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Pedido hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Instalación desde</label>
                <input type="date" name="instalacion_desde" class="form-control" value="<?= htmlspecialchars($instalacion_desde) ?>" <?= !$tiene_fecha_instalacion ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3">
                <label class="form-label">Instalación hasta</label>
                <input type="date" name="instalacion_hasta" class="form-control" value="<?= htmlspecialchars($instalacion_hasta) ?>" <?= !$tiene_fecha_instalacion ? 'disabled' : '' ?>>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="incluir_entregados" value="1" id="incluir_entregados" <?= $incluir_entregados ? 'checked' : '' ?>>
                    <label class="form-check-label" for="incluir_entregados">Incluir entregados</label>
                </div>
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="instalaciones.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($pedidos)): ?>
    <div class="alert alert-info">No hay pedidos para instalaciones con los filtros seleccionados.</div>
<?php else: ?>
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><?= count($pedidos) ?> pedido(s) para instalaciones</h5>
            <div class="d-flex gap-2">
                <a href="instalaciones_reporte_direcciones.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por direcciones</a>
                <a href="instalaciones_reporte_productos.php?<?= $qs ?>" class="btn btn-light btn-sm" target="_blank">Reporte por productos</a>
            </div>
        </div>

        <div class="card-body p-0">
            <div class="p-3 border-bottom">
                <form method="POST" id="programar-form" class="row g-2 align-items-center">
                    <input type="hidden" name="action" value="programar_instalacion">
                    <div class="col-auto">
                        <label class="form-label mb-0">Fecha instalación</label>
                        <input type="date" name="fecha_instalacion" class="form-control" <?= !$tiene_fecha_instalacion ? 'disabled' : '' ?>>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary" <?= !$tiene_fecha_instalacion ? 'disabled' : '' ?>>Programar para seleccionados</button>
                    </div>
                    <div class="col-auto">
                        <button type="button" id="select-all" class="btn btn-outline-secondary">Seleccionar todo</button>
                        <button type="button" id="clear-all" class="btn btn-outline-secondary">Limpiar</button>
                    </div>
                </form>
            </div>

            <form method="POST" id="ordenes-form">
                <input type="hidden" name="action" value="programar_instalacion">

                <?php foreach ($pedidos_por_fecha as $fecha_instalada => $items_fecha): ?>
                    <div class="border-top p-3 bg-light">
                        <h5 class="mb-0">📅 <?= htmlspecialchars(date('d/m/Y', strtotime($fecha_instalada))) ?> <span class="badge bg-primary ms-2"><?= count($items_fecha) ?></span></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Pedido</th>
                                    <th>Cliente / Envío</th>
                                    <th>Dirección</th>
                                    <th>Localidad</th>
                                    <th>Fecha pedido</th>
                                    <th>Fecha instalación</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items_fecha as $row): ?>
                                    <?php
                                    $nombre = trim($row['envio_nombre'] ?? '') ?: ($row['cliente_nombre'] ?? 'Sin nombre');
                                    $dir = trim($row['envio_direccion'] ?? '');
                                    $loc = trim($row['envio_localidad'] ?? '');
                                    if (!empty($row['envio_provincia'])) {
                                        $loc .= ($loc ? ', ' : '') . $row['envio_provincia'];
                                    }
                                    ?>
                                    <tr class="table-success">
                                        <td><input type="checkbox" name="orden_ids[]" value="<?= (int)$row['orden_id'] ?>" class="form-check-input"></td>
                                        <td><strong><?= htmlspecialchars($row['numero_pedido']) ?></strong></td>
                                        <td><?= htmlspecialchars($nombre) ?></td>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= htmlspecialchars($loc) ?></td>
                                        <td><?= !empty($row['fecha_creacion']) ? date('d/m/Y', strtotime($row['fecha_creacion'])) : '-' ?></td>
                                        <td><?= !empty($row['fecha_instalacion']) ? htmlspecialchars(date('d/m/Y', strtotime($row['fecha_instalacion']))) : '-' ?></td>
                                        <td><a href="orden_produccion_detalle.php?pedido_id=<?= (int)$row['pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <?php if (!empty($pedidos_sin_fecha)): ?>
                    <div class="border-top p-3 bg-white">
                        <h5 class="mb-0">🗂️ Sin fecha de instalación <span class="badge bg-secondary ms-2"><?= count($pedidos_sin_fecha) ?></span></h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th style="width:40px;"></th>
                                    <th>Pedido</th>
                                    <th>Cliente / Envío</th>
                                    <th>Dirección</th>
                                    <th>Localidad</th>
                                    <th>Fecha pedido</th>
                                    <th>Fecha instalación</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pedidos_sin_fecha as $row): ?>
                                    <?php
                                    $nombre = trim($row['envio_nombre'] ?? '') ?: ($row['cliente_nombre'] ?? 'Sin nombre');
                                    $dir = trim($row['envio_direccion'] ?? '');
                                    $loc = trim($row['envio_localidad'] ?? '');
                                    if (!empty($row['envio_provincia'])) {
                                        $loc .= ($loc ? ', ' : '') . $row['envio_provincia'];
                                    }
                                    ?>
                                    <tr>
                                        <td><input type="checkbox" name="orden_ids[]" value="<?= (int)$row['orden_id'] ?>" class="form-check-input"></td>
                                        <td><strong><?= htmlspecialchars($row['numero_pedido']) ?></strong></td>
                                        <td><?= htmlspecialchars($nombre) ?></td>
                                        <td><?= htmlspecialchars($dir) ?></td>
                                        <td><?= htmlspecialchars($loc) ?></td>
                                        <td><?= !empty($row['fecha_creacion']) ? date('d/m/Y', strtotime($row['fecha_creacion'])) : '-' ?></td>
                                        <td>-</td>
                                        <td><a href="orden_produccion_detalle.php?pedido_id=<?= (int)$row['pedido_id'] ?>" class="btn btn-sm btn-outline-primary">Ver detalle</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var selectAll = document.getElementById('select-all');
    var clearAll = document.getElementById('clear-all');
    var progForm = document.getElementById('programar-form');
    var ordenesForm = document.getElementById('ordenes-form');

    if (selectAll) {
        selectAll.addEventListener('click', function () {
            document.querySelectorAll('input[name="orden_ids[]"]').forEach(function (cb) { cb.checked = true; });
        });
    }

    if (clearAll) {
        clearAll.addEventListener('click', function () {
            document.querySelectorAll('input[name="orden_ids[]"]').forEach(function (cb) { cb.checked = false; });
        });
    }

    if (progForm && ordenesForm) {
        progForm.addEventListener('submit', function (e) {
            var selected = document.querySelectorAll('input[name="orden_ids[]"]:checked');
            if (!selected.length) {
                e.preventDefault();
                alert('Seleccioná al menos una orden para programar.');
                return;
            }

            var fechaInput = progForm.querySelector('input[name="fecha_instalacion"]');
            var fecha = fechaInput ? fechaInput.value : '';
            if (!fecha) {
                e.preventDefault();
                alert('Seleccioná la fecha de instalación.');
                return;
            }

            var hiddenDate = ordenesForm.querySelector('input[name="fecha_instalacion"]');
            if (!hiddenDate) {
                hiddenDate = document.createElement('input');
                hiddenDate.type = 'hidden';
                hiddenDate.name = 'fecha_instalacion';
                ordenesForm.appendChild(hiddenDate);
            }
            hiddenDate.value = fecha;

            ordenesForm.querySelectorAll('input[name="orden_ids[]"]').forEach(function (n) {
                n.parentNode.removeChild(n);
            });

            selected.forEach(function (cb) {
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'orden_ids[]';
                h.value = cb.value;
                ordenesForm.appendChild(h);
            });

            ordenesForm.submit();
            e.preventDefault();
        });
    }
});
</script>

<?php require 'includes/footer.php'; ?>
