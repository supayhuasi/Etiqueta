<?php
require 'config.php';
require 'includes/header.php';

// Solo admins pueden acceder
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado - Se requiere rol de administrador");
}

// Obtener lista de usuarios
$stmt = $pdo->query("
    SELECT u.id, u.usuario, u.nombre, u.activo, r.nombre as rol_nombre
    FROM usuarios u
    LEFT JOIN roles r ON u.rol_id = r.id
    ORDER BY u.usuario
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener lista de roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $_POST['id'];
        $rol_id = $_POST['rol_id'];
        
        $stmt = $pdo->prepare("UPDATE usuarios SET rol_id = ? WHERE id = ?");
        $stmt->execute([$rol_id, $id]);
        
        $success = "Rol actualizado correctamente";
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
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
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
                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                <select name="rol_id" class="form-select form-select-sm">
                                    <?php foreach ($roles as $rol): ?>
                                        <option value="<?= $rol['id'] ?>" 
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

<?php require 'includes/footer.php'; ?>
