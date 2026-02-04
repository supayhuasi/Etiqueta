<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $color = $_POST['color'] ?? '#000000';
    
    $errores = [];
    if (empty($nombre)) $errores[] = "El nombre es obligatorio";
    
    if (empty($errores)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tipos_gastos SET nombre = ?, descripcion = ?, color = ? WHERE id = ?");
                $stmt->execute([$nombre, $descripcion, $color, $id]);
                $mensaje = "Tipo actualizado correctamente";
            } else {
                $stmt = $pdo->prepare("INSERT INTO tipos_gastos (nombre, descripcion, color) VALUES (?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $color]);
                $mensaje = "Tipo creado correctamente";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errores);
    }
}

// Obtener todos los tipos
$stmt = $pdo->query("SELECT * FROM tipos_gastos ORDER BY nombre");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Tipos de Gastos</h2>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert"><?= $mensaje ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Nuevo Tipo de Gasto</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nombre" class="form-label">Nombre *</label>
                                <input type="text" class="form-control" id="nombre" name="nombre" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="color" class="form-label">Color</label>
                                <input type="color" class="form-control" id="color" name="color" value="#007BFF">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Crear Tipo</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Tipos Registrados</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Color</th>
                                    <th>Descripción</th>
                                    <th>Activo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos as $tipo): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($tipo['nombre']) ?></strong></td>
                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($tipo['color']) ?>">
                                            <?= htmlspecialchars($tipo['color']) ?>
                                        </span>
                                    </td>
                                    <td><?= htmlspecialchars($tipo['descripcion'] ?? '-') ?></td>
                                    <td><?= $tipo['activo'] ? '✓' : '✗' ?></td>
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
