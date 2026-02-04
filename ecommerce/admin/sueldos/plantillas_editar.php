<?php
session_start();
require '../includes/header.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM plantillas_conceptos WHERE id = ?");
$stmt->execute([$id]);
$plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plantilla) {
    die("Plantilla no encontrada");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("UPDATE plantillas_conceptos SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->execute([
            $_POST['nombre'],
            $_POST['descripcion'],
            $id
        ]);
        
        header("Location: plantillas.php?success=Plantilla actualizada");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <h2>Editar Plantilla</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Nombre</label>
                    <input type="text" class="form-control" name="nombre" value="<?= htmlspecialchars($plantilla['nombre']) ?>" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Descripción</label>
                    <textarea class="form-control" name="descripcion" rows="3"><?= htmlspecialchars($plantilla['descripcion']) ?></textarea>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                    <a href="plantillas.php" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
