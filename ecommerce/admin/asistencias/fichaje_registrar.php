<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada. Volvé a iniciar sesión.']);
    exit;
}

require dirname(__DIR__, 3) . '/config.php';

$col = $pdo->query("SHOW COLUMNS FROM empleados LIKE 'fichaje_rapido'");
if ($col->rowCount() === 0) {
    $pdo->exec("ALTER TABLE empleados ADD COLUMN fichaje_rapido TINYINT(1) NOT NULL DEFAULT 1 AFTER activo");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$empleado_id = (int)($_POST['empleado_id'] ?? 0);
if ($empleado_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Empleado inválido']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nombre FROM empleados WHERE id = ? AND activo = 1 AND fichaje_rapido = 1");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empleado) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Empleado no encontrado o inactivo']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, hora_salida FROM asistencias WHERE empleado_id = ? AND fecha = CURDATE() LIMIT 1");
    $stmt->execute([$empleado_id]);
    $existente = $stmt->fetch(PDO::FETCH_ASSOC);

    $hora_actual = date('H:i:s');

    if ($existente && empty($existente['hora_salida'])) {
        $stmt = $pdo->prepare("UPDATE asistencias SET hora_salida = ? WHERE id = ?");
        $stmt->execute([$hora_actual, $existente['id']]);

        echo json_encode([
            'success' => true,
            'tipo' => 'salida',
            'empleado' => $empleado,
            'hora' => date('H:i', strtotime($hora_actual)),
            'message' => $empleado['nombre'] . ': salida registrada correctamente',
        ]);
        exit;
    }

    if ($existente && !empty($existente['hora_salida'])) {
        echo json_encode([
            'success' => false,
            'message' => $empleado['nombre'] . ' ya completó su jornada hoy',
        ]);
        exit;
    }

    // Sin registro hoy todavía: es una entrada. Calcular si llega tarde según su horario.
    $stmt = $pdo->prepare("
        SELECT
            COALESCE(ehd.hora_entrada, eh.hora_entrada) AS hora_entrada,
            COALESCE(ehd.tolerancia_minutos, eh.tolerancia_minutos, 10) AS tolerancia_minutos
        FROM empleados e
        LEFT JOIN empleados_horarios eh ON e.id = eh.empleado_id AND eh.activo = 1
        LEFT JOIN empleados_horarios_dias ehd ON e.id = ehd.empleado_id
            AND ehd.dia_semana = DAYOFWEEK(CURDATE()) - 1
            AND ehd.activo = 1
        WHERE e.id = ?
    ");
    $stmt->execute([$empleado_id]);
    $horario = $stmt->fetch(PDO::FETCH_ASSOC);

    $estado = 'presente';
    if ($horario && !empty($horario['hora_entrada'])) {
        $esperada = strtotime($horario['hora_entrada']);
        $real = strtotime($hora_actual);
        $tolerancia = ((int)($horario['tolerancia_minutos'] ?? 10)) * 60;
        if ($real > ($esperada + $tolerancia)) {
            $estado = 'tarde';
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO asistencias (empleado_id, fecha, hora_entrada, estado, creado_por, fecha_creacion)
        VALUES (?, CURDATE(), ?, ?, ?, NOW())
    ");
    $stmt->execute([$empleado_id, $hora_actual, $estado, $_SESSION['user']['id'] ?? null]);

    echo json_encode([
        'success' => true,
        'tipo' => 'entrada',
        'empleado' => $empleado,
        'hora' => date('H:i', strtotime($hora_actual)),
        'estado' => $estado,
        'message' => $empleado['nombre'] . ': ' . ($estado === 'presente' ? 'entrada registrada correctamente' : 'entrada tardía registrada'),
    ]);
} catch (Throwable $e) {
    error_log('fichaje_registrar error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar la asistencia']);
}
