<?php
require 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM ecommerce_proveedores ORDER BY nombre");
$proveedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🏭 Proveedores</h1>
        <p class="text-muted">Administrá proveedores para compras e inventario</p>
    </div>
    <a href="proveedores_crear.php" class="btn btn-primary">+ Nuevo Proveedor</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($proveedores)): ?>
            <div class="alert alert-info">No hay proveedores cargados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Teléfono</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($proveedores as $prov): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($prov['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($prov['email'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($prov['telefono'] ?? '-') ?></td>
                                <td>
                                    <?php if ($prov['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="proveedores_crear.php?id=<?= $prov['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                    <a href="proveedores_eliminar.php?id=<?= $prov['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar proveedor?')">🗑️ Eliminar</a>
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
