<?php
require 'includes/header.php';

// Obtener todos los clientes
$stmt = $pdo->query("SELECT * FROM ecommerce_clientes_logos ORDER BY orden ASC");
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-5">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>👥 Gestión de Clientes</h1>
            <p class="text-muted">Administra los logos de clientes que aparecen en la página principal</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="clientes_crear.php" class="btn btn-primary">+ Agregar Cliente</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($clientes)): ?>
                <div class="alert alert-info">
                    No hay clientes creados. <a href="clientes_crear.php">Crea uno ahora</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th width="80">Orden</th>
                                <th>Nombre</th>
                                <th>Logo</th>
                                <th width="150">Estado</th>
                                <th width="200">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary"><?= $cliente['orden'] ?></span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($cliente['nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($cliente['logo_url']): ?>
                                            <img src="../uploads/<?= htmlspecialchars($cliente['logo_url']) ?>" alt="Logo" style="max-height: 40px;">
                                        <?php else: ?>
                                            <span class="text-muted">Sin logo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($cliente['activo']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="clientes_editar.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                        <a href="clientes_eliminar.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Estás seguro?')">🗑️ Eliminar</a>
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
