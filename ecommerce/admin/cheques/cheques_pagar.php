<?php
require '../../config.php';
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

$id = $_GET['id'] ?? 0;

// Obtener datos del cheque
$stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ?");
$stmt->execute([$id]);
$cheque = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cheque) {
    die("Cheque no encontrado");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha_pago = $_POST['fecha_pago'] ?? date('Y-m-d');
    $observaciones = $_POST['observaciones'] ?? '';
    
    if (empty($fecha_pago)) {
        $error = "La fecha de pago es obligatoria";
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE cheques 
                SET pagado = 1, fecha_pago = ?, observaciones = ?
                WHERE id = ?
            ");
            $stmt->execute([$fecha_pago, $observaciones, $id]);
            
            $mensaje = "Cheque marcado como pagado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ?");
            $stmt->execute([$id]);
            $cheque = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Registrar Pago de Cheque</h2>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $mensaje ?>
                    <br><a href="cheques.php?mes=<?= $cheque['mes_emision'] ?>" class="btn btn-primary btn-sm mt-2">Volver a Cheques</a>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <!-- Información del cheque -->
            <div class="card mb-4">
                <div class="card-header bg-info">
                    <h5>Datos del Cheque</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Número:</strong> <?= htmlspecialchars($cheque['numero_cheque']) ?></p>
                            <p><strong>Beneficiario:</strong> <?= htmlspecialchars($cheque['beneficiario']) ?></p>
                            <p><strong>Banco:</strong> <?= htmlspecialchars($cheque['banco']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Monto:</strong> <span class="h4 text-primary">$<?= number_format($cheque['monto'], 2, ',', '.') ?></span></p>
                            <p><strong>Fecha Emisión:</strong> <?= date('d/m/Y', strtotime($cheque['fecha_emision'])) ?></p>
                            <p><strong>Observaciones:</strong> <?= htmlspecialchars($cheque['observaciones'] ?? 'Sin observaciones') ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de pago -->
            <div class="card">
                <div class="card-header bg-success">
                    <h5>Registrar Pago</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="fecha_pago" class="form-label">Fecha de Pago *</label>
                            <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" 
                                   value="<?= $cheque['fecha_pago'] ?? date('Y-m-d') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars($cheque['observaciones'] ?? '') ?></textarea>
                        </div>

                        <div class="alert alert-info">
                            <strong>⚠️ Nota:</strong> Al marcar como pagado, el cheque no podrá editarse nuevamente.
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="cheques.php?mes=<?= $cheque['mes_emision'] ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-success">Marcar como Pagado</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
