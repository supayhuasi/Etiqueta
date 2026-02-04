<?php
require '../../config.php';
require '../includes/header.php';

$id = $_GET['id'] ?? 0;

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE empleados 
            SET nombre = ?, email = ?, sueldo_base = ? 
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['email'],
            floatval($_POST['sueldo_base']),
            $id
        ]);
        
        header("Location: sueldos.php?success=Empleado actualizado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2>Editar Empleado</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($empleado['nombre']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($empleado['email']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Sueldo Base ($)</label>
                    <input type="number" class="form-control" name="sueldo_base" step="0.01" value="<?= $empleado['sueldo_base'] ?>" required>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar Cambios</button>
                    <a href="sueldos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
