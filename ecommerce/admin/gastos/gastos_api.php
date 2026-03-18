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
 *   "usuario_id": 1,             // opcional: ID del usuario que registra (solo al usar X-API-KEY)
 *                                //           si se omite, se usa el usuario admin por defecto
 *   "archivo": {                // opcional
 *       "filename": "factura.pdf",
 *       "content": "<base64>"
 *   }
 * }
 *
 * Autenticación via API key (header X-API-KEY):
 *   - El valor de la clave está en config.php → $robot_api_key (por defecto: '3020450830204508')
 *   - También puede configurarse con la variable de entorno GASTOS_API_KEY
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
        // Compatibilidad: permitir payload x-www-form-urlencoded o multipart/form-data
        $data = $_POST;
    }
    if (!is_array($data) || empty($data)) {
        throw new Exception('Payload inválido: enviar JSON o datos POST');
    }

    $pickValue = function(array $source, array $keys, $default = null) {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && $source[$key] !== null && $source[$key] !== '') {
                return $source[$key];
            }
        }
        return $default;
    };

    $resolveIdByName = function(PDO $pdo, string $table, $rawValue): int {
        if ($rawValue === null || $rawValue === '') {
            return 0;
        }

        if (is_numeric($rawValue)) {
            return (int)$rawValue;
        }

        $name = trim((string)$rawValue);
        if ($name === '') {
            return 0;
        }

        $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE LOWER(nombre) = LOWER(?) LIMIT 1");
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : 0;
    };

    // campos requeridos
    $fecha = (string)($pickValue($data, ['fecha', 'date'], '') ?? '');
    $tipoRaw = $pickValue($data, ['tipo_gasto_id', 'tipo_id', 'tipo_gasto', 'tipo', 'expense_type_id']);
    $estadoRaw = $pickValue($data, ['estado_gasto_id', 'estado_id', 'estado_gasto', 'estado', 'status_id']);
    $tipo_id = $resolveIdByName($pdo, 'tipos_gastos', $tipoRaw);
    $estado_id = $resolveIdByName($pdo, 'estados_gastos', $estadoRaw);
    $descripcion = trim((string)($pickValue($data, ['descripcion', 'description', 'detalle'], '') ?? ''));
    $monto = floatval($pickValue($data, ['monto', 'amount'], 0));
    $empleadoRaw = $pickValue($data, ['empleado_id', 'beneficiario_id', 'employee_id']);
    $empleado_id = ($empleadoRaw !== null && $empleadoRaw !== '' ? intval($empleadoRaw) : null);
    $observaciones = trim((string)($pickValue($data, ['observaciones', 'observacion', 'notes'], '') ?? ''));

    $errores = [];
    if (empty($fecha)) $errores[] = 'La fecha es obligatoria';
    if ($tipo_id <= 0) $errores[] = 'Debe indicar un tipo de gasto válido';
    if ($estado_id <= 0) $errores[] = 'Debe indicar un estado válido';
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

    $usuario_id = $_SESSION['user']['id'] ?? null;
    // Cuando se autentica vía API key (sin sesión), resolver el usuario que registra.
    // Estrategia 1: primer usuario con rol 'admin' activo.
    // Estrategia 2 (fallback): cualquier usuario activo (por si la tabla roles aún no fue creada).
    if ($usuario_id === null) {
        try {
            $stmt_admin = $pdo->query("SELECT u.id FROM usuarios u JOIN roles r ON u.rol_id = r.id WHERE r.nombre = 'admin' AND u.activo = 1 ORDER BY u.id LIMIT 1");
            $row = $stmt_admin->fetch();
            $usuario_id = isset($row['id']) ? (int)$row['id'] : null;
        } catch (PDOException $e) {
            error_log('gastos_api: admin lookup failed: ' . $e->getMessage());
            $usuario_id = null; // la tabla roles puede no existir aún
        }
        if ($usuario_id === null) {
            // Fallback: cualquier usuario activo
            try {
                $stmt_any = $pdo->query("SELECT id FROM usuarios WHERE activo = 1 ORDER BY id LIMIT 1");
                $row = $stmt_any->fetch();
                $usuario_id = isset($row['id']) ? (int)$row['id'] : null;
            } catch (PDOException $e) {
                error_log('gastos_api: fallback user lookup failed: ' . $e->getMessage());
                $usuario_id = null;
            }
        }
        if ($usuario_id === null) {
            throw new Exception('No hay ningún usuario activo para registrar el gasto via API');
        }
    }
    // Resolver usuario: sesión > campo del body > admin por defecto
    if (isset($_SESSION['user']['id'])) {
        $usuario_id = $_SESSION['user']['id'];
    } elseif (!empty($data['usuario_id'])) {
        $usuario_id = intval($data['usuario_id']);
    } else {
        $stmt_admin = $pdo->query(
            "SELECT u.id FROM usuarios u
             JOIN roles r ON u.rol_id = r.id
             WHERE r.nombre = 'admin' AND u.activo = 1
             ORDER BY u.id LIMIT 1"
        );
        $admin_row = $stmt_admin->fetch();
        if (!$admin_row) {
            throw new Exception('No se encontró un usuario admin activo para registrar el gasto');
        }
        $usuario_id = $admin_row['id'];
    }

    $lockKey = 'gastos_numero_gasto_lock';
    $stmtLock = $pdo->prepare("SELECT GET_LOCK(?, 10)");
    $stmtLock->execute([$lockKey]);
    $gotLock = (int)$stmtLock->fetchColumn() === 1;
    if (!$gotLock) {
        throw new Exception('No se pudo obtener bloqueo para numerar el gasto. Intente nuevamente.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(numero_gasto, 3) AS UNSIGNED)) AS ultimo_numero FROM gastos WHERE numero_gasto LIKE 'G-%'");
        $ultimoNumero = (int)$stmt->fetchColumn();
        $numero = "G-" . str_pad($ultimoNumero + 1, 6, '0', STR_PAD_LEFT);

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

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    } finally {
        $stmtUnlock = $pdo->prepare("SELECT RELEASE_LOCK(?)");
        $stmtUnlock->execute([$lockKey]);
    }

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
