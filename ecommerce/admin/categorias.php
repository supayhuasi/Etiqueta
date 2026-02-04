<?php
require 'includes/header.php';

// Asegurar columnas para manual de categoría
$cols_cat = $pdo->query("SHOW COLUMNS FROM ecommerce_categorias")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('manual_archivo', $cols_cat, true)) {
    $pdo->exec("ALTER TABLE ecommerce_categorias ADD COLUMN manual_archivo VARCHAR(255) NULL AFTER icono");
}
if (!in_array('manual_titulo', $cols_cat, true)) {
    $pdo->exec("ALTER TABLE ecommerce_categorias ADD COLUMN manual_titulo VARCHAR(255) NULL AFTER manual_archivo");
}

$stmt = $pdo->query("SELECT * FROM ecommerce_categorias ORDER BY orden, nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Categorías</h1>
    <a href="categorias_crear.php" class="btn btn-primary">+ Nueva Categoría</a>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($categorias)): ?>
            <p class="text-muted">No hay categorías</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Icono</th>
                            <th>Manual</th>
                            <th>Orden</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat): ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                <td><?= htmlspecialchars(substr($cat['descripcion'] ?? '', 0, 50)) ?></td>
                                <td><?= $cat['icono'] ?? '📦' ?></td>
                                <td>
                                    <?php if (!empty($cat['manual_archivo'] ?? null)): ?>
                                        <a href="/uploads/manuales/<?= htmlspecialchars($cat['manual_archivo']) ?>" target="_blank">Ver</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $cat['orden'] ?></td>
                                <td>
                                    <span class="badge <?= $cat['activo'] ? 'bg-success' : 'bg-danger' ?>">
                                        <?= $cat['activo'] ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="categorias_editar.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-warning">✎</a>
                                    <a href="categorias_eliminar.php?id=<?= $cat['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">🗑️</a>
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
