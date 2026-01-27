<?php
require 'includes/header.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM ecommerce_categorias WHERE id = ?");
$stmt->execute([$id]);
$categoria = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$categoria) {
    die("Categoría no encontrada");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM ecommerce_categorias WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: categorias.php");
        exit;
    } catch (Exception $e) {
        $error = "No se puede eliminar: " . $e->getMessage();
    }
}
?>

<div class="row">
    <div class="col-md-6 offset-md-3">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5>Eliminar Categoría</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                
                <p>¿Estás seguro de que deseas eliminar la categoría?</p>
                <div class="alert alert-warning">
                    <strong><?= htmlspecialchars($categoria['nombre']) ?></strong>
                </div>

                <form method="POST">
                    <div class="d-flex gap-2">
                        <a href="categorias.php" class="btn btn-secondary">No, Cancelar</a>
                        <button type="submit" class="btn btn-danger">Sí, Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
