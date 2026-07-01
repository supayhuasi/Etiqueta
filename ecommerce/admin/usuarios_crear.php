<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/usuarios_empleados_helper.php';

if (!isset($can_access) || !$can_access('usuarios')) {
    die("Acceso denegado.");
}

$msg = '';

// Asegurar migración y verificar disponibilidad
$tiene_empleados = usuarios_empleados_asegurar_migracion($pdo);

// Obtener roles disponibles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados disponibles si la migración está OK
$empleados = $tiene_empleados ? usuarios_empleados_obtener_disponibles($pdo) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $nombre  = trim($_POST['nombre'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $rol_id  = intval($_POST['rol_id'] ?? 0);
    $empleado_id = $tiene_empleados ? intval($_POST['empleado_id'] ?? 0) : 0;

    if ($usuario && $pass && $rol_id) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->rowCount() > 0) {
                $msg = '<div class="alert alert-danger">El usuario ya existe</div>';
            } else {
                // Intentar insertar
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (usuario, password, nombre, rol_id, empleado_id, activo)
                    VALUES (?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$usuario, $hash, $nombre, $rol_id, $empleado_id > 0 ? $empleado_id : null]);
                $nuevo_usuario_id = (int)$pdo->lastInsertId();

                // Si se seleccionó un empleado, vincular
                if ($tiene_empleados && $empleado_id > 0) {
                    $resultado = usuarios_empleados_vincular($pdo, $nuevo_usuario_id, $empleado_id);
                    if (!$resultado['ok']) {
                        $msg = '<div class="alert alert-warning">Usuario creado pero sin vincular empleado: ' . htmlspecialchars($resultado['error']) . '</div>';
                    } else {
                        $msg = '<div class="alert alert-success">Usuario creado y vinculado a empleado correctamente</div>';
                    }
                } else {
                    $msg = '<div class="alert alert-success">Usuario creado correctamente</div>';
                }
            }
        } catch (PDOException $e) {
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

                <?php if ($tiene_empleados): ?>
                    <div class="mb-3">
                        <label class="form-label">Empleado relacionado (opcional)</label>
                        <select name="empleado_id" class="form-select">
                            <option value="0">-- Sin vincular --</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?= (int)$empleado['id'] ?>">
                                    <?= htmlspecialchars($empleado['nombre']) ?>
                                    <?php if (!empty($empleado['documento'])): ?>
                                        (<?= htmlspecialchars($empleado['documento']) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">✓ Cada empleado puede vincularse a un solo usuario del sistema</div>
                    </div>
                <?php elseif (admin_table_exists($pdo, 'empleados')): ?>
                    <div class="alert alert-info">
                        ⓘ La tabla de empleados existe pero la relación aún no está disponible. Intenta actualizar la página.
                    </div>
                <?php endif; ?>

                <button class="btn btn-primary">Crear usuario</button>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
