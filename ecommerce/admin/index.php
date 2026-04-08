<?php
require 'includes/header.php';

function dashboard_table_exists(PDO $pdo, $table)
{
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        if ($stmt->fetchColumn()) {
            return true;
        }
    } catch (Throwable $e) {
    }

    try {
        $pdo->query("SELECT 1 FROM {$table} LIMIT 1");
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function dashboard_scalar(PDO $pdo, $sql, $default = 0)
{
    try {
        $value = $pdo->query($sql)->fetchColumn();
        return $value !== false && $value !== null ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

$total_productos = dashboard_table_exists($pdo, 'ecommerce_productos')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_productos WHERE activo = 1")
    : 0;

$total_categorias = dashboard_table_exists($pdo, 'ecommerce_categorias')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_categorias WHERE activo = 1")
    : 0;

$total_atributos = dashboard_table_exists($pdo, 'ecommerce_producto_atributos')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_producto_atributos")
    : 0;

$total_pedidos = dashboard_table_exists($pdo, 'ecommerce_pedidos')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_pedidos WHERE estado != 'cancelado'")
    : 0;

$ingresos_totales = dashboard_table_exists($pdo, 'ecommerce_pedidos')
    ? (float)dashboard_scalar($pdo, "SELECT SUM(total) FROM ecommerce_pedidos WHERE estado IN ('confirmado', 'preparando', 'enviado', 'entregado')", 0)
    : 0;

$cotizaciones_pendientes = dashboard_table_exists($pdo, 'ecommerce_cotizaciones')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_cotizaciones WHERE estado = 'pendiente'")
    : 0;

$visitas_pendientes = dashboard_table_exists($pdo, 'ecommerce_visitas')
    ? (int)dashboard_scalar($pdo, "SELECT COUNT(*) FROM ecommerce_visitas WHERE estado = 'pendiente'")
    : 0;

$ultimos_pedidos = [];
if (dashboard_table_exists($pdo, 'ecommerce_pedidos')) {
    try {
        $sql = "
            SELECT p.numero_pedido, c.nombre, p.total, p.estado, p.fecha_pedido
            FROM ecommerce_pedidos p
            LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
            WHERE p.estado != 'cancelado'
            ORDER BY p.fecha_pedido DESC
            LIMIT 6
        ";
        $ultimos_pedidos = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $ultimos_pedidos = [];
    }
}

$modulos = [
    ['perm' => 'pedidos', 'titulo' => 'Pedidos', 'desc' => 'Ventas y estados de compra', 'url' => 'pedidos.php', 'icon' => 'bi-cart-check'],
    ['perm' => 'ordenes_produccion', 'titulo' => 'Producción', 'desc' => 'Órdenes y fabricación', 'url' => 'ordenes_produccion.php', 'icon' => 'bi-gear-wide-connected'],
    ['perm' => 'instalaciones', 'titulo' => 'Instalaciones y visitas', 'desc' => 'Programación y seguimiento en un solo tablero', 'url' => 'instalaciones.php', 'icon' => 'bi-calendar-check'],
    ['perm' => 'crm', 'titulo' => 'CRM', 'desc' => 'Seguimiento comercial desde visitas y leads', 'url' => 'crm.php', 'icon' => 'bi-person-lines-fill'],
    ['perm' => 'calidad', 'titulo' => 'Calidad', 'desc' => 'Reclamos, rehechos, demoras y satisfacción', 'url' => 'calidad.php', 'icon' => 'bi-award'],
    ['perm' => 'finanzas', 'titulo' => 'Contabilidad', 'desc' => 'Impuestos y configuración fiscal', 'url' => 'contabilidad.php', 'icon' => 'bi-receipt-cutoff'],
    ['perm' => 'gastos', 'titulo' => 'Gastos', 'desc' => 'Control financiero diario', 'url' => 'gastos/gastos.php', 'icon' => 'bi-cash-coin'],
    ['perm' => 'cheques', 'titulo' => 'Cheques', 'desc' => 'Seguimiento y vencimientos', 'url' => 'cheques/cheques.php', 'icon' => 'bi-journal-check'],
    ['perm' => 'inventario', 'titulo' => 'Inventario', 'desc' => 'Stock y reposición', 'url' => 'inventario.php', 'icon' => 'bi-box-seam'],
    ['perm' => 'productos', 'titulo' => 'Reporte Productos', 'desc' => 'Vendidos y pendientes por color', 'url' => 'reporte_productos.php', 'icon' => 'bi-bar-chart'],
];
?>

<style>
    .dash-hero {
        background: linear-gradient(135deg, #1d4ed8 0%, #2563eb 55%, #3b82f6 100%);
        border-radius: 16px;
        color: #fff;
        padding: 1.25rem 1.4rem;
        box-shadow: 0 12px 28px rgba(37, 99, 235, 0.24);
    }
    .dash-hero .badge {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.25);
    }
    .kpi-card {
        border: 1px solid #e5eaf2;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(15, 23, 42, 0.06);
        height: 100%;
    }
    .kpi-icon {
        width: 40px;
        height: 40px;
        border-radius: 11px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.05rem;
    }
    .module-card {
        border: 1px solid #e9eef6;
        border-radius: 12px;
        transition: transform .18s ease, box-shadow .18s ease;
        height: 100%;
    }
    .module-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    }
    .security-list li {
        margin-bottom: .55rem;
    }
    @media (max-width: 767px) {
        .dash-hero {
            padding: 1rem;
        }
    }
</style>

<div class="dash-hero mb-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h1 class="h3 mb-1">Dashboard</h1>
            <p class="mb-0 opacity-75">Vista general de operación, ventas y seguridad del panel.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="../index.php" target="_blank" class="btn btn-light btn-sm">
                <i class="bi bi-shop me-1"></i>Ver Tienda
            </a>
            <span class="badge rounded-pill px-3 py-2">
                <i class="bi bi-shield-check me-1"></i>Panel protegido
            </span>
        </div>
    </div>
</div>

<div class="row g-3 mb-2">
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Productos activos</div>
                        <div class="h3 mb-0"><?= number_format($total_productos) ?></div>
                    </div>
                    <span class="kpi-icon bg-primary-subtle text-primary"><i class="bi bi-box-seam"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Categorías activas</div>
                        <div class="h3 mb-0"><?= number_format($total_categorias) ?></div>
                    </div>
                    <span class="kpi-icon bg-success-subtle text-success"><i class="bi bi-diagram-3"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Pedidos activos</div>
                        <div class="h3 mb-0"><?= number_format($total_pedidos) ?></div>
                    </div>
                    <span class="kpi-icon bg-warning-subtle text-warning"><i class="bi bi-receipt"></i></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="text-muted small">Ingresos acumulados</div>
                        <div class="h3 mb-0">$<?= number_format($ingresos_totales, 0, ',', '.') ?></div>
                    </div>
                    <span class="kpi-icon bg-info-subtle text-info"><i class="bi bi-currency-dollar"></i></span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center bg-white">
                <h5 class="mb-0">Últimos pedidos</h5>
                <a href="pedidos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($ultimos_pedidos)): ?>
                    <div class="p-3 text-muted">No hay pedidos recientes para mostrar.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-3">Pedido</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th class="pe-3">Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_pedidos as $pedido): ?>
                                    <tr>
                                        <td class="ps-3 fw-semibold"><?= admin_h($pedido['numero_pedido']) ?></td>
                                        <td><?= admin_h($pedido['nombre'] ?? 'Sin nombre') ?></td>
                                        <td>$<?= number_format((float)$pedido['total'], 2, ',', '.') ?></td>
                                        <td><span class="badge bg-primary-subtle text-primary"><?= admin_h($pedido['estado']) ?></span></td>
                                        <td class="pe-3 text-muted"><?= date('d/m/Y H:i', strtotime((string)$pedido['fecha_pedido'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h5 class="mb-0">Acciones rápidas</h5>
            </div>
            <div class="card-body d-grid gap-2">
                <?php if (isset($can_access) && $can_access('productos')): ?>
                    <a href="productos_crear.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Nuevo producto</a>
                <?php endif; ?>
                <?php if (isset($can_access) && $can_access('categorias')): ?>
                    <a href="categorias_crear.php" class="btn btn-outline-primary"><i class="bi bi-folder-plus me-1"></i>Nueva categoría</a>
                <?php endif; ?>
                <?php if (isset($can_access) && $can_access('ordenes_produccion')): ?>
                    <a href="ordenes_produccion.php" class="btn btn-outline-secondary"><i class="bi bi-gear me-1"></i>Ver producción</a>
                <?php endif; ?>
                <?php if (isset($can_access) && $can_access('instalaciones')): ?>
                    <a href="instalaciones.php" class="btn btn-outline-dark"><i class="bi bi-calendar2-week me-1"></i>Instalaciones y visitas (pendientes: <?= number_format($visitas_pendientes) ?>)</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-8">
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0">Módulos del sistema</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($modulos as $mod): ?>
                        <?php if (!isset($can_access) || !$can_access($mod['perm'])) continue; ?>
                        <div class="col-12 col-md-6">
                            <a href="<?= admin_h($mod['url']) ?>" class="text-decoration-none">
                                <div class="module-card p-3">
                                    <div class="d-flex align-items-start gap-2">
                                        <i class="bi <?= admin_h($mod['icon']) ?> text-primary"></i>
                                        <div>
                                            <div class="fw-semibold text-dark"><?= admin_h($mod['titulo']) ?></div>
                                            <div class="text-muted small"><?= admin_h($mod['desc']) ?></div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4">
        <div class="card h-100 border-danger-subtle">
            <div class="card-header bg-danger-subtle">
                <h5 class="mb-0 text-danger-emphasis"><i class="bi bi-shield-lock me-1"></i>Seguridad</h5>
            </div>
            <div class="card-body">
                <ul class="security-list ps-3 mb-3">
                    <li>Sesiones endurecidas con cookies HTTPOnly y SameSite.</li>
                    <li>Protección CSRF disponible para formularios POST del admin.</li>
                    <li>Cabeceras activas contra clickjacking y sniffing.</li>
                    <li>Control de permisos por rol en navegación y páginas.</li>
                </ul>
                <div class="d-grid gap-2">
                    <a href="auth/logout.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión segura</a>
                    <a href="../api/manual_robot.md" class="btn btn-outline-primary btn-sm" target="_blank"><i class="bi bi-book me-1"></i>Manual API Robot</a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-md-4">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="text-muted small">Cotizaciones pendientes</div>
                <div class="h4 mb-0"><?= number_format($cotizaciones_pendientes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="text-muted small">Visitas pendientes</div>
                <div class="h4 mb-0"><?= number_format($visitas_pendientes) ?></div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card kpi-card">
            <div class="card-body">
                <div class="text-muted small">Atributos cargados</div>
                <div class="h4 mb-0"><?= number_format($total_atributos) ?></div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
