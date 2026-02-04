<?php
require '../includes/header.php';

$empleado_id = intval($_GET['empleado_id'] ?? 0);
if ($empleado_id <= 0) {
    header('Location: asistencias_horarios.php');
    exit;
}

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT id, nombre FROM empleados WHERE id = ? AND activo = 1");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    header('Location: asistencias_horarios.php');
    exit;
}

// Días de la semana
$dias = [
    0 => 'Domingo',
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado'
];

$modo = $_GET['modo'] ?? 'dias'; // 'dias' o 'general'
$mensaje = '';
$error = '';

// Obtener horarios por día
$stmt = $pdo->prepare("
    SELECT * FROM empleados_horarios_dias 
    WHERE empleado_id = ? AND activo = 1
    ORDER BY dia_semana
");
$stmt->execute([$empleado_id]);
$horarios_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
$horarios_por_dia = [];
foreach ($horarios_dias as $h) {
    $horarios_por_dia[$h['dia_semana']] = $h;
}

// Obtener horario general (si existe)
$stmt = $pdo->prepare("
    SELECT * FROM empleados_horarios 
    WHERE empleado_id = ? AND activo = 1
");
$stmt->execute([$empleado_id]);
$horario_general = $stmt->fetch(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'guardar_general') {
        // Guardar horario general
        $hora_entrada = $_POST['hora_entrada'] ?? '';
        $hora_salida = $_POST['hora_salida'] ?? '';
        $tolerancia = intval($_POST['tolerancia_minutos'] ?? 10);
        
        try {
            if ($horario_general) {
                // Actualizar
                $stmt = $pdo->prepare("
                    UPDATE empleados_horarios 
                    SET hora_entrada = ?, hora_salida = ?, tolerancia_minutos = ?
                    WHERE id = ?
                ");
                $stmt->execute([$hora_entrada, $hora_salida, $tolerancia, $horario_general['id']]);
            } else {
                // Insertar
                $stmt = $pdo->prepare("
                    INSERT INTO empleados_horarios (empleado_id, hora_entrada, hora_salida, tolerancia_minutos, activo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$empleado_id, $hora_entrada, $hora_salida, $tolerancia]);
            }
            $mensaje = "Horario general guardado correctamente";
            
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM empleados_horarios WHERE empleado_id = ? AND activo = 1");
            $stmt->execute([$empleado_id]);
            $horario_general = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    } 
    elseif ($accion === 'guardar_dias') {
        // Guardar horarios por día
        try {
            // Primero, desactivar todos los horarios por día existentes
            $stmt = $pdo->prepare("UPDATE empleados_horarios_dias SET activo = 0 WHERE empleado_id = ?");
            $stmt->execute([$empleado_id]);
            
            // Guardar los nuevos
            $stmt_check = $pdo->prepare("SELECT id FROM empleados_horarios_dias WHERE empleado_id = ? AND dia_semana = ?");
            $stmt_insert = $pdo->prepare("
                INSERT INTO empleados_horarios_dias (empleado_id, dia_semana, hora_entrada, hora_salida, tolerancia_minutos, activo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            $stmt_update = $pdo->prepare("
                UPDATE empleados_horarios_dias 
                SET hora_entrada = ?, hora_salida = ?, tolerancia_minutos = ?, activo = 1
                WHERE id = ?
            ");
            
            for ($dia = 0; $dia <= 6; $dia++) {
                $entrada = $_POST["dia_{$dia}_entrada"] ?? '';
                $salida = $_POST["dia_{$dia}_salida"] ?? '';
                $tolerancia = intval($_POST["dia_{$dia}_tolerancia"] ?? 10);
                
                if (!empty($entrada) && !empty($salida)) {
                    // Buscar si existe
                    $stmt_check->execute([$empleado_id, $dia]);
                    $existe = $stmt_check->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existe) {
                        $stmt_update->execute([$entrada, $salida, $tolerancia, $existe['id']]);
                    } else {
                        $stmt_insert->execute([$empleado_id, $dia, $entrada, $salida, $tolerancia]);
                    }
                }
            }
            
            $mensaje = "Horarios por día guardados correctamente";
            
            // Recargar
            $stmt = $pdo->prepare("
                SELECT * FROM empleados_horarios_dias 
                WHERE empleado_id = ? AND activo = 1
                ORDER BY dia_semana
            ");
            $stmt->execute([$empleado_id]);
            $horarios_dias = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $horarios_por_dia = [];
            foreach ($horarios_dias as $h) {
                $horarios_por_dia[$h['dia_semana']] = $h;
            }
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>⏰ Horarios de <?= htmlspecialchars($empleado['nombre']) ?></h1>
            <p class="text-muted">Configura el horario diario o por día de la semana</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias_horarios.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            ✅ <?= htmlspecialchars($mensaje) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            ❌ <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs para Modo General vs Por Día -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $modo === 'general' ? 'active' : '' ?>" 
                    id="tab-general" data-bs-toggle="tab" data-bs-target="#content-general" type="button">
                📋 Horario General
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $modo === 'dias' ? 'active' : '' ?>" 
                    id="tab-dias" data-bs-toggle="tab" data-bs-target="#content-dias" type="button">
                📅 Por Día de la Semana
            </button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- Horario General -->
        <div class="tab-pane <?= $modo === 'general' ? 'active' : '' ?>" id="content-general">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted">Usa el mismo horario para todos los días de la semana</p>
                    
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="accion" value="guardar_general">
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="hora_entrada" class="form-label">Hora de Entrada *</label>
                                <input type="time" class="form-control" id="hora_entrada" name="hora_entrada" 
                                       value="<?= $horario_general['hora_entrada'] ?? '09:00' ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="hora_salida" class="form-label">Hora de Salida *</label>
                                <input type="time" class="form-control" id="hora_salida" name="hora_salida" 
                                       value="<?= $horario_general['hora_salida'] ?? '17:00' ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="tolerancia_minutos" class="form-label">Tolerancia (minutos)</label>
                                <input type="number" class="form-control" id="tolerancia_minutos" name="tolerancia_minutos" 
                                       value="<?= $horario_general['tolerancia_minutos'] ?? 10 ?>" min="0" max="120">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Guardar Horario General</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Horario por Día -->
        <div class="tab-pane <?= $modo === 'dias' ? 'active' : '' ?>" id="content-dias">
            <div class="card">
                <div class="card-body">
                    <p class="text-muted">Configura un horario diferente para cada día de la semana. Si dejas vacío, se usará el horario general.</p>
                    
                    <form method="POST" class="mt-3">
                        <input type="hidden" name="accion" value="guardar_dias">
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 20%;">Día</th>
                                        <th style="width: 25%;">Entrada</th>
                                        <th style="width: 25%;">Salida</th>
                                        <th style="width: 30%;">Tolerancia (min)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php for ($dia = 1; $dia <= 5; $dia++): // Lunes a Viernes ?>
                                        <tr>
                                            <td><strong><?= $dias[$dia] ?></strong></td>
                                            <td>
                                                <input type="time" class="form-control" name="dia_<?= $dia ?>_entrada" 
                                                       value="<?= $horarios_por_dia[$dia]['hora_entrada'] ?? '' ?>">
                                            </td>
                                            <td>
                                                <input type="time" class="form-control" name="dia_<?= $dia ?>_salida" 
                                                       value="<?= $horarios_por_dia[$dia]['hora_salida'] ?? '' ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="dia_<?= $dia ?>_tolerancia" 
                                                       value="<?= $horarios_por_dia[$dia]['tolerancia_minutos'] ?? 10 ?>" min="0" max="120">
                                            </td>
                                        </tr>
                                    <?php endfor; ?>
                                    
                                    <tr class="table-light">
                                        <td colspan="4"><strong>Fin de Semana</strong></td>
                                    </tr>
                                    
                                    <?php foreach ([6, 0] as $dia): ?>
                                        <tr>
                                            <td><strong><?= $dias[$dia] ?></strong></td>
                                            <td>
                                                <input type="time" class="form-control" name="dia_<?= $dia ?>_entrada" 
                                                       value="<?= $horarios_por_dia[$dia]['hora_entrada'] ?? '' ?>">
                                            </td>
                                            <td>
                                                <input type="time" class="form-control" name="dia_<?= $dia ?>_salida" 
                                                       value="<?= $horarios_por_dia[$dia]['hora_salida'] ?? '' ?>">
                                            </td>
                                            <td>
                                                <input type="number" class="form-control" name="dia_<?= $dia ?>_tolerancia" 
                                                       value="<?= $horarios_por_dia[$dia]['tolerancia_minutos'] ?? 10 ?>" min="0" max="120">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>💡 Nota:</strong> Si configuras horarios por día, tendrán prioridad sobre el horario general.
                        </div>
                        
                        <button type="submit" class="btn btn-primary">💾 Guardar Horarios por Día</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Información actual -->
    <div class="card mt-4">
        <div class="card-header bg-light">
            <h5 class="mb-0">📊 Horarios Configurados</h5>
        </div>
        <div class="card-body">
            <?php if ($horario_general): ?>
                <div class="mb-3">
                    <h6>General (usado como base):</h6>
                    <p class="mb-1">
                        <strong><?= date('H:i', strtotime($horario_general['hora_entrada'])) ?></strong> - 
                        <strong><?= date('H:i', strtotime($horario_general['hora_salida'])) ?></strong>
                        (Tolerancia: <?= $horario_general['tolerancia_minutos'] ?> min)
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($horarios_por_dia)): ?>
                <div>
                    <h6>Por Día de la Semana:</h6>
                    <ul class="list-unstyled">
                        <?php foreach ($horarios_por_dia as $dia => $h): ?>
                            <li>
                                <strong><?= $dias[$dia] ?>:</strong>
                                <?= date('H:i', strtotime($h['hora_entrada'])) ?> - 
                                <?= date('H:i', strtotime($h['hora_salida'])) ?>
                                (Tolerancia: <?= $h['tolerancia_minutos'] ?> min)
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php else: ?>
                <p class="text-muted">Sin horarios por día configurados</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
