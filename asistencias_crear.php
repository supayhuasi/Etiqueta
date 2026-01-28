<?php
require 'config.php';
require 'includes/header.php';

// Obtener empleados activos
$stmt = $pdo->query("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleado_id = $_POST['empleado_id'] ?? 0;
    $fecha = $_POST['fecha'] ?? '';
    $hora_entrada = $_POST['hora_entrada'] ?? null;
    $hora_salida = $_POST['hora_salida'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    $estado = $_POST['estado'] ?? 'presente';

    // Validaciones
    if (!$empleado_id || !$fecha) {
        $error = "Empleado y fecha son obligatorios";
    } else {
        try {
            // Verificar si ya existe asistencia para este empleado en esta fecha
            $stmt = $pdo->prepare("SELECT id FROM asistencias WHERE empleado_id = ? AND fecha = ?");
            $stmt->execute([$empleado_id, $fecha]);
            
            if ($stmt->fetch()) {
                $error = "Ya existe una asistencia registrada para este empleado en esta fecha";
            } else {
                // Determinar estado automáticamente si tiene horario
                $stmt = $pdo->prepare("
                    SELECT hora_entrada, tolerancia_minutos 
                    FROM empleados_horarios 
                    WHERE empleado_id = ? AND activo = 1
                ");
                $stmt->execute([$empleado_id]);
                $horario = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($horario && $hora_entrada && $estado == 'presente') {
                    $horario_obj = new DateTime($horario['hora_entrada']);
                    $entrada_obj = new DateTime($hora_entrada);
                    $tolerancia = $horario['tolerancia_minutos'] ?? 10;
                    $horario_obj->modify("+{$tolerancia} minutes");
                    
                    if ($entrada_obj > $horario_obj) {
                        $estado = 'tarde';
                    }
                }

                // Insertar asistencia
                $stmt = $pdo->prepare("
                    INSERT INTO asistencias 
                    (empleado_id, fecha, hora_entrada, hora_salida, observaciones, estado, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $empleado_id, 
                    $fecha, 
                    $hora_entrada ?: null, 
                    $hora_salida ?: null, 
                    $observaciones, 
                    $estado,
                    $_SESSION['user']['id']
                ]);

                $mensaje = "✓ Asistencia registrada correctamente";
                echo "<div class='alert alert-success'>$mensaje</div>";
                echo "<script>setTimeout(() => window.location.href='asistencias.php', 1500);</script>";
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }

    if (isset($error)) {
        echo "<div class='alert alert-danger'>$error</div>";
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8">
            <h1>➕ Cargar Asistencia</h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-body">
            <form method="POST" id="formAsistencia">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Empleado *</label>
                        <select name="empleado_id" id="empleado_id" class="form-select" required>
                            <option value="">Seleccione un empleado</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Fecha *</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hora de Entrada</label>
                        <input type="time" name="hora_entrada" id="hora_entrada" class="form-control">
                        <small class="text-muted" id="horario_info"></small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Hora de Salida</label>
                        <input type="time" name="hora_salida" class="form-control">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="presente">Presente</option>
                        <option value="tarde">Tarde</option>
                        <option value="ausente">Ausente</option>
                        <option value="justificado">Justificado</option>
                    </select>
                    <small class="text-muted">El estado "Tarde" se detecta automáticamente si tiene horario configurado</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3" placeholder="Notas adicionales..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary">💾 Guardar Asistencia</button>
                <a href="asistencias.php" class="btn btn-secondary">Cancelar</a>
            </form>
        </div>
    </div>
</div>

<script>
// Mostrar horario del empleado seleccionado
document.getElementById('empleado_id').addEventListener('change', function() {
    const empleadoId = this.value;
    if (!empleadoId) {
        document.getElementById('horario_info').textContent = '';
        return;
    }

    fetch('asistencias_horario_ajax.php?empleado_id=' + empleadoId)
        .then(response => response.json())
        .then(data => {
            if (data.horario) {
                document.getElementById('horario_info').textContent = 
                    `Horario: ${data.horario.hora_entrada} - ${data.horario.hora_salida} (Tolerancia: ${data.horario.tolerancia_minutos} min)`;
            } else {
                document.getElementById('horario_info').textContent = 'Sin horario configurado';
            }
        });
});
</script>

<?php require 'includes/footer.php'; ?>
