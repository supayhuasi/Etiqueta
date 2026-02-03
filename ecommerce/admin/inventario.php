<?php
require 'includes/header.php';

// Filtros
$tipo_filtro = $_GET['tipo'] ?? 'todos'; // todos, materiales, productos
$alerta_filtro = $_GET['alerta'] ?? 'todos'; // todos, bajo_minimo, negativo, sin_alerta
$origen_filtro = $_GET['origen'] ?? 'todos'; // todos, fabricacion_propia, compra
$buscar = $_GET['buscar'] ?? '';

// Obtener materiales
$where_materiales = ["1=1"];
$params_materiales = [];

if ($buscar) {
    $where_materiales[] = "nombre LIKE ?";
    $params_materiales[] = "%$buscar%";
}

if ($origen_filtro !== 'todos') {
    $where_materiales[] = "tipo_origen = ?";
    $params_materiales[] = $origen_filtro;
}

$sql_materiales = "
    SELECT 
        'material' as tipo_item,
        id,
        nombre,
        stock,
        stock_minimo,
        tipo_origen,
        unidad_medida,
        proveedor_habitual_id,
        CASE 
            WHEN stock < 0 THEN 'negativo'
            WHEN stock = 0 THEN 'sin_stock'
            WHEN stock <= stock_minimo THEN 'bajo_minimo'
            ELSE 'normal'
        END as estado_alerta
    FROM ecommerce_materiales
    WHERE " . implode(" AND ", $where_materiales);

$stmt = $pdo->prepare($sql_materiales);
$stmt->execute($params_materiales);
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener productos
$where_productos = ["1=1"];
$params_productos = [];

if ($buscar) {
    $where_productos[] = "nombre LIKE ?";
    $params_productos[] = "%$buscar%";
}

if ($origen_filtro !== 'todos') {
    $where_productos[] = "tipo_origen = ?";
    $params_productos[] = $origen_filtro;
}

$sql_productos = "
    SELECT 
        'producto' as tipo_item,
        id,
        nombre,
        stock,
        stock_minimo,
        tipo_origen,
        'unidad' as unidad_medida,
        proveedor_habitual_id,
        CASE 
            WHEN stock < 0 THEN 'negativo'
            WHEN stock = 0 THEN 'sin_stock'
            WHEN stock <= stock_minimo THEN 'bajo_minimo'
            ELSE 'normal'
        END as estado_alerta
    FROM ecommerce_productos
    WHERE " . implode(" AND ", $where_productos);

$stmt = $pdo->prepare($sql_productos);
$stmt->execute($params_productos);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combinar inventario
$inventario = [];
if (in_array($tipo_filtro, ['todos', 'materiales'])) {
    $inventario = array_merge($inventario, $materiales);
}
if (in_array($tipo_filtro, ['todos', 'productos'])) {
    $inventario = array_merge($inventario, $productos);
}

// Aplicar filtro de alertas
if ($alerta_filtro !== 'todos') {
    $inventario = array_filter($inventario, function($item) use ($alerta_filtro) {
        if ($alerta_filtro === 'bajo_minimo') {
            return $item['estado_alerta'] === 'bajo_minimo';
        } elseif ($alerta_filtro === 'negativo') {
            return $item['estado_alerta'] === 'negativo';
        } elseif ($alerta_filtro === 'sin_alerta') {
            return $item['estado_alerta'] === 'normal';
        }
        return true;
    });
}

// Obtener proveedores
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_proveedores WHERE activo = 1");
$proveedores_map = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $prov) {
    $proveedores_map[$prov['id']] = $prov['nombre'];
}

// Estadísticas
$total_items = count($inventario);
$items_negativo = count(array_filter($inventario, fn($i) => $i['estado_alerta'] === 'negativo'));
$items_bajo_minimo = count(array_filter($inventario, fn($i) => $i['estado_alerta'] === 'bajo_minimo'));
$items_sin_stock = count(array_filter($inventario, fn($i) => $i['estado_alerta'] === 'sin_stock'));
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>📦 Inventario</h1>
                <p class="text-muted">Gestión de stock de materiales y productos</p>
            </div>
            <div>
                <a href="inventario_reporte_reponer.php" class="btn btn-warning">⚠️ Productos a Reponer</a>
                <a href="inventario_ajustes.php" class="btn btn-primary">⚙️ Ajustes de Inventario</a>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h6>Total Items</h6>
                <h3><?= $total_items ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h6>Stock Negativo</h6>
                <h3><?= $items_negativo ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h6>Bajo Mínimo</h6>
                <h3><?= $items_bajo_minimo ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h6>Sin Stock</h6>
                <h3><?= $items_sin_stock ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Buscar</label>
                <input type="text" name="buscar" class="form-control" value="<?= htmlspecialchars($buscar) ?>" placeholder="Nombre del item...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Tipo</label>
                <select name="tipo" class="form-select">
                    <option value="todos" <?= $tipo_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="materiales" <?= $tipo_filtro === 'materiales' ? 'selected' : '' ?>>Materiales</option>
                    <option value="productos" <?= $tipo_filtro === 'productos' ? 'selected' : '' ?>>Productos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Alertas</label>
                <select name="alerta" class="form-select">
                    <option value="todos" <?= $alerta_filtro === 'todos' ? 'selected' : '' ?>>Todas</option>
                    <option value="negativo" <?= $alerta_filtro === 'negativo' ? 'selected' : '' ?>>Stock Negativo</option>
                    <option value="bajo_minimo" <?= $alerta_filtro === 'bajo_minimo' ? 'selected' : '' ?>>Bajo Mínimo</option>
                    <option value="sin_alerta" <?= $alerta_filtro === 'sin_alerta' ? 'selected' : '' ?>>Sin Alertas</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Origen</label>
                <select name="origen" class="form-select">
                    <option value="todos" <?= $origen_filtro === 'todos' ? 'selected' : '' ?>>Todos</option>
                    <option value="fabricacion_propia" <?= $origen_filtro === 'fabricacion_propia' ? 'selected' : '' ?>>Fabricación Propia</option>
                    <option value="compra" <?= $origen_filtro === 'compra' ? 'selected' : '' ?>>Compra</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">🔍 Filtrar</button>
                <a href="inventario.php" class="btn btn-secondary">🔄 Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Tabla de inventario -->
<div class="card">
    <div class="card-body">
        <?php if (empty($inventario)): ?>
            <div class="alert alert-info">No hay items en el inventario</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Stock Actual</th>
                            <th>Stock Mínimo</th>
                            <th>Estado</th>
                            <th>Origen</th>
                            <th>Proveedor Habitual</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inventario as $item): ?>
                            <tr>
                                <td>
                                    <?php if ($item['tipo_item'] === 'material'): ?>
                                        <span class="badge bg-info">Material</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Producto</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                                <td>
                                    <strong class="<?= $item['stock'] < 0 ? 'text-danger' : ($item['stock'] == 0 ? 'text-secondary' : '') ?>">
                                        <?= number_format($item['stock'], 2) ?> <?= htmlspecialchars($item['unidad_medida']) ?>
                                    </strong>
                                </td>
                                <td><?= number_format($item['stock_minimo'], 2) ?> <?= htmlspecialchars($item['unidad_medida']) ?></td>
                                <td>
                                    <?php if ($item['estado_alerta'] === 'negativo'): ?>
                                        <span class="badge bg-danger">⚠️ Stock Negativo</span>
                                    <?php elseif ($item['estado_alerta'] === 'sin_stock'): ?>
                                        <span class="badge bg-secondary">Sin Stock</span>
                                    <?php elseif ($item['estado_alerta'] === 'bajo_minimo'): ?>
                                        <span class="badge bg-warning text-dark">⚠️ Bajo Mínimo</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">✓ Normal</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipo_origen'] === 'fabricacion_propia'): ?>
                                        <span class="badge bg-primary">🏭 Fabricación Propia</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">🛒 Compra</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['proveedor_habitual_id']): ?>
                                        <?= htmlspecialchars($proveedores_map[$item['proveedor_habitual_id']] ?? 'N/A') ?>
                                    <?php else: ?>
                                        <small class="text-muted">-</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipo_item'] === 'material'): ?>
                                        <a href="materiales_editar.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">✏️ Editar</a>
                                    <?php else: ?>
                                        <a href="productos_crear.php?id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary">✏️ Editar</a>
                                    <?php endif; ?>
                                    <a href="inventario_movimientos.php?tipo=<?= $item['tipo_item'] ?>&id=<?= $item['id'] ?>" class="btn btn-sm btn-outline-secondary">📊 Historial</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
