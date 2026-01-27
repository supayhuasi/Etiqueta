<?php
require 'includes/header.php';

// Obtener estadísticas
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_productos WHERE activo = 1");
$total_productos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_categorias WHERE activo = 1");
$total_categorias = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_pedidos WHERE estado != 'cancelado'");
$total_pedidos = $stmt->fetch()['total'];

$stmt = $pdo->query("SELECT SUM(total) as total FROM ecommerce_pedidos WHERE estado IN ('confirmado', 'preparando', 'enviado', 'entregado')");
$ingresos_totales = $stmt->fetch()['total'] ?? 0;

// Últimos pedidos
$stmt = $pdo->query("
    SELECT p.numero_pedido, c.nombre, p.total, p.estado, p.fecha_pedido 
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    ORDER BY p.fecha_pedido DESC
    LIMIT 5
");
$ultimos_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Dashboard</h1>
<p class="text-muted">Bienvenido al panel de administración</p>

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
                <a href="empresa.php" class="btn btn-info w-100">🏪 Editar Empresa</a>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
