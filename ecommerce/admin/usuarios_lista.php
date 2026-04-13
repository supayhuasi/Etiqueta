<?php
require 'includes/header.php';

$usuarios_tienen_empleado = admin_table_exists($pdo, 'empleados') && admin_column_exists($pdo, 'usuarios', 'empleado_id');

// Obtener lista de usuarios
$sql_usuarios = "
    SELECT u.id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre" . ($usuarios_tienen_empleado ? ", u.empleado_id, e.nombre AS empleado_nombre" : ", NULL AS empleado_id, NULL AS empleado_nombre") . "
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
" . ($usuarios_tienen_empleado ? "    LEFT JOIN empleados e ON e.id = u.empleado_id
" : "") . "    ORDER BY u.usuario
";
$stmt = $pdo->query($sql_usuarios);
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<h2>Usuarios</h2>
<p class="text-muted">Listado de usuarios del sistema</p>

<div class="mb-3 d-flex gap-2">
    <a href="<?= $admin_url ?>usuarios_crear.php" class="btn btn-primary">Crear Usuario</a>
    <a href="<?= $admin_url ?>roles_usuarios.php" class="btn btn-outline-secondary">Gestionar Roles</a>
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
                        <?php if ($usuarios_tienen_empleado): ?>
                            <th>Empleado</th>
                        <?php endif; ?>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($usuarios)): ?>
                        <tr>
                            <td colspan="<?= $usuarios_tienen_empleado ? '7' : '6' ?>" class="text-center text-muted">No hay usuarios registrados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($usuarios as $user): ?>
                            <tr>
                                <td><?= (int)$user['id'] ?></td>
                                <td><?= htmlspecialchars($user['usuario']) ?></td>
                                <td><?= htmlspecialchars($user['nombre']) ?></td>
                                <?php if ($usuarios_tienen_empleado): ?>
                                    <td>
                                        <?php if (!empty($user['empleado_id'])): ?>
                                            <span class="badge bg-light text-dark border">#<?= (int)$user['empleado_id'] ?> · <?= htmlspecialchars($user['empleado_nombre'] ?? 'Empleado') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Sin vincular</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
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
                                <td>
                                    <a href="<?= $admin_url ?>usuarios_editar.php?id=<?= (int)$user['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
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
