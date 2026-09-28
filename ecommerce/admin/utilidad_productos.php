<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/utilidad_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

function utilidad_fmt_money(float $valor): string
{
    return '$' . number_format($valor, 2, ',', '.');
}

$hoy = new DateTime('today');
$desdeDefault = (clone $hoy)->modify('first day of this month')->format('Y-m-d');
$hastaDefault = $hoy->format('Y-m-d');

$preset = trim((string)($_GET['preset'] ?? ''));
$desde = trim((string)($_GET['desde'] ?? ''));
$hasta = trim((string)($_GET['hasta'] ?? ''));
$categoriaFiltro = (int)($_GET['categoria_id'] ?? 0);

if ($preset === 'mes') {
    $desde = (clone $hoy)->modify('first day of this month')->format('Y-m-d');
    $hasta = $hoy->format('Y-m-d');
} elseif ($preset === 'mes_ant') {
    $desde = (clone $hoy)->modify('first day of last month')->format('Y-m-d');
    $hasta = (clone $hoy)->modify('last day of last month')->format('Y-m-d');
} elseif ($preset === '30') {
    $desde = (clone $hoy)->modify('-29 days')->format('Y-m-d');
    $hasta = $hoy->format('Y-m-d');
} elseif ($preset === '90') {
    $desde = (clone $hoy)->modify('-89 days')->format('Y-m-d');
    $hasta = $hoy->format('Y-m-d');
} elseif ($preset === 'anio') {
    $desde = $hoy->format('Y-01-01');
    $hasta = $hoy->format('Y-m-d');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
    $desde = $desdeDefault;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
    $hasta = $hastaDefault;
}
if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}

$reporte = utilidad_reporte_rango($pdo, $desde . ' 00:00:00', $hasta . ' 23:59:59');
$categorias = $reporte['categorias'];
$totales = $reporte['totales'];
$sinCosto = (int)$reporte['sin_costo'];

if ($categoriaFiltro > 0) {
    $categorias = array_values(array_filter($categorias, static function ($cat) use ($categoriaFiltro) {
        return (int)$cat['categoria_id'] === $categoriaFiltro;
    }));
    $totales = [
        'cantidad' => 0.0,
        'venta' => 0.0,
        'costo' => 0.0,
        'utilidad' => 0.0,
        'margen_pct' => 0.0,
        'pedidos' => 0,
        'productos' => 0,
    ];
    $pedidosFiltro = 0;
    foreach ($categorias as $cat) {
        $totales['cantidad'] += (float)$cat['cantidad'];
        $totales['venta'] += (float)$cat['venta'];
        $totales['costo'] += (float)$cat['costo'];
        $totales['utilidad'] += (float)$cat['utilidad'];
        $totales['productos'] += count($cat['productos']);
        $pedidosFiltro += (int)$cat['pedidos'];
    }
    $totales['pedidos'] = $pedidosFiltro;
    $totales['venta'] = round((float)$totales['venta'], 2);
    $totales['costo'] = round((float)$totales['costo'], 2);
    $totales['utilidad'] = round((float)$totales['utilidad'], 2);
    $totales['margen_pct'] = $totales['venta'] > 0 ? round(($totales['utilidad'] / $totales['venta']) * 100, 1) : 0.0;
}

$categoriasSelect = [];
try {
    if (function_exists('admin_table_exists') && admin_table_exists($pdo, 'ecommerce_categorias')) {
        $categoriasSelect = $pdo->query("SELECT id, nombre FROM ecommerce_categorias ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $categoriasSelect = [];
}

$maxUtilidad = 0.0;
foreach ($categorias as $cat) {
    $maxUtilidad = max($maxUtilidad, abs((float)$cat['utilidad']));
}
$maxUtilidad = max(1.0, $maxUtilidad);

$labelRango = date('d/m/Y', strtotime($desde)) . ' — ' . date('d/m/Y', strtotime($hasta));
?>
<style>
.utilidad-card .value { font-size: 1.45rem; font-weight: 700; }
.utilidad-neg { color: #b91c1c; }
.utilidad-pos { color: #15803d; }
.utilidad-cat + .utilidad-prod { background: #f8fafc; }
.utilidad-prod td { font-size: .92rem; }
@media print {
    .top-navbar, .sidebar, .admin-sidebar-backdrop, .cotizacion-mobile-bar,
    .utilidad-filtros, .utilidad-acciones, #chatWidgetBtn, #chatWidgetPanel { display: none !important; }
    .main-content { width: 100% !important; padding: 0 !important; }
}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="mb-1">Utilidad por categoría</h1>
        <p class="text-muted mb-0">Venta menos costo, por producto y agrupado por categoría. <?= htmlspecialchars($labelRango) ?></p>
    </div>
    <div class="utilidad-acciones d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">Imprimir</button>
        <a href="ventas_reportes.php" class="btn btn-outline-secondary">Reporte de ventas</a>
    </div>
</div>

<div class="card mb-4 utilidad-filtros">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-6 col-md-2">
                <label class="form-label" for="desde">Desde</label>
                <input type="date" class="form-control" id="desde" name="desde" value="<?= htmlspecialchars($desde) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="hasta">Hasta</label>
                <input type="date" class="form-control" id="hasta" name="hasta" value="<?= htmlspecialchars($hasta) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="categoria_id">Categoría</label>
                <select class="form-select" id="categoria_id" name="categoria_id">
                    <option value="0">Todas</option>
                    <?php foreach ($categoriasSelect as $catOpt): ?>
                        <option value="<?= (int)$catOpt['id'] ?>" <?= $categoriaFiltro === (int)$catOpt['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$catOpt['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">Calcular</button>
                <a class="btn btn-outline-secondary" href="utilidad_productos.php?preset=mes">Este mes</a>
                <a class="btn btn-outline-secondary" href="utilidad_productos.php?preset=mes_ant">Mes anterior</a>
                <a class="btn btn-outline-secondary" href="utilidad_productos.php?preset=anio">Este año</a>
            </div>
        </form>
        <div class="small text-muted mt-3 mb-0">
            El costo sale de la receta de materiales o, si no hay receta, de la última compra del producto.
            Los descuentos del pedido se prorratean. El envío no entra en este cálculo.
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="card utilidad-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Venta</div>
                <div class="value text-primary"><?= utilidad_fmt_money((float)$totales['venta']) ?></div>
                <div class="small text-muted"><?= number_format((float)$totales['cantidad'], 0, ',', '.') ?> uds · <?= (int)$totales['pedidos'] ?> pedidos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card utilidad-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Costo</div>
                <div class="value"><?= utilidad_fmt_money((float)$totales['costo']) ?></div>
                <div class="small text-muted"><?= (int)$totales['productos'] ?> productos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card utilidad-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Utilidad</div>
                <div class="value <?= (float)$totales['utilidad'] >= 0 ? 'utilidad-pos' : 'utilidad-neg' ?>">
                    <?= utilidad_fmt_money((float)$totales['utilidad']) ?>
                </div>
                <div class="small text-muted">Venta − costo</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card utilidad-card h-100 border-0 shadow-sm">
            <div class="card-body">
                <div class="text-muted small">Margen</div>
                <div class="value <?= (float)$totales['margen_pct'] >= 0 ? 'utilidad-pos' : 'utilidad-neg' ?>">
                    <?= number_format((float)$totales['margen_pct'], 1, ',', '.') ?>%
                </div>
                <div class="small text-muted">Sobre la venta</div>
            </div>
        </div>
    </div>
</div>

<?php if ($sinCosto > 0): ?>
    <div class="alert alert-warning">
        Hay <?= (int)$sinCosto ?> ítems vendidos sin costo de receta ni de compra.
        En esos casos la utilidad queda igual a la venta. Cargá receta o una compra para ver el margen real.
    </div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Utilidad por categoría</h5>
        <span class="small text-muted">Tocá una categoría para ver sus productos</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($categorias)): ?>
            <div class="p-4 text-muted">No hay ventas en este rango.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th></th>
                            <th>Categoría / Producto</th>
                            <th class="text-end">Unidades</th>
                            <th class="text-end">Venta</th>
                            <th class="text-end">Costo</th>
                            <th class="text-end">Utilidad</th>
                            <th class="text-end">Margen</th>
                            <th style="min-width:90px">Peso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $i => $cat): ?>
                            <?php
                            $collapseId = 'utilidad-cat-' . (int)$cat['categoria_id'] . '-' . $i;
                            $pctBar = round((abs((float)$cat['utilidad']) / $maxUtilidad) * 100);
                            $utilClass = (float)$cat['utilidad'] >= 0 ? 'utilidad-pos' : 'utilidad-neg';
                            ?>
                            <tr class="utilidad-cat" data-bs-toggle="collapse" data-bs-target="#<?= htmlspecialchars($collapseId) ?>" role="button" aria-expanded="false">
                                <td class="text-muted"><i class="bi bi-chevron-down"></i></td>
                                <td>
                                    <strong><?= htmlspecialchars((string)$cat['categoria']) ?></strong>
                                    <div class="small text-muted"><?= count($cat['productos']) ?> productos · <?= (int)$cat['pedidos'] ?> pedidos</div>
                                </td>
                                <td class="text-end"><?= number_format((float)$cat['cantidad'], 0, ',', '.') ?></td>
                                <td class="text-end"><?= utilidad_fmt_money((float)$cat['venta']) ?></td>
                                <td class="text-end"><?= utilidad_fmt_money((float)$cat['costo']) ?></td>
                                <td class="text-end fw-semibold <?= $utilClass ?>"><?= utilidad_fmt_money((float)$cat['utilidad']) ?></td>
                                <td class="text-end <?= $utilClass ?>"><?= number_format((float)$cat['margen_pct'], 1, ',', '.') ?>%</td>
                                <td>
                                    <div class="progress" style="height:8px;">
                                        <div class="progress-bar <?= (float)$cat['utilidad'] >= 0 ? 'bg-success' : 'bg-danger' ?>" style="width: <?= $pctBar ?>%"></div>
                                    </div>
                                </td>
                            </tr>
                            <tr class="collapse utilidad-prod" id="<?= htmlspecialchars($collapseId) ?>">
                                <td colspan="8" class="p-0">
                                    <table class="table table-sm mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Producto</th>
                                                <th class="text-end">Unidades</th>
                                                <th class="text-end">Venta</th>
                                                <th class="text-end">Costo</th>
                                                <th class="text-end">Utilidad</th>
                                                <th class="text-end">Margen</th>
                                                <th>Costo según</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($cat['productos'] as $prod): ?>
                                                <?php
                                                $prodClass = (float)$prod['utilidad'] >= 0 ? 'utilidad-pos' : 'utilidad-neg';
                                                $origen = (string)($prod['origen_costo'] ?? '');
                                                $origenLabel = [
                                                    'receta' => 'Receta',
                                                    'compra' => 'Última compra',
                                                    'sin_costo' => 'Sin costo',
                                                    'sin_dato' => 'Sin dato',
                                                ][$origen] ?? $origen;
                                                ?>
                                                <tr>
                                                    <td class="ps-4"><?= htmlspecialchars((string)$prod['producto']) ?></td>
                                                    <td class="text-end"><?= number_format((float)$prod['cantidad'], 0, ',', '.') ?></td>
                                                    <td class="text-end"><?= utilidad_fmt_money((float)$prod['venta']) ?></td>
                                                    <td class="text-end"><?= utilidad_fmt_money((float)$prod['costo']) ?></td>
                                                    <td class="text-end fw-semibold <?= $prodClass ?>"><?= utilidad_fmt_money((float)$prod['utilidad']) ?></td>
                                                    <td class="text-end <?= $prodClass ?>"><?= number_format((float)$prod['margen_pct'], 1, ',', '.') ?>%</td>
                                                    <td>
                                                        <?php if ($origen === 'sin_costo'): ?>
                                                            <span class="badge text-bg-warning"><?= htmlspecialchars($origenLabel) ?></span>
                                                        <?php else: ?>
                                                            <span class="small text-muted"><?= htmlspecialchars($origenLabel) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th></th>
                            <th>Total</th>
                            <th class="text-end"><?= number_format((float)$totales['cantidad'], 0, ',', '.') ?></th>
                            <th class="text-end"><?= utilidad_fmt_money((float)$totales['venta']) ?></th>
                            <th class="text-end"><?= utilidad_fmt_money((float)$totales['costo']) ?></th>
                            <th class="text-end <?= (float)$totales['utilidad'] >= 0 ? 'utilidad-pos' : 'utilidad-neg' ?>"><?= utilidad_fmt_money((float)$totales['utilidad']) ?></th>
                            <th class="text-end"><?= number_format((float)$totales['margen_pct'], 1, ',', '.') ?>%</th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
