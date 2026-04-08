<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

header('Content-Type: application/json; charset=utf-8');

function tabla_tiene_columna_ajax(PDO $pdo, string $tabla, string $columna): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$tabla, $columna]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function tabla_tiene_indice_ajax(PDO $pdo, string $tabla, string $indice): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $stmt->execute([$tabla, $indice]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function normalizar_texto_cliente_ajax(string $valor): string {
    $valor = trim((string)$valor);
    $valor = preg_replace('/\s+/', ' ', $valor);
    return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
}

function normalizar_telefono_cliente_ajax(string $valor): string {
    return preg_replace('/\D+/', '', (string)$valor);
}

function asegurar_unicidad_clientes_ajax(PDO $pdo): void {
    static $ejecutado = false;
    if ($ejecutado) {
        return;
    }
    $ejecutado = true;

    $columnas = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('email_normalizado', $columnas, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN email_normalizado VARCHAR(191) NULL AFTER email");
    }
    if (!in_array('telefono_normalizado', $columnas, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN telefono_normalizado VARCHAR(50) NULL AFTER telefono");
    }
    if (!in_array('cuit_normalizado', $columnas, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit_normalizado VARCHAR(20) NULL AFTER cuit");
    }

    $stmt = $pdo->query("SELECT id, email, telefono, cuit FROM ecommerce_cotizacion_clientes ORDER BY id DESC");
    $update = $pdo->prepare("UPDATE ecommerce_cotizacion_clientes
        SET email_normalizado = ?, telefono_normalizado = ?, cuit_normalizado = ?
        WHERE id = ?");
    $emails = [];
    $telefonos = [];
    $cuits = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $emailNorm = !empty($row['email']) ? strtolower(trim((string)$row['email'])) : '';
        if ($emailNorm !== '' && isset($emails[$emailNorm])) {
            $emailNorm = '';
        }
        if ($emailNorm !== '') {
            $emails[$emailNorm] = true;
        }

        $telefonoNorm = !empty($row['telefono']) ? normalizar_telefono_cliente_ajax((string)$row['telefono']) : '';
        if ($telefonoNorm !== '' && isset($telefonos[$telefonoNorm])) {
            $telefonoNorm = '';
        }
        if ($telefonoNorm !== '') {
            $telefonos[$telefonoNorm] = true;
        }

        $cuitNorm = !empty($row['cuit']) ? preg_replace('/\D+/', '', (string)$row['cuit']) : '';
        if ($cuitNorm !== '' && isset($cuits[$cuitNorm])) {
            $cuitNorm = '';
        }
        if ($cuitNorm !== '') {
            $cuits[$cuitNorm] = true;
        }

        $update->execute([
            $emailNorm !== '' ? $emailNorm : null,
            $telefonoNorm !== '' ? $telefonoNorm : null,
            $cuitNorm !== '' ? $cuitNorm : null,
            (int)$row['id'],
        ]);
    }

    if (!tabla_tiene_indice_ajax($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_email_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_email_norm (email_normalizado)");
    }
    if (!tabla_tiene_indice_ajax($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_tel_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_tel_norm (telefono_normalizado)");
    }
    if (!tabla_tiene_indice_ajax($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_cuit_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_cuit_norm (cuit_normalizado)");
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bi");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bi BEFORE INSERT ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
    } catch (Exception $e) {
        // No interrumpir la respuesta si el servidor no permite triggers.
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bu");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bu BEFORE UPDATE ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
    } catch (Exception $e) {
        // No interrumpir la respuesta si el servidor no permite triggers.
    }
}

function guardar_cliente_unico_ajax(PDO $pdo, string $nombre_cliente, ?string $email = null, ?string $telefono = null, ?string $direccion = null, ?string $cuit = null, int $factura_a = 0, int $es_empresa = 0): int {
    $emailLimpio = trim((string)$email);
    $telefonoLimpio = trim((string)$telefono);
    $direccionLimpia = trim((string)$direccion);
    $cuitLimpio = preg_replace('/\D+/', '', (string)$cuit);
    $emailNormalizado = $emailLimpio !== '' ? strtolower($emailLimpio) : null;
    $telefonoNormalizado = $telefonoLimpio !== '' ? normalizar_telefono_cliente_ajax($telefonoLimpio) : null;
    $cuitNormalizado = $cuitLimpio !== '' ? $cuitLimpio : null;

    $stmt = $pdo->prepare("INSERT INTO ecommerce_cotizacion_clientes
        (nombre, email, telefono, direccion, cuit, factura_a, es_empresa, email_normalizado, telefono_normalizado, cuit_normalizado, activo)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE
            id = LAST_INSERT_ID(id),
            nombre = VALUES(nombre),
            email = COALESCE(VALUES(email), email),
            telefono = COALESCE(VALUES(telefono), telefono),
            direccion = COALESCE(VALUES(direccion), direccion),
            cuit = COALESCE(VALUES(cuit), cuit),
            factura_a = VALUES(factura_a),
            es_empresa = VALUES(es_empresa),
            activo = 1");
    $stmt->execute([
        $nombre_cliente,
        $emailNormalizado,
        $telefonoLimpio !== '' ? $telefonoLimpio : null,
        $direccionLimpia !== '' ? $direccionLimpia : null,
        $cuitNormalizado,
        $factura_a,
        $es_empresa,
        $emailNormalizado,
        $telefonoNormalizado,
        $cuitNormalizado,
    ]);

    return (int)$pdo->lastInsertId();
}

function resolver_cliente_existente_ajax(PDO $pdo, int $cliente_id, string $nombre_cliente, ?string $email = null, ?string $telefono = null, ?string $direccion = null, ?string $cuit = null): int {
    if ($cliente_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
        $stmt->execute([$cliente_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $cliente_id;
        }
    }

    $cuit_norm = preg_replace('/\D+/', '', (string)$cuit);
    if ($cuit_norm !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE REPLACE(REPLACE(COALESCE(cuit, ''), '-', ''), ' ', '') = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$cuit_norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $email_norm = !empty($email) ? strtolower(trim((string)$email)) : '';
    if ($email_norm !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE LOWER(TRIM(COALESCE(email, ''))) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email_norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $telefono_norm = normalizar_telefono_cliente_ajax((string)$telefono);
    if ($telefono_norm !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ?
            ORDER BY id DESC LIMIT 1");
        $stmt->execute([$telefono_norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $nombre_norm = normalizar_texto_cliente_ajax($nombre_cliente);
    $direccion_norm = normalizar_texto_cliente_ajax((string)$direccion);
    if ($nombre_norm !== '') {
        if ($direccion_norm !== '') {
            $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes
                WHERE LOWER(TRIM(COALESCE(nombre, ''))) = ? AND LOWER(TRIM(COALESCE(direccion, ''))) = ?
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$nombre_norm, $direccion_norm]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE LOWER(TRIM(COALESCE(nombre, ''))) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$nombre_norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    return 0;
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
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizaciones', 'comprobante_tipo')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN comprobante_tipo VARCHAR(20) NOT NULL DEFAULT 'factura' AFTER factura_a");
    }
    if (!tabla_tiene_columna_ajax($pdo, 'ecommerce_cotizaciones', 'es_empresa')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER comprobante_tipo");
    }

    asegurar_unicidad_clientes_ajax($pdo);

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
    $comprobante_tipo = contabilidad_normalizar_comprobante_tipo((string)($_POST['comprobante_tipo'] ?? 'factura'));
    if ($comprobante_tipo === 'recibo') {
        $factura_a = 0;
    }
    $cuit = preg_replace('/\D+/', '', (string)($_POST['cuit'] ?? ''));

    if ($es_empresa && $factura_a && strlen($cuit) !== 11) {
        throw new Exception('Si es empresa con Factura A, el CUIT debe tener 11 dígitos');
    }

    // Resolver / crear cliente en agenda de cotizaciones evitando duplicados
    $cliente_id = resolver_cliente_existente_ajax(
        $pdo,
        $cliente_id,
        $nombre_cliente,
        $email,
        $telefono,
        $direccion,
        $cuit
    );

    if ($cliente_id <= 0) {
        $cliente_id = guardar_cliente_unico_ajax(
            $pdo,
            $nombre_cliente,
            $email,
            $telefono,
            $direccion,
            $cuit,
            $factura_a,
            $es_empresa
        );
    }

    $stmt = $pdo->prepare("UPDATE ecommerce_cotizacion_clientes
        SET nombre = ?,
            email = COALESCE(?, email),
            telefono = COALESCE(?, telefono),
            direccion = COALESCE(?, direccion),
            cuit = COALESCE(?, cuit),
            factura_a = ?,
            es_empresa = ?,
            activo = 1
        WHERE id = ?");
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

    $base_cotizacion = max(0, $subtotal - $descuento - $cupon_descuento);
    $impuestos_activos = contabilidad_get_impuestos($pdo, true);
    $resumen_impuestos = contabilidad_calcular_impuestos(
        $impuestos_activos,
        $base_cotizacion,
        $base_cotizacion,
        'cotizacion'
    );
    $total = max(0, (float)($resumen_impuestos['total_con_impuestos'] ?? $base_cotizacion));

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
    if (in_array('comprobante_tipo', $cols_cot, true)) {
        $insert_cols[] = 'comprobante_tipo';
        $insert_vals[] = $comprobante_tipo;
    }

    $insert_cols = array_merge($insert_cols, ['lista_precio_id', 'items', 'subtotal', 'descuento', 'cupon_codigo', 'cupon_descuento', 'total', 'observaciones', 'validez_dias', 'creado_por']);
    $insert_vals = array_merge($insert_vals, [$lista_precio_id, $items_json, $subtotal, $descuento, $cupon_codigo ?: null, $cupon_descuento, $total, $observaciones, $validez_dias, $_SESSION['user']['id'] ?? null]);

    if (in_array('impuestos_json', $cols_cot, true)) {
        $insert_cols[] = 'impuestos_json';
        $insert_vals[] = !empty($resumen_impuestos['detalle']) ? json_encode($resumen_impuestos['detalle'], JSON_UNESCAPED_UNICODE) : null;
    }
    if (in_array('impuestos_incluidos', $cols_cot, true)) {
        $insert_cols[] = 'impuestos_incluidos';
        $insert_vals[] = (float)($resumen_impuestos['total_incluidos'] ?? 0);
    }
    if (in_array('impuestos_adicionales', $cols_cot, true)) {
        $insert_cols[] = 'impuestos_adicionales';
        $insert_vals[] = (float)($resumen_impuestos['total_adicionales'] ?? 0);
    }

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
