<?php
require '../includes/header.php';

// Obtener horarios de todos los empleados
$stmt = $pdo->query("
    SELECT 
        e.id,
        e.nombre,
        h.id as horario_id,
        h.hora_entrada,
        h.hora_salida,
        h.tolerancia_minutos,
        h.activo
    FROM empleados e
    LEFT JOIN empleados_horarios h ON e.id = h.empleado_id AND h.activo = 1
    WHERE e.activo = 1
    ORDER BY e.nombre
");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>⏰ Gestión de Horarios</h1>
            <p class="text-muted">Configura los horarios de entrada y salida para cada empleado</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="asistencias.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Empleado</th>
                            <th>Hora Entrada</th>
                            <th>Hora Salida</th>
                            <th>Tolerancia (min)</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($emp['nombre']) ?></strong></td>
                                <td>
                                    <?php if ($emp['horario_id']): ?>
                                        <?= date('H:i', strtotime($emp['hora_entrada'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin configurar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['horario_id']): ?>
                                        <?= date('H:i', strtotime($emp['hora_salida'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">Sin configurar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['horario_id']): ?>
                                        <?= $emp['tolerancia_minutos'] ?> min
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['horario_id'] && $emp['activo']): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Sin horario</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($emp['horario_id']): ?>
                                        <a href="asistencias_horarios_editar_v2.php?empleado_id=<?= $emp['id'] ?>" class="btn btn-sm btn-primary">✏️ Editar</a>
                                    <?php else: ?>
                                        <a href="asistencias_horarios_editar_v2.php?empleado_id=<?= $emp['id'] ?>" class="btn btn-sm btn-success">➕ Asignar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
