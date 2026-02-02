<?php
require 'includes/header.php';

$stmt = $pdo->query("SELECT * FROM ecommerce_materiales ORDER BY nombre");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧵 Materiales</h1>
        <p class="text-muted">Catálogo de materiales para recetas</p>
    </div>
    <a href="materiales_crear.php" class="btn btn-primary">+ Nuevo Material</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($materiales)): ?>
            <div class="alert alert-info">No hay materiales cargados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Unidad</th>
                            <th>Costo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materiales as $m): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>
                                <td><?= htmlspecialchars($m['unidad']) ?></td>
                                <td><?= $m['costo'] !== null ? '$' . number_format($m['costo'], 2, ',', '.') : '-' ?></td>
                                <td>
                                    <?php if ($m['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="materiales_crear.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-warning">✏️ Editar</a>
                                    <a href="materiales_eliminar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar material?')">🗑️ Eliminar</a>
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
