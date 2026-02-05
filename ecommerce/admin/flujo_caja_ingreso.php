<?php
require '../../config.php';
require '../../auth/check.php';

$error = '';
$exito = '';

// Obtener pedidos pagados para autocompletar
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        ep.id,
        ep.numero_pedido,
        ep.cliente_id,
        ec.nombre as cliente_nombre,
        ep.total,
        ep.monto_pagado
    FROM ecommerce_pedidos ep
    LEFT JOIN ecommerce_clientes ec ON ep.cliente_id = ec.id
    WHERE ep.monto_pagado > 0
    ORDER BY ep.id DESC
    LIMIT 20
");
$stmt->execute();
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $categoria = $_POST['categoria'] ?? '';
        $descripcion = $_POST['descripcion'] ?? '';
        $monto = floatval($_POST['monto'] ?? 0);
        $referencia = $_POST['referencia'] ?? '';
        $id_referencia = intval($_POST['id_referencia'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';

        if (!$categoria) {
            throw new Exception('La categoría es requerida');
        }
        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }

        $stmt = $pdo->prepare("
            INSERT INTO flujo_caja 
            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
            VALUES (?, 'ingreso', ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $fecha,
            $categoria,
            $descripcion,
            $monto,
            $referencia,
            $id_referencia > 0 ? $id_referencia : null,
            $_SESSION['user_id'] ?? null,
            $observaciones
        ]);

        $exito = 'Ingreso registrado correctamente';

        // Limpiar formulario
        $_POST = [];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nuevo Ingreso</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>➕ Nuevo Ingreso</h1>
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
        <div class="card-body">
            <form method="POST" class="needs-validation">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" class="form-control" value="<?= $_POST['fecha'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select id="categoria" name="categoria" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                <option value="Pago Pedido" <?= ($_POST['categoria'] ?? '') === 'Pago Pedido' ? 'selected' : '' ?>>Pago Pedido</option>
                                <option value="Pago Orden Producción" <?= ($_POST['categoria'] ?? '') === 'Pago Orden Producción' ? 'selected' : '' ?>>Pago Orden Producción</option>
                                <option value="Cotización Aprobada" <?= ($_POST['categoria'] ?? '') === 'Cotización Aprobada' ? 'selected' : '' ?>>Cotización Aprobada</option>
                                <option value="Crédito" <?= ($_POST['categoria'] ?? '') === 'Crédito' ? 'selected' : '' ?>>Crédito</option>
                                <option value="Otro" <?= ($_POST['categoria'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <input type="text" id="descripcion" name="descripcion" class="form-control" value="<?= htmlspecialchars($_POST['descripcion'] ?? '') ?>" placeholder="Ej: Pago cliente Juan García">
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monto" class="form-label">Monto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="monto" name="monto" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="referencia" class="form-label">Referencia (Ej: Número de cheque)</label>
                            <input type="text" id="referencia" name="referencia" class="form-control" value="<?= htmlspecialchars($_POST['referencia'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="id_referencia" class="form-label">Pedido Asociado (Opcional)</label>
                    <select id="id_referencia" name="id_referencia" class="form-select">
                        <option value="">Sin Pedido</option>
                        <?php foreach ($pedidos as $pedido): ?>
                            <option value="<?= $pedido['id'] ?>" <?= ($_POST['id_referencia'] ?? '') == $pedido['id'] ? 'selected' : '' ?>>
                                Pedido #<?= $pedido['numero_pedido'] ?> - <?= htmlspecialchars($pedido['cliente_nombre']) ?> - $<?= number_format($pedido['monto_pagado'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> Registrar Ingreso
                    </button>
                    <a href="flujo_caja.php" class="btn btn-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
