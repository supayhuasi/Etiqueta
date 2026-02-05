<?php
session_start();
require '../../config.php';

$id = intval($_GET['id'] ?? 0);

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
        // Iniciar transacción
        $pdo->beginTransaction();

        // Si es un pago de sueldo, eliminar también el registro de pagos_sueldos_parciales
        if ($transaccion['categoria'] === 'Pago de Sueldo' && strpos($transaccion['referencia'], 'Sueldo') === 0) {
            $empleado_id = $transaccion['id_referencia'];
            $stmt = $pdo->prepare("DELETE FROM pagos_sueldos_parciales WHERE empleado_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$empleado_id]);
        }

        // Eliminar transacción de flujo de caja
        $stmt = $pdo->prepare("DELETE FROM flujo_caja WHERE id = ?");
        $stmt->execute([$id]);

        $pdo->commit();

        header('Location: flujo_caja.php?exito=1');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error al eliminar: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Eliminar Transacción</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container my-4">
    <div class="card card-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">⚠️ Confirmar Eliminación</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-warning">
                <strong>Advertencia:</strong> Esta acción no se puede deshacer. ¿Está seguro de que desea eliminar esta transacción?
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered">
                    <tr>
                        <th>Fecha:</th>
                        <td><?= date('d/m/Y', strtotime($transaccion['fecha'])) ?></td>
                    </tr>
                    <tr>
                        <th>Tipo:</th>
                        <td><span class="badge bg-<?= $transaccion['tipo'] === 'ingreso' ? 'success' : 'danger' ?>"><?= ucfirst($transaccion['tipo']) ?></span></td>
                    </tr>
                    <tr>
                        <th>Categoría:</th>
                        <td><?= htmlspecialchars($transaccion['categoria']) ?></td>
                    </tr>
                    <tr>
                        <th>Descripción:</th>
                        <td><?= htmlspecialchars($transaccion['descripcion'] ?? '-') ?></td>
                    </tr>
                    <tr>
                        <th>Monto:</th>
                        <td><strong>$<?= number_format($transaccion['monto'], 2) ?></strong></td>
                    </tr>
                </table>
            </div>

            <form method="POST" class="d-flex gap-2">
                <button type="submit" class="btn btn-danger btn-lg" onclick="return confirm('¿Está completamente seguro?')">
                    <i class="bi bi-trash"></i> Sí, Eliminar Transacción
                </button>
                <a href="flujo_caja.php" class="btn btn-secondary btn-lg">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
