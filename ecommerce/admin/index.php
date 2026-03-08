<?php
require 'includes/header.php';

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_productos WHERE activo = 1");
$total_productos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_categorias WHERE activo = 1");
$total_categorias = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_producto_atributos");
$total_atributos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_pedidos WHERE estado != 'cancelado'");
$total_pedidos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(total) as total FROM ecommerce_pedidos WHERE estado IN ('confirmado', 'preparando', 'enviado', 'entregado')");
$ingresos_totales = $stmt->fetch()['total'] ?? 0;

// Cotizaciones
$stmt_cot = $pdo->query("SHOW TABLES LIKE 'ecommerce_cotizaciones'");
if ($stmt_cot->rowCount() > 0) {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_cotizaciones WHERE estado = 'pendiente'");
    $cotizaciones_pendientes = $stmt->fetch()['total'];
} else {
    $cotizaciones_pendientes = 0;
}

// Últimos pedidos
$stmt = $pdo->query("
    SELECT p.numero_pedido, c.nombre, p.total, p.estado, p.fecha_pedido 
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.estado != 'cancelado'
    ORDER BY p.fecha_pedido DESC
    LIMIT 5
");
$ultimos_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Dashboard</h1>
<p class="text-muted">Bienvenido al panel de administración</p>

<div class="mb-3">
    <a href="../index.php" target="_blank" class="btn btn-success">
        🏪 Ir a la Tienda
    </a>
</div>

<div class="row mt-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="card-title">📦 Productos</h6>
                <h3><?= $total_productos ?></h3>
                <a href="productos.php" class="btn btn-light btn-sm mt-3">Ver</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="card-title">📁 Categorías</h6>
                <h3><?= $total_categorias ?></h3>
                <a href="categorias.php" class="btn btn-light btn-sm mt-3">Ver</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-secondary text-white">
            <div class="card-body">
                <h6 class="card-title">⚙️ Atributos</h6>
                <h3><?= $total_atributos ?></h3>
                <a href="productos.php" class="btn btn-light btn-sm mt-3">Ver</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <h6 class="card-title">📋 Pedidos</h6>
                <h3><?= $total_pedidos ?></h3>
                <a href="pedidos.php" class="btn btn-light btn-sm mt-3">Ver</a>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="card-title">💰 Ingresos</h6>
                <h3>$<?= number_format($ingresos_totales, 0) ?></h3>
                <small>Total acumulado</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-dark text-white">
            <div class="card-body">
                <h6 class="card-title">💼 Cotizaciones</h6>
                <h3><?= $cotizaciones_pendientes ?></h3>
                <a href="cotizaciones.php" class="btn btn-light btn-sm mt-3">Ver</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5>Últimos Pedidos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($ultimos_pedidos)): ?>
                    <p class="text-muted">Sin pedidos</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimos_pedidos as $pedido): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($pedido['numero_pedido']) ?></td>
                                        <td><?= htmlspecialchars($pedido['nombre'] ?? 'Sin nombre') ?></td>
                                        <td>$<?= number_format($pedido['total'], 2) ?></td>
                                        <td>
                                            <span class="badge bg-primary"><?= htmlspecialchars($pedido['estado']) ?></span>
                                        </td>
                                        <td><?= date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="pedidos.php" class="btn btn-primary btn-sm">Ver todos</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5>Acciones Rápidas</h5>
            </div>
            <div class="card-body">
                <a href="productos_crear.php" class="btn btn-primary w-100 mb-2">+ Nuevo Producto</a>
                <a href="categorias_crear.php" class="btn btn-success w-100 mb-2">+ Nueva Categoría</a>
                <a href="matriz_precios.php" class="btn btn-warning w-100 mb-2">📏 Generar Matriz</a>
                <?php if (isset($can_access) && $can_access('visitas')): ?>
                <a href="visitas.php" class="btn btn-secondary w-100 mb-2">🗓️ Visitas (To Do)</a>
                <?php endif; ?>
                <a href="empresa.php" class="btn btn-info w-100">🏪 Editar Empresa</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <h3 class="mb-3">� Recursos Humanos</h3>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>💰 Sueldos</h5>
                <p class="text-muted">Gestión de nómina</p>
                <a href="sueldos/sueldos.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>📋 Plantillas</h5>
                <p class="text-muted">Plantillas de sueldo</p>
                <a href="sueldos/plantillas.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>📌 Asistencias</h5>
                <p class="text-muted">Registro de asistencias</p>
                <a href="asistencias/asistencias.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>⏰ Horarios</h5>
                <p class="text-muted">Gestionar horarios</p>
                <a href="asistencias/asistencias_horarios.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <h3 class="mb-3">💳 Finanzas</h3>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>🏦 Cheques</h5>
                <p class="text-muted">Gestión de cheques</p>
                <a href="cheques/cheques.php" class="btn btn-success btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>💸 Gastos</h5>
                <p class="text-muted">Registro de gastos</p>
                <a href="gastos/gastos.php" class="btn btn-success btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>📊 Tipos Gastos</h5>
                <p class="text-muted">Categorías de gastos</p>
                <a href="gastos/tipos_gastos.php" class="btn btn-success btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>📜 Documentación</h5>
                <p class="text-muted">Guías y referencias</p>
                <a href="MODULOS_MIGRATOS.md" class="btn btn-info btn-sm w-100" target="_blank">Ver</a>
            </div>
        </div>
    </div>
</div>

<div class="row mt-4">
    <div class="col-md-12">
        <h3 class="mb-3">�📱 Gestión de Contenido</h3>
    </div>
    
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>📸 Slideshow</h5>
                <p class="text-muted">Carrusel principal</p>
                <a href="slideshow.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>👥 Clientes</h5>
                <p class="text-muted">Logos en página inicio</p>
                <a href="clientes.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>💰 Listas Precios</h5>
                <p class="text-muted">Descuentos por lista</p>
                <a href="listas_precios.php" class="btn btn-primary btn-sm w-100">Administrar</a>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <h5>🔧 Setup</h5>
                <p class="text-muted">Crear tablas</p>
                <a href="../setup_content.php" class="btn btn-warning btn-sm w-100" target="_blank">Ejecutar</a>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
