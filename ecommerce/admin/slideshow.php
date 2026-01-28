<?php
require 'includes/header.php';

// Obtener todos los slideshows
$stmt = $pdo->query("SELECT * FROM ecommerce_slideshow ORDER BY orden ASC");
$slideshows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>📸 Gestión de Slideshow</h1>
            <p class="text-muted">Administra las imágenes del carrusel de la página principal</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="slideshow_crear.php" class="btn btn-primary">+ Agregar Slide</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($slideshows)): ?>
                <div class="alert alert-info">
                    No hay slideshows creados. <a href="slideshow_crear.php">Crea uno ahora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="80">Orden</th>
                                <th>Título</th>
                                <th>Descripción</th>
                                <th width="150">Estado</th>
                                <th width="200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slideshows as $slide): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= $slide['orden'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($slide['titulo'] ?? 'Sin título') ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(substr($slide['descripcion'] ?? '', 0, 50)) ?>
                                    </td>
                                    <td>
                                        <?php if ($slide['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="slideshow_editar.php?id=<?= $slide['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                        <a href="slideshow_eliminar.php?id=<?= $slide['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">🗑️ Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
