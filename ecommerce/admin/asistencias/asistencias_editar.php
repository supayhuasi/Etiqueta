<?php
require '../includes/header.php';

$id = $_GET['id'] ?? 0;

// Obtener asistencia
$stmt = $pdo->prepare("SELECT * FROM asistencias WHERE id = ?");
$stmt->execute([$id]);
$asistencia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$asistencia) {
    die("<div class='alert alert-danger'>Asistencia no encontrada</div>");
}

// Obtener empleados
$stmt = $pdo->query("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hora_entrada = $_POST['hora_entrada'] ?? null;
    $hora_salida = $_POST['hora_salida'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    $estado = $_POST['estado'] ?? 'presente';

    try {
        $stmt = $pdo->prepare("
            UPDATE asistencias 
            SET hora_entrada = ?, hora_salida = ?, observaciones = ?, estado = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $hora_entrada ?: null,
            $hora_salida ?: null,
            $observaciones,
            $estado,
            $id
        ]);

        $mensaje = "✓ Asistencia actualizada correctamente";
        echo "<div class='alert alert-success'>$mensaje</div>";
        echo "<script>setTimeout(() => window.location.href='asistencias.php', 1500);</script>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h1>✏️ Editar Asistencia</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST">
                <div class="alert alert-info">
                    <strong>Empleado:</strong> 
                    <?php
                    $stmt = $pdo->prepare("SELECT nombre FROM empleados WHERE id = ?");
                    $stmt->execute([$asistencia['empleado_id']]);
                    echo htmlspecialchars($stmt->fetchColumn());
                    ?><br>
                    <strong>Fecha:</strong> <?= date('d/m/Y', strtotime($asistencia['fecha'])) ?>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hora de Entrada</label>
                        <input type="time" name="hora_entrada" class="form-control" value="<?= $asistencia['hora_entrada'] ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hora de Salida</label>
                        <input type="time" name="hora_salida" class="form-control" value="<?= $asistencia['hora_salida'] ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="presente" <?= $asistencia['estado'] == 'presente' ? 'selected' : '' ?>>Presente</option>
                        <option value="tarde" <?= $asistencia['estado'] == 'tarde' ? 'selected' : '' ?>>Tarde</option>
                        <option value="ausente" <?= $asistencia['estado'] == 'ausente' ? 'selected' : '' ?>>Ausente</option>
                        <option value="justificado" <?= $asistencia['estado'] == 'justificado' ? 'selected' : '' ?>>Justificado</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($asistencia['observaciones']) ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary">💾 Actualizar</button>
                <a href="asistencias.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
