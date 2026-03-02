<?php
/**
 * API REST simple para crear (y eventualmente actualizar) gastos.
 * - Método POST crea un nuevo gasto
 * - Acepta JSON en el body con los mismos campos que el formulario web
 * - Se puede autenticar mediante sesión (usuario con permiso "gastos")
 *   o enviando un header X-API-KEY con el valor configurado en el sistema.
 *
 * Formato de entrada (ejemplo):
 * {
 *   "fecha": "2026-03-02",
 *   "tipo_gasto_id": 1,
 *   "estado_gasto_id": 2,
 *   "descripcion": "Compra de materiales",
 *   "monto": 1500.75,
 *   "empleado_id": 5,            // opcional
 *   "observaciones": "Nota interna",
 *   "archivo": {                // opcional
 *       "filename": "factura.pdf",
 *       "content": "<base64>"
 *   }
 * }
 *
 * Respuesta JSON:
 *   { "success": true, "gasto_id": 123 }
 *   { "success": false, "message": "Error descriptivo" }
 */

require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json');

// autenticar
session_start();
// preferimos la constante/config de config.php si existe
$apiKey = $robot_api_key ?? (getenv('GASTOS_API_KEY') ?: 'cambia_esta_clave');
$provided = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hasSessionAccess = isset($_SESSION['user']) && isset($can_access) && $can_access('gastos');

if (!$hasSessionAccess && $provided !== $apiKey) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit;
}

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        throw new Exception('JSON inválido');
    }

    // campos requeridos
    $fecha = $data['fecha'] ?? '';
    $tipo_id = intval($data['tipo_gasto_id'] ?? 0);
    $estado_id = intval($data['estado_gasto_id'] ?? 0);
    $descripcion = trim($data['descripcion'] ?? '');
    $monto = floatval($data['monto'] ?? 0);
    $empleado_id = (!empty($data['empleado_id']) ? intval($data['empleado_id']) : null);
    $observaciones = trim($data['observaciones'] ?? '');

    $errores = [];
    if (empty($fecha)) $errores[] = 'La fecha es obligatoria';
    if ($tipo_id <= 0) $errores[] = 'Debe indicar un tipo de gasto';
    if ($estado_id <= 0) $errores[] = 'Debe indicar un estado';
    if ($descripcion === '') $errores[] = 'La descripción es obligatoria';
    if ($monto <= 0) $errores[] = 'El monto debe ser mayor que 0';

    $archivoNombre = null;
    if (!empty($data['archivo']) && is_array($data['archivo'])) {
        $file = $data['archivo'];
        if (!isset($file['filename'], $file['content'])) {
            $errores[] = 'Formato de archivo incorrecto';
        } else {
            $allowed = ['pdf','jpg','jpeg','png','xlsx','xls','docx','doc'];
            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed)) {
                $errores[] = 'Extensión de archivo no permitida';
            } else {
                $bin = base64_decode($file['content'], true);
                if ($bin === false) {
                    $errores[] = 'Contenido de archivo inválido';
                } elseif (strlen($bin) > 5242880) {
                    $errores[] = 'Archivo demasiado grande (máx 5MB)';
                } else {
                    $upload_dir = __DIR__ . '/uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
                    $archivoNombre = 'gasto_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    file_put_contents($upload_dir . $archivoNombre, $bin);
                }
            }
        }
    }

    if (!empty($errores)) {
        throw new Exception(implode(', ', $errores));
    }

    // generar número de gasto
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM gastos");
    $res = $stmt->fetch();
    $numero = "G-" . str_pad($res['total'] + 1, 6, '0', STR_PAD_LEFT);

    $usuario_id = $_SESSION['user']['id'] ?? null;

    $stmt = $pdo->prepare("INSERT INTO gastos
        (numero_gasto, fecha, tipo_gasto_id, empleado_id, estado_gasto_id, descripcion,
         monto, observaciones, archivo, usuario_registra)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$numero, $fecha, $tipo_id, $empleado_id, $estado_id,
                    $descripcion, $monto, $observaciones, $archivoNombre, $usuario_id]);
    $gasto_id = $pdo->lastInsertId();

    // historial
    $stmt = $pdo->prepare("INSERT INTO historial_gastos (gasto_id, estado_nuevo_id, usuario_id, observaciones)
        VALUES (?, ?, ?, ?)");
    $stmt->execute([$gasto_id, $estado_id, $usuario_id, 'Creado via API']);

    // flujo de caja si pagado
    $stmt_pag = $pdo->prepare("SELECT id FROM estados_gastos WHERE LOWER(nombre) = 'pagado' LIMIT 1");
    $stmt_pag->execute();
    $pagadoId = $stmt_pag->fetchColumn();
    if ($pagadoId && (int)$estado_id === (int)$pagadoId) {
        $stmt_fc = $pdo->prepare("INSERT INTO flujo_caja
            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
            VALUES (?, 'egreso', 'Gasto', ?, ?, ?, ?, ?, ?)");
        $stmt_fc->execute([
            $fecha, $descripcion, $monto, $numero, $gasto_id, $usuario_id,
            $observaciones ?: 'Registrado desde API'
        ]);
    }

    echo json_encode(['success'=>true, 'gasto_id'=>$gasto_id]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
