<?php
require 'includes/header.php';
require '../includes/banners_publico_helper.php';

banners_asegurar_tabla($pdo);
$zonas = banners_zonas_disponibles();

$stmt = $pdo->query("SELECT * FROM ecommerce_banners ORDER BY ubicacion ASC, orden ASC");
$banners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>🖼️ Gestión de Banners</h1>
            <p class="text-muted">Administra los banners promocionales del sitio (laterales del blog, tienda, etc.)</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="banners_crear.php" class="btn btn-primary">+ Agregar Banner</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($banners)): ?>
                <div class="alert alert-info">
                    No hay banners creados. <a href="banners_crear.php">Crea uno ahora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="80">Orden</th>
                                <th width="90">Imagen</th>
                                <th>Título</th>
                                <th width="180">Ubicación</th>
                                <th width="120">Estado</th>
                                <th width="200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($banners as $banner): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= (int)$banner['orden'] ?></span>
                                    </td>
                                    <td>
                                        <img src="../uploads/<?= htmlspecialchars($banner['imagen']) ?>" alt="" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;">
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($banner['titulo']) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($zonas[$banner['ubicacion']] ?? $banner['ubicacion']) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($banner['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="banners_crear.php?id=<?= (int)$banner['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                        <a href="banners_eliminar.php?id=<?= (int)$banner['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">🗑️ Eliminar</a>
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
