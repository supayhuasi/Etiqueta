<?php
require 'includes/header.php';

$es_revendedor = (($role ?? '') === 'revendedor');

$stmt = $pdo->query("SELECT * FROM ecommerce_cotizacion_clientes ORDER BY nombre");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>👥 Clientes de Cotización</h1>
        <p class="text-muted">Gestioná clientes para agrupar cotizaciones</p>
    </div>
    <a href="cotizacion_clientes_crear.php" class="btn btn-primary <?= $es_revendedor ? 'd-none' : '' ?>">+ Nuevo Cliente</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="alert alert-info">No hay clientes registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Dirección</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cli): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($cli['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($cli['direccion'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($cli['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($cli['telefono'] ?? '-') ?></td>
                                <td>
                                    <?php if ($cli['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$es_revendedor): ?>
                                    <a href="cotizacion_clientes_crear.php?id=<?= $cli['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                    <a href="cotizacion_clientes_eliminar.php?id=<?= $cli['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar cliente?')">🗑️ Eliminar</a>
                                    <?php else: ?>
                                    <span class="text-muted small">Solo lectura</span>
                                    <?php endif; ?>
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
