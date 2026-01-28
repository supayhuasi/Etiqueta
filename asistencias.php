<?php
require 'config.php';
require 'includes/header.php';

// Obtener filtros
$mes_filtro = $_GET['mes'] ?? date('Y-m');
$empleado_filtro = $_GET['empleado_id'] ?? '';

// Obtener lista de empleados para filtro
$stmt = $pdo->query("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Construir query de asistencias con filtros
$where = ["1=1"];
$params = [];

if ($mes_filtro) {
    $where[] = "DATE_FORMAT(a.fecha, '%Y-%m') = ?";
    $params[] = $mes_filtro;
}

if ($empleado_filtro) {
    $where[] = "a.empleado_id = ?";
    $params[] = $empleado_filtro;
}

$sql = "
    SELECT 
        a.*,
        e.nombre as empleado_nombre,
        COALESCE(hd.hora_entrada, h.hora_entrada) as horario_entrada,
        COALESCE(hd.hora_salida, h.hora_salida) as horario_salida,
        COALESCE(hd.tolerancia_minutos, h.tolerancia_minutos) as tolerancia_minutos,
        u.usuario as creado_por_usuario
    FROM asistencias a
    JOIN empleados e ON a.empleado_id = e.id
    LEFT JOIN empleados_horarios h ON a.empleado_id = h.empleado_id AND h.activo = 1
    LEFT JOIN empleados_horarios_dias hd ON a.empleado_id = hd.empleado_id 
        AND hd.dia_semana = DAYOFWEEK(a.fecha) - 1 
        AND hd.activo = 1
    LEFT JOIN usuarios u ON a.creado_por = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY a.fecha DESC, e.nombre ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular estadísticas del mes
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'presente' THEN 1 ELSE 0 END) as presentes,
        SUM(CASE WHEN estado = 'tarde' THEN 1 ELSE 0 END) as tardes,
        SUM(CASE WHEN estado = 'ausente' THEN 1 ELSE 0 END) as ausentes,
        SUM(CASE WHEN estado = 'justificado' THEN 1 ELSE 0 END) as justificados
    FROM asistencias a
    WHERE DATE_FORMAT(a.fecha, '%Y-%m') = ?
    " . ($empleado_filtro ? "AND a.empleado_id = ?" : "")
);
$params_stats = [$mes_filtro];
if ($empleado_filtro) {
    $params_stats[] = $empleado_filtro;
}
$stmt->execute($params_stats);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>📋 Control de Asistencias</h1>
            <p class="text-muted">Gestiona las asistencias de los empleados</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="asistencias_crear.php" class="btn btn-primary">➕ Cargar Asistencia</a>
            <a href="asistencias_horarios.php" class="btn btn-info">⏰ Gestionar Horarios</a>
            <a href="asistencias_reporte.php?mes=<?= $mes_filtro ?>&empleado_id=<?= $empleado_filtro ?>" class="btn btn-success" target="_blank">📊 Generar Reporte</a>
        </div>
    </div>

    <!-- Estadísticas -->
    <div class="row mb-4">
        <div class="col-md-2">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h6>Total</h6>
                    <h3><?= $stats['total'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h6>Presentes</h6>
                    <h3><?= $stats['presentes'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h6>Tardanzas</h6>
                    <h3><?= $stats['tardes'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-danger text-white">
                <div class="card-body text-center">
                    <h6>Ausentes</h6>
                    <h3><?= $stats['ausentes'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <h6>Justificados</h6>
                    <h3><?= $stats['justificados'] ?? 0 ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Mes</label>
                    <input type="month" name="mes" class="form-control" value="<?= $mes_filtro ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Empleado</label>
                    <select name="empleado_id" class="form-select">
                        <option value="">Todos los empleados</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?= $emp['id'] ?>" <?= $empleado_filtro == $emp['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">🔍 Filtrar</button>
                    <a href="asistencias.php" class="btn btn-secondary">🔄 Limpiar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Asistencias -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($asistencias)): ?>
                <div class="alert alert-info">
                    No hay asistencias registradas con los filtros seleccionados.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Fecha</th>
                                <th>Empleado</th>
                                <th>Horario</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Estado</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($asistencias as $asistencia): ?>
                                <?php
                                // Calcular si llegó tarde
                                $llego_tarde = false;
                                if ($asistencia['horario_entrada'] && $asistencia['hora_entrada']) {
                                    $horario = new DateTime($asistencia['horario_entrada']);
                                    $entrada = new DateTime($asistencia['hora_entrada']);
                                    $tolerancia = $asistencia['tolerancia_minutos'] ?? 10;
                                    $horario->modify("+{$tolerancia} minutes");
                                    $llego_tarde = $entrada > $horario;
                                }
                                ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($asistencia['fecha'])) ?></td>
                                    <td><strong><?= htmlspecialchars($asistencia['empleado_nombre']) ?></strong></td>
                                    <td>
                                        <?php if ($asistencia['horario_entrada']): ?>
                                            <small class="text-muted">
                                                <?= date('H:i', strtotime($asistencia['horario_entrada'])) ?> - 
                                                <?= date('H:i', strtotime($asistencia['horario_salida'])) ?>
                                            </small>
                                        <?php else: ?>
                                            <small class="text-muted">Sin horario</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $asistencia['hora_entrada'] ? date('H:i', strtotime($asistencia['hora_entrada'])) : '-' ?>
                                        <?php if ($llego_tarde): ?>
                                            <span class="badge bg-warning text-dark ms-1">Tarde</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $asistencia['hora_salida'] ? date('H:i', strtotime($asistencia['hora_salida'])) : '-' ?></td>
                                    <td>
                                        <?php
                                        $badges = [
                                            'presente' => 'success',
                                            'tarde' => 'warning',
                                            'ausente' => 'danger',
                                            'justificado' => 'info'
                                        ];
                                        $badge = $badges[$asistencia['estado']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $badge ?>"><?= ucfirst($asistencia['estado']) ?></span>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars(substr($asistencia['observaciones'] ?? '', 0, 50)) ?></small>
                                    </td>
                                    <td>
                                        <a href="asistencias_editar.php?id=<?= $asistencia['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                                        <a href="asistencias_eliminar.php?id=<?= $asistencia['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar esta asistencia?')">🗑️</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
