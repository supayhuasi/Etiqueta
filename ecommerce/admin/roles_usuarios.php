<?php
require 'includes/header.php';

if (!isset($can_access) || !$can_access('roles')) {
    die("Acceso denegado.");
}

$usuarios_tienen_empleado = false;
if (admin_table_exists($pdo, 'empleados')) {
    if (!admin_column_exists($pdo, 'usuarios', 'empleado_id')) {
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN empleado_id INT NULL AFTER rol_id");
        } catch (Throwable $e) {
            // No interrumpir la pantalla si no se puede migrar automáticamente.
        }
    }
    $usuarios_tienen_empleado = admin_column_exists($pdo, 'usuarios', 'empleado_id');
}

// Obtener lista de usuarios
$stmt = $pdo->query("
    SELECT u.id, u.usuario, u.nombre, u.activo, u.rol_id, r.nombre as rol_nombre" . ($usuarios_tienen_empleado ? ", u.empleado_id, e.nombre AS empleado_nombre" : ", NULL AS empleado_id, NULL AS empleado_nombre") . "
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
" . ($usuarios_tienen_empleado ? "    LEFT JOIN empleados e ON e.id = u.empleado_id\n" : "") . "
    ORDER BY u.usuario
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$empleados = [];
$empleado_asignado_a = [];
if ($usuarios_tienen_empleado) {
    $stmt = $pdo->query("SELECT id, nombre, COALESCE(activo, 1) AS activo FROM empleados ORDER BY activo DESC, nombre ASC");
    $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->query("SELECT id, empleado_id FROM usuarios WHERE empleado_id IS NOT NULL");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $empId = (int)($row['empleado_id'] ?? 0);
        if ($empId > 0) {
            $empleado_asignado_a[$empId] = (int)$row['id'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $_POST['id'] ?? 0;
        $rol_id = $_POST['rol_id'] ?? 0;
        $empleado_id = $usuarios_tienen_empleado ? (int)($_POST['empleado_id'] ?? 0) : 0;
        
        if ($id && $rol_id) {
            if ($usuarios_tienen_empleado && $empleado_id > 0) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE empleado_id = ? AND id <> ? LIMIT 1");
                $stmt->execute([$empleado_id, $id]);
                if ($stmt->fetch()) {
                    $error = "Ese empleado ya está vinculado a otro usuario";
                }
            }

            if (!isset($error)) {
                if ($usuarios_tienen_empleado) {
                    $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = ?, empleado_id = ? WHERE id = ?");
                    $stmt->execute([$rol_id, $empleado_id > 0 ? $empleado_id : null, $id]);
                    $success = "Rol y empleado actualizados correctamente";
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
                    $stmt->execute([$rol_id, $id]);
                    $success = "Rol actualizado correctamente";
                }
            }
        } else {
            $error = "Datos inválidos";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Gestión de Roles de Usuarios</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= $admin_url ?>usuarios_lista.php" class="btn btn-outline-secondary">Volver a Usuarios</a>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <?php if ($usuarios_tienen_empleado): ?>
                                <th>Empleado vinculado</th>
                            <?php endif; ?>
                            <th>Rol Actual</th>
                            <th>Estado</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $user): ?>
                        <tr>
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
                                <span class="badge bg-<?= $user['rol_nombre'] === 'admin' ? 'danger' : 'info' ?>">
                                    <?= htmlspecialchars($user['rol_nombre'] ?? 'Sin rol') ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $user['activo'] ? 'bg-success' : 'bg-secondary' ?>">
                                    <?= $user['activo'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="id" value="<?= (int)$user['id'] ?>">
                                    <?php if ($usuarios_tienen_empleado): ?>
                                        <select name="empleado_id" class="form-select form-select-sm">
                                            <option value="0">Sin vincular</option>
                                            <?php foreach ($empleados as $empleado): ?>
                                                <?php
                                                    $empId = (int)$empleado['id'];
                                                    $ownerUserId = isset($empleado_asignado_a[$empId]) ? (int)$empleado_asignado_a[$empId] : 0;
                                                    if ($ownerUserId > 0 && $ownerUserId !== (int)$user['id']) {
                                                        continue;
                                                    }
                                                ?>
                                                <option value="<?= $empId ?>" <?= (int)($user['empleado_id'] ?? 0) === $empId ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($empleado['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                    <select name="rol_id" class="form-select form-select-sm">
                                        <?php foreach ($roles as $rol): ?>
                                            <option value="<?= (int)$rol['id'] ?>" 
                                                <?= $user['rol_id'] == $rol['id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($rol['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary">Guardar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
