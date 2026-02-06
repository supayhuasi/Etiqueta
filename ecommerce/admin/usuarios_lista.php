<?php
require 'includes/header.php';

// Obtener lista de usuarios
$stmt = $pdo->query("
    SELECT u.id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    ORDER BY u.usuario
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Usuarios</h2>
<p class="text-muted">Listado de usuarios del sistema</p>

<div class="mb-3 d-flex gap-2">
    <a href="<?= $relative_root ?>usuarios_crear.php" class="btn btn-primary">Crear Usuario</a>
    <a href="<?= $relative_root ?>roles_usuarios.php" class="btn btn-outline-secondary">Gestionar Roles</a>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th>Rol</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted">No hay usuarios registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><?= (int)$user['id'] ?></td>
                                <td><?= htmlspecialchars($user['usuario']) ?></td>
                                <td><?= htmlspecialchars($user['nombre']) ?></td>
                                <td>
                                    <span class="badge bg-<?= ($user['rol_nombre'] ?? '') === 'admin' ? 'danger' : 'info' ?>">
                                        <?= htmlspecialchars($user['rol_nombre'] ?? 'Sin rol') ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= !empty($user['activo']) ? 'bg-success' : 'bg-secondary' ?>">
                                        <?= !empty($user['activo']) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
