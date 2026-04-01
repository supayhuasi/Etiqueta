<?php
// Habilitar errores para debug
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Incluir configuración para obtener $pdo
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require $base_path . '/config.php';

// Verificar que $pdo existe
if (!isset($pdo)) {
    die('Error: No hay conexión a la base de datos');
}

$mes_filtro = $_GET['mes'] ?? date('Y-m');
$empleado_filtro = $_GET['empleado_id'] ?? '';

// Construir query
$where = ["DATE_FORMAT(a.fecha, '%Y-%m') = ?"];
$params = [$mes_filtro];

if ($empleado_filtro) {
    $where[] = "a.empleado_id = ?";
    $params[] = $empleado_filtro;
}

try {
    $sql = "
        SELECT 
            e.nombre as empleado,
            a.fecha,
            a.hora_entrada,
            a.hora_salida,
            a.estado,
            a.observaciones,
            COALESCE(hd.hora_entrada, h.hora_entrada) as horario_entrada,
            COALESCE(hd.hora_salida, h.hora_salida) as horario_salida
        FROM asistencias a
        JOIN empleados e ON a.empleado_id = e.id
        LEFT JOIN empleados_horarios h ON a.empleado_id = h.empleado_id AND h.activo = 1
        LEFT JOIN empleados_horarios_dias hd ON a.empleado_id = hd.empleado_id 
            AND hd.dia_semana = DAYOFWEEK(a.fecha) - 1 
            AND hd.activo = 1
        WHERE " . implode(" AND ", $where) . "
        ORDER BY e.nombre, a.fecha
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $asistencias = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Error en consulta de asistencias: ' . $e->getMessage());
}

// Calcular totales
$totales = [
    'total' => count($asistencias),
    'presentes' => 0,
    'tardes' => 0,
    'ausentes' => 0,
    'justificados' => 0,
    'minutos_extra' => 0
];

foreach ($asistencias as &$a) {
    $totales[$a['estado'] . 's']++;

    $minutos_extra = 0;
    if (!empty($a['fecha']) && !empty($a['horario_salida']) && !empty($a['hora_salida'])) {
        $dt_programada = strtotime($a['fecha'] . ' ' . $a['horario_salida']);
        $dt_real = strtotime($a['fecha'] . ' ' . $a['hora_salida']);
        if ($dt_programada && $dt_real && $dt_real > $dt_programada) {
            $minutos_extra = (int) floor(($dt_real - $dt_programada) / 60);
        }
    }

    $a['minutos_extra'] = $minutos_extra;
    $totales['minutos_extra'] += $minutos_extra;
}
unset($a);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Asistencias - <?= date('m/Y', strtotime($mes_filtro . '-01')) ?></title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none; }
            body { font-size: 12px; }
        }
        .header-reporte {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #000;
            padding-bottom: 20px;
        }
        .stats-box {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
    </style>
</head>
<body>

<div class="container mt-4">
    <div class="header-reporte">
        <h1>📊 Reporte de Asistencias</h1>
        <h3>Período: <?= date('F Y', strtotime($mes_filtro . '-01')) ?></h3>
        <?php if ($empleado_filtro): ?>
            <?php
            try {
                $stmt = $pdo->prepare("SELECT nombre FROM empleados WHERE id = ?");
                $stmt->execute([$empleado_filtro]);
                $nombre_empleado = $stmt->fetchColumn();
            } catch (Exception $e) {
                $nombre_empleado = 'Error al cargar';
            }
            ?>
            <h4>Empleado: <?= htmlspecialchars($nombre_empleado) ?></h4>
        <?php endif; ?>
        <p>Generado: <?= date('d/m/Y H:i') ?></p>
    </div>

    <!-- Resumen Estadístico -->
    <div class="text-center mb-4">
        <h4>Resumen</h4>
        <div class="stats-box bg-primary text-white">
            <strong>Total:</strong> <?= $totales['total'] ?>
        </div>
        <div class="stats-box bg-success text-white">
            <strong>Presentes:</strong> <?= $totales['presentes'] ?>
        </div>
        <div class="stats-box bg-warning">
            <strong>Tardanzas:</strong> <?= $totales['tardes'] ?>
        </div>
        <div class="stats-box bg-danger text-white">
            <strong>Ausentes:</strong> <?= $totales['ausentes'] ?>
        </div>
        <div class="stats-box bg-info text-white">
            <strong>Justificados:</strong> <?= $totales['justificados'] ?>
        </div>
        <div class="stats-box bg-dark text-white">
            <strong>Min. Extra:</strong> <?= (int)$totales['minutos_extra'] ?>
        </div>
    </div>

    <!-- Tabla de Asistencias -->
    <table class="table table-bordered table-sm">
        <thead class="table-dark">
            <tr>
                <th>Empleado</th>
                <th>Fecha</th>
                <th>Horario</th>
                <th>Entrada</th>
                <th>Salida</th>
                <th>Min. Extra</th>
                <th>Estado</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($asistencias)): ?>
                <tr>
                    <td colspan="8" class="text-center">No hay registros</td>
                </tr>
            <?php else: ?>
                <?php foreach ($asistencias as $a): ?>
                    <tr>
                        <td><?= htmlspecialchars($a['empleado']) ?></td>
                        <td><?= date('d/m/Y', strtotime($a['fecha'])) ?></td>
                        <td>
                            <?php if ($a['horario_entrada']): ?>
                        <td>
                            <?php if (($a['minutos_extra'] ?? 0) > 0): ?>
                                <strong>+<?= (int)$a['minutos_extra'] ?> min</strong>
                            <?php else: ?>
                                <span class="text-muted">0</span>
                            <?php endif; ?>
                        </td>
                                <?= date('H:i', strtotime($a['horario_entrada'])) ?> - 
                                <?= date('H:i', strtotime($a['horario_salida'])) ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= $a['hora_entrada'] ? date('H:i', strtotime($a['hora_entrada'])) : '-' ?></td>
                        <td><?= $a['hora_salida'] ? date('H:i', strtotime($a['hora_salida'])) : '-' ?></td>
                        <td>
                            <?php
                            $badges = [
                                'presente' => 'success',
                                'tarde' => 'warning',
                                'ausente' => 'danger',
                                'justificado' => 'info'
                            ];
                            $badge = $badges[$a['estado']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?= $badge ?>"><?= ucfirst($a['estado']) ?></span>
                        </td>
                        <td><small><?= htmlspecialchars($a['observaciones']) ?></small></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- Botones de acción -->
    <div class="text-center mt-4 no-print">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimir</button>
        <a href="asistencias.php" class="btn btn-secondary">← Volver</a>
    </div>
</div>

</body>
</html>
