<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/utilidad_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

$periodo = utilidad_resolver_periodo($_GET);
$desde = $periodo['desde'];
$hasta = $periodo['hasta'];
$categoriaFiltro = $periodo['categoria_id'];
$labelRango = $periodo['label'];

$reporte = utilidad_reporte_rango($pdo, $desde . ' 00:00:00', $hasta . ' 23:59:59');
$reporte = utilidad_aplicar_filtro_categoria($reporte, $categoriaFiltro);
$categorias = $reporte['categorias'];
$totales = $reporte['totales'];
$sinCosto = (int)$reporte['sin_costo'];
$contexto = utilidad_contexto_periodo($pdo, $desde . ' 00:00:00', $hasta . ' 23:59:59', (float)$totales['utilidad']);
$gastosCtx = $contexto['gastos'];
$comprasCtx = $contexto['compras'];
$decision = $contexto['decision'];
$reporteQs = http_build_query([
    'desde' => $desde,
    'hasta' => $hasta,
    'categoria_id' => $categoriaFiltro > 0 ? $categoriaFiltro : null,
    'print' => 1,
]);

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
?>
<style>
.utilidad-card .value { font-size: 1.45rem; font-weight: 700; }
.utilidad-neg { color: #b91c1c; }
.utilidad-pos { color: #15803d; }
.utilidad-cat + .utilidad-prod { background: #f8fafc; }
.utilidad-prod td { font-size: .92rem; }
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="mb-1">Utilidad por categoría</h1>
        <p class="text-muted mb-0">Venta menos costo, por producto y agrupado por categoría. <?= htmlspecialchars($labelRango) ?></p>
    </div>
    <div class="utilidad-acciones d-flex flex-wrap gap-2">
        <a class="btn btn-outline-primary" href="utilidad_productos_reporte.php?<?= htmlspecialchars($reporteQs) ?>" target="_blank">Imprimir reporte</a>
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
            Gastos y compras del período se muestran aparte, para decidir: no se restan del margen de cada producto.
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-transparent">
        <h5 class="mb-0">Contexto para decidir</h5>
        <div class="small text-muted">Gastos y compras del mismo rango. No modifican la utilidad por producto.</div>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-6 col-lg-3">
                <div class="small text-muted">Después de gastos</div>
                <div class="fs-4 fw-bold <?= (float)$decision['despues_gastos'] >= 0 ? 'utilidad-pos' : 'utilidad-neg' ?>">
                    <?= utilidad_fmt_money((float)$decision['despues_gastos']) ?>
                </div>
                <div class="small text-muted">Utilidad de productos − gastos</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="small text-muted">Gastos del período</div>
                <div class="fs-4 fw-bold"><?= utilidad_fmt_money((float)$gastosCtx['total']) ?></div>
                <div class="small text-muted"><?= (int)$gastosCtx['cantidad'] ?> gastos · pagado <?= utilidad_fmt_money((float)$gastosCtx['pagado']) ?></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="small text-muted">Compras del período</div>
                <div class="fs-4 fw-bold"><?= utilidad_fmt_money((float)$comprasCtx['total']) ?></div>
                <div class="small text-muted"><?= (int)$comprasCtx['cantidad'] ?> órdenes · reposición de stock</div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="small text-muted">Salida de caja</div>
                <div class="fs-4 fw-bold"><?= utilidad_fmt_money((float)$decision['salida_caja']) ?></div>
                <div class="small text-muted">Gastos + compras (efectivo que salió)</div>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-lg-6">
                <h6 class="mb-2">Gastos por tipo</h6>
                <?php if (empty($gastosCtx['tipos'])): ?>
                    <div class="text-muted small">No hay gastos en este rango.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Tipo</th>
                                    <th class="text-end">Cantidad</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($gastosCtx['tipos'] as $tipo): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$tipo['nombre']) ?></td>
                                        <td class="text-end"><?= (int)$tipo['cantidad'] ?></td>
                                        <td class="text-end"><?= utilidad_fmt_money((float)$tipo['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Pendiente de pagar</th>
                                    <th></th>
                                    <th class="text-end"><?= utilidad_fmt_money((float)$gastosCtx['pendiente']) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <h6 class="mb-2">Compras por proveedor</h6>
                <?php if (empty($comprasCtx['proveedores']) && empty($comprasCtx['estados'])): ?>
                    <div class="text-muted small">No hay compras en este rango.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Proveedor</th>
                                    <th class="text-end">Órdenes</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($comprasCtx['proveedores'] as $prov): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$prov['nombre']) ?></td>
                                        <td class="text-end"><?= (int)$prov['cantidad'] ?></td>
                                        <td class="text-end"><?= utilidad_fmt_money((float)$prov['total']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($comprasCtx['estados'])): ?>
                        <div class="small text-muted mt-2">
                            <?php foreach ($comprasCtx['estados'] as $est): ?>
                                <span class="me-2"><?= htmlspecialchars((string)$est['nombre']) ?>: <?= utilidad_fmt_money((float)$est['total']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
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
