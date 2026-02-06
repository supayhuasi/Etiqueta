<?php
require 'includes/header.php';

if (!isset($can_access) || !$can_access('usuarios')) {
    die("Acceso denegado.");
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    die("Usuario no encontrado");
}

$stmt = $pdo->prepare("SELECT id, usuario, nombre, rol_id, activo FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    die("Usuario no encontrado");
}

// Obtener roles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_nuevo = trim($_POST['usuario'] ?? '');
    $nombre = trim($_POST['nombre'] ?? '');
    $rol_id = intval($_POST['rol_id'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';

    if ($usuario_nuevo && $nombre && $rol_id) {
        try {
            // Validar usuario único si cambia
            if ($usuario_nuevo !== $usuario['usuario']) {
                $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ? AND id != ?");
                $stmt->execute([$usuario_nuevo, $id]);
                if ($stmt->rowCount() > 0) {
                    $msg = '<div class="alert alert-danger">El usuario ya existe</div>';
                } else {
                    $usuario['usuario'] = $usuario_nuevo;
                }
            }

            if (!$msg) {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, nombre = ?, rol_id = ?, activo = ?, password = ? WHERE id = ?");
                    $stmt->execute([$usuario_nuevo, $nombre, $rol_id, $activo, $hash, $id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET usuario = ?, nombre = ?, rol_id = ?, activo = ? WHERE id = ?");
                    $stmt->execute([$usuario_nuevo, $nombre, $rol_id, $activo, $id]);
                }
                $msg = '<div class="alert alert-success">Usuario actualizado correctamente</div>';

                // Recargar datos
                $stmt = $pdo->prepare("SELECT id, usuario, nombre, rol_id, activo FROM usuarios WHERE id = ?");
                $stmt->execute([$id]);
                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Completá todos los campos obligatorios</div>';
    }
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>Editar Usuario</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= $admin_url ?>usuarios_lista.php" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <?= $msg ?>

    <div class="card">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Usuario *</label>
                    <input type="text" name="usuario" class="form-control" value="<?= htmlspecialchars($usuario['usuario']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rol *</label>
                    <select name="rol_id" class="form-select" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>" <?= $usuario['rol_id'] == $r['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($r['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña (opcional)</label>
                    <input type="password" name="password" class="form-control" placeholder="Dejar en blanco para no cambiar">
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= $usuario['activo'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Usuario activo</label>
                </div>

                <button class="btn btn-primary">Guardar cambios</button>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
