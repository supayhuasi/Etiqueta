<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user']) || ($_SESSION['rol'] ?? '') !== 'admin') {
    http_response_code(403);
    echo '<div class="container mt-4"><div class="alert alert-danger">Acceso solo permitido para administradores.</div></div>';
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: empleados.php');
    exit;
}

$id = intval($_GET['id']);

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("<div class='container mt-4'><div class='alert alert-danger'>Empleado no encontrado</div></div>");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $activo = isset($_POST['activo']) ? 1 : 0;
        $stmt = $pdo->prepare("
            UPDATE empleados 
            SET nombre = ?, email = ?, documento = ?, tipo_documento = ?, telefono = ?,
                direccion = ?, ciudad = ?, provincia = ?, codigo_postal = ?,
                puesto = ?, departamento = ?, fecha_ingreso = ?, sueldo_base = ?, activo = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['email'],
            $_POST['documento'] ?? null,
            $_POST['tipo_documento'] ?? 'DNI',
            $_POST['telefono'] ?? null,
            $_POST['direccion'] ?? null,
            $_POST['ciudad'] ?? null,
            $_POST['provincia'] ?? null,
            $_POST['codigo_postal'] ?? null,
            $_POST['puesto'] ?? null,
            $_POST['departamento'] ?? null,
            $_POST['fecha_ingreso'] ?? null,
            floatval($_POST['sueldo_base']),
            $activo,
            $id
        ]);
        header("Location: empleados.php?success=Empleado actualizado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Editar Empleado</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= ($empleado['activo'] ?? 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Empleado activo (si está desmarcado, no se liquida sueldo)</label>
                </div>
                <div class="card mb-3">
                    <div class="card-header"><h5>Datos Personales</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($empleado['nombre']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select class="form-select" name="tipo_documento">
                                    <option value="DNI" <?= ($empleado['tipo_documento'] ?? 'DNI') === 'DNI' ? 'selected' : '' ?>>DNI</option>
                                    <option value="CUIT" <?= ($empleado['tipo_documento'] ?? 'DNI') === 'CUIT' ? 'selected' : '' ?>>CUIT</option>
                                    <option value="Pasaporte" <?= ($empleado['tipo_documento'] ?? 'DNI') === 'Pasaporte' ? 'selected' : '' ?>>Pasaporte</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Número de Documento</label>
                                <input type="text" class="form-control" name="documento" value="<?= htmlspecialchars($empleado['documento'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($empleado['email']) ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" class="form-control" name="telefono" value="<?= htmlspecialchars($empleado['telefono'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header"><h5>Domicilio</h5></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" class="form-control" name="direccion" value="<?= htmlspecialchars($empleado['direccion'] ?? '') ?>">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Ciudad</label>
                                <input type="text" class="form-control" name="ciudad" value="<?= htmlspecialchars($empleado['ciudad'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Provincia</label>
                                <input type="text" class="form-control" name="provincia" value="<?= htmlspecialchars($empleado['provincia'] ?? '') ?>">
                            </div>
                            <div class="col-md-2 mb-3">
                                <label class="form-label">C.P.</label>
                                <input type="text" class="form-control" name="codigo_postal" value="<?= htmlspecialchars($empleado['codigo_postal'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card mb-3">
                    <div class="card-header"><h5>Información Laboral</h5></div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Puesto</label>
                                <input type="text" class="form-control" name="puesto" value="<?= htmlspecialchars($empleado['puesto'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Departamento</label>
                                <input type="text" class="form-control" name="departamento" value="<?= htmlspecialchars($empleado['departamento'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha de Ingreso</label>
                                <input type="date" class="form-control" name="fecha_ingreso" value="<?= $empleado['fecha_ingreso'] ?? '' ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Sueldo Base ($) *</label>
                                <input type="number" class="form-control" name="sueldo_base" step="0.01" value="<?= $empleado['sueldo_base'] ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <a href="empleados.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require '../includes/footer.php'; ?>
