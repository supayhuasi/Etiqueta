<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($can_access) || !$can_access('seguros')) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['action'] ?? 'save');
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $color = $_POST['color'] ?? '#0d6efd';
    $activo = !empty($_POST['activo']) ? 1 : 0;

    try {
        if ($accion === 'toggle' && $id > 0) {
            $stmt = $pdo->prepare("UPDATE tipos_seguros_permisos SET activo = CASE WHEN activo = 1 THEN 0 ELSE 1 END WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = 'Estado del tipo actualizado correctamente';
        } elseif ($accion === 'delete' && $id > 0) {
            $stmtUso = $pdo->prepare("SELECT COUNT(*) FROM seguros_permisos WHERE tipo_id = ?");
            $stmtUso->execute([$id]);
            $cantidadUsos = (int)$stmtUso->fetchColumn();

            if ($cantidadUsos > 0) {
                $stmt = $pdo->prepare("UPDATE tipos_seguros_permisos SET activo = 0 WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'El tipo tenía registros asociados, por seguridad se desactivó en lugar de eliminarse.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM tipos_seguros_permisos WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = 'Tipo eliminado correctamente';
            }
        } else {
            if ($nombre === '') {
                throw new Exception('El nombre es obligatorio');
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tipos_seguros_permisos SET nombre = ?, descripcion = ?, color = ?, activo = ? WHERE id = ?");
                $stmt->execute([$nombre, $descripcion, $color, $activo, $id]);
                $mensaje = 'Tipo actualizado correctamente';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tipos_seguros_permisos (nombre, descripcion, color, activo) VALUES (?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $color, $activo]);
                $mensaje = 'Tipo creado correctamente';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$stmt = $pdo->query("
    SELECT t.*, COUNT(sp.id) AS cantidad_registros
    FROM tipos_seguros_permisos t
    LEFT JOIN seguros_permisos sp ON sp.tipo_id = t.id
    GROUP BY t.id, t.nombre, t.descripcion, t.color, t.activo, t.fecha_creacion
    ORDER BY t.activo DESC, t.nombre ASC
");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <div>
                    <h2 class="mb-1">Tipos de Seguros / Permisos</h2>
                    <p class="text-muted mb-0">Creá nuevos tipos, desactivá los que no uses o eliminá los que todavía no tengan registros.</p>
                </div>
                <a href="seguros.php" class="btn btn-outline-secondary">← Volver a Seguros y Permisos</a>
            </div>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Nuevo Tipo</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-5">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-2">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#0d6efd">
                        </div>
                        <div class="col-md-5">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion" name="descripcion">
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Crear Tipo</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="mb-0">Tipos Registrados</h5>
                    <small class="text-muted">Si un tipo ya tiene registros guardados, al quitarlo se desactiva para no romper el historial.</small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Color</th>
                                    <th>Descripción</th>
                                    <th>Activo</th>
                                    <th>Usos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos as $tipo): ?>
                                    <tr>
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= (int)$tipo['id'] ?>">
                                            <td><input type="text" class="form-control form-control-sm" name="nombre" value="<?= htmlspecialchars((string)$tipo['nombre']) ?>" required></td>
                                            <td><input type="color" class="form-control form-control-color" name="color" value="<?= htmlspecialchars((string)($tipo['color'] ?? '#0d6efd')) ?>"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="descripcion" value="<?= htmlspecialchars((string)($tipo['descripcion'] ?? '')) ?>"></td>
                                            <td class="text-center">
                                                <input type="checkbox" class="form-check-input" name="activo" <?= !empty($tipo['activo']) ? 'checked' : '' ?>>
                                                <div><small class="<?= !empty($tipo['activo']) ? 'text-success' : 'text-muted' ?>"><?= !empty($tipo['activo']) ? 'Activo' : 'Inactivo' ?></small></div>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-secondary"><?= (int)($tipo['cantidad_registros'] ?? 0) ?></span>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button type="submit" name="action" value="save" class="btn btn-sm btn-outline-primary">Guardar</button>
                                                    <button type="submit" name="action" value="toggle" class="btn btn-sm btn-outline-warning">
                                                        <?= !empty($tipo['activo']) ? 'Desactivar' : 'Activar' ?>
                                                    </button>
                                                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Querés quitar este tipo? Si ya tiene registros, se va a desactivar.')">Quitar</button>
                                                </div>
                                            </td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
