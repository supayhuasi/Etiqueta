<?php
session_start();
require '../includes/header.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO plantillas_conceptos (nombre, descripcion, activo)
            VALUES (?, ?, 1)
        ");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['descripcion']
        ]);
        
        header("Location: plantillas.php?success=Plantilla creada");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2>Crear Plantilla</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre de Plantilla</label>
                    <input type="text" class="form-control" name="nombre" required placeholder="Ej: Plantilla Básica">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3" placeholder="Describe qué incluye esta plantilla"></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Crear</button>
                    <a href="plantillas.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
