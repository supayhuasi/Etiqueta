<?php
// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'includes/header.php';

// Verificar que $pdo existe
if (!isset($pdo)) {
    die('Error: No hay conexión a la base de datos');
}

// Filtros
$categoria_filtro = $_GET['categoria'] ?? '';
$stock_filtro = $_GET['stock'] ?? ''; // todos, bajo, sin, negativo
$buscar = $_GET['buscar'] ?? '';
$exportar = !empty($_GET['exportar']);

try {
    // Obtener categorías
    $stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias WHERE activo = 1 ORDER BY nombre");
    $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error al obtener categorías: ' . $e->getMessage());
}

// Construir consulta de productos
$where = ["p.activo = 1"];
$params = [];

if ($buscar) {
    $where[] = "p.nombre LIKE ?";
    $params[] = "%$buscar%";
}

if ($categoria_filtro) {
    $where[] = "p.categoria_id = ?";
    $params[] = intval($categoria_filtro);
}

// Verificar qué columna de stock mínimo existe
try {
    $columnas = $pdo->query("SHOW COLUMNS FROM ecommerce_productos")->fetchAll(PDO::FETCH_ASSOC);
    $columnas_nombres = array_column($columnas, 'Field');
    
    $stock_minimo_col = 'stock_minimo'; // por defecto
    if (!in_array('stock_minimo', $columnas_nombres)) {
        $stock_minimo_col = '0'; // Si no existe, usar 0 como stock mínimo
    }
} catch (Exception $e) {
    $stock_minimo_col = '0';
}

if ($stock_filtro === 'bajo') {
    $where[] = "p.stock > 0 AND p.stock <= COALESCE(p.stock_minimo, 0)";
} elseif ($stock_filtro === 'sin') {
    $where[] = "p.stock = 0";
} elseif ($stock_filtro === 'negativo') {
    $where[] = "p.stock < 0";
}

$sql = "
    SELECT 
        p.id,
        p.nombre,
        p.codigo,
        p.stock,
        COALESCE(p.stock_minimo, 0) as stock_minimo,
        p.precio_base,
        p.fecha_creacion,
        c.nombre as categoria,
        CASE 
            WHEN p.stock < 0 THEN 'negativo'
            WHEN p.stock = 0 THEN 'sin_stock'
            WHEN p.stock <= COALESCE(p.stock_minimo, 0) THEN 'bajo_minimo'
            ELSE 'normal'
        END as estado,
        CASE 
            WHEN p.stock < 0 THEN 'danger'
            WHEN p.stock = 0 THEN 'warning'
            WHEN p.stock <= COALESCE(p.stock_minimo, 0) THEN 'warning'
            ELSE 'success'
        END as badge_color
    FROM ecommerce_productos p
    LEFT JOIN ecommerce_categorias c ON p.categoria_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY 
        CASE 
            WHEN p.stock < 0 THEN 1
            WHEN p.stock = 0 THEN 2
            WHEN p.stock <= COALESCE(p.stock_minimo, 0) THEN 3
            ELSE 4
        END,
        p.nombre ASC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error en consulta de productos: ' . $e->getMessage());
}

// Si es exportar a CSV
if ($exportar) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventario_productos_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Nombre', 'Código', 'Categoría', 'Stock Actual', 'Stock Mínimo', 'Precio Base', 'Estado', 'Fecha Creación'], ';');
    
    foreach ($productos as $p) {
        fputcsv($output, [
            $p['id'],
            $p['nombre'],
            $p['codigo'] ?? '',
            $p['categoria'] ?? '',
            $p['stock'],
            $p['stock_minimo'] ?? 0,
            $p['precio_base'],
            $p['estado'],
            date('d/m/Y', strtotime($p['fecha_creacion']))
        ], ';');
    }
    
    fclose($output);
    exit;
}

// Estadísticas
$total_productos = count($productos);
$total_stock = 0;
foreach ($productos as $p) {
    $total_stock += $p['stock'];
}

$productos_bajo_minimo = 0;
$productos_sin_stock = 0;
$productos_negativo = 0;
$valor_total_stock = 0;

foreach ($productos as $p) {
    $stock_min = $p['stock_minimo'] ?? 0;
    if ($p['stock'] > 0 && $p['stock'] <= $stock_min) {
        $productos_bajo_minimo++;
    }
    if ($p['stock'] == 0) {
        $productos_sin_stock++;
    }
    if ($p['stock'] < 0) {
        $productos_negativo++;
    }
    $valor_total_stock += $p['stock'] * ($p['precio_base'] ?? 0);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>📋 Reporte de Productos en Inventario</h1>
        <p class="text-muted">Listado completo de productos con estado de stock</p>
    </div>
    <a href="?<?= http_build_query(array_merge($_GET, ['exportar' => 1])) ?>" class="btn btn-success">📥 Exportar CSV</a>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3 mb-2">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-0">Total Productos</h6>
                <h3><?= $total_productos ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-0">Total Stock</h6>
                <h3><?= number_format($total_stock) ?> unidades</h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="mb-0">Bajo Mínimo</h6>
                <h3><?= $productos_bajo_minimo ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-2">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <h6 class="mb-0">Problemas</h6>
                <h3><?= $productos_sin_stock + $productos_negativo ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Búsqueda</label>
                <input type="text" name="buscar" class="form-control" placeholder="Nombre o código..." value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">Categoría</label>
                <select name="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoria_filtro == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Stock</label>
                <select name="stock" class="form-select">
                    <option value="">Todos</option>
                    <option value="normal" <?= $stock_filtro === 'normal' ? 'selected' : '' ?>>Niveles normales</option>
                    <option value="bajo" <?= $stock_filtro === 'bajo' ? 'selected' : '' ?>>Bajo mínimo</option>
                    <option value="sin" <?= $stock_filtro === 'sin' ? 'selected' : '' ?>>Sin stock</option>
                    <option value="negativo" <?= $stock_filtro === 'negativo' ? 'selected' : '' ?>>Negativo</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <a href="inventario_reporte_productos.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de productos -->
<div class="card">
    <div class="card-body table-responsive">
        <?php if (empty($productos)): ?>
            <div class="alert alert-info">No hay productos que coincidan con los filtros.</div>
        <?php else: ?>
            <table class="table table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th style="width: 25%;">Nombre</th>
                        <th style="width: 15%;">Categoría</th>
                        <th style="width: 10%;">Código</th>
                        <th class="text-end">Stock Actual</th>
                        <th class="text-end">Stock Mín.</th>
                        <th class="text-end">Precio Base</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($p['nombre']) ?></strong>
                                <br>
                                <small class="text-muted">Agregado: <?= date('d/m/Y', strtotime($p['fecha_creacion'])) ?></small>
                            </td>
                            <td><?= htmlspecialchars($p['categoria'] ?? 'Sin categoría') ?></td>
                            <td><code><?= htmlspecialchars($p['codigo'] ?? '-') ?></code></td>
                            <td class="text-end">
                                <strong><?= number_format($p['stock']) ?></strong> un.
                            </td>
                            <td class="text-end">
                                <code><?= number_format($p['stock_minimo'] ?? 0) ?></code>
                            </td>
                            <td class="text-end">
                                $<?= number_format($p['precio_base'] ?? 0, 2) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-<?= $p['badge_color'] ?>">
                                    <?php
                                    if ($p['estado'] === 'negativo') {
                                        echo '🔴 Negativo';
                                    } elseif ($p['estado'] === 'sin_stock') {
                                        echo '⚠️ Sin stock';
                                    } elseif ($p['estado'] === 'bajo_minimo') {
                                        echo '🟡 Bajo mín.';
                                    } else {
                                        echo '🟢 Normal';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="productos_editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">✏️</a>
                                <a href="inventario_ajustes.php?producto_id=<?= $p['id'] ?>" class="btn btn-sm btn-outline-info" title="Ajustar stock">📊</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
