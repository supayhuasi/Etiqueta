<?php
require '../../config.php';
require '../includes/header.php';

$empleado_id = $_GET['empleado_id'] ?? 0;

// Obtener empleado
$stmt = $pdo->prepare("SELECT nombre FROM empleados WHERE id = ?");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("<div class='alert alert-danger'>Empleado no encontrado</div>");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hora_entrada = $_POST['hora_entrada'] ?? '';
    $hora_salida = $_POST['hora_salida'] ?? '';
    $tolerancia = $_POST['tolerancia_minutos'] ?? 10;

    try {
        // Desactivar horarios anteriores
        $stmt = $pdo->prepare("UPDATE empleados_horarios SET activo = 0 WHERE empleado_id = ?");
        $stmt->execute([$empleado_id]);

        // Insertar nuevo horario
        $stmt = $pdo->prepare("
            INSERT INTO empleados_horarios (empleado_id, hora_entrada, hora_salida, tolerancia_minutos, activo)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$empleado_id, $hora_entrada, $hora_salida, $tolerancia]);

        echo "<div class='alert alert-success'>✓ Horario asignado correctamente</div>";
        echo "<script>setTimeout(() => window.location.href='asistencias_horarios.php', 1500);</script>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h1>➕ Asignar Horario</h1>
            <p class="text-muted">Empleado: <strong><?= htmlspecialchars($empleado['nombre']) ?></strong></p>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias_horarios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora de Entrada *</label>
                        <input type="time" name="hora_entrada" class="form-control" value="08:00" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Hora de Salida *</label>
                        <input type="time" name="hora_salida" class="form-control" value="17:00" required>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">Tolerancia (minutos)</label>
                        <input type="number" name="tolerancia_minutos" class="form-control" value="10" min="0" max="60">
                        <small class="text-muted">Minutos de tolerancia para marcar tardanza</small>
                    </div>
                </div>

                <div class="alert alert-info">
                    <strong>ℹ️ Tolerancia:</strong> Si el empleado llega después del horario + tolerancia, se marcará automáticamente como "Tarde".
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar Horario</button>
                <a href="asistencias_horarios.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
