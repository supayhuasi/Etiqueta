<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

// Obtener datos del gasto
$stmt = $pdo->prepare("
    SELECT g.*, t.nombre as tipo_nombre, e.nombre as estado_nombre
    FROM gastos g
    LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
    LEFT JOIN estados_gastos e ON g.estado_gasto_id = e.id
    WHERE g.id = ?
");
$stmt->execute([$id]);
$gasto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gasto) {
    die("Gasto no encontrado");
}

// Obtener estados disponibles
$stmt_estados = $pdo->query("SELECT id, nombre FROM estados_gastos WHERE activo = 1 ORDER BY nombre");
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Procesar cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $estado_nuevo_id = $_POST['estado_gasto_id'] ?? 0;
    $observaciones = $_POST['observaciones'] ?? '';
    
    if ($estado_nuevo_id <= 0) {
        $error = "Debe seleccionar un estado";
    } else {
        try {
            $estado_anterior_id = $gasto['estado_gasto_id'];
            
            // Actualizar estado del gasto
            $stmt = $pdo->prepare("UPDATE gastos SET estado_gasto_id = ? WHERE id = ?");
            $stmt->execute([$estado_nuevo_id, $id]);
            
            // Registrar en historial
            $stmt = $pdo->prepare("
                INSERT INTO historial_gastos (gasto_id, estado_anterior_id, estado_nuevo_id, usuario_id, observaciones)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$id, $estado_anterior_id, $estado_nuevo_id, $_SESSION['user']['id'], $observaciones]);
            
            // Si el nuevo estado es "Pagado", registrar en flujo de caja
            $stmt_pagado = $pdo->prepare("SELECT id FROM estados_gastos WHERE LOWER(nombre) = 'pagado' LIMIT 1");
            $stmt_pagado->execute();
            $pagado_id = $stmt_pagado->fetchColumn();

            if ($pagado_id && (int)$estado_nuevo_id === (int)$pagado_id) {
                try {
                    // Verificar si ya existe en flujo_caja
                    $stmt_fc_check = $pdo->prepare("
                        SELECT id FROM flujo_caja 
                        WHERE id_referencia = ? AND categoria = 'Gasto'
                        LIMIT 1
                    ");
                    $stmt_fc_check->execute([$id]);
                    $fc_existe = $stmt_fc_check->fetch(PDO::FETCH_ASSOC);

                    if ($fc_existe) {
                        $stmt_fc_update = $pdo->prepare("
                            UPDATE flujo_caja
                            SET fecha = ?, descripcion = ?, monto = ?, observaciones = ?
                            WHERE id_referencia = ? AND categoria = 'Gasto'
                        ");
                        $stmt_fc_update->execute([
                            $gasto['fecha'],
                            $gasto['descripcion'],
                            $gasto['monto'],
                            $observaciones ?: 'Actualizado desde cambio de estado a Pagado',
                            $id
                        ]);
                    } else {
                        // Solo crear si no existe
                        $stmt_fc = $pdo->prepare("
                            INSERT INTO flujo_caja 
                            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                            VALUES (?, 'egreso', 'Gasto', ?, ?, ?, ?, ?)
                        ");
                        $stmt_fc->execute([
                            $gasto['fecha'],
                            $gasto['descripcion'],
                            $gasto['monto'],
                            $gasto['numero_gasto'],
                            $id,
                            $_SESSION['user']['id'],
                            $observaciones ?: 'Registrado desde cambio de estado a Pagado'
                        ]);
                    }
                } catch (Exception $e) {
                    // Si falla el flujo de caja, no afecta el cambio de estado
                }
            }
            
            $mensaje = "Estado actualizado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("
                SELECT g.*, t.nombre as tipo_nombre, e.nombre as estado_nombre
                FROM gastos g
                LEFT JOIN tipos_gastos t ON g.tipo_gasto_id = t.id
                LEFT JOIN estados_gastos e ON g.estado_gasto_id = e.id
                WHERE g.id = ?
            ");
            $stmt->execute([$id]);
            $gasto = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error al cambiar estado: " . $e->getMessage();
        }
    }
}

// Obtener historial de cambios
$stmt_historial = $pdo->prepare("
    SELECT h.*, e.nombre as estado_nombre, u.nombre as usuario_nombre
    FROM historial_gastos h
    LEFT JOIN estados_gastos e ON h.estado_nuevo_id = e.id
    LEFT JOIN usuarios u ON h.usuario_id = u.id
    WHERE h.gasto_id = ?
    ORDER BY h.fecha_cambio DESC
");
$stmt_historial->execute([$id]);
$historial = $stmt_historial->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Cambiar Estado del Gasto</h2>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $mensaje ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <!-- Información del gasto -->
            <div class="card mb-4">
                <div class="card-header bg-info">
                    <h5>Datos del Gasto</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Número:</strong> <?= htmlspecialchars($gasto['numero_gasto']) ?></p>
                            <p><strong>Tipo:</strong> <?= htmlspecialchars($gasto['tipo_nombre']) ?></p>
                            <p><strong>Descripción:</strong> <?= htmlspecialchars($gasto['descripcion']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Monto:</strong> <span class="h4 text-primary">$<?= number_format($gasto['monto'], 2, ',', '.') ?></span></p>
                            <p><strong>Fecha:</strong> <?= date('d/m/Y', strtotime($gasto['fecha'])) ?></p>
                            <p><strong>Estado Actual:</strong> <?= htmlspecialchars($gasto['estado_nombre']) ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de cambio de estado -->
            <div class="card mb-4">
                <div class="card-header bg-primary">
                    <h5>Cambiar Estado</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="estado_gasto_id" class="form-label">Nuevo Estado *</label>
                            <select class="form-select" id="estado_gasto_id" name="estado_gasto_id" required>
                                <option value="">Seleccionar estado...</option>
                                <?php foreach ($estados as $estado): ?>
                                    <option value="<?= $estado['id'] ?>" <?= $estado['id'] == $gasto['estado_gasto_id'] ? 'disabled' : '' ?>>
                                        <?= htmlspecialchars($estado['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="3"></textarea>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="gastos.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Cambiar Estado</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Historial de cambios -->
            <div class="card">
                <div class="card-header">
                    <h5>Historial de Cambios</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($historial)): ?>
                        <p class="text-muted text-center">Sin cambios registrados</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-sm">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Usuario</th>
                                        <th>Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($historial as $registro): ?>
                                    <tr>
                                        <td><?= date('d/m/Y H:i', strtotime($registro['fecha_cambio'])) ?></td>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= htmlspecialchars($registro['estado_nombre']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($registro['usuario_nombre']) ?></td>
                                        <td><?= htmlspecialchars($registro['observaciones'] ?? '-') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
