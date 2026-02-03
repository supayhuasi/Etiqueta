<?php
require 'includes/header.php';

// Obtener items que necesitan reposición
$items_reponer = [];

// Materiales a reponer
$stmt = $pdo->query("
    SELECT 
        'material' as tipo_item,
        m.id,
        m.nombre,
        m.stock,
        m.stock_minimo,
        m.unidad_medida,
        m.tipo_origen,
        m.proveedor_habitual_id,
        p.nombre as proveedor_nombre,
        (m.stock_minimo - m.stock) as cantidad_reponer,
        CASE 
            WHEN m.stock < 0 THEN 'negativo'
            WHEN m.stock = 0 THEN 'sin_stock'
            WHEN m.stock <= m.stock_minimo THEN 'bajo_minimo'
        END as prioridad
    FROM ecommerce_materiales m
    LEFT JOIN ecommerce_proveedores p ON m.proveedor_habitual_id = p.id
    WHERE m.stock <= m.stock_minimo
    ORDER BY 
        CASE 
            WHEN m.stock < 0 THEN 1
            WHEN m.stock = 0 THEN 2
            ELSE 3
        END,
        m.stock ASC
");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Productos a reponer
$stmt = $pdo->query("
    SELECT 
        'producto' as tipo_item,
        pr.id,
        pr.nombre,
        pr.stock,
        pr.stock_minimo,
        'unidad' as unidad_medida,
        pr.tipo_origen,
        pr.proveedor_habitual_id,
        p.nombre as proveedor_nombre,
        (pr.stock_minimo - pr.stock) as cantidad_reponer,
        CASE 
            WHEN pr.stock < 0 THEN 'negativo'
            WHEN pr.stock = 0 THEN 'sin_stock'
            WHEN pr.stock <= pr.stock_minimo THEN 'bajo_minimo'
        END as prioridad
    FROM ecommerce_productos pr
    LEFT JOIN ecommerce_proveedores p ON pr.proveedor_habitual_id = p.id
    WHERE pr.stock <= pr.stock_minimo
    ORDER BY 
        CASE 
            WHEN pr.stock < 0 THEN 1
            WHEN pr.stock = 0 THEN 2
            ELSE 3
        END,
        pr.stock ASC
");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items_reponer = array_merge($materiales, $productos);

// Obtener historial de compras para referencia
$stmt = $pdo->query("
    SELECT 
        ci.material_id,
        ci.producto_id,
        c.proveedor_id,
        pr.nombre as proveedor_nombre,
        AVG(ci.precio_unitario) as precio_promedio,
        MAX(c.fecha_compra) as ultima_compra,
        COUNT(*) as veces_comprado
    FROM ecommerce_compras_items ci
    JOIN ecommerce_compras c ON ci.compra_id = c.id
    LEFT JOIN ecommerce_proveedores pr ON c.proveedor_id = pr.id
    GROUP BY ci.material_id, ci.producto_id, c.proveedor_id, pr.nombre
");
$historial_compras = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $hist) {
    $key = ($hist['material_id'] ? 'material_' . $hist['material_id'] : 'producto_' . $hist['producto_id']);
    if (!isset($historial_compras[$key])) {
        $historial_compras[$key] = [];
    }
    $historial_compras[$key][] = $hist;
}

// Estadísticas
$total_items = count($items_reponer);
$items_negativos = count(array_filter($items_reponer, fn($i) => $i['prioridad'] === 'negativo'));
$items_sin_stock = count(array_filter($items_reponer, fn($i) => $i['prioridad'] === 'sin_stock'));
$items_solo_compra = count(array_filter($items_reponer, fn($i) => $i['tipo_origen'] === 'compra'));
?>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1>⚠️ Reporte de Reposición</h1>
                <p class="text-muted">Items que necesitan ser repuestos urgentemente</p>
            </div>
            <div>
                <a href="inventario.php" class="btn btn-secondary">← Volver a Inventario</a>
                <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
            </div>
        </div>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h6>Total a Reponer</h6>
                <h3><?= $total_items ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body text-center">
                <h6>Stock Negativo</h6>
                <h3><?= $items_negativos ?></h3>
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
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h6>Por Compra</h6>
                <h3><?= $items_solo_compra ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Tabla de items a reponer -->
<div class="card">
    <div class="card-body">
        <?php if (empty($items_reponer)): ?>
            <div class="alert alert-success">
                ✅ ¡Todo el inventario está en niveles óptimos! No hay items para reponer.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Prioridad</th>
                            <th>Tipo</th>
                            <th>Nombre</th>
                            <th>Stock Actual</th>
                            <th>Stock Mínimo</th>
                            <th>Cantidad a Reponer</th>
                            <th>Origen</th>
                            <th>Proveedor</th>
                            <th>Historial</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items_reponer as $item): 
                            $key = $item['tipo_item'] . '_' . $item['id'];
                            $historial = $historial_compras[$key] ?? [];
                        ?>
                            <tr class="<?= $item['prioridad'] === 'negativo' ? 'table-danger' : ($item['prioridad'] === 'sin_stock' ? 'table-warning' : '') ?>">
                                <td>
                                    <?php if ($item['prioridad'] === 'negativo'): ?>
                                        <span class="badge bg-danger">🔴 URGENTE</span>
                                    <?php elseif ($item['prioridad'] === 'sin_stock'): ?>
                                        <span class="badge bg-warning text-dark">🟡 ALTA</span>
                                    <?php else: ?>
                                        <span class="badge bg-info">🔵 MEDIA</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipo_item'] === 'material'): ?>
                                        <span class="badge bg-info">Material</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Producto</span>
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                                <td>
                                    <strong class="<?= $item['stock'] < 0 ? 'text-danger' : 'text-secondary' ?>">
                                        <?= number_format($item['stock'], 2) ?> <?= htmlspecialchars($item['unidad_medida']) ?>
                                    </strong>
                                </td>
                                <td><?= number_format($item['stock_minimo'], 2) ?> <?= htmlspecialchars($item['unidad_medida']) ?></td>
                                <td>
                                    <strong class="text-primary">
                                        <?= number_format(max(0, $item['cantidad_reponer']), 2) ?> <?= htmlspecialchars($item['unidad_medida']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php if ($item['tipo_origen'] === 'fabricacion_propia'): ?>
                                        <span class="badge bg-primary">🏭 Fab. Propia</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">🛒 Compra</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($item['tipo_origen'] === 'compra'): ?>
                                        <?php if ($item['proveedor_nombre']): ?>
                                            <strong><?= htmlspecialchars($item['proveedor_nombre']) ?></strong>
                                        <?php elseif (!empty($historial)): ?>
                                            <!-- Mostrar proveedor más frecuente del historial -->
                                            <?php 
                                            usort($historial, fn($a, $b) => $b['veces_comprado'] - $a['veces_comprado']);
                                            $prov_recomendado = $historial[0];
                                            ?>
                                            <span class="text-muted">
                                                Sugerido: <?= htmlspecialchars($prov_recomendado['proveedor_nombre']) ?>
                                                <small>(<?= $prov_recomendado['veces_comprado'] ?> compras)</small>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-danger">Sin proveedor definido</small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <small class="text-muted">N/A</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($historial)): ?>
                                        <button class="btn btn-sm btn-outline-info" type="button" data-bs-toggle="collapse" data-bs-target="#historial_<?= $key ?>">
                                            📊 Ver Historial
                                        </button>
                                    <?php else: ?>
                                        <small class="text-muted">Sin historial</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            
                            <!-- Fila desplegable con historial -->
                            <?php if (!empty($historial)): ?>
                                <tr>
                                    <td colspan="9" class="p-0">
                                        <div class="collapse" id="historial_<?= $key ?>">
                                            <div class="card card-body bg-light m-2">
                                                <h6>📋 Historial de Compras</h6>
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Proveedor</th>
                                                            <th>Precio Promedio</th>
                                                            <th>Última Compra</th>
                                                            <th>Veces Comprado</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($historial as $hist): ?>
                                                            <tr>
                                                                <td><?= htmlspecialchars($hist['proveedor_nombre']) ?></td>
                                                                <td>$<?= number_format($hist['precio_promedio'], 2) ?></td>
                                                                <td><?= date('d/m/Y', strtotime($hist['ultima_compra'])) ?></td>
                                                                <td><?= $hist['veces_comprado'] ?> veces</td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Sección de recomendaciones -->
<?php if (!empty($items_reponer)): ?>
<div class="card mt-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0">💡 Recomendaciones</h5>
    </div>
    <div class="card-body">
        <ul>
            <li><strong>Items con stock negativo (<?= $items_negativos ?>):</strong> Requieren atención inmediata. Ya hay ventas/producción pendiente sin stock.</li>
            <li><strong>Items sin stock (<?= $items_sin_stock ?>):</strong> Prioridad alta para reponer antes de nuevos pedidos.</li>
            <li><strong>Items por compra (<?= $items_solo_compra ?>):</strong> Contactar a proveedores habituales o revisar historial de compras.</li>
            <li><strong>Items de fabricación propia:</strong> Revisar materias primas disponibles y programar producción.</li>
        </ul>
        <div class="mt-3">
            <a href="compras_crear.php" class="btn btn-primary">🛒 Crear Orden de Compra</a>
            <a href="ordenes_produccion.php" class="btn btn-success">🏭 Programar Producción</a>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
