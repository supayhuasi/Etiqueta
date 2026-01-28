<?php
require 'config.php';
require 'includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

$id = $_GET['id'] ?? 0;
$mes = $_GET['mes'] ?? date('Y-m');

// Obtener datos del cheque
$stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ?");
$stmt->execute([$id]);
$cheque = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cheque) {
    die("Cheque no encontrado");
}

// No permitir eliminar cheques pagados
if ($cheque['pagado']) {
    die("No se pueden eliminar cheques que ya han sido pagados");
}

// Procesar eliminación
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("DELETE FROM cheques WHERE id = ?");
        $stmt->execute([$id]);
        
        header("Location: cheques.php?mes=$mes");
        exit;
    } catch (Exception $e) {
        $error = "Error al eliminar: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h5>Eliminar Cheque</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger" role="alert"><?= $error ?></div>
                    <?php endif; ?>

                    <p class="lead">¿Estás seguro de que deseas eliminar este cheque?</p>

                    <div class="alert alert-info">
                        <p><strong>Número:</strong> <?= htmlspecialchars($cheque['numero_cheque']) ?></p>
                        <p><strong>Beneficiario:</strong> <?= htmlspecialchars($cheque['beneficiario']) ?></p>
                        <p><strong>Monto:</strong> $<?= number_format($cheque['monto'], 2, ',', '.') ?></p>
                    </div>

                    <form method="POST">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="cheques.php?mes=<?= $mes ?>" class="btn btn-secondary">No, Cancelar</a>
                            <button type="submit" class="btn btn-danger">Sí, Eliminar Cheque</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
