<?php
require 'includes/header.php';

$pdo = $GLOBALS['pdo'] ?? ($pdo ?? null);
if (!($pdo instanceof PDO)) {
    throw new RuntimeException('Conexion PDO no disponible en modulo KPI.');
}

if (!function_exists('kpi_identificador_valido')) {
    function kpi_identificador_valido(string $value): bool
    {
        return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $value);
    }
}

if (!function_exists('kpi_tipos_con_fecha')) {
    function kpi_tipos_con_fecha(string $tipo): bool
    {
        $tipo = strtolower($tipo);
        return strpos($tipo, 'date') !== false || strpos($tipo, 'time') !== false || strpos($tipo, 'year') !== false;
    }
}

if (!function_exists('kpi_tipos_numericos')) {
    function kpi_tipos_numericos(string $tipo): bool
    {
        $tipo = strtolower($tipo);
        $numericos = ['int', 'decimal', 'float', 'double', 'real', 'numeric', 'bit'];
        foreach ($numericos as $needle) {
            if (strpos($tipo, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('kpi_columnas_tabla')) {
    function kpi_columnas_tabla(PDO $pdo, string $tabla): array
    {
        if (!kpi_identificador_valido($tabla)) {
            return [];
        }

        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM `{$tabla}`");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return [];
        }

        $cols = [];
        foreach ($rows as $row) {
            $nombre = (string)($row['Field'] ?? '');
            $tipo = strtolower((string)($row['Type'] ?? ''));
            if ($nombre === '' || !kpi_identificador_valido($nombre)) {
                continue;
            }
            $cols[$nombre] = [
                'type' => $tipo,
                'is_numeric' => kpi_tipos_numericos($tipo),
                'is_date' => kpi_tipos_con_fecha($tipo),
            ];
        }

        return $cols;
    }
}

if (!function_exists('kpi_tablas_disponibles')) {
    function kpi_tablas_disponibles(PDO $pdo): array
    {
        $tablas = [];
        try {
            $rows = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return [];
        }

        foreach ($rows as $row) {
            $tabla = (string)($row[0] ?? '');
            if ($tabla === '' || !kpi_identificador_valido($tabla)) {
                continue;
            }

            // Evita tablas del motor y caches internos del framework.
            if (strpos($tabla, 'information_schema') === 0 || strpos($tabla, 'performance_schema') === 0) {
                continue;
            }

            $tablas[] = $tabla;
        }

        sort($tablas);
        return $tablas;
    }
}

$tablas = kpi_tablas_disponibles($pdo);
$tablaDefault = in_array('ecommerce_pedidos', $tablas, true)
    ? 'ecommerce_pedidos'
    : ((count($tablas) > 0) ? $tablas[0] : '');

$tabla = (string)($_GET['tabla'] ?? $tablaDefault);
if (!in_array($tabla, $tablas, true)) {
    $tabla = $tablaDefault;
}

$columnas = $tabla !== '' ? kpi_columnas_tabla($pdo, $tabla) : [];
$columnasNombres = array_keys($columnas);

$columnasNumericas = [];
$columnasFecha = [];
foreach ($columnas as $col => $meta) {
    if (!empty($meta['is_numeric'])) {
        $columnasNumericas[] = $col;
    }
    if (!empty($meta['is_date'])) {
        $columnasFecha[] = $col;
    }
}

$agregaciones = ['count', 'sum', 'avg', 'min', 'max'];
$agregacion = strtolower((string)($_GET['agregacion'] ?? 'count'));
if (!in_array($agregacion, $agregaciones, true)) {
    $agregacion = 'count';
}

$columnaMetrica = (string)($_GET['columna_metrica'] ?? '');
if ($columnaMetrica === '' || !isset($columnas[$columnaMetrica])) {
    $columnaMetrica = count($columnasNumericas) > 0 ? $columnasNumericas[0] : (count($columnasNombres) > 0 ? $columnasNombres[0] : '');
}

if ($agregacion !== 'count' && $columnaMetrica !== '' && empty($columnas[$columnaMetrica]['is_numeric'])) {
    $agregacion = 'count';
}

$columnaFecha = (string)($_GET['columna_fecha'] ?? '');
if ($columnaFecha === '' || !isset($columnas[$columnaFecha]) || empty($columnas[$columnaFecha]['is_date'])) {
    $columnaFecha = count($columnasFecha) > 0 ? $columnasFecha[0] : '';
}

$agrupacion = strtolower((string)($_GET['agrupacion'] ?? 'month'));
$agrupacionesValidas = ['none', 'day', 'week', 'month', 'column'];
if (!in_array($agrupacion, $agrupacionesValidas, true)) {
    $agrupacion = 'month';
}
if ($columnaFecha === '' && in_array($agrupacion, ['day', 'week', 'month'], true)) {
    $agrupacion = 'column';
}

$columnaAgrupar = (string)($_GET['columna_agrupar'] ?? '');
if ($columnaAgrupar === '' || !isset($columnas[$columnaAgrupar])) {
    if (in_array('estado', $columnasNombres, true)) {
        $columnaAgrupar = 'estado';
    } elseif (count($columnasNombres) > 0) {
        $columnaAgrupar = $columnasNombres[0];
    }
}

$limite = (int)($_GET['limite'] ?? 12);
if ($limite < 3) {
    $limite = 3;
}
if ($limite > 100) {
    $limite = 100;
}

$desde = (string)($_GET['desde'] ?? '');
$hasta = (string)($_GET['hasta'] ?? '');
if ($columnaFecha !== '') {
    if ($desde === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
        $desde = date('Y-m-d', strtotime('-30 days'));
    }
    if ($hasta === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
        $hasta = date('Y-m-d');
    }
}

$filtroColumna = (string)($_GET['filtro_columna'] ?? '');
$filtroValor = trim((string)($_GET['filtro_valor'] ?? ''));
if ($filtroColumna === '' || !isset($columnas[$filtroColumna])) {
    $filtroColumna = '';
    $filtroValor = '';
}

if ($filtroValor === '') {
    $filtroColumna = '';
}

$kpiValor = null;
$series = [];
$error = null;

if ($tabla !== '' && count($columnas) > 0) {
    $where = [];
    $params = [];

    if ($columnaFecha !== '') {
        $where[] = "`{$columnaFecha}` BETWEEN ? AND ?";
        $params[] = $desde . ' 00:00:00';
        $params[] = $hasta . ' 23:59:59';
    }

    if ($filtroColumna !== '' && $filtroValor !== '') {
        $where[] = "`{$filtroColumna}` = ?";
        $params[] = $filtroValor;
    }

    $whereSql = count($where) > 0 ? (' WHERE ' . implode(' AND ', $where)) : '';

    $metricaSql = 'COUNT(*)';
    $metricaLabel = 'Registros';

    if ($agregacion !== 'count' && $columnaMetrica !== '') {
        $metricaSql = strtoupper($agregacion) . "(`{$columnaMetrica}`)";
        $metricaLabel = strtoupper($agregacion) . ' de ' . $columnaMetrica;
    }

    try {
        $sqlValor = "SELECT {$metricaSql} AS valor_total FROM `{$tabla}`{$whereSql}";
        $stmtValor = $pdo->prepare($sqlValor);
        $stmtValor->execute($params);
        $kpiValor = $stmtValor->fetchColumn();
        if ($kpiValor === false || $kpiValor === null) {
            $kpiValor = 0;
        }

        if ($agrupacion !== 'none') {
            $groupExpr = '';
            $orderExpr = 'valor DESC';

            if ($agrupacion === 'day' && $columnaFecha !== '') {
                $groupExpr = "DATE_FORMAT(`{$columnaFecha}`, '%Y-%m-%d')";
                $orderExpr = 'etiqueta ASC';
            } elseif ($agrupacion === 'week' && $columnaFecha !== '') {
                $groupExpr = "DATE_FORMAT(`{$columnaFecha}`, '%x-W%v')";
                $orderExpr = 'etiqueta ASC';
            } elseif ($agrupacion === 'month' && $columnaFecha !== '') {
                $groupExpr = "DATE_FORMAT(`{$columnaFecha}`, '%Y-%m')";
                $orderExpr = 'etiqueta ASC';
            } elseif ($agrupacion === 'column' && $columnaAgrupar !== '') {
                $groupExpr = "COALESCE(CAST(`{$columnaAgrupar}` AS CHAR), 'Sin dato')";
                $orderExpr = 'valor DESC';
            }

            if ($groupExpr !== '') {
                $sqlSeries = "
                    SELECT {$groupExpr} AS etiqueta, {$metricaSql} AS valor
                    FROM `{$tabla}`
                    {$whereSql}
                    GROUP BY etiqueta
                    ORDER BY {$orderExpr}
                    LIMIT {$limite}
                ";
                $stmtSeries = $pdo->prepare($sqlSeries);
                $stmtSeries->execute($params);
                $series = $stmtSeries->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (Throwable $e) {
        $error = 'No se pudo generar el KPI con la configuracion elegida.';
        $series = [];
    }
}

$chartLabels = [];
$chartValues = [];
foreach ($series as $row) {
    $chartLabels[] = (string)($row['etiqueta'] ?? 'N/A');
    $chartValues[] = (float)($row['valor'] ?? 0);
}
?>

<style>
    .kpi-builder-card {
        border: 1px solid #dfe7f3;
        border-radius: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .05);
    }
    .kpi-main-card {
        border-radius: 16px;
        border: 0;
        color: #fff;
        background: linear-gradient(135deg, #0f766e 0%, #0d9488 45%, #14b8a6 100%);
        box-shadow: 0 16px 35px rgba(13, 148, 136, .25);
    }
    .kpi-chip {
        background: rgba(255, 255, 255, .2);
        border: 1px solid rgba(255, 255, 255, .3);
        border-radius: 999px;
        padding: .3rem .8rem;
        font-size: .8rem;
    }
    .table-kpi thead th {
        font-size: .82rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }
</style>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">Generador dinamico de KPIs</h1>
        <p class="text-muted mb-0">Construi indicadores en tiempo real sobre cualquier tabla del sistema.</p>
    </div>
</div>

<div class="card kpi-builder-card mb-4">
    <div class="card-body">
        <form method="GET" id="kpiBuilderForm" class="row g-3">
            <div class="col-12 col-md-4 col-xl-3">
                <label class="form-label">Tabla</label>
                <select class="form-select" name="tabla" id="tablaSelect" onchange="document.getElementById('kpiBuilderForm').submit()">
                    <?php foreach ($tablas as $tbl): ?>
                        <option value="<?= admin_h($tbl) ?>" <?= $tbl === $tabla ? 'selected' : '' ?>><?= admin_h($tbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Agregacion</label>
                <select class="form-select" name="agregacion">
                    <?php foreach ($agregaciones as $agg): ?>
                        <option value="<?= admin_h($agg) ?>" <?= $agg === $agregacion ? 'selected' : '' ?>><?= strtoupper(admin_h($agg)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label">Columna metrica</label>
                <select class="form-select" name="columna_metrica">
                    <?php foreach ($columnasNombres as $col): ?>
                        <option value="<?= admin_h($col) ?>" <?= $col === $columnaMetrica ? 'selected' : '' ?>><?= admin_h($col) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Agrupar por</label>
                <select class="form-select" name="agrupacion">
                    <option value="none" <?= $agrupacion === 'none' ? 'selected' : '' ?>>Sin agrupacion</option>
                    <option value="day" <?= $agrupacion === 'day' ? 'selected' : '' ?>>Dia</option>
                    <option value="week" <?= $agrupacion === 'week' ? 'selected' : '' ?>>Semana</option>
                    <option value="month" <?= $agrupacion === 'month' ? 'selected' : '' ?>>Mes</option>
                    <option value="column" <?= $agrupacion === 'column' ? 'selected' : '' ?>>Columna</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Columna de grupo</label>
                <select class="form-select" name="columna_agrupar">
                    <?php foreach ($columnasNombres as $col): ?>
                        <option value="<?= admin_h($col) ?>" <?= $col === $columnaAgrupar ? 'selected' : '' ?>><?= admin_h($col) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-6 col-md-4 col-xl-3">
                <label class="form-label">Columna fecha</label>
                <select class="form-select" name="columna_fecha">
                    <option value="">Sin fecha</option>
                    <?php foreach ($columnasFecha as $col): ?>
                        <option value="<?= admin_h($col) ?>" <?= $col === $columnaFecha ? 'selected' : '' ?>><?= admin_h($col) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="desde" value="<?= admin_h($desde) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="hasta" value="<?= admin_h($hasta) ?>">
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <label class="form-label">Max grupos</label>
                <input type="number" class="form-control" min="3" max="100" name="limite" value="<?= (int)$limite ?>">
            </div>

            <div class="col-12 col-md-4 col-xl-2">
                <label class="form-label">Filtro columna</label>
                <select class="form-select" name="filtro_columna">
                    <option value="">Sin filtro</option>
                    <?php foreach ($columnasNombres as $col): ?>
                        <option value="<?= admin_h($col) ?>" <?= $col === $filtroColumna ? 'selected' : '' ?>><?= admin_h($col) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8 col-xl-4">
                <label class="form-label">Filtro valor (igual a)</label>
                <input type="text" class="form-control" name="filtro_valor" value="<?= admin_h($filtroValor) ?>" placeholder="Ej: pagado, 1, Juan">
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><i class="bi bi-bar-chart-line me-1"></i>Generar KPI</button>
                <a href="kpis.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-warning"><?= admin_h($error) ?></div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-4">
        <div class="card kpi-main-card h-100">
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex gap-2 flex-wrap mb-3">
                        <span class="kpi-chip">Tabla: <?= admin_h($tabla !== '' ? $tabla : '-') ?></span>
                        <span class="kpi-chip">Metrica: <?= admin_h($metricaLabel ?? 'N/A') ?></span>
                    </div>
                    <div class="text-white-50 small mb-1">Valor principal</div>
                    <div class="display-6 mb-0"><?= is_numeric($kpiValor) ? number_format((float)$kpiValor, 2, ',', '.') : '0,00' ?></div>
                </div>
                <div class="small mt-3 text-white-50">
                    Configuracion totalmente dinamica con filtros, agrupaciones y calculo en vivo.
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Serie del KPI</h6>
                <span class="text-muted small"><?= count($chartLabels) ?> punto(s)</span>
            </div>
            <div class="card-body">
                <canvas id="kpiChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-white">
        <h6 class="mb-0">Detalle agrupado</h6>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0 table-kpi">
            <thead>
                <tr>
                    <th class="ps-3">Etiqueta</th>
                    <th class="pe-3 text-end">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($series) === 0): ?>
                    <tr>
                        <td class="ps-3 text-muted" colspan="2">No hay datos para la configuracion seleccionada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($series as $row): ?>
                        <tr>
                            <td class="ps-3"><?= admin_h((string)($row['etiqueta'] ?? 'N/A')) ?></td>
                            <td class="pe-3 text-end fw-semibold"><?= number_format((float)($row['valor'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function () {
    const labels = <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>;
    const values = <?= json_encode($chartValues, JSON_UNESCAPED_UNICODE) ?>;
    const el = document.getElementById('kpiChart');
    if (!el || labels.length === 0) {
        return;
    }

    new Chart(el, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Valor',
                data: values,
                borderWidth: 0,
                borderRadius: 8,
                backgroundColor: '#0d9488'
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
