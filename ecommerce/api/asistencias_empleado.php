<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function table_exists(PDO $pdo, string $table): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        return false;
    }
    $quoted = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE {$quoted}");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table) || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
        return false;
    }
    $quotedColumn = $pdo->quote($column);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE {$quotedColumn}");
    return $stmt ? (bool)$stmt->fetchColumn() : false;
}

function first_existing_column(PDO $pdo, string $table, array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if (column_exists($pdo, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

function qi(string $identifier): string {
    return '`' . str_replace('`', '``', $identifier) . '`';
}

session_start();
$apiKey = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']['id']) || !empty($_SESSION['user_id']) || !empty($_SESSION['usuario_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$nombreEmpleado = trim((string)($_GET['empleado_nombre'] ?? ($_GET['nombre'] ?? '')));
$fechaDesde = trim((string)($_GET['fecha_desde'] ?? ''));
$fechaHasta = trim((string)($_GET['fecha_hasta'] ?? ''));
$limite = (int)($_GET['limite'] ?? 100);
if ($limite < 1) {
    $limite = 1;
}
if ($limite > 500) {
    $limite = 500;
}

if ($nombreEmpleado === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Debe indicar empleado_nombre']);
    exit;
}

if ($fechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'fecha_desde inválida. Use YYYY-MM-DD']);
    exit;
}

if ($fechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'fecha_hasta inválida. Use YYYY-MM-DD']);
    exit;
}

try {
    if (!table_exists($pdo, 'asistencias') || !table_exists($pdo, 'empleados')) {
        throw new Exception('Tablas requeridas no disponibles (asistencias/empleados)');
    }

    $colUsuarioAsist = first_existing_column($pdo, 'asistencias', ['creado_por', 'usuario_id']);
    $colEmpleadoAsist = first_existing_column($pdo, 'asistencias', ['empleado_id', 'id_empleado', 'empleado']);
    $colFechaAsist = first_existing_column($pdo, 'asistencias', ['fecha', 'fecha_asistencia', 'dia']);
    $colFechaCreacionAsist = first_existing_column($pdo, 'asistencias', ['fecha_creacion', 'created_at']);
    $colHoraEntradaAsist = first_existing_column($pdo, 'asistencias', ['hora_entrada', 'hora_ingreso', 'entrada']);
    $colHoraSalidaAsist = first_existing_column($pdo, 'asistencias', ['hora_salida', 'hora_egreso', 'salida']);
    $colEstadoAsist = first_existing_column($pdo, 'asistencias', ['estado']);

    if (!$colEmpleadoAsist) {
        throw new Exception('La tabla asistencias no tiene columna de empleado');
    }

    $selectFecha = $colFechaAsist
        ? 'a.' . qi($colFechaAsist) . ' AS fecha'
        : ($colFechaCreacionAsist
            ? 'DATE(a.' . qi($colFechaCreacionAsist) . ') AS fecha'
            : 'NULL AS fecha');

    $selectHoraEntrada = $colHoraEntradaAsist ? 'a.' . qi($colHoraEntradaAsist) . ' AS hora_entrada' : 'NULL AS hora_entrada';
    $selectHoraSalida = $colHoraSalidaAsist ? 'a.' . qi($colHoraSalidaAsist) . ' AS hora_salida' : 'NULL AS hora_salida';
    $selectEstado = $colEstadoAsist ? 'a.' . qi($colEstadoAsist) . ' AS estado' : 'NULL AS estado';
    $selectFechaRegistro = $colFechaCreacionAsist ? 'a.' . qi($colFechaCreacionAsist) . ' AS fecha_registro' : 'NULL AS fecha_registro';

    $joinUsuario = '';
    $selectUsuario = 'NULL AS usuario_id, NULL AS usuario, NULL AS usuario_nombre';
    if ($colUsuarioAsist && table_exists($pdo, 'usuarios')) {
        $joinUsuario = ' LEFT JOIN usuarios u ON u.id = a.' . qi($colUsuarioAsist);
        $selectUsuario = 'u.id AS usuario_id, u.usuario AS usuario, u.nombre AS usuario_nombre';
    }

    $sql = "SELECT
        {$selectUsuario},
        e.id AS empleado_id,
        e.nombre AS empleado_nombre,
        {$selectFecha},
        {$selectHoraEntrada},
        {$selectHoraSalida},
        {$selectEstado},
        {$selectFechaRegistro}
    FROM asistencias a
    JOIN empleados e ON e.id = a." . qi($colEmpleadoAsist) . "
    {$joinUsuario}
    WHERE e.nombre LIKE ?";

    $params = ["%{$nombreEmpleado}%"];

    if ($fechaDesde !== '') {
        if ($colFechaAsist) {
            $sql .= ' AND a.' . qi($colFechaAsist) . ' >= ?';
        } elseif ($colFechaCreacionAsist) {
            $sql .= ' AND DATE(a.' . qi($colFechaCreacionAsist) . ') >= ?';
        }
        if ($colFechaAsist || $colFechaCreacionAsist) {
            $params[] = $fechaDesde;
        }
    }

    if ($fechaHasta !== '') {
        if ($colFechaAsist) {
            $sql .= ' AND a.' . qi($colFechaAsist) . ' <= ?';
        } elseif ($colFechaCreacionAsist) {
            $sql .= ' AND DATE(a.' . qi($colFechaCreacionAsist) . ') <= ?';
        }
        if ($colFechaAsist || $colFechaCreacionAsist) {
            $params[] = $fechaHasta;
        }
    }

    if ($colFechaAsist) {
        $sql .= ' ORDER BY a.' . qi($colFechaAsist) . ' DESC';
    } elseif ($colFechaCreacionAsist) {
        $sql .= ' ORDER BY a.' . qi($colFechaCreacionAsist) . ' DESC';
    }

    if (column_exists($pdo, 'asistencias', 'id')) {
        $sql .= ', a.`id` DESC';
    }

    $sql .= ' LIMIT ' . (int)$limite;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
        'success' => true,
        'empleado_nombre' => $nombreEmpleado,
        'total' => count($rows),
        'registros' => $rows
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al consultar asistencias por empleado',
        'error' => $e->getMessage()
    ]);
}
