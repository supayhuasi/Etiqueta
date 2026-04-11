<?php
require 'includes/header.php';

if (!isset($can_access) || !$can_access('usuarios')) {
    die("Acceso denegado.");
}

$msg = '';
$usuarios_tienen_empleado = false;
if (admin_table_exists($pdo, 'empleados')) {
    if (!admin_column_exists($pdo, 'usuarios', 'empleado_id')) {
        try {
            $pdo->exec("ALTER TABLE usuarios ADD COLUMN empleado_id INT NULL AFTER rol_id");
        } catch (Throwable $e) {
            // Si no se puede alterar en este entorno, mantener pantalla funcional sin romper.
        }
    }
    $usuarios_tienen_empleado = admin_column_exists($pdo, 'usuarios', 'empleado_id');
}

// Obtener roles disponibles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

$empleados = [];
if ($usuarios_tienen_empleado) {
        $stmt = $pdo->query("
                SELECT e.id, e.nombre
                FROM empleados e
                LEFT JOIN usuarios u ON u.empleado_id = e.id
                WHERE COALESCE(e.activo, 1) = 1
                    AND u.id IS NULL
                ORDER BY e.nombre ASC
        ");
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre  = trim($_POST['nombre'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $rol_id  = intval($_POST['rol_id'] ?? 0);
    $empleado_id = $usuarios_tienen_empleado ? intval($_POST['empleado_id'] ?? 0) : 0;

    if ($usuario && $pass && $rol_id) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->rowCount() > 0) {
                $msg = '<div class="alert alert-danger">El usuario ya existe</div>';
            } else {
                if ($usuarios_tienen_empleado && $empleado_id > 0) {
                    $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE empleado_id = ? LIMIT 1");
                    $stmt->execute([$empleado_id]);
                    if ($stmt->fetch()) {
                        $msg = '<div class="alert alert-danger">Ese empleado ya está vinculado a otro usuario</div>';
                    }
                }
            }

            if (!$msg) {
                if ($usuarios_tienen_empleado) {
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (usuario, password, nombre, rol_id, empleado_id, activo)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$usuario, $hash, $nombre, $rol_id, $empleado_id > 0 ? $empleado_id : null]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO usuarios (usuario, password, nombre, rol_id, activo)
                        VALUES (?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$usuario, $hash, $nombre, $rol_id]);
                }
                $msg = '<div class="alert alert-success">Usuario creado correctamente</div>';
            }
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Completá todos los campos</div>';
    }
}
?>

<div class="container mt-4">
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>Crear Usuario</h2>
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
                    <label class="form-label">Usuario</label>
                    <input type="text" name="usuario" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" name="nombre" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Contraseña</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Rol</label>
                    <select name="rol_id" class="form-select" required>
                        <option value="">Seleccionar rol...</option>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int)$r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($usuarios_tienen_empleado): ?>
                    <div class="mb-3">
                        <label class="form-label">Empleado relacionado</label>
                        <select name="empleado_id" class="form-select">
                            <option value="0">Sin vincular</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?= (int)$empleado['id'] ?>"><?= htmlspecialchars($empleado['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Cada empleado puede quedar vinculado a un solo usuario.</div>
                    </div>
                <?php endif; ?>

                <button class="btn btn-primary">Crear usuario</button>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
