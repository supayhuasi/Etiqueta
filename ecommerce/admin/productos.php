<?php
require 'includes/header.php';

$categoria_filter = $_GET['categoria'] ?? '';
$tipo_filter = $_GET['tipo'] ?? '';
$estado_filter = $_GET['estado'] ?? '';
$busqueda = $_GET['busqueda'] ?? '';
$pagina = intval($_GET['pagina'] ?? 1);
if ($pagina < 1) $pagina = 1;

$productos_por_pagina = 25;
$offset = ($pagina - 1) * $productos_por_pagina;

$query = "
    SELECT p.*, c.nombre as categoria_nombre 
    FROM ecommerce_productos p
    JOIN ecommerce_categorias c ON p.categoria_id = c.id
    WHERE 1=1
";
$count_query = "
    SELECT COUNT(*) as total 
    FROM ecommerce_productos p
    JOIN ecommerce_categorias c ON p.categoria_id = c.id
    WHERE 1=1
";
$params = [];

if (($_POST['accion'] ?? '') === 'toggle_receta') {
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $usa_receta = isset($_POST['usa_receta']) ? 1 : 0;
    if ($producto_id > 0) {
        $stmt = $pdo->prepare("UPDATE ecommerce_productos SET usa_receta = ? WHERE id = ?");
        $stmt->execute([$usa_receta, $producto_id]);
    }
    header("Location: productos.php");
    exit;
}

// Filtros
if (!empty($busqueda)) {
    $query .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.descripcion LIKE ?)";
    $count_query .= " AND (p.nombre LIKE ? OR p.codigo LIKE ? OR p.descripcion LIKE ?)";
    $busqueda_param = "%$busqueda%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
}

if (!empty($categoria_filter)) {
    $query .= " AND p.categoria_id = ?";
    $count_query .= " AND p.categoria_id = ?";
    $params[] = $categoria_filter;
}

if (!empty($tipo_filter)) {
    $query .= " AND p.tipo_precio = ?";
    $count_query .= " AND p.tipo_precio = ?";
    $params[] = $tipo_filter;
}

if (!empty($estado_filter)) {
    if ($estado_filter === 'activo') {
        $query .= " AND p.activo = 1";
        $count_query .= " AND p.activo = 1";
    } elseif ($estado_filter === 'inactivo') {
        $query .= " AND p.activo = 0";
        $count_query .= " AND p.activo = 0";
    } elseif ($estado_filter === 'visible') {
        $query .= " AND p.mostrar_ecommerce = 1";
        $count_query .= " AND p.mostrar_ecommerce = 1";
    } elseif ($estado_filter === 'oculto') {
        $query .= " AND p.mostrar_ecommerce = 0";
        $count_query .= " AND p.mostrar_ecommerce = 0";
    }
}

// Obtener total de productos ANTES de agregar LIMIT/OFFSET
$stmt = $pdo->prepare($count_query);
$stmt->execute($params);
$total_productos = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_paginas = ceil($total_productos / $productos_por_pagina);

// Agregar LIMIT/OFFSET a la query de productos
$query .= " ORDER BY p.orden, p.nombre LIMIT " . intval($productos_por_pagina) . " OFFSET " . intval($offset);

// Obtener productos paginados
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener categorías para el filtro
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir URL de filtros para paginación
$filter_params = [];
if (!empty($busqueda)) $filter_params['busqueda'] = $busqueda;
if (!empty($categoria_filter)) $filter_params['categoria'] = $categoria_filter;
if (!empty($tipo_filter)) $filter_params['tipo'] = $tipo_filter;
if (!empty($estado_filter)) $filter_params['estado'] = $estado_filter;
$filter_url = !empty($filter_params) ? '&' . http_build_query($filter_params) : '';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Productos</h1>
    <a href="productos_crear.php" class="btn btn-primary">+ Nuevo Producto</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <!-- Búsqueda -->
            <div class="col-md-3">
                <label for="busqueda" class="form-label">Buscar:</label>
                <input type="text" name="busqueda" id="busqueda" class="form-control" placeholder="Nombre, código o descripción..." value="<?= htmlspecialchars($busqueda) ?>">
                <small class="text-muted">Busca en nombre, código o descripción</small>
            </div>

            <!-- Categoría -->
            <div class="col-md-2">
                <label for="categoria" class="form-label">Categoría:</label>
                <select name="categoria" id="categoria" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $categoria_filter == $cat['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Tipo de Precio -->
            <div class="col-md-2">
                <label for="tipo" class="form-label">Tipo:</label>
                <select name="tipo" id="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="fijo" <?= $tipo_filter === 'fijo' ? 'selected' : '' ?>>Fijo</option>
                    <option value="variable" <?= $tipo_filter === 'variable' ? 'selected' : '' ?>>Variable</option>
                </select>
            </div>

            <!-- Estado / Visibilidad -->
            <div class="col-md-2">
                <label for="estado" class="form-label">Estado:</label>
                <select name="estado" id="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="activo" <?= $estado_filter === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $estado_filter === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    <option value="visible" <?= $estado_filter === 'visible' ? 'selected' : '' ?>>Visible Ecommerce</option>
                    <option value="oculto" <?= $estado_filter === 'oculto' ? 'selected' : '' ?>>Oculto Ecommerce</option>
                </select>
            </div>

            <!-- Botones -->
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">🔍 Buscar</button>
                <?php if (!empty($busqueda) || !empty($categoria_filter) || !empty($tipo_filter) || !empty($estado_filter)): ?>
                    <a href="productos.php" class="btn btn-outline-secondary w-100 mt-2">Limpiar filtros</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<!-- Información de paginación -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <p class="text-muted mb-0">
            Mostrando <strong><?= count($productos) > 0 ? (($pagina - 1) * $productos_por_pagina) + 1 : 0 ?></strong> a 
            <strong><?= min($pagina * $productos_por_pagina, $total_productos) ?></strong> de 
            <strong><?= $total_productos ?></strong> productos
        </p>
    </div>
    <div>
        <small class="text-muted">Página <?= $pagina ?> de <?= max(1, $total_paginas) ?></small>
    </div>
</div>

<?php if (empty($productos)): ?>
    <div class="alert alert-info">No hay productos</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Precio Base</th>
                    <th>Tipo</th>
                    <th>Receta</th>
                    <th>Ecommerce</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $prod): ?>
                    <tr>
                        <td><small><?= htmlspecialchars($prod['codigo']) ?></small></td>
                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                        <td><?= htmlspecialchars($prod['categoria_nombre']) ?></td>
                        <td>$<?= number_format($prod['precio_base'], 2, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-<?= $prod['tipo_precio'] === 'variable' ? 'warning' : 'info' ?>">
                                <?= $prod['tipo_precio'] === 'variable' ? 'Variable' : 'Fijo' ?>
                            </span>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="accion" value="toggle_receta">
                                <input type="hidden" name="producto_id" value="<?= $prod['id'] ?>">
                                <input type="checkbox" name="usa_receta" onchange="this.form.submit()" <?= !empty($prod['usa_receta']) ? 'checked' : '' ?>>
                            </form>
                        </td>
                        <td>
                            <?php if (!empty($prod['mostrar_ecommerce'])): ?>
                                <span class="badge bg-success">Visible</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Oculto</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($prod['activo']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="productos_crear.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="productos_atributos.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-info" title="Atributos">⚙️</a>
                            <a href="productos_imagenes.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-secondary" title="Galería">🖼️</a>
                            <a href="productos_eliminar.php?id=<?= $prod['id'] ?>" class="btn btn-sm btn-danger">Eliminar</a>
                            <?php if ($prod['tipo_precio'] === 'variable'): ?>
                                <a href="matriz_precios.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-info">Matriz</a>
                                <a href="receta_producto.php?producto_id=<?= $prod['id'] ?>" class="btn btn-sm btn-secondary">Receta</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Controles de Paginación -->
    <?php if ($total_paginas > 1): ?>
    <nav aria-label="Paginación de productos" class="mt-4">
        <ul class="pagination justify-content-center">
            <!-- Botón Anterior -->
            <?php if ($pagina > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="productos.php?pagina=1<?= $filter_url ?>">Primera</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="productos.php?pagina=<?= $pagina - 1 ?><?= $filter_url ?>">Anterior</a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link">Primera</span>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Anterior</span>
                </li>
            <?php endif; ?>

            <!-- Números de Página -->
            <?php
            $rango = 2;
            $inicio = max(1, $pagina - $rango);
            $fin = min($total_paginas, $pagina + $rango);
            
            if ($inicio > 1): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif;
            
            for ($i = $inicio; $i <= $fin; $i++): ?>
                <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                    <a class="page-link" href="productos.php?pagina=<?= $i ?><?= $filter_url ?>">
                        <?= $i ?>
                    </a>
                </li>
            <?php endfor;
            
            if ($fin < $total_paginas): ?>
                <li class="page-item disabled">
                    <span class="page-link">...</span>
                </li>
            <?php endif; ?>

            <!-- Botón Siguiente -->
            <?php if ($pagina < $total_paginas): ?>
                <li class="page-item">
                    <a class="page-link" href="productos.php?pagina=<?= $pagina + 1 ?><?= $filter_url ?>">Siguiente</a>
                </li>
                <li class="page-item">
                    <a class="page-link" href="productos.php?pagina=<?= $total_paginas ?><?= $filter_url ?>">Última</a>
                </li>
            <?php else: ?>
                <li class="page-item disabled">
                    <span class="page-link">Siguiente</span>
                </li>
                <li class="page-item disabled">
                    <span class="page-link">Última</span>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
