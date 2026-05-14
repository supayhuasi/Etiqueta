<?php
require 'includes/header.php';

if (!isset($pdo)) {
    die('Error: No hay conexión a la base de datos');
}

// Período
$periodo  = $_GET['periodo'] ?? 'mes';
if ($periodo !== 'trimestre') $periodo = 'mes';

$year    = max(2000, min(2100, (int)($_GET['year']    ?? date('Y'))));
$month   = max(1,    min(12,   (int)($_GET['month']   ?? date('n'))));
$quarter = max(1,    min(4,    (int)($_GET['quarter'] ?? (int)ceil(date('n') / 3))));

if ($periodo === 'mes') {
    $start        = new DateTime(sprintf('%04d-%02d-01', $year, $month));
    $end          = (clone $start)->modify('last day of this month')->setTime(23, 59, 59);
    $label_periodo = $start->format('m/Y');
} else {
    $startMonth   = ($quarter - 1) * 3 + 1;
    $start        = new DateTime(sprintf('%04d-%02d-01', $year, $startMonth));
    $end          = (clone $start)->modify('+2 months')->modify('last day of this month')->setTime(23, 59, 59);
    $label_periodo = 'T' . $quarter . ' ' . $year;
}

$startStr = $start->format('Y-m-d H:i:s');
$endStr   = $end->format('Y-m-d H:i:s');

// Verificar tablas necesarias con SELECT directo (SHOW TABLES LIKE falla con _ en el nombre)
$tiene_items    = false;
$tiene_productos = false;
$tiene_pedidos  = false;
try { $pdo->query("SELECT 1 FROM ecommerce_pedido_items LIMIT 1");  $tiene_items    = true; } catch (PDOException $e) {}
try { $pdo->query("SELECT 1 FROM ecommerce_productos LIMIT 1");     $tiene_productos = true; } catch (PDOException $e) {}
try { $pdo->query("SELECT 1 FROM ecommerce_pedidos LIMIT 1");       $tiene_pedidos  = true; } catch (PDOException $e) {}

// ---------------------------------------------------------------
// 1. RANKING DE PRODUCTOS MÁS VENDIDOS (por cantidad)
// ---------------------------------------------------------------
$ranking_productos = [];
if ($tiene_items && $tiene_productos && $tiene_pedidos) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                pr.nombre AS producto,
                SUM(pi.cantidad) AS total_cantidad,
                COUNT(DISTINCT pi.pedido_id) AS total_pedidos,
                SUM(pi.precio_unitario * pi.cantidad) AS total_importe
            FROM ecommerce_pedido_items pi
            JOIN ecommerce_pedidos p ON p.id = pi.pedido_id
            LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
            WHERE p.fecha_pedido BETWEEN ? AND ?
              AND p.estado NOT IN ('cancelado')
            GROUP BY pi.producto_id, pr.nombre
            ORDER BY total_cantidad DESC
            LIMIT 20
        ");
        $stmt->execute([$startStr, $endStr]);
        $ranking_productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('estadisticas_productos ranking: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// 2. COLORES MÁS VENDIDOS (extrae del JSON de atributos)
// ---------------------------------------------------------------
$ranking_colores = [];
if ($tiene_items && $tiene_pedidos) {
    try {
        $stmt = $pdo->prepare("
            SELECT pi.atributos, pi.cantidad
            FROM ecommerce_pedido_items pi
            JOIN ecommerce_pedidos p ON p.id = pi.pedido_id
            WHERE p.fecha_pedido BETWEEN ? AND ?
              AND p.estado NOT IN ('cancelado')
              AND pi.atributos IS NOT NULL AND pi.atributos != ''
        ");
        $stmt->execute([$startStr, $endStr]);
        $color_map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $attrs = json_decode((string)$row['atributos'], true);
            if (!is_array($attrs)) continue;
            foreach ($attrs as $attr) {
                $nombre = strtolower(trim((string)($attr['nombre'] ?? '')));
                $valor  = trim((string)($attr['valor'] ?? ''));
                if (stripos($nombre, 'color') !== false && $valor !== '') {
                    $key = strtolower($valor);
                    $color_map[$key] = ($color_map[$key] ?? 0) + (float)($row['cantidad'] ?? 1);
                }
            }
        }
        arsort($color_map);
        foreach (array_slice($color_map, 0, 15, true) as $color => $qty) {
            $ranking_colores[] = ['color' => $color, 'cantidad' => $qty];
        }
    } catch (PDOException $e) {
        error_log('estadisticas_productos colores: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// 3. TOTAL UNIDADES Y PEDIDOS EN EL PERÍODO
// ---------------------------------------------------------------
$total_unidades = 0;
$total_pedidos_periodo = 0;
$total_importe_periodo = 0;
if ($tiene_items && $tiene_pedidos) {
    try {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(pi.cantidad), 0) AS unidades,
                COUNT(DISTINCT p.id) AS pedidos,
                COALESCE(SUM(p.total), 0) AS importe
            FROM ecommerce_pedidos p
            JOIN ecommerce_pedido_items pi ON pi.pedido_id = p.id
            WHERE p.fecha_pedido BETWEEN ? AND ?
              AND p.estado NOT IN ('cancelado')
        ");
        $stmt->execute([$startStr, $endStr]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $total_unidades          = (float)($row['unidades'] ?? 0);
        $total_pedidos_periodo   = (int)($row['pedidos']   ?? 0);
        $total_importe_periodo   = (float)($row['importe']  ?? 0);
    } catch (PDOException $e) {
        error_log('estadisticas_productos totales: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------
// 4. EVOLUCIÓN MENSUAL (últimos 12 meses, siempre)
// ---------------------------------------------------------------
$evolucion = [];
if ($tiene_items && $tiene_pedidos) {
    try {
        $stmt = $pdo->query("
            SELECT
                DATE_FORMAT(p.fecha_pedido, '%Y-%m') AS mes,
                COALESCE(SUM(pi.cantidad), 0) AS unidades,
                COUNT(DISTINCT p.id) AS pedidos
            FROM ecommerce_pedidos p
            JOIN ecommerce_pedido_items pi ON pi.pedido_id = p.id
            WHERE p.fecha_pedido >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
              AND p.estado NOT IN ('cancelado')
            GROUP BY mes
            ORDER BY mes ASC
        ");
        $evolucion = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('estadisticas_productos evolucion: ' . $e->getMessage());
    }
}

// Máximo para proporcionar barras
$max_evolucion = max(1, ...array_map(fn($r) => (float)$r['unidades'], $evolucion ?: [['unidades' => 1]]));
$max_ranking   = max(1, ...array_map(fn($r) => (float)$r['total_cantidad'], $ranking_productos ?: [['total_cantidad' => 1]]));
$max_colores   = max(1, ...array_map(fn($r) => (float)$r['cantidad'], $ranking_colores ?: [['cantidad' => 1]]));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1">📦 Estadísticas de Productos</h1>
        <p class="text-muted mb-0">Ranking de productos y colores por período</p>
    </div>
    <div class="badge bg-primary-subtle text-primary-emphasis fs-6"><?= htmlspecialchars($label_periodo) ?></div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label">Período</label>
                <select name="periodo" class="form-select" onchange="this.form.submit()">
                    <option value="mes"       <?= $periodo === 'mes'       ? 'selected' : '' ?>>Mensual</option>
                    <option value="trimestre" <?= $periodo === 'trimestre' ? 'selected' : '' ?>>Trimestral</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label">Año</label>
                <input type="number" name="year" class="form-control" value="<?= $year ?>" min="2000" max="2100">
            </div>
            <?php if ($periodo === 'mes'): ?>
            <div class="col-6 col-md-2">
                <label class="form-label">Mes</label>
                <select name="month" class="form-select">
                    <?php
                    $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
                    for ($m = 1; $m <= 12; $m++):
                    ?>
                        <option value="<?= $m ?>" <?= $m === $month ? 'selected' : '' ?>><?= $meses[$m - 1] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php else: ?>
            <div class="col-6 col-md-2">
                <label class="form-label">Trimestre</label>
                <select name="quarter" class="form-select">
                    <?php for ($q = 1; $q <= 4; $q++): ?>
                        <option value="<?= $q ?>" <?= $q === $quarter ? 'selected' : '' ?>>T<?= $q ?> (<?= ['Ene-Mar','Abr-Jun','Jul-Sep','Oct-Dic'][$q-1] ?>)</option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="col-6 col-md-2">
                <button type="submit" class="btn btn-primary w-100">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-primary"><?= number_format($total_unidades, 0, ',', '.') ?></div>
                <div class="text-muted small">Unidades vendidas</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-success"><?= $total_pedidos_periodo ?></div>
                <div class="text-muted small">Pedidos con ítems</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-body text-center">
                <div class="fs-2 fw-bold text-warning">$<?= number_format($total_importe_periodo, 0, ',', '.') ?></div>
                <div class="text-muted small">Importe total</div>
            </div>
        </div>
    </div>
</div>

<?php if (!$tiene_items || !$tiene_pedidos): ?>
    <div class="alert alert-warning">No se encontraron las tablas necesarias (<code>ecommerce_pedido_items</code> / <code>ecommerce_pedidos</code>).</div>
<?php else: ?>

<div class="row g-4">
    <!-- Ranking productos -->
    <div class="col-12 col-lg-7">
        <div class="card h-100 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">🏆 Ranking de Productos más vendidos — <?= htmlspecialchars($label_periodo) ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ranking_productos)): ?>
                    <div class="p-4 text-muted">Sin datos para este período.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Producto</th>
                                <th class="text-end">Unidades</th>
                                <th class="text-end">Pedidos</th>
                                <th class="text-end">Importe</th>
                                <th style="width:120px">Participación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_productos as $i => $row): ?>
                            <tr>
                                <td>
                                    <?php if ($i === 0): ?>
                                        <span class="badge bg-warning text-dark">🥇 1</span>
                                    <?php elseif ($i === 1): ?>
                                        <span class="badge bg-secondary">🥈 2</span>
                                    <?php elseif ($i === 2): ?>
                                        <span class="badge bg-danger-subtle text-danger-emphasis">🥉 3</span>
                                    <?php else: ?>
                                        <span class="text-muted"><?= $i + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($row['producto'] ?? 'Sin nombre') ?></td>
                                <td class="text-end fw-semibold"><?= number_format((float)$row['total_cantidad'], 0, ',', '.') ?></td>
                                <td class="text-end text-muted"><?= (int)$row['total_pedidos'] ?></td>
                                <td class="text-end">$<?= number_format((float)$row['total_importe'], 0, ',', '.') ?></td>
                                <td>
                                    <?php $pct = round((float)$row['total_cantidad'] / $max_ranking * 100); ?>
                                    <div class="progress" style="height:8px;" title="<?= $pct ?>%">
                                        <div class="progress-bar bg-primary" style="width:<?= $pct ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ranking colores -->
    <div class="col-12 col-lg-5">
        <div class="card h-100 shadow-sm">
            <div class="card-header">
                <h5 class="mb-0">🎨 Colores más vendidos — <?= htmlspecialchars($label_periodo) ?></h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ranking_colores)): ?>
                    <div class="p-4 text-muted">Sin datos de color para este período. Verificá que los ítems tengan atributos de color cargados.</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Color</th>
                                <th class="text-end">Unidades</th>
                                <th style="width:120px">Participación</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ranking_colores as $i => $row): ?>
                            <?php
                                // Intentar mostrar swatch del color
                                $colorName = strtolower(trim($row['color']));
                                $cssColors = [
                                    'negro' => '#212529', 'blanco' => '#f8f9fa', 'rojo' => '#dc3545',
                                    'azul' => '#0d6efd', 'verde' => '#198754', 'amarillo' => '#ffc107',
                                    'naranja' => '#fd7e14', 'violeta' => '#6f42c1', 'rosa' => '#e83e8c',
                                    'gris' => '#6c757d', 'marron' => '#795548', 'celeste' => '#0dcaf0',
                                    'beige' => '#f5f5dc', 'dorado' => '#ffd700', 'plateado' => '#c0c0c0',
                                    'bordó' => '#800020', 'bordo' => '#800020',
                                    'black' => '#212529', 'white' => '#f8f9fa', 'red' => '#dc3545',
                                    'blue' => '#0d6efd', 'green' => '#198754',
                                ];
                                $swatchColor = $cssColors[$colorName] ?? null;
                                $pct = round((float)$row['cantidad'] / $max_colores * 100);
                            ?>
                            <tr>
                                <td class="text-muted"><?= $i + 1 ?></td>
                                <td>
                                    <?php if ($swatchColor): ?>
                                        <span class="d-inline-block rounded-circle border me-1" style="width:14px;height:14px;background:<?= htmlspecialchars($swatchColor) ?>;vertical-align:middle;"></span>
                                    <?php endif; ?>
                                    <?= htmlspecialchars(ucfirst($row['color'])) ?>
                                </td>
                                <td class="text-end fw-semibold"><?= number_format((float)$row['cantidad'], 0, ',', '.') ?></td>
                                <td>
                                    <div class="progress" style="height:8px;" title="<?= $pct ?>%">
                                        <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= $pct ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Evolución últimos 12 meses -->
<?php if (!empty($evolucion)): ?>
<div class="card mt-4 shadow-sm">
    <div class="card-header">
        <h5 class="mb-0">📈 Evolución mensual — últimos 12 meses (unidades vendidas)</h5>
    </div>
    <div class="card-body">
        <div class="d-flex align-items-end gap-2 overflow-auto pb-2" style="min-height:140px;">
            <?php foreach ($evolucion as $evo): ?>
            <?php
                $h = max(8, round((float)$evo['unidades'] / $max_evolucion * 120));
                [$y, $m] = explode('-', $evo['mes']);
                $meses_cortos = ['01'=>'Ene','02'=>'Feb','03'=>'Mar','04'=>'Abr','05'=>'May','06'=>'Jun','07'=>'Jul','08'=>'Ago','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dic'];
                $label = ($meses_cortos[$m] ?? $m) . ' ' . substr($y, 2);
                $esMesActual = ($evo['mes'] === date('Y-m'));
            ?>
            <div class="d-flex flex-column align-items-center text-center" style="min-width:52px;">
                <small class="text-muted mb-1 fw-semibold" style="font-size:11px;"><?= number_format((float)$evo['unidades'], 0, ',', '.') ?></small>
                <div class="rounded-top <?= $esMesActual ? 'bg-primary' : 'bg-primary bg-opacity-50' ?>"
                     style="width:36px;height:<?= $h ?>px;"
                     title="<?= htmlspecialchars($label) ?>: <?= number_format((float)$evo['unidades'], 0, ',', '.') ?> uds — <?= (int)$evo['pedidos'] ?> pedidos">
                </div>
                <small class="text-muted mt-1" style="font-size:11px;"><?= htmlspecialchars($label) ?></small>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php require 'includes/footer.php'; ?>
