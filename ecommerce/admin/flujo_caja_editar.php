<?php
require '../../config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Incluir header AQUÍ, antes de enviar HTML
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);
$error = '';
$exito = '';

if ($id <= 0) {
    header('Location: flujo_caja.php');
    exit;
}

// Obtener transacción
$stmt = $pdo->prepare("SELECT * FROM flujo_caja WHERE id = ?");
$stmt->execute([$id]);
$transaccion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transaccion) {
    header('Location: flujo_caja.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fecha = $_POST['fecha'] ?? $transaccion['fecha'];
        $categoria = $_POST['categoria'] ?? $transaccion['categoria'];
        $descripcion = $_POST['descripcion'] ?? $transaccion['descripcion'];
        $monto = floatval($_POST['monto'] ?? $transaccion['monto']);
        $referencia = $_POST['referencia'] ?? $transaccion['referencia'];
        $observaciones = $_POST['observaciones'] ?? $transaccion['observaciones'];

        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }

        $stmt = $pdo->prepare("
            UPDATE flujo_caja
            SET fecha = ?, categoria = ?, descripcion = ?, monto = ?, referencia = ?, observaciones = ?, fecha_actualizacion = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $fecha,
            $categoria,
            $descripcion,
            $monto,
            $referencia,
            $observaciones,
            $id
        ]);

        $exito = 'Transacción actualizada correctamente';

        // Recargar datos
        $stmt = $pdo->prepare("SELECT * FROM flujo_caja WHERE id = ?");
        $stmt->execute([$id]);
        $transaccion = $stmt->fetch(PDO::FETCH_ASSOC);

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$tipo_badge = $transaccion['tipo'] === 'ingreso' ? 'success' : 'danger';
$tipo_texto = ucfirst($transaccion['tipo']);

?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>✏️ Editar Transacción</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="flujo_caja.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Éxito:</strong> <?= htmlspecialchars($exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <span class="badge bg-<?= $tipo_badge ?>"><?= $tipo_texto ?></span>
            <span class="ms-2">Creado: <?= date('d/m/Y H:i', strtotime($transaccion['fecha_creacion'])) ?></span>
        </div>
        <div class="card-body">
            <form method="POST" class="needs-validation">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" class="form-control" value="<?= $transaccion['fecha'] ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <input type="text" id="categoria" name="categoria" class="form-control" value="<?= htmlspecialchars($transaccion['categoria']) ?>" required>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" value="<?= htmlspecialchars($transaccion['descripcion'] ?? '') ?>">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monto" class="form-label">Monto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="monto" name="monto" class="form-control" step="0.01" min="0" value="<?= number_format($transaccion['monto'], 2, '.', '') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="referencia" class="form-label">Referencia</label>
                            <input type="text" id="referencia" name="referencia" class="form-control" value="<?= htmlspecialchars($transaccion['referencia'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($transaccion['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-check-circle"></i> Guardar Cambios
                    </button>
                    <a href="flujo_caja.php" class="btn btn-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
