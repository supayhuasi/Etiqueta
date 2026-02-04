<?php
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

// No permitir editar cheques pagados
if ($cheque['pagado']) {
    die("No se pueden editar cheques que ya han sido pagados");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_cheque = $_POST['numero_cheque'] ?? '';
    $monto = floatval($_POST['monto'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? '';
    $fecha_pago = $_POST['fecha_pago'] ?? null;
    $banco = $_POST['banco'] ?? '';
    $beneficiario = $_POST['beneficiario'] ?? '';
    $observaciones = $_POST['observaciones'] ?? '';
    
    // Validar datos
    $errores = [];
    
    if (empty($numero_cheque)) $errores[] = "El número de cheque es obligatorio";
    if ($monto <= 0) $errores[] = "El monto debe ser mayor a 0";
    if (empty($fecha_emision)) $errores[] = "La fecha de emisión es obligatoria";
    if (empty($banco)) $errores[] = "El banco es obligatorio";
    if (empty($beneficiario)) $errores[] = "El beneficiario es obligatorio";
    
    // Validar que fecha_pago sea posterior a fecha_emision si se proporciona
    if (!empty($fecha_pago) && strtotime($fecha_pago) < strtotime($fecha_emision)) {
        $errores[] = "La fecha de pago no puede ser anterior a la fecha de emisión";
    }
    
    // Verificar que el número no exista en otro cheque
    $stmt = $pdo->prepare("SELECT id FROM cheques WHERE numero_cheque = ? AND id != ?");
    $stmt->execute([$numero_cheque, $id]);
    if ($stmt->fetch()) {
        $errores[] = "Este número de cheque ya existe";
    }
    
    if (empty($errores)) {
        try {
            $mes_emision = date('Y-m', strtotime($fecha_emision));
            $pagado = !empty($fecha_pago) ? 1 : 0;
            
            $stmt = $pdo->prepare("
                UPDATE cheques 
                SET numero_cheque = ?, monto = ?, fecha_emision = ?, mes_emision = ?, 
                    banco = ?, beneficiario = ?, observaciones = ?, fecha_pago = ?, pagado = ?
                WHERE id = ?
            ");
            $stmt->execute([$numero_cheque, $monto, $fecha_emision, $mes_emision, $banco, $beneficiario, $observaciones, $fecha_pago, $pagado, $id]);
            
            $mensaje = "Cheque actualizado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM cheques WHERE id = ?");
            $stmt->execute([$id]);
            $cheque = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errores);
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Editar Cheque</h2>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $mensaje ?>
                    <br><a href="cheques.php?mes=<?= $cheque['mes_emision'] ?>" class="btn btn-primary btn-sm mt-2">Volver a Cheques</a>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="numero_cheque" class="form-label">Número de Cheque *</label>
                                <input type="text" class="form-control" id="numero_cheque" name="numero_cheque" 
                                       value="<?= htmlspecialchars($cheque['numero_cheque']) ?>" required>
                                <small class="form-text text-muted">Ej: 001234</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="monto" class="form-label">Monto *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="monto" name="monto" 
                                           value="<?= $cheque['monto'] ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de Emisión *</label>
                                <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" 
                                       value="<?= $cheque['fecha_emision'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="fecha_pago" class="form-label">Fecha de Pago</label>
                                <input type="date" class="form-control" id="fecha_pago" name="fecha_pago" 
                                       value="<?= $cheque['fecha_pago'] ?? '' ?>">
                                <small class="form-text text-muted">Dejar vacío si aún no se ha pagado</small>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="banco" class="form-label">Banco *</label>
                                <input type="text" class="form-control" id="banco" name="banco" 
                                       value="<?= htmlspecialchars($cheque['banco']) ?>" placeholder="Banco Nación, BBVA, etc." required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="beneficiario" class="form-label">Beneficiario *</label>
                            <input type="text" class="form-control" id="beneficiario" name="beneficiario" 
                                   value="<?= htmlspecialchars($cheque['beneficiario']) ?>" 
                                   placeholder="Nombre de la persona o empresa" required>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"><?= htmlspecialchars($cheque['observaciones'] ?? '') ?></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="cheques.php?mes=<?= $cheque['mes_emision'] ?>" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Cheque</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
