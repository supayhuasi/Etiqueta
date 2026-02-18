<?php
require '../includes/header.php';

// Obtener empleados activos
$stmt = $pdo->query("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empleado_id = $_POST['empleado_id'] ?? 0;
    $fecha_desde = $_POST['fecha_desde'] ?? '';
    $fecha_hasta = $_POST['fecha_hasta'] ?? '';
    $tipo = $_POST['tipo'] ?? 'vacaciones';
    $observaciones = $_POST['observaciones'] ?? '';

    // Validaciones
    if (!$empleado_id || !$fecha_desde || !$fecha_hasta) {
        $error = "Todos los campos son obligatorios";
    } elseif (strtotime($fecha_desde) > strtotime($fecha_hasta)) {
        $error = "La fecha desde no puede ser mayor a la fecha hasta";
    } else {
        try {
            // Determinar el estado según el tipo
            $estado_map = [
                'vacaciones' => 'justificado',
                'licencia' => 'justificado',
                'permiso' => 'justificado',
                'ausente' => 'ausente'
            ];
            $estado = $estado_map[$tipo] ?? 'justificado';
            
            // Preparar texto de observaciones
            $tipos_texto = [
                'vacaciones' => 'Vacaciones',
                'licencia' => 'Licencia',
                'permiso' => 'Permiso',
                'ausente' => 'Ausencia'
            ];
            $obs_prefix = $tipos_texto[$tipo] ?? 'Ausencia';
            $observaciones_final = $obs_prefix . ($observaciones ? ': ' . $observaciones : '');

            // Obtener todas las fechas del rango
            $fecha_actual = new DateTime($fecha_desde);
            $fecha_fin = new DateTime($fecha_hasta);
            $fecha_fin->modify('+1 day'); // Para incluir el último día
            
            $fechas_procesadas = 0;
            $fechas_omitidas = 0;
            $errores = [];

            // Iterar por cada día del rango
            while ($fecha_actual < $fecha_fin) {
                $fecha_str = $fecha_actual->format('Y-m-d');
                
                // Verificar si ya existe asistencia para esta fecha
                $stmt = $pdo->prepare("SELECT id FROM asistencias WHERE empleado_id = ? AND fecha = ?");
                $stmt->execute([$empleado_id, $fecha_str]);
                
                if ($stmt->fetch()) {
                    $fechas_omitidas++;
                } else {
                    // Insertar asistencia
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO asistencias 
                            (empleado_id, fecha, hora_entrada, hora_salida, observaciones, estado, creado_por)
                            VALUES (?, ?, NULL, NULL, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $empleado_id, 
                            $fecha_str, 
                            $observaciones_final, 
                            $estado,
                            $_SESSION['user']['id']
                        ]);
                        $fechas_procesadas++;
                    } catch (Exception $e) {
                        $errores[] = "Error en fecha $fecha_str: " . $e->getMessage();
                    }
                }
                
                $fecha_actual->modify('+1 day');
            }

            // Mostrar resultado
            $mensaje = "✓ Registro completado:<br>";
            $mensaje .= "- Fechas procesadas: $fechas_procesadas<br>";
            if ($fechas_omitidas > 0) {
                $mensaje .= "- Fechas omitidas (ya existían): $fechas_omitidas<br>";
            }
            if (!empty($errores)) {
                $mensaje .= "- Errores: " . implode(", ", $errores);
            }
            
            echo "<div class='alert alert-success'>$mensaje</div>";
            if ($fechas_procesadas > 0) {
                echo "<script>setTimeout(() => window.location.href='asistencias.php', 2500);</script>";
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
            <h1>📅 Cargar Asistencias por Rango</h1>
            <p class="text-muted">Para vacaciones, licencias y ausencias prolongadas</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-md-8">
            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Empleado *</label>
                            <select name="empleado_id" class="form-select" required id="empleado_select">
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($empleados as $emp): ?>
                                    <option value="<?= $emp['id'] ?>">
                                        <?= htmlspecialchars($emp['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha Desde *</label>
                                <input type="date" name="fecha_desde" class="form-control" required 
                                       id="fecha_desde" value="<?= date('Y-m-d') ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fecha Hasta *</label>
                                <input type="date" name="fecha_hasta" class="form-control" required 
                                       id="fecha_hasta" value="<?= date('Y-m-d') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Tipo de Ausencia *</label>
                            <select name="tipo" class="form-select" required id="tipo_select">
                                <option value="vacaciones">🏖️ Vacaciones</option>
                                <option value="licencia">🏥 Licencia</option>
                                <option value="permiso">📋 Permiso</option>
                                <option value="ausente">❌ Ausencia Injustificada</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2" 
                                      placeholder="Información adicional (opcional)"></textarea>
                            <small class="text-muted">
                                El tipo seleccionado se agregará automáticamente a las observaciones
                            </small>
                        </div>

                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <strong>Información:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Se creará un registro de asistencia para cada día del rango seleccionado</li>
                                <li>Si ya existe un registro para alguna fecha, se omitirá automáticamente</li>
                                <li>Las vacaciones, licencias y permisos se marcarán como "Justificado"</li>
                                <li>Las ausencias injustificadas se marcarán como "Ausente"</li>
                            </ul>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Registrar Rango
                            </button>
                            <a href="asistencias.php" class="btn btn-secondary">Cancelar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card bg-light">
                <div class="card-header">
                    <h5 class="mb-0">💡 Guía de Uso</h5>
                </div>
                <div class="card-body">
                    <h6>¿Cuándo usar esta función?</h6>
                    <ul>
                        <li><strong>Vacaciones:</strong> Cuando un empleado se toma días de vacaciones</li>
                        <li><strong>Licencia:</strong> Por enfermedad, estudios, etc.</li>
                        <li><strong>Permiso:</strong> Permisos especiales aprobados</li>
                        <li><strong>Ausencia:</strong> Faltas sin justificación</li>
                    </ul>

                    <h6 class="mt-3">Ejemplos:</h6>
                    <div class="small">
                        <strong>Vacaciones de 1 semana:</strong><br>
                        Desde: 10/03/2026<br>
                        Hasta: 16/03/2026<br>
                        Tipo: Vacaciones<br>
                        <hr>
                        <strong>Licencia por enfermedad:</strong><br>
                        Desde: 18/02/2026<br>
                        Hasta: 20/02/2026<br>
                        Tipo: Licencia<br>
                        Obs: Gripe
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validación: fecha_hasta no puede ser menor a fecha_desde
document.getElementById('fecha_desde').addEventListener('change', function() {
    document.getElementById('fecha_hasta').min = this.value;
});

document.getElementById('fecha_hasta').addEventListener('change', function() {
    const desde = document.getElementById('fecha_desde').value;
    if (desde && this.value < desde) {
        alert('La fecha hasta no puede ser anterior a la fecha desde');
        this.value = desde;
    }
});

// Calcular días del rango
function calcularDias() {
    const desde = document.getElementById('fecha_desde').value;
    const hasta = document.getElementById('fecha_hasta').value;
    
    if (desde && hasta) {
        const d1 = new Date(desde);
        const d2 = new Date(hasta);
        const dias = Math.round((d2 - d1) / (1000 * 60 * 60 * 24)) + 1;
        
        if (dias > 0) {
            const info = document.querySelector('.alert-info ul');
            const existente = info.querySelector('.dias-count');
            if (existente) existente.remove();
            
            const li = document.createElement('li');
            li.className = 'dias-count fw-bold text-primary';
            li.innerHTML = `Se registrarán ${dias} día${dias > 1 ? 's' : ''}`;
            info.insertBefore(li, info.firstChild);
        }
    }
}

document.getElementById('fecha_desde').addEventListener('change', calcularDias);
document.getElementById('fecha_hasta').addEventListener('change', calcularDias);

// Calcular al cargar si ya hay valores
calcularDias();
</script>

<?php require '../includes/footer.php'; ?>
