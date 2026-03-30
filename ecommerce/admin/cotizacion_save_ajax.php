<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';

header('Content-Type: application/json; charset=utf-8');

function tabla_tiene_columna_ajax(PDO $pdo, string $tabla, string $columna): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$tabla} LIKE ?");
        $stmt->execute([$columna]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

try {
    // Log incoming raw post for debugging
    try {
        $logsDir = __DIR__ . '/logs';
        if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);
        $res = @file_put_contents($logsDir . '/cotizacion_save_ajax.log', "--- " . date('c') . " ---\nPOST_keys: " . implode(',', array_keys($_POST)) . "\n\n", FILE_APPEND);
        if ($res === false) error_log('cotizacion_save_ajax: failed to write to ' . $logsDir . '/cotizacion_save_ajax.log');
    } catch (Exception $e) { error_log('cotizacion_save_ajax: log init exception: ' . $e->getMessage()); }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

    // Asegurar columnas usadas por este endpoint
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizacion_clientes', 'direccion')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizacion_clientes', 'cuit')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit VARCHAR(20) NULL AFTER direccion");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizacion_clientes', 'factura_a')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizacion_clientes', 'es_empresa')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizaciones', 'cuit')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER telefono");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizaciones', 'factura_a')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizaciones', 'es_empresa')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
    }

    // decode items_json if present
    if (!empty($_POST['items_json'])) {
        $decoded = json_decode($_POST['items_json'], true);
        if (is_array($decoded)) {
            $items = $decoded;
        } else {
            $items = [];
        }
    } elseif (!empty($_POST['items']) && is_array($_POST['items'])) {
        $items = $_POST['items'];
    } else {
        $items = [];
    }

    if (empty($items)) throw new Exception('Debe agregar al menos un item');

    $nombre_cliente = trim($_POST['nombre_cliente'] ?? '');
    if ($nombre_cliente === '') throw new Exception('Nombre de cliente obligatorio');

    $email = $_POST['email'] ?? null;
    $telefono = $_POST['telefono'] ?? null;
    $direccion = $_POST['direccion'] ?? null;
    $observaciones = $_POST['observaciones'] ?? null;
    $validez_dias = intval($_POST['validez_dias'] ?? 15);
    $lista_precio_id = !empty($_POST['lista_precio_id']) ? intval($_POST['lista_precio_id']) : null;
    $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
    $es_empresa = !empty($_POST['es_empresa']) ? 1 : 0;
    $factura_a = !empty($_POST['factura_a']) ? 1 : 0;
    $cuit = preg_replace('/\D+/', '', (string)($_POST['cuit'] ?? ''));

    if ($es_empresa && $factura_a && strlen($cuit) !== 11) {
        throw new Exception('Si es empresa con Factura A, el CUIT debe tener 11 dígitos');
    }

    // Resolver / crear cliente en agenda de cotizaciones
    if ($cliente_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
        $stmt->execute([$cliente_id]);
        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            $cliente_id = 0;
        }
    }

    if ($cliente_id <= 0 && !empty($email)) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE email = ? LIMIT 1");
        $stmt->execute([strtolower(trim((string)$email))]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cliente_id = (int)$row['id'];
        }
    }

    if ($cliente_id <= 0 && !empty($telefono)) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE telefono = ? LIMIT 1");
        $stmt->execute([trim((string)$telefono)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $cliente_id = (int)$row['id'];
        }
    }

    if ($cliente_id <= 0) {
        $stmt = $pdo->prepare("INSERT INTO ecommerce_cotizacion_clientes (nombre, email, telefono, direccion, cuit, factura_a, es_empresa, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            $nombre_cliente,
            !empty($email) ? strtolower(trim((string)$email)) : null,
            !empty($telefono) ? trim((string)$telefono) : null,
            !empty($direccion) ? trim((string)$direccion) : null,
            $cuit !== '' ? $cuit : null,
            $factura_a,
            $es_empresa,
        ]);
        $cliente_id = (int)$pdo->lastInsertId();
    }

    $stmt = $pdo->prepare("UPDATE ecommerce_cotizacion_clientes SET nombre = ?, email = ?, telefono = ?, direccion = ?, cuit = ?, factura_a = ?, es_empresa = ?, activo = 1 WHERE id = ?");
    $stmt->execute([
        $nombre_cliente,
        !empty($email) ? strtolower(trim((string)$email)) : null,
        !empty($telefono) ? trim((string)$telefono) : null,
        !empty($direccion) ? trim((string)$direccion) : null,
        $cuit !== '' ? $cuit : null,
        $factura_a,
        $es_empresa,
        $cliente_id,
    ]);

    // calcular totales simples
    $subtotal = 0;
    foreach ($items as $it) {
        $cantidad = intval($it['cantidad'] ?? 0);
        $precio = floatval($it['precio'] ?? 0);
        $subtotal += $cantidad * $precio;
    }

    $descuento = floatval($_POST['descuento'] ?? 0);
    $cupon_codigo = isset($_POST['cupon_codigo']) ? normalizar_codigo_descuento($_POST['cupon_codigo']) : '';
    $cupon_descuento = floatval($_POST['cupon_descuento'] ?? 0);
    if ($cupon_codigo !== '') {
        $descuento_row = obtener_descuento_por_codigo($pdo, $cupon_codigo);
        if (!$descuento_row) throw new Exception('Cupón inválido');
        $validacion = validar_descuento($descuento_row, $subtotal);
        if (!$validacion['valido']) throw new Exception($validacion['mensaje']);
        $cupon_descuento = calcular_monto_descuento($descuento_row['tipo'], (float)$descuento_row['valor'], $subtotal);
    } else {
        $cupon_descuento = 0;
    }

    $total = $subtotal - $descuento - $cupon_descuento;

    // generar numero
    $año = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM ecommerce_cotizaciones");
    $max_id = $stmt->fetch()['max_id'] ?? 0;
    $numero_cotizacion = 'COT-' . $año . '-' . str_pad($max_id + 1, 5, '0', STR_PAD_LEFT);

    // insertar dinámico según columnas disponibles
    $cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
    $items_json = json_encode($items);

    $insert_cols = ['numero_cotizacion', 'nombre_cliente', 'email', 'telefono'];
    $insert_vals = [$numero_cotizacion, $nombre_cliente, $email ?: null, $telefono ?: null];

    if (in_array('direccion', $cols_cot, true)) {
        $insert_cols[] = 'direccion';
        $insert_vals[] = $direccion ?: null;
    } elseif (in_array('empresa', $cols_cot, true)) {
        $insert_cols[] = 'empresa';
        $insert_vals[] = $direccion ?: null;
    }
    if (in_array('cliente_id', $cols_cot, true)) {
        $insert_cols[] = 'cliente_id';
        $insert_vals[] = $cliente_id ?: null;
    }
    if (in_array('es_empresa', $cols_cot, true)) {
        $insert_cols[] = 'es_empresa';
        $insert_vals[] = $es_empresa;
    }
    if (in_array('cuit', $cols_cot, true)) {
        $insert_cols[] = 'cuit';
        $insert_vals[] = $cuit !== '' ? $cuit : null;
    }
    if (in_array('factura_a', $cols_cot, true)) {
        $insert_cols[] = 'factura_a';
        $insert_vals[] = $factura_a;
    }

    $insert_cols = array_merge($insert_cols, ['lista_precio_id', 'items', 'subtotal', 'descuento', 'cupon_codigo', 'cupon_descuento', 'total', 'observaciones', 'validez_dias', 'creado_por']);
    $insert_vals = array_merge($insert_vals, [$lista_precio_id, $items_json, $subtotal, $descuento, $cupon_codigo ?: null, $cupon_descuento, $total, $observaciones, $validez_dias, $_SESSION['user']['id'] ?? null]);

    $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
    $sql_insert = "INSERT INTO ecommerce_cotizaciones (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
    $stmt = $pdo->prepare($sql_insert);
    $stmt->execute($insert_vals);

    $cotizacion_id = $pdo->lastInsertId();

    try { $res = @file_put_contents($logsDir . '/cotizacion_save_ajax.log', "Inserted id: " . $cotizacion_id . "\n", FILE_APPEND); if ($res === false) error_log('cotizacion_save_ajax: failed to append inserted id'); } catch (Exception $e) { error_log('cotizacion_save_ajax: append exception: ' . $e->getMessage()); }

    echo json_encode(['success' => true, 'id' => $cotizacion_id, 'redirect' => 'cotizacion_detalle.php?id=' . $cotizacion_id]);
    exit;
} catch (Exception $e) {
    try { $res = @file_put_contents(__DIR__ . '/logs/cotizacion_save_ajax.log', "ERROR: " . $e->getMessage() . "\n\n", FILE_APPEND); if ($res === false) error_log('cotizacion_save_ajax: failed to write error to log'); } catch (Exception $ex) { error_log('cotizacion_save_ajax: error write exception: ' . $ex->getMessage()); }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
