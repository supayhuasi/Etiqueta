
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once 'includes/header.php';

// Verificar permisos si es necesario
// if (!isset($can_access) || !$can_access('productos')) { die('Acceso denegado'); }

$pdo = $pdo ?? null;
if (!$pdo) {
    require_once '../config.php';
}

// Filtros
$buscar = $_GET['buscar'] ?? '';
$color_filtro = $_GET['color'] ?? '';

// Consulta para obtener productos vendidos y pendientes de entrega, agrupados por producto y color
$sql = "
SELECT p.id AS producto_id, p.nombre AS producto, pa.valor AS color,
    SUM(CASE WHEN pe.estado IN ('confirmado','preparando','enviado','entregado') THEN pi.cantidad ELSE 0 END) AS vendidos,
    SUM(CASE WHEN pe.estado IN ('confirmado','preparando','enviado') THEN pi.cantidad ELSE 0 END) AS faltan_entregar
FROM ecommerce_pedidos pe
JOIN ecommerce_pedido_items pi ON pe.id = pi.pedido_id
JOIN ecommerce_productos p ON pi.producto_id = p.id
LEFT JOIN ecommerce_producto_atributos pa ON pa.producto_id = p.id AND pa.nombre = 'color'
WHERE pe.estado != 'cancelado'
";
$params = [];
if ($buscar) {
    $sql .= " AND p.nombre LIKE ?";
    $params[] = "%$buscar%";
}
if ($color_filtro) {
    $sql .= " AND pa.valor = ?";
    $params[] = $color_filtro;
}
$sql .= " GROUP BY p.id, pa.valor ORDER BY p.nombre, pa.valor";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$reporte = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de colores para filtro
$colores = $pdo->query("SELECT DISTINCT valor FROM ecommerce_producto_atributos WHERE nombre = 'color' AND valor IS NOT NULL AND valor != '' ORDER BY valor")->fetchAll(PDO::FETCH_COLUMN);

// Estadísticas
$total_productos = count(array_unique(array_column($reporte, 'producto_id')));
$total_vendidos = array_sum(array_column($reporte, 'vendidos'));
$total_faltan = array_sum(array_column($reporte, 'faltan_entregar'));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📦 Reporte de Productos Vendidos y Pendientes</h1>
        <p class="text-muted">Agrupado por producto y color</p>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-4 mb-2">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-0">Total Productos</h6>
                <h3><?= $total_productos ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-0">Vendidos</h6>
                <h3><?= number_format($total_vendidos) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-4 mb-2">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="mb-0">Faltan Entregar</h6>
                <h3><?= number_format($total_faltan) ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Búsqueda</label>
                <input type="text" name="buscar" class="form-control" placeholder="Nombre de producto..." value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Color</label>
                <select name="color" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($colores as $col): ?>
                        <option value="<?= htmlspecialchars($col) ?>" <?= $color_filtro === $col ? 'selected' : '' ?>><?= htmlspecialchars($col) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de productos -->
<div class="card">
    <div class="card-body table-responsive">
        <?php if (empty($reporte)): ?>
            <div class="alert alert-info">No hay productos que coincidan con los filtros.</div>
        <?php else: ?>
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Producto</th>
                        <th>Color</th>
                        <th class="text-end">Vendidos</th>
                        <th class="text-end">Faltan Entregar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reporte as $row): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['producto']) ?></strong></td>
                            <td><?= htmlspecialchars($row['color'] ?? 'Sin color') ?></td>
                            <td class="text-end"><?= (int)$row['vendidos'] ?></td>
                            <td class="text-end"><?= (int)$row['faltan_entregar'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
