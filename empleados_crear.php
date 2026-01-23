<?php
require 'config.php';
require 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO empleados (nombre, email, sueldo_base, activo, fecha_creacion) 
            VALUES (?, ?, ?, 1, NOW())
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['email'],
            floatval($_POST['sueldo_base'])
        ]);
        
        header("Location: sueldos.php?success=Empleado creado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2>Crear Nuevo Empleado</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" class="form-control" name="nombre" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" name="email" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Sueldo Base ($)</label>
                    <input type="number" class="form-control" name="sueldo_base" step="0.01" required>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="sueldos.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
