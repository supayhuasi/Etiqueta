<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/utilidad_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

$periodo = utilidad_resolver_periodo($_GET);
$reporte = utilidad_reporte_rango($pdo, $periodo['desde'] . ' 00:00:00', $periodo['hasta'] . ' 23:59:59');
$reporte = utilidad_aplicar_filtro_categoria($reporte, $periodo['categoria_id']);
$categorias = $reporte['categorias'];
$totales = $reporte['totales'];
$sinCosto = (int)$reporte['sin_costo'];
$contexto = utilidad_contexto_periodo($pdo, $periodo['desde'] . ' 00:00:00', $periodo['hasta'] . ' 23:59:59', (float)$totales['utilidad']);
$gastosCtx = $contexto['gastos'];
$comprasCtx = $contexto['compras'];
$decision = $contexto['decision'];
$empresa = utilidad_empresa_membrete($pdo);

$categoriaNombre = 'Todas';
if ($periodo['categoria_id'] > 0) {
    foreach ($categorias as $cat) {
        $categoriaNombre = (string)$cat['categoria'];
        break;
    }
    if ($categoriaNombre === 'Todas') {
        try {
            $stmt = $pdo->prepare('SELECT nombre FROM ecommerce_categorias WHERE id = ?');
            $stmt->execute([$periodo['categoria_id']]);
            $categoriaNombre = (string)($stmt->fetchColumn() ?: 'Categoría #' . $periodo['categoria_id']);
        } catch (Throwable $e) {
            $categoriaNombre = 'Categoría #' . $periodo['categoria_id'];
        }
    }
}

$usuarioNombre = trim((string)($_SESSION['user']['nombre'] ?? $_SESSION['user']['usuario'] ?? ''));
$autoPrint = isset($_GET['print']) && (string)$_GET['print'] === '1';
$volverQs = http_build_query([
    'desde' => $periodo['desde'],
    'hasta' => $periodo['hasta'],
    'categoria_id' => $periodo['categoria_id'] > 0 ? $periodo['categoria_id'] : null,
]);
?>
<style>
html.admin-is-mobile .sidebar,
.sidebar,
.top-navbar,
.admin-sidebar-backdrop,
#chatWidgetBtn,
#chatWidgetPanel { display: none !important; }
.container-fluid, .container-fluid > .row { display: block !important; margin: 0 !important; padding: 0 !important; }
.main-content {
    width: 100% !important;
    max-width: 920px !important;
    margin: 0 auto !important;
    padding: 28px 20px 48px !important;
    background: #fff;
}
body { background: #e8edf3 !important; }
.util-doc {
    background: #fff;
    color: #111827;
    font-family: "Segoe UI", Calibri, Arial, sans-serif;
}
.util-letterhead {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    border-bottom: 2px solid #111827;
    padding-bottom: 14px;
    margin-bottom: 18px;
}
.util-letterhead img { max-height: 56px; max-width: 160px; object-fit: contain; }
.util-letterhead h1 {
    font-size: 1.35rem;
    margin: 0 0 4px;
    font-weight: 700;
    letter-spacing: .02em;
}
.util-meta { font-size: 12px; color: #4b5563; line-height: 1.45; }
.util-kpis {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 10px;
    margin: 0 0 20px;
}
.util-kpi {
    border: 1px solid #d1d5db;
    padding: 10px 12px;
}
.util-kpi .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
.util-kpi .val { font-size: 1.15rem; font-weight: 700; margin-top: 2px; }
.util-pos { color: #166534; }
.util-neg { color: #991b1b; }
.util-doc table { width: 100%; border-collapse: collapse; font-size: 12px; }
.util-doc th, .util-doc td { border: 1px solid #d1d5db; padding: 6px 8px; }
.util-doc thead th { background: #111827; color: #fff; font-weight: 600; }
.util-doc .cat-row { background: #f3f4f6; font-weight: 700; }
.util-doc .prod-row td:first-child { padding-left: 22px; }
.util-doc tfoot th { background: #e5e7eb; }
.util-ctx { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 18px 0 8px; }
.util-ctx h2 { font-size: 13px; margin: 0 0 8px; text-transform: uppercase; letter-spacing: .04em; }
.util-kpi-wide { grid-column: 1 / -1; display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; }
.util-note { font-size: 11px; color: #4b5563; margin-top: 16px; }
.util-actions { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
@media (max-width: 700px) {
    .util-kpis, .util-ctx, .util-kpi-wide { grid-template-columns: 1fr 1fr; }
}
@page {
    size: A4;
    margin: 14mm 12mm 16mm;
}
@media print {
    html, body { background: #fff !important; }
    .util-actions, .sidebar, .top-navbar, #chatWidgetBtn, #chatWidgetPanel { display: none !important; }
    .main-content { max-width: none !important; padding: 0 !important; }
    .util-doc { box-shadow: none !important; }
    .util-doc thead { display: table-header-group; }
    .util-doc tr { break-inside: avoid; }
}
</style>

<div class="util-doc">
    <div class="util-actions no-print">
        <a class="btn btn-outline-secondary btn-sm" href="utilidad_productos.php?<?= htmlspecialchars($volverQs) ?>">← Volver</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <header class="util-letterhead">
        <div>
            <?php if (!empty($empresa['logo'])): ?>
                <img src="<?= htmlspecialchars((string)$empresa['logo']) ?>" alt="<?= htmlspecialchars($empresa['nombre']) ?>">
            <?php endif; ?>
            <div class="util-meta mt-2">
                <strong><?= htmlspecialchars($empresa['nombre']) ?></strong><br>
                Informe interno de rentabilidad
            </div>
        </div>
        <div class="text-end">
            <h1>Utilidad por categoría</h1>
            <div class="util-meta">
                Período: <strong><?= htmlspecialchars($periodo['label']) ?></strong><br>
                Categoría: <strong><?= htmlspecialchars($categoriaNombre) ?></strong><br>
                Generado: <?= htmlspecialchars(date('d/m/Y H:i')) ?>
                <?php if ($usuarioNombre !== ''): ?>
                    · <?= htmlspecialchars($usuarioNombre) ?>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="util-kpis">
        <div class="util-kpi">
            <div class="lbl">Venta</div>
            <div class="val"><?= utilidad_fmt_money((float)$totales['venta']) ?></div>
            <div class="util-meta"><?= number_format((float)$totales['cantidad'], 0, ',', '.') ?> uds · <?= (int)$totales['pedidos'] ?> pedidos</div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Costo</div>
            <div class="val"><?= utilidad_fmt_money((float)$totales['costo']) ?></div>
            <div class="util-meta"><?= (int)$totales['productos'] ?> productos</div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Utilidad</div>
            <div class="val <?= (float)$totales['utilidad'] >= 0 ? 'util-pos' : 'util-neg' ?>"><?= utilidad_fmt_money((float)$totales['utilidad']) ?></div>
            <div class="util-meta">Venta − costo</div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Margen</div>
            <div class="val <?= (float)$totales['margen_pct'] >= 0 ? 'util-pos' : 'util-neg' ?>"><?= number_format((float)$totales['margen_pct'], 1, ',', '.') ?>%</div>
            <div class="util-meta">Sobre la venta</div>
        </div>
    </section>

    <?php if (empty($categorias)): ?>
        <p>No hay ventas en el período seleccionado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Categoría / Producto</th>
                    <th class="text-end">Unidades</th>
                    <th class="text-end">Venta</th>
                    <th class="text-end">Costo</th>
                    <th class="text-end">Utilidad</th>
                    <th class="text-end">Margen</th>
                    <th>Origen del costo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categorias as $cat): ?>
                    <tr class="cat-row">
                        <td><?= htmlspecialchars((string)$cat['categoria']) ?></td>
                        <td class="text-end"><?= number_format((float)$cat['cantidad'], 0, ',', '.') ?></td>
                        <td class="text-end"><?= utilidad_fmt_money((float)$cat['venta']) ?></td>
                        <td class="text-end"><?= utilidad_fmt_money((float)$cat['costo']) ?></td>
                        <td class="text-end <?= (float)$cat['utilidad'] >= 0 ? 'util-pos' : 'util-neg' ?>"><?= utilidad_fmt_money((float)$cat['utilidad']) ?></td>
                        <td class="text-end"><?= number_format((float)$cat['margen_pct'], 1, ',', '.') ?>%</td>
                        <td><?= count($cat['productos']) ?> productos · <?= (int)$cat['pedidos'] ?> pedidos</td>
                    </tr>
                    <?php foreach ($cat['productos'] as $prod): ?>
                        <?php
                        $origen = (string)($prod['origen_costo'] ?? '');
                        $origenLabel = [
                            'receta' => 'Receta',
                            'compra' => 'Última compra',
                            'sin_costo' => 'Sin costo cargado',
                            'sin_dato' => 'Sin dato',
                        ][$origen] ?? $origen;
                        $cls = (float)$prod['utilidad'] >= 0 ? 'util-pos' : 'util-neg';
                        ?>
                        <tr class="prod-row">
                            <td><?= htmlspecialchars((string)$prod['producto']) ?></td>
                            <td class="text-end"><?= number_format((float)$prod['cantidad'], 0, ',', '.') ?></td>
                            <td class="text-end"><?= utilidad_fmt_money((float)$prod['venta']) ?></td>
                            <td class="text-end"><?= utilidad_fmt_money((float)$prod['costo']) ?></td>
                            <td class="text-end <?= $cls ?>"><?= utilidad_fmt_money((float)$prod['utilidad']) ?></td>
                            <td class="text-end <?= $cls ?>"><?= number_format((float)$prod['margen_pct'], 1, ',', '.') ?>%</td>
                            <td><?= htmlspecialchars($origenLabel) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th>Total general</th>
                    <th class="text-end"><?= number_format((float)$totales['cantidad'], 0, ',', '.') ?></th>
                    <th class="text-end"><?= utilidad_fmt_money((float)$totales['venta']) ?></th>
                    <th class="text-end"><?= utilidad_fmt_money((float)$totales['costo']) ?></th>
                    <th class="text-end <?= (float)$totales['utilidad'] >= 0 ? 'util-pos' : 'util-neg' ?>"><?= utilidad_fmt_money((float)$totales['utilidad']) ?></th>
                    <th class="text-end"><?= number_format((float)$totales['margen_pct'], 1, ',', '.') ?>%</th>
                    <th></th>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <h2 style="font-size:14px;margin:22px 0 10px;">Contexto para decidir</h2>
    <section class="util-kpis util-kpi-wide">
        <div class="util-kpi">
            <div class="lbl">Después de gastos</div>
            <div class="val <?= (float)$decision['despues_gastos'] >= 0 ? 'util-pos' : 'util-neg' ?>"><?= utilidad_fmt_money((float)$decision['despues_gastos']) ?></div>
            <div class="util-meta">Utilidad de productos − gastos</div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Gastos</div>
            <div class="val"><?= utilidad_fmt_money((float)$gastosCtx['total']) ?></div>
            <div class="util-meta"><?= (int)$gastosCtx['cantidad'] ?> · pagado <?= utilidad_fmt_money((float)$gastosCtx['pagado']) ?></div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Compras</div>
            <div class="val"><?= utilidad_fmt_money((float)$comprasCtx['total']) ?></div>
            <div class="util-meta"><?= (int)$comprasCtx['cantidad'] ?> órdenes</div>
        </div>
        <div class="util-kpi">
            <div class="lbl">Salida de caja</div>
            <div class="val"><?= utilidad_fmt_money((float)$decision['salida_caja']) ?></div>
            <div class="util-meta">Gastos + compras</div>
        </div>
    </section>
    <div class="util-ctx">
        <div>
            <h2>Gastos por tipo</h2>
            <?php if (empty($gastosCtx['tipos'])): ?>
                <p class="util-meta">Sin gastos en el período.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Tipo</th><th class="text-end">Cant.</th><th class="text-end">Monto</th></tr></thead>
                    <tbody>
                        <?php foreach ($gastosCtx['tipos'] as $tipo): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$tipo['nombre']) ?></td>
                                <td class="text-end"><?= (int)$tipo['cantidad'] ?></td>
                                <td class="text-end"><?= utilidad_fmt_money((float)$tipo['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div>
            <h2>Compras por proveedor</h2>
            <?php if (empty($comprasCtx['proveedores'])): ?>
                <p class="util-meta">Sin compras en el período.</p>
            <?php else: ?>
                <table>
                    <thead><tr><th>Proveedor</th><th class="text-end">Órd.</th><th class="text-end">Monto</th></tr></thead>
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
            <?php endif; ?>
        </div>
    </div>

    <p class="util-note">
        Criterio: utilidad = venta neta (descuentos del pedido prorrateados) − costo.
        El costo proviene de la receta de materiales o, si no hay receta, de la última compra.
        El envío no se incluye.
        Gastos y compras son informativos: no se restan del margen de cada producto. Las compras son reposición de stock / salida de caja.
        <?php if ($sinCosto > 0): ?>
            Ítems sin costo cargado: <?= (int)$sinCosto ?> (en esos casos la utilidad coincide con la venta).
        <?php endif; ?>
        Documento de uso interno. No es un comprobante fiscal.
    </p>
</div>

<?php if ($autoPrint): ?>
<script>
window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 250);
});
</script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
