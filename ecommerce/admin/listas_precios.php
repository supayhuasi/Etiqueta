<?php
require 'includes/header.php';

// Obtener todas las listas de precios
$stmt = $pdo->query("SELECT * FROM ecommerce_listas_precios ORDER BY nombre ASC");
$listas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>💰 Gestión de Listas de Precios</h1>
            <p class="text-muted">Crea y administra diferentes listas de precios con descuentos para tus productos</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="listas_precios_crear.php" class="btn btn-primary">+ Nueva Lista</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($listas)): ?>
                <div class="alert alert-info">
                    No hay listas de precios creadas. <a href="listas_precios_crear.php">Crea una ahora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th width="150">Estado</th>
                                <th width="300">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($listas as $lista): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($lista['nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars(substr($lista['descripcion'] ?? '', 0, 60)) ?>
                                    </td>
                                    <td>
                                        <?php if ($lista['activo']): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="listas_precios_editar.php?id=<?= $lista['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                        <a href="listas_precios_items.php?id=<?= $lista['id'] ?>" class="btn btn-sm btn-info">📊 Precios</a>
                                        <a href="listas_precios_categorias.php?lista_id=<?= $lista['id'] ?>" class="btn btn-sm btn-secondary">🏷️ Categorías</a>
                                        <a href="listas_precios_eliminar.php?id=<?= $lista['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">🗑️ Eliminar</a>
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
