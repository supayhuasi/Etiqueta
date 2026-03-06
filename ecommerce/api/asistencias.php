<?php
/**
 * API para consultar asistencias de empleados.
 *
 * Método: GET
 *
 * Autenticación:
 *   - Sesión activa en el sistema, o
 *   - Header X-API-KEY con el valor configurado en config.php ($robot_api_key)
 *
 * Parámetros:
 *   - nombre   (obligatorio): texto a buscar en el nombre del empleado
 *   - fecha    (opcional):    fecha específica en formato YYYY-MM-DD
 *   - mes      (opcional):    mes en formato YYYY-MM (ignorado si se especifica fecha)
 *
 * Respuesta de éxito:
 * {
 *   "success": true,
 *   "nombre": "Juan",
 *   "total": 1,
 *   "asistencias": [
 *     {
 *       "empleado_id": 3,
 *       "empleado_nombre": "Juan Pérez",
 *       "fecha": "2026-03-06",
 *       "hora_entrada": "08:05:00",
 *       "hora_salida": "17:10:00",
 *       "estado": "presente",
 *       "observaciones": ""
 *     }
 *   ]
 * }
 *
 * Respuesta de error:
 * { "success": false, "message": "Detalle del error" }
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

session_start();
$apiKey   = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSession = !empty($_SESSION['user']) || !empty($_SESSION['user_id']) || !empty($_SESSION['usuario_id']);

if (!$hasSession && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

$nombre = trim($_GET['nombre'] ?? '');
$fecha  = trim($_GET['fecha'] ?? '');
$mes    = trim($_GET['mes'] ?? '');

if ($nombre === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parámetro nombre requerido']);
    exit;
}

try {
    $sql = "
        SELECT
            a.empleado_id,
            e.nombre  AS empleado_nombre,
            a.fecha,
            a.hora_entrada,
            a.hora_salida,
            a.estado,
            a.observaciones
        FROM asistencias a
        JOIN empleados e ON a.empleado_id = e.id
        WHERE e.nombre LIKE ?
    ";
    $params = ["%$nombre%"];

    if ($fecha !== '') {
        $sql   .= " AND a.fecha = ?";
        $params[] = $fecha;
    } elseif ($mes !== '') {
        $sql   .= " AND DATE_FORMAT(a.fecha, '%Y-%m') = ?";
        $params[] = $mes;
    }

    $sql .= " ORDER BY e.nombre ASC, a.fecha DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success'     => true,
        'nombre'      => $nombre,
        'total'       => count($rows),
        'asistencias' => $rows,
    ]);
} catch (Exception $e) {
    error_log('asistencias_api error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error interno']);
}
