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
    $estado = $_POST['estado'] ?? 'pendiente';
    $fecha_pago = $_POST['fecha_pago'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    try {
        // Actualizar campo pagado para compatibilidad
        $pagado = ($estado === 'pagado') ? 1 : 0;
        
        $stmt = $pdo->prepare("
            UPDATE cheques 
            SET estado = ?, pagado = ?, fecha_pago = ?, observaciones = ?
            WHERE id = ?
        ");
        $stmt->execute([$estado, $pagado, $fecha_pago ?: null, $observaciones, $id]);
        
        $mensaje = "Estado del cheque actualizado correctamente";
        
        // Recargar datos
        $stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ?");
        $stmt->execute([$id]);
        $cheque = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $error = "Error al guardar: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>🔄 Cambiar Estado del Cheque</h2>

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
                <div class="card-header bg-info text-white">
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
                            <p><strong>Estado Actual:</strong> 
                                <?php
                                $estado_actual = $cheque['estado'] ?? 'pendiente';
                                $badges = [
                                    'pendiente' => 'warning',
                                    'pagado' => 'success',
                                    'rechazado' => 'danger',
                                    'aceptado' => 'info'
                                ];
                                ?>
                                <span class="badge bg-<?= $badges[$estado_actual] ?? 'secondary' ?>"><?= ucfirst($estado_actual) ?></span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de cambio de estado -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5>Actualizar Estado</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="estado" class="form-label">Estado *</label>
                            <select class="form-select" id="estado" name="estado" required>
                                <option value="pendiente" <?= ($cheque['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>⏳ Pendiente</option>
                                <option value="aceptado" <?= ($cheque['estado'] ?? '') === 'aceptado' ? 'selected' : '' ?>>✓ Aceptado</option>
                                <option value="pagado" <?= ($cheque['estado'] ?? '') === 'pagado' ? 'selected' : '' ?>>💰 Pagado</option>
                                <option value="rechazado" <?= ($cheque['estado'] ?? '') === 'rechazado' ? 'selected' : '' ?>>✗ Rechazado</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                            <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" 
                                   value="<?= $cheque['fecha_pago'] ?? '' ?>">
                            <small class="text-muted">Dejar en blanco si aún no se ha pagado</small>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars($cheque['observaciones'] ?? '') ?></textarea>
                        </div>

                        <div class="alert alert-info">
                            <strong>ℹ️ Estados:</strong>
                            <ul class="mb-0 mt-2">
                                <li><strong>Pendiente:</strong> Cheque emitido, esperando procesamiento</li>
                                <li><strong>Aceptado:</strong> Cheque aceptado por el beneficiario</li>
                                <li><strong>Pagado:</strong> Cheque cobrado/pagado efectivamente</li>
                                <li><strong>Rechazado:</strong> Cheque rechazado o cancelado</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="cheques.php?mes=<?= $cheque['mes_emision'] ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">💾 Actualizar Estado</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
