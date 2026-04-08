<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

$mensaje = '';
$error = '';
$contabilidad_config = contabilidad_get_config($pdo);
$contabilidad_impuestos_activos = contabilidad_get_impuestos($pdo, true);
$contabilidad_moneda = trim((string)($contabilidad_config['moneda'] ?? 'ARS')) ?: 'ARS';

function cotizacion_tabla_existe(PDO $pdo, string $tabla): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$tabla]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        try {
            $pdo->query("SELECT 1 FROM {$tabla} LIMIT 1");
            return true;
        } catch (Exception $ex) {
            return false;
        }
    }
}

function tabla_tiene_columna(PDO $pdo, string $tabla, string $columna): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
        $stmt->execute([$tabla, $columna]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function tabla_tiene_indice(PDO $pdo, string $tabla, string $indice): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?");
        $stmt->execute([$tabla, $indice]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function cotizacion_normalizar_texto(string $valor): string {
    $valor = trim((string)$valor);
    $valor = preg_replace('/\s+/', ' ', $valor);
    return function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
}

function cotizacion_normalizar_telefono(string $valor): string {
    return preg_replace('/\D+/', '', (string)$valor);
}

function cotizacion_asegurar_unicidad_clientes(PDO $pdo): void {
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

        $telefonoNorm = !empty($row['telefono']) ? cotizacion_normalizar_telefono((string)$row['telefono']) : '';
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

    if (!tabla_tiene_indice($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_email_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_email_norm (email_normalizado)");
    }
    if (!tabla_tiene_indice($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_tel_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_tel_norm (telefono_normalizado)");
    }
    if (!tabla_tiene_indice($pdo, 'ecommerce_cotizacion_clientes', 'uniq_cot_cli_cuit_norm')) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD UNIQUE INDEX uniq_cot_cli_cuit_norm (cuit_normalizado)");
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bi");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bi BEFORE INSERT ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
    } catch (Exception $e) {
        // No interrumpir la carga si el servidor no permite triggers.
    }

    try {
        $pdo->exec("DROP TRIGGER IF EXISTS ecommerce_cot_cli_bu");
        $pdo->exec("CREATE TRIGGER ecommerce_cot_cli_bu BEFORE UPDATE ON ecommerce_cotizacion_clientes FOR EACH ROW SET
            NEW.email_normalizado = NULLIF(LOWER(TRIM(COALESCE(NEW.email, ''))), ''),
            NEW.telefono_normalizado = NULLIF(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(NEW.telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', ''), ''),
            NEW.cuit_normalizado = NULLIF(REPLACE(REPLACE(COALESCE(NEW.cuit, ''), '-', ''), ' ', ''), '')");
    } catch (Exception $e) {
        // No interrumpir la carga si el servidor no permite triggers.
    }
}

function cotizacion_guardar_cliente_unico(PDO $pdo, string $nombre_cliente, string $email = '', string $telefono = '', string $direccion = '', string $cuit = '', int $factura_a = 0, int $es_empresa = 0): int {
    $emailLimpio = trim((string)$email);
    $telefonoLimpio = trim((string)$telefono);
    $direccionLimpia = trim((string)$direccion);
    $cuitLimpio = preg_replace('/\D+/', '', (string)$cuit);
    $emailNormalizado = $emailLimpio !== '' ? strtolower($emailLimpio) : null;
    $telefonoNormalizado = $telefonoLimpio !== '' ? cotizacion_normalizar_telefono($telefonoLimpio) : null;
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

function cotizacion_resolver_cliente_existente(PDO $pdo, int $cliente_id, string $nombre_cliente, string $email = '', string $telefono = '', string $direccion = '', string $cuit = ''): int {
    if ($cliente_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
        $stmt->execute([$cliente_id]);
        if ($stmt->fetch(PDO::FETCH_ASSOC)) {
            return $cliente_id;
        }
    }

    $cuit_norm = preg_replace('/\D+/', '', $cuit);
    if ($cuit_norm !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE REPLACE(REPLACE(COALESCE(cuit, ''), '-', ''), ' ', '') = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$cuit_norm]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $email_normalizado = $email ? strtolower(trim($email)) : '';
    if ($email_normalizado !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE LOWER(TRIM(COALESCE(email, ''))) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$email_normalizado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $telefono_normalizado = cotizacion_normalizar_telefono($telefono);
    if ($telefono_normalizado !== '') {
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes
            WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefono, ''), ' ', ''), '-', ''), '(', ''), ')', ''), '+', ''), '.', '') = ?
            ORDER BY id DESC LIMIT 1");
        $stmt->execute([$telefono_normalizado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    $nombre_normalizado = cotizacion_normalizar_texto($nombre_cliente);
    $direccion_normalizada = cotizacion_normalizar_texto($direccion);
    if ($nombre_normalizado !== '') {
        if ($direccion_normalizada !== '') {
            $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes
                WHERE LOWER(TRIM(COALESCE(nombre, ''))) = ? AND LOWER(TRIM(COALESCE(direccion, ''))) = ?
                ORDER BY id DESC LIMIT 1");
            $stmt->execute([$nombre_normalizado, $direccion_normalizada]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return (int)$row['id'];
            }
        }

        $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE LOWER(TRIM(COALESCE(nombre, ''))) = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$nombre_normalizado]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return (int)$row['id'];
        }
    }

    return 0;
}

// Tabla de cupones
$pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_cupones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('porcentaje','monto') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Columnas de cupón en cotizaciones
$cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('cupon_codigo', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_codigo VARCHAR(50) NULL");
}
if (!in_array('cupon_descuento', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_descuento DECIMAL(10,2) NULL");
}
if (!in_array('cuit', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER telefono");
}
if (!in_array('factura_a', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
}
if (!in_array('comprobante_tipo', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN comprobante_tipo VARCHAR(20) NOT NULL DEFAULT 'factura' AFTER factura_a");
}
if (!in_array('es_empresa', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER comprobante_tipo");
}
if (!in_array('crm_id', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN crm_id INT NULL AFTER cliente_id");
}
if (!in_array('visita_id', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN visita_id INT NULL AFTER crm_id");
}

$cols_cli_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('direccion', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono");
}
if (!in_array('cuit', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit VARCHAR(20) NULL AFTER direccion");
}
if (!in_array('factura_a', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
}
if (!in_array('es_empresa', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
}

cotizacion_asegurar_unicidad_clientes($pdo);

$crm_id_context = max(0, (int)($_POST['crm_id'] ?? $_GET['crm_id'] ?? 0));
$crm_context = null;

if ($crm_id_context > 0 && cotizacion_tabla_existe($pdo, 'ecommerce_crm_visitas') && cotizacion_tabla_existe($pdo, 'ecommerce_visitas')) {
    try {
        $stmt = $pdo->prepare("SELECT
            c.id,
            c.visita_id,
            c.estado AS crm_estado,
            c.notas_internas,
            c.proximo_contacto,
            c.ultima_cotizacion_id,
            c.ultima_cotizacion_numero,
            v.titulo,
            v.descripcion AS visita_descripcion,
            v.cliente_nombre,
            v.telefono,
            v.direccion,
            v.fecha_visita
        FROM ecommerce_crm_visitas c
        INNER JOIN ecommerce_visitas v ON v.id = c.visita_id
        WHERE c.id = ?
        LIMIT 1");
        $stmt->execute([$crm_id_context]);
        $crm_context = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Exception $e) {
        $crm_context = null;
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Inicializar log de POST (registro inmediato para detectar fallos tempranos)
    try {
        $logsDir = __DIR__ . '/logs';
        if (!is_dir($logsDir)) {
            @mkdir($logsDir, 0755, true);
        }
        $res = @file_put_contents($logsDir . '/cotizacion_post.log', "=== POST ENTRY " . date('c') . " ===\n" . print_r($_POST, true) . "\n", FILE_APPEND);
        if ($res === false) error_log('cotizacion_crear: failed to write to ' . $logsDir . '/cotizacion_post.log');
    } catch (Exception $e) { error_log('cotizacion_crear: log init exception: ' . $e->getMessage()); }
    try {
        $nombre_cliente = $_POST['nombre_cliente'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $observaciones = $_POST['observaciones'] ?? '';
        $validez_dias = intval($_POST['validez_dias'] ?? 15);
        $lista_precio_id = !empty($_POST['lista_precio_id']) ? intval($_POST['lista_precio_id']) : null;
        $cliente_id = !empty($_POST['cliente_id']) ? intval($_POST['cliente_id']) : 0;
        $crm_id = !empty($_POST['crm_id']) ? intval($_POST['crm_id']) : $crm_id_context;
        $es_empresa = !empty($_POST['es_empresa']) ? 1 : 0;
        $factura_a = !empty($_POST['factura_a']) ? 1 : 0;
        $comprobante_tipo = contabilidad_normalizar_comprobante_tipo((string)($_POST['comprobante_tipo'] ?? 'factura'));
        if ($comprobante_tipo === 'recibo') {
            $factura_a = 0;
        }
        $cuit = preg_replace('/\D+/', '', (string)($_POST['cuit'] ?? ''));
        
        // Validaciones
        if (empty($nombre_cliente)) {
            throw new Exception("Nombre es obligatorio");
        }
        
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }

        if ($es_empresa && $factura_a && strlen($cuit) !== 11) {
            throw new Exception("Si es empresa con Factura A, el CUIT debe tener 11 dígitos");
        }
        
        // Resolver cliente existente para evitar duplicados
        $cliente_id = cotizacion_resolver_cliente_existente(
            $pdo,
            $cliente_id,
            (string)$nombre_cliente,
            (string)$email,
            (string)$telefono,
            (string)$direccion,
            (string)$cuit
        );

        if ($cliente_id <= 0) {
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_cotizacion_clientes (nombre, email, telefono, direccion, cuit, factura_a, es_empresa, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $nombre_cliente,
                $email ? strtolower(trim($email)) : null,
                $telefono ? $telefono : null,
                $direccion ? $direccion : null,
                $cuit !== '' ? $cuit : null,
                $factura_a,
                $es_empresa,
            ]);
            $cliente_id = (int)$pdo->lastInsertId();
        }

        if ($cliente_id > 0) {
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
                $email ? strtolower(trim($email)) : null,
                $telefono ? $telefono : null,
                $direccion ? $direccion : null,
                $cuit !== '' ? $cuit : null,
                $factura_a,
                $es_empresa,
                $cliente_id,
            ]);
        }

        // Procesar items
        $items = [];
        $subtotal = 0;

        // soportar items enviados como JSON desde el cliente
        if (!empty($_POST['items_json'])) {
            $decoded = json_decode($_POST['items_json'], true);
            if (is_array($decoded)) {
                $_POST['items'] = $decoded;
            }
        }

        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $item) {
                if (empty($item['nombre']) || empty($item['cantidad']) || empty($item['precio'])) {
                    continue;
                }
                
                $cantidad = intval($item['cantidad']);
                $precio = floatval($item['precio']);
                $total_item = $cantidad * $precio;
                
                // Procesar atributos si existen
                $atributos = [];
                $costo_atributos_total = 0;
                if (!empty($item['atributos']) && is_array($item['atributos'])) {
                    foreach ($item['atributos'] as $attr) {
                        if (!empty($attr['nombre'])) {
                            $costo = floatval($attr['costo'] ?? 0);
                            $atributos[] = [
                                'nombre' => $attr['nombre'],
                                'valor' => $attr['valor'] ?? '',
                                'costo_adicional' => $costo
                            ];
                            $costo_atributos_total += $costo;
                        }
                    }
                }
                
                // Agregar costo de atributos al precio unitario
                $precio_total_unitario = $precio + $costo_atributos_total;
                $total_item = $cantidad * $precio_total_unitario;
                
                $items[] = [
                    'producto_id' => !empty($item['producto_id']) ? intval($item['producto_id']) : null,
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $cantidad,
                    'ancho' => !empty($item['ancho']) ? floatval($item['ancho']) : null,
                    'alto' => !empty($item['alto']) ? floatval($item['alto']) : null,
                    'precio_base' => $precio,
                    'atributos' => $atributos,
                    'descuento_pct' => max(0, min(100, floatval($item['descuento_pct'] ?? 0))),
                    'precio_unitario' => $precio_total_unitario,
                    'precio_total' => $total_item
                ];
                
                $subtotal += $total_item;
            }
        }
        
        if (empty($items)) {
            throw new Exception("Debe agregar al menos un item");
        }
        
        $descuento = floatval($_POST['descuento'] ?? 0);
        $cupon_codigo = normalizar_codigo_descuento((string)($_POST['cupon_codigo'] ?? ''));
        $cupon_descuento = floatval($_POST['cupon_descuento'] ?? 0);
        if ($cupon_codigo !== '') {
            $descuento_row = obtener_descuento_por_codigo($pdo, $cupon_codigo);
            if (!$descuento_row) {
                throw new Exception('Cupón inválido');
            }
            $validacion = validar_descuento($descuento_row, $subtotal);
            if (!$validacion['valido']) {
                throw new Exception($validacion['mensaje']);
            }
            $cupon_descuento = calcular_monto_descuento($descuento_row['tipo'], (float)$descuento_row['valor'], $subtotal);
        } else {
            $cupon_descuento = 0;
        }

        $base_cotizacion = max(0, $subtotal - $descuento - $cupon_descuento);
        $resumen_impuestos = contabilidad_calcular_impuestos(
            $contabilidad_impuestos_activos,
            $base_cotizacion,
            $base_cotizacion,
            'cotizacion'
        );
        $total = max(0, (float)($resumen_impuestos['total_con_impuestos'] ?? $base_cotizacion));
        
        // Generar número de cotización
        $año = date('Y');
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM ecommerce_cotizaciones");
        $max_id = $stmt->fetch()['max_id'] ?? 0;
        $numero_cotizacion = 'COT-' . $año . '-' . str_pad($max_id + 1, 5, '0', STR_PAD_LEFT);
        
        // Guardar cotización en forma dinámica según columnas disponibles
        $cols_cot_insert = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
        $insert_cols = ['numero_cotizacion', 'nombre_cliente', 'email', 'telefono'];
        $insert_vals = [$numero_cotizacion, $nombre_cliente, $email, $telefono];

        if (in_array('direccion', $cols_cot_insert, true)) {
            $insert_cols[] = 'direccion';
            $insert_vals[] = $direccion;
        } elseif (in_array('empresa', $cols_cot_insert, true)) {
            $insert_cols[] = 'empresa';
            $insert_vals[] = $direccion;
        }

        if (in_array('cliente_id', $cols_cot_insert, true)) {
            $insert_cols[] = 'cliente_id';
            $insert_vals[] = $cliente_id ?: null;
        }
        if (in_array('crm_id', $cols_cot_insert, true)) {
            $insert_cols[] = 'crm_id';
            $insert_vals[] = $crm_id > 0 ? $crm_id : null;
        }
        if (in_array('visita_id', $cols_cot_insert, true)) {
            $insert_cols[] = 'visita_id';
            $insert_vals[] = !empty($crm_context['visita_id']) ? (int)$crm_context['visita_id'] : null;
        }
        if (in_array('es_empresa', $cols_cot_insert, true)) {
            $insert_cols[] = 'es_empresa';
            $insert_vals[] = $es_empresa;
        }
        if (in_array('cuit', $cols_cot_insert, true)) {
            $insert_cols[] = 'cuit';
            $insert_vals[] = $cuit !== '' ? $cuit : null;
        }
        if (in_array('factura_a', $cols_cot_insert, true)) {
            $insert_cols[] = 'factura_a';
            $insert_vals[] = $factura_a;
        }
        if (in_array('comprobante_tipo', $cols_cot_insert, true)) {
            $insert_cols[] = 'comprobante_tipo';
            $insert_vals[] = $comprobante_tipo;
        }

        $insert_cols = array_merge($insert_cols, ['lista_precio_id', 'items', 'subtotal', 'descuento', 'cupon_codigo', 'cupon_descuento', 'total', 'observaciones', 'validez_dias', 'creado_por']);
        $insert_vals = array_merge($insert_vals, [$lista_precio_id, json_encode($items), $subtotal, $descuento, $cupon_codigo ?: null, $cupon_descuento, $total, $observaciones, $validez_dias, $_SESSION['user']['id']]);

        if (in_array('impuestos_json', $cols_cot_insert, true)) {
            $insert_cols[] = 'impuestos_json';
            $insert_vals[] = !empty($resumen_impuestos['detalle']) ? json_encode($resumen_impuestos['detalle'], JSON_UNESCAPED_UNICODE) : null;
        }
        if (in_array('impuestos_incluidos', $cols_cot_insert, true)) {
            $insert_cols[] = 'impuestos_incluidos';
            $insert_vals[] = (float)($resumen_impuestos['total_incluidos'] ?? 0);
        }
        if (in_array('impuestos_adicionales', $cols_cot_insert, true)) {
            $insert_cols[] = 'impuestos_adicionales';
            $insert_vals[] = (float)($resumen_impuestos['total_adicionales'] ?? 0);
        }

        $placeholders = implode(', ', array_fill(0, count($insert_cols), '?'));
        $sql_insert = "INSERT INTO ecommerce_cotizaciones (" . implode(', ', $insert_cols) . ") VALUES (" . $placeholders . ")";
        $stmt = $pdo->prepare($sql_insert);
        $stmt->execute($insert_vals);
        
        $cotizacion_id = $pdo->lastInsertId();

        if (!empty($crm_id) && cotizacion_tabla_existe($pdo, 'ecommerce_crm_visitas')) {
            try {
                $proximo_seguimiento = date('Y-m-d', strtotime('+3 days'));
                $update_parts = [
                    "estado = 'propuesta'",
                    "fecha_cierre = NULL",
                    "ultima_gestion = NOW()",
                    "proximo_contacto = CASE WHEN proximo_contacto IS NULL OR proximo_contacto < CURDATE() THEN ? ELSE proximo_contacto END"
                ];
                $update_vals = [$proximo_seguimiento];

                if (tabla_tiene_columna($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_id')) {
                    $update_parts[] = 'ultima_cotizacion_id = ?';
                    $update_vals[] = $cotizacion_id;
                }
                if (tabla_tiene_columna($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_numero')) {
                    $update_parts[] = 'ultima_cotizacion_numero = ?';
                    $update_vals[] = $numero_cotizacion;
                }
                if (tabla_tiene_columna($pdo, 'ecommerce_crm_visitas', 'fecha_ultima_cotizacion')) {
                    $update_parts[] = 'fecha_ultima_cotizacion = NOW()';
                }

                $update_vals[] = $crm_id;
                $stmt = $pdo->prepare("UPDATE ecommerce_crm_visitas SET " . implode(', ', $update_parts) . " WHERE id = ?");
                $stmt->execute($update_vals);

                if (cotizacion_tabla_existe($pdo, 'ecommerce_crm_seguimientos')) {
                    $comentario_crm = "Se generó la cotización {$numero_cotizacion} por " . ($_SESSION['user']['usuario'] ?? $_SESSION['user']['nombre'] ?? 'usuario') . ".";
                    $stmt = $pdo->prepare("INSERT INTO ecommerce_crm_seguimientos (crm_id, visita_id, usuario_id, canal, resultado, comentario, proximo_contacto)
                        VALUES (?, ?, ?, 'cotizacion', 'cotizado', ?, ?)");
                    $stmt->execute([
                        $crm_id,
                        !empty($crm_context['visita_id']) ? (int)$crm_context['visita_id'] : 0,
                        $_SESSION['user']['id'] ?? null,
                        $comentario_crm,
                        $proximo_seguimiento,
                    ]);
                }
            } catch (Exception $e) {
                error_log('cotizacion_crear_crm: ' . $e->getMessage());
            }

            header("Location: crm.php?lead=" . (int)$crm_id . "&ok=" . urlencode("Cotización {$numero_cotizacion} creada y vinculada al CRM."));
            exit;
        }
        
        header("Location: cotizaciones.php?mensaje=creada");
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        try {
            if (!empty($logsDir)) {
                @file_put_contents($logsDir . '/cotizacion_post.log', "!!! EXCEPTION: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            }
        } catch (Exception $ex) {}
    }
}

// Obtener productos activos para el selector (incluir tipo_origen si existe)
$cols_prod = $pdo->query("SHOW COLUMNS FROM ecommerce_productos")->fetchAll(PDO::FETCH_COLUMN, 0);
$select_tipo = in_array('tipo_origen', $cols_prod, true) ? 'tipo_origen' : "'fabricacion_propia' as tipo_origen";
$stmt = $pdo->query("
    SELECT id, nombre, tipo_precio, precio_base, categoria_id, $select_tipo 
    FROM ecommerce_productos 
    WHERE activo = 1 
    ORDER BY nombre
");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listas de precios activas
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_listas_precios WHERE activo = 1 ORDER BY nombre");
$listas_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Clientes para cotizaciones
$stmt = $pdo->query("SELECT id, nombre, email, telefono, direccion, cuit, factura_a, es_empresa FROM ecommerce_cotizacion_clientes WHERE activo = 1 ORDER BY nombre");
$clientes_cot = $stmt->fetchAll(PDO::FETCH_ASSOC);

$observacion_crm = '';
if ($crm_context) {
    $observaciones_crm_partes = [];
    $observaciones_crm_partes[] = 'Origen CRM desde visita';
    if (!empty($crm_context['fecha_visita'])) {
        $observaciones_crm_partes[] = 'Fecha de visita: ' . date('d/m/Y', strtotime((string)$crm_context['fecha_visita']));
    }
    if (!empty($crm_context['titulo'])) {
        $observaciones_crm_partes[] = 'Motivo: ' . trim((string)$crm_context['titulo']);
    }
    if (!empty($crm_context['visita_descripcion'])) {
        $observaciones_crm_partes[] = 'Detalle visita: ' . trim((string)$crm_context['visita_descripcion']);
    }
    if (!empty($crm_context['notas_internas'])) {
        $observaciones_crm_partes[] = 'Notas CRM: ' . trim((string)$crm_context['notas_internas']);
    }
    $observacion_crm = implode("\n", $observaciones_crm_partes);
}

$form_data = [
    'crm_id' => $crm_id_context,
    'cliente_id' => (int)($_POST['cliente_id'] ?? 0),
    'nombre_cliente' => trim((string)($_POST['nombre_cliente'] ?? ($crm_context['cliente_nombre'] ?? ''))),
    'email' => trim((string)($_POST['email'] ?? '')),
    'telefono' => trim((string)($_POST['telefono'] ?? ($crm_context['telefono'] ?? ''))),
    'direccion' => trim((string)($_POST['direccion'] ?? ($crm_context['direccion'] ?? ''))),
    'observaciones' => (string)($_POST['observaciones'] ?? $observacion_crm),
    'validez_dias' => (int)($_POST['validez_dias'] ?? 15),
    'lista_precio_id' => trim((string)($_POST['lista_precio_id'] ?? '')),
    'es_empresa' => !empty($_POST['es_empresa']),
    'factura_a' => !empty($_POST['factura_a']),
    'comprobante_tipo' => contabilidad_normalizar_comprobante_tipo((string)($_POST['comprobante_tipo'] ?? 'factura')),
    'cuit' => trim((string)($_POST['cuit'] ?? '')), 
];

if ($form_data['cliente_id'] <= 0) {
    $form_data['cliente_id'] = cotizacion_resolver_cliente_existente(
        $pdo,
        0,
        (string)($form_data['nombre_cliente'] ?? ''),
        (string)($form_data['email'] ?? ''),
        (string)($form_data['telefono'] ?? ''),
        (string)($form_data['direccion'] ?? ''),
        (string)($form_data['cuit'] ?? '')
    );
}

if ($form_data['cliente_id'] > 0) {
    foreach ($clientes_cot as $cli) {
        if ((int)$cli['id'] === (int)$form_data['cliente_id']) {
            if ($form_data['email'] === '' && !empty($cli['email'])) {
                $form_data['email'] = (string)$cli['email'];
            }
            if ($form_data['telefono'] === '' && !empty($cli['telefono'])) {
                $form_data['telefono'] = (string)$cli['telefono'];
            }
            if ($form_data['direccion'] === '' && !empty($cli['direccion'])) {
                $form_data['direccion'] = (string)$cli['direccion'];
            }
            if ($form_data['cuit'] === '' && !empty($cli['cuit'])) {
                $form_data['cuit'] = (string)$cli['cuit'];
            }
            if (!$form_data['es_empresa']) {
                $form_data['es_empresa'] = !empty($cli['es_empresa']);
            }
            if (!$form_data['factura_a']) {
                $form_data['factura_a'] = !empty($cli['factura_a']);
            }
            break;
        }
    }
}

$stmt = $pdo->query("SELECT lista_precio_id, producto_id, precio_nuevo, descuento_porcentaje FROM ecommerce_lista_precio_items WHERE activo = 1");
$lista_items_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_items_map = [];
foreach ($lista_items_rows as $row) {
    $lista_items_map[$row['lista_precio_id']][$row['producto_id']] = [
        'precio_nuevo' => (float)$row['precio_nuevo'],
        'descuento_porcentaje' => (float)$row['descuento_porcentaje']
    ];
}

$stmt = $pdo->query("SELECT lista_precio_id, categoria_id, descuento_porcentaje FROM ecommerce_lista_precio_categorias WHERE activo = 1");
$lista_cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_cat_map = [];
foreach ($lista_cat_rows as $row) {
    $lista_cat_map[$row['lista_precio_id']][$row['categoria_id']] = (float)$row['descuento_porcentaje'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>➕ Nueva Cotización</h1>
        <p class="text-muted">Crear una cotización/presupuesto para un cliente</p>
    </div>
    <a href="cotizaciones.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($crm_context): ?>
    <div class="alert alert-info border d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center">
        <div>
            <strong>CRM vinculado:</strong>
            <?= htmlspecialchars($crm_context['cliente_nombre'] ?: $crm_context['titulo'] ?: ('Lead #' . $crm_id_context)) ?>
            <?php if (!empty($crm_context['fecha_visita'])): ?>
                <span class="text-muted">· visita del <?= htmlspecialchars(date('d/m/Y', strtotime((string)$crm_context['fecha_visita']))) ?></span>
            <?php endif; ?>
            <div class="small text-muted">Al guardar, la cotización quedará registrada automáticamente en el historial del CRM.</div>
        </div>
        <a href="crm.php?lead=<?= (int)$crm_id_context ?>" class="btn btn-outline-primary btn-sm">← Volver al CRM</a>
    </div>
<?php endif; ?>

<form method="POST" id="formCotizacion">
    <input type="hidden" name="crm_id" value="<?= (int)($form_data['crm_id'] ?? 0) ?>">
    <style>
        .attr-option-item {
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }
        .attr-option-item.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,.2);
            background: #e7f1ff;
        }
        .item-resumen-title {
            font-weight: 600;
            font-size: 1rem;
        }
        .item-resumen-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .item-resumen-attrs .badge {
            font-weight: 500;
        }
    </style>
    <div class="row">
        <!-- Información del Cliente -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">👤 Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-light border mb-3">
                        <label for="buscar_cliente" class="form-label fw-semibold mb-1">1. Buscar cliente existente</label>
                        <input type="text" class="form-control mb-2" id="buscar_cliente" placeholder="Buscar por nombre, teléfono, email, dirección o CUIT">
                        <select class="form-select" id="cliente_id" name="cliente_id" onchange="autocompletarCliente()">
                            <option value="">-- Buscar / seleccionar cliente --</option>
                            <?php foreach ($clientes_cot as $cli): ?>
                                <option value="<?= $cli['id'] ?>" <?= (int)($form_data['cliente_id'] ?? 0) === (int)$cli['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cli['nombre']) ?><?= !empty($cli['telefono']) ? ' · ' . htmlspecialchars($cli['telefono']) : '' ?><?= !empty($cli['email']) ? ' · ' . htmlspecialchars($cli['email']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="cliente_match_status" class="form-text">Primero buscá un cliente existente. Si no aparece, completá los datos de abajo y se dará de alta al guardar.</div>
                    </div>
                    <div class="mb-3">
                        <label for="nombre_cliente" class="form-label">2. Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars((string)($form_data['nombre_cliente'] ?? '')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars((string)($form_data['email'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars((string)($form_data['telefono'] ?? '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars((string)($form_data['direccion'] ?? '')) ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="es_empresa" name="es_empresa" onchange="toggleEmpresaFields()" <?= !empty($form_data['es_empresa']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="es_empresa">Es empresa</label>
                    </div>
                    <div id="empresaFields" style="display:none;">
                        <div class="mb-3">
                            <label for="cuit" class="form-label">CUIT</label>
                            <input type="text" class="form-control" id="cuit" name="cuit" maxlength="13" value="<?= htmlspecialchars((string)($form_data['cuit'] ?? '')) ?>" placeholder="Ej: 30-12345678-9">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="factura_a" name="factura_a" onchange="toggleEmpresaFields()" <?= !empty($form_data['factura_a']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="factura_a">Necesita Factura A</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Configuración -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">⚙️ Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="validez_dias" class="form-label">Validez (días)</label>
                        <input type="number" class="form-control" id="validez_dias" name="validez_dias" value="<?= htmlspecialchars((string)($form_data['validez_dias'] ?? 15)) ?>" min="1" max="90">
                        <small class="text-muted">Días de validez del presupuesto</small>
                    </div>
                    <div class="mb-3">
                        <label for="lista_precio_id" class="form-label">Lista de Precios</label>
                        <select class="form-select" id="lista_precio_id" name="lista_precio_id" onchange="aplicarListaPrecios()">
                            <option value="">-- Sin lista --</option>
                            <?php foreach ($listas_precios as $lista): ?>
                                <option value="<?= $lista['id'] ?>" <?= (string)($form_data['lista_precio_id'] ?? '') === (string)$lista['id'] ? 'selected' : '' ?>><?= htmlspecialchars($lista['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Aplica descuentos por producto o categoría</small>
                    </div>
                    <div class="mb-3">
                        <label for="descuento_porcentaje_default" class="form-label">Descuento % por defecto (nuevos ítems)</label>
                        <input type="number" class="form-control" id="descuento_porcentaje_default" name="descuento_porcentaje_default" value="0" min="0" max="100" step="0.01">
                        <small class="text-muted">Se aplica al precio sugerido al agregar un item nuevo.</small>
                    </div>
                    <div class="mb-3">
                        <label for="comprobante_tipo" class="form-label">Comprobante previsto</label>
                        <select class="form-select" id="comprobante_tipo" name="comprobante_tipo">
                            <option value="factura" <?= (string)($form_data['comprobante_tipo'] ?? 'factura') !== 'recibo' ? 'selected' : '' ?>>Factura fiscal</option>
                            <option value="recibo" <?= (string)($form_data['comprobante_tipo'] ?? 'factura') === 'recibo' ? 'selected' : '' ?>>Recibo interno (sin ARCA/AFIP)</option>
                        </select>
                        <small class="text-muted">Definí desde ahora si al convertirla en pedido debería facturarse o emitirse como recibo.</small>
                    </div>
                    <div class="alert alert-light border mb-3 py-2 small">
                        La cotización primero intenta reutilizar un cliente existente por <strong>CUIT, email, teléfono o nombre</strong>. Solo si no existe, se crea uno nuevo.
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4" placeholder="Notas internas, condiciones especiales, etc."><?= htmlspecialchars((string)($form_data['observaciones'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Items -->
    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Cotización</h5>
            <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
        </div>
        <div class="card-body">
            <div id="itemsContainer">
                <!-- Los items se agregan dinámicamente aquí -->
            </div>
            
            <div class="row mt-4">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <table class="table">
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-end"><span id="subtotal">$0.00</span></td>
                        </tr>
                        <tr>
                            <th>
                                <label for="descuento">Descuento:</label>
                            </th>
                            <td class="text-end">
                                <input type="number" class="form-control form-control-sm text-end" id="descuento" name="descuento" value="0" step="0.01" min="0" onchange="calcularTotales()">
                                <small id="descuento_lista_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr>
                            <th>
                                <label for="cupon_codigo">Cupón:</label>
                            </th>
                            <td class="text-end">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control text-end" id="cupon_codigo" name="cupon_codigo" placeholder="Código">
                                    <button class="btn btn-outline-secondary" type="button" onclick="aplicarCupon()">Aplicar</button>
                                </div>
                                <input type="hidden" id="cupon_descuento" name="cupon_descuento" value="0">
                                <small id="cupon_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr>
                            <th>Imp. incluidos:</th>
                            <td class="text-end">
                                <span id="impuestos_incluidos" class="text-muted">$0.00</span>
                                <small class="text-muted d-block">Informativo, ya contemplado en el precio.</small>
                            </td>
                        </tr>
                        <tr>
                            <th>Imp. adicionales:</th>
                            <td class="text-end">
                                <span id="impuestos_adicionales" class="text-danger">$0.00</span>
                                <small id="detalle_impuestos" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <th>TOTAL:</th>
                            <th class="text-end"><span id="total">$0.00</span></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        .modal-backdrop.show {
            opacity: 0.4;
            backdrop-filter: blur(3px);
            -webkit-backdrop-filter: blur(3px);
        }
        .modal-content {
            border-radius: 12px;
        }
    </style>

    <!-- Modal Agregar/Editar Item -->
    <div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header px-4 py-3">
                    <h5 class="modal-title" id="itemModalLabel">Agregar item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="itemModalForm">
                        <div class="row mb-3">
                            <div class="col-md-7">
                                <label class="form-label">Producto del catálogo</label>
                                <input type="text" class="form-control" list="productos-datalist" id="producto_input_modal" placeholder="Escriba para buscar..." oninput="cargarProductoDesdeModalInput()">
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" value="1" id="filtrar_propios" checked>
                                    <label class="form-check-label" for="filtrar_propios">Mostrar solo productos de fabricación propia</label>
                                </div>
                                <input type="hidden" id="producto_id_modal">
                                <small class="text-muted">O completá manualmente los campos.</small>
                            </div>
                            <div class="col-md-5">
                                <div id="precio-info-modal" class="alert alert-info mt-4" style="display:none; padding: 8px; margin: 0;"></div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Nombre del Producto *</label>
                                <input type="text" class="form-control" id="nombre_modal">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Descripción</label>
                                <input type="text" class="form-control" id="descripcion_modal">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Ancho (cm)</label>
                                <input type="number" class="form-control" id="ancho_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Alto (cm)</label>
                                <input type="number" class="form-control" id="alto_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Cant. *</label>
                                <input type="number" class="form-control" id="cantidad_modal" value="1" min="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Precio Unit. *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_modal" step="0.01" min="0" onchange="actualizarBasePrecioModal()">
                                </div>
                                <small class="text-muted">Se guarda como precio base, sin atributos.</small>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Desc. %</label>
                                <input type="number" class="form-control" id="descuento_pct_modal" step="0.01" min="0" max="100" value="0">
                            </div>
                        </div>

                        <!-- Atributos del producto -->
                        <div id="atributos-container-modal" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                            <h6 class="mb-3">🎨 Atributos del Producto</h6>
                            <div id="atributos-list-modal"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer px-4 py-3">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="guardarItemBtn">Guardar item</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 Crear Cotización</button>
        <a href="cotizaciones.php" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<script>
let itemIndex = 0;
const productos = <?= json_encode($productos) ?>;
const listaItems = <?= json_encode($lista_items_map) ?>;
const listaCategorias = <?= json_encode($lista_cat_map) ?>;
const clientesCot = <?= json_encode($clientes_cot) ?>;

function productoLabel(p) {
    const precioLabel = p.tipo_precio === 'variable'
        ? '(Precio variable)'
        : '($' + parseFloat(p.precio_base).toFixed(2) + ')';
    return `${p.nombre} ${precioLabel}`;
}

function obtenerListaSeleccionada() {
    const select = document.getElementById('lista_precio_id');
    return select ? parseInt(select.value || '0', 10) : 0;
}

function calcularPrecioConLista(productoId, precioBase) {
    const listaId = obtenerListaSeleccionada();
    if (!listaId) {
        return precioBase;
    }

    const item = listaItems?.[listaId]?.[productoId];
    if (item) {
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        const descItem = parseFloat(item.descuento_porcentaje || 0);
        if (precioNuevo > 0) {
            return precioNuevo;
        }
        if (descItem > 0) {
            return precioBase * (1 - descItem / 100);
        }
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    const categoriaId = producto?.categoria_id;
    const descCat = listaCategorias?.[listaId]?.[categoriaId] ?? 0;
    if (descCat > 0) {
        return precioBase * (1 - descCat / 100);
    }

    return precioBase;
}

function calcularDescuentoPorLista(productoId, precioBase) {
    const listaId = obtenerListaSeleccionada();
    const base = parseFloat(precioBase || 0);
    if (!listaId || !isFinite(base) || base <= 0) {
        return 0;
    }

    const item = listaItems?.[listaId]?.[productoId];
    if (item) {
        const descItem = parseFloat(item.descuento_porcentaje || 0);
        if (descItem > 0) {
            return Math.min(100, Math.max(0, descItem));
        }
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        if (precioNuevo > 0 && precioNuevo < base) {
            return Math.min(100, Math.max(0, ((base - precioNuevo) / base) * 100));
        }
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    const categoriaId = producto?.categoria_id;
    const descCat = parseFloat(listaCategorias?.[listaId]?.[categoriaId] ?? 0);
    if (descCat > 0) {
        return Math.min(100, Math.max(0, descCat));
    }

    return 0;
}

function obtenerDescuentoPorcentajeDefault() {
    const input = document.getElementById('descuento_porcentaje_default');
    const valor = parseFloat(input?.value || 0);
    if (!isFinite(valor) || valor <= 0) {
        return 0;
    }
    return Math.min(100, Math.max(0, valor));
}

function aplicarDescuentoDefault(precioBase) {
    const base = parseFloat(precioBase || 0);
    if (!isFinite(base) || base <= 0) {
        return 0;
    }
    const pct = obtenerDescuentoPorcentajeDefault();
    if (pct <= 0) {
        return base;
    }
    return Math.max(0, parseFloat((base * (1 - (pct / 100))).toFixed(2)));
}

function obtenerDescuentoInicialItem(productoId, precioBase) {
    const descLista = calcularDescuentoPorLista(productoId, precioBase);
    if (descLista > 0) {
        return descLista;
    }
    return obtenerDescuentoPorcentajeDefault();
}

function actualizarCostoAtributo(index, attrId, costoBase, costoOpcion, valorSeleccionado) {
    const inputCosto = document.getElementById(`attr_costo_${attrId}_${index}`);
    if (!inputCosto) return;

    const base = parseFloat(costoBase || 0);
    const opcion = parseFloat(costoOpcion || 0);
    const tieneValor = valorSeleccionado !== undefined && valorSeleccionado !== null && String(valorSeleccionado).trim() !== '';

    if (!tieneValor) {
        inputCosto.value = '0';
    } else if (opcion > 0) {
        inputCosto.value = opcion.toFixed(2);
    } else if (base > 0) {
        inputCosto.value = base.toFixed(2);
    } else {
        inputCosto.value = '0';
    }

    calcularTotales();
}

function asegurarDatalistProductos() {
    // Reconstruir datalist cada vez para respetar el filtro
    let existing = document.getElementById('productos-datalist');
    if (existing) existing.remove();
    const datalist = document.createElement('datalist');
    datalist.id = 'productos-datalist';
    const filterPropios = document.getElementById('filtrar_propios');
    const listaProductos = Array.isArray(productos) ? productos.filter(p => {
        try {
            if (!filterPropios) return true;
            if (!filterPropios.checked) return true;
            return (p.tipo_origen || '') === 'fabricacion_propia';
        } catch (e) { return true; }
    }) : [];
    listaProductos.forEach(p => {
        const option = document.createElement('option');
        option.value = productoLabel(p);
        datalist.appendChild(option);
    });
    document.body.appendChild(datalist);
    // cuando se cambie el checkbox, reconstruir
    if (filterPropios) {
        filterPropios.removeEventListener('change', asegurarDatalistProductos);
        filterPropios.addEventListener('change', asegurarDatalistProductos);
    }
}

let modalEditIndex = null;

function agregarItem() {
    abrirModalItem();
}

function abrirModalItem(editIndex = null) {
    try { console.debug('abrirModalItem', editIndex); } catch(e){}
    modalEditIndex = editIndex;
    asegurarDatalistProductos();
    resetearModalItem();
    // Guardar el elemento que tenía el foco antes de abrir el modal
    try {
        const prev = document.activeElement;
        // Almacenar referencia global temporal; se copiará al modal cuando exista
        window.__lastFocusedBeforeModal = prev;
    } catch (e) {}
    if (editIndex !== null && editIndex !== undefined) {
        const itemData = obtenerItemDesdeDOM(editIndex);
        if (itemData) {
            document.getElementById('itemModalLabel').textContent = 'Editar item';
            document.getElementById('producto_input_modal').value = itemData.productoLabel || '';
            document.getElementById('producto_id_modal').value = itemData.producto_id || '';
            document.getElementById('nombre_modal').value = itemData.nombre || '';
            document.getElementById('descripcion_modal').value = itemData.descripcion || '';
            document.getElementById('ancho_modal').value = itemData.ancho || '';
            document.getElementById('alto_modal').value = itemData.alto || '';
            document.getElementById('cantidad_modal').value = itemData.cantidad || 1;
            const precioInput = document.getElementById('precio_modal');
            precioInput.value = itemData.precio || '';
            precioInput.dataset.base = itemData.precio || '';
            const descuentoInput = document.getElementById('descuento_pct_modal');
            if (descuentoInput) descuentoInput.value = parseFloat(itemData.descuento_pct || 0).toFixed(2);
            if (itemData.producto_id) {
                cargarAtributosProductoModal(itemData.producto_id, itemData.atributos || []);
            }
        }
    } else {
        document.getElementById('itemModalLabel').textContent = 'Agregar item';
    }

    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
        if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        try { modalEl._previouslyFocused = window.__lastFocusedBeforeModal || null; } catch(e){}
        // Asegurar que no quede aria-hidden/inert del cierre anterior
        try { modalEl.removeAttribute('aria-hidden'); } catch(e){}
        try { if ('inert' in modalEl) modalEl.inert = false; } catch(e){}
        // Añadir listeners para asegurar foco antes de que bootstrap marque aria-hidden
        try {
            if (!modalEl._bsListenersAdded) {
                modalEl.addEventListener('hide.bs.modal', function() {
                    try {
                        const active = document.activeElement;
                        if (active && modalEl.contains(active)) {
                            const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
                            if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
                        }
                    } catch(e){}
                });
                modalEl.addEventListener('hidden.bs.modal', function() {
                    try { modalEl.setAttribute('aria-hidden', 'true'); } catch(e){}
                    try { if ('inert' in modalEl) modalEl.inert = true; } catch(e){}
                });
                modalEl._bsListenersAdded = true;
            }
        } catch(e){}
        modal.show();
        return;
    }
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    // restaurar atributos accesibilidad / inert si existe
    modalEl.removeAttribute('aria-hidden');
    try { if ('inert' in modalEl) modalEl.inert = false; } catch(e){}
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}

function resetearModalItem() {
    const form = document.getElementById('itemModalForm');
    if (form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        inputs.forEach(el => {
            const type = (el.type || '').toLowerCase();
            if (type === 'checkbox' || type === 'radio') {
                el.checked = false;
            } else {
                el.value = '';
            }
        });
    }
    const productoIdInput = document.getElementById('producto_id_modal');
    if (productoIdInput) productoIdInput.value = '';
    const precioInput = document.getElementById('precio_modal');
    if (precioInput) {
        precioInput.value = '';
        precioInput.dataset.base = '';
    }
    const info = document.getElementById('precio-info-modal');
    if (info) info.style.display = 'none';
    const attrsContainer = document.getElementById('atributos-list-modal');
    if (attrsContainer) attrsContainer.innerHTML = '';
    const attrsWrapper = document.getElementById('atributos-container-modal');
    if (attrsWrapper) attrsWrapper.style.display = 'none';
}

function obtenerProductoPorTexto(texto) {
    const textoNormalizado = texto.toLowerCase();
    return productos.find(p => productoLabel(p).toLowerCase() === textoNormalizado);
}
function cargarProductoDesdeModalInput() {
    const input = document.getElementById('producto_input_modal');
    const texto = input.value.trim();
    const producto = obtenerProductoPorTexto(texto);

    if (!producto) {
        document.getElementById('producto_id_modal').value = '';
        if (texto === '') {
            resetearModalItem();
        }
        return;
    }

    document.getElementById('producto_id_modal').value = producto.id;
    aplicarProductoModal(producto);
}

function aplicarProductoModal(producto) {
    document.getElementById('nombre_modal').value = producto.nombre;
    cargarAtributosProductoModal(producto.id);

    const precioInput = document.getElementById('precio_modal');
    const info = document.getElementById('precio-info-modal');

    if (producto.tipo_precio === 'fijo') {
        const precioBase = parseFloat(producto.precio_base || 0);
        const esNuevo = (modalEditIndex === null || modalEditIndex === undefined);
        const precioFinal = esNuevo ? aplicarDescuentoDefault(precioBase) : precioBase;
        precioInput.dataset.base = precioFinal.toFixed(2);
        precioInput.value = precioFinal.toFixed(2);
        const descuentoInput = document.getElementById('descuento_pct_modal');
        if (descuentoInput && esNuevo) {
            descuentoInput.value = obtenerDescuentoInicialItem(producto.id, precioBase).toFixed(2);
        }
        if (info) {
            const pct = obtenerDescuentoPorcentajeDefault();
            info.innerHTML = (esNuevo && pct > 0)
                ? ('✓ Precio fijo del producto con descuento por defecto (' + pct.toFixed(2) + '%)')
                : '✓ Precio fijo del producto';
            info.style.display = 'block';
        }
    } else {
        precioInput.value = '';
        precioInput.dataset.base = '';
        if (info) {
            info.innerHTML = '⚠️ Ingrese ancho y alto para calcular precio';
            info.style.display = 'block';
        }
    }
}

function cargarAtributosProductoModal(productoId, valoresExistentes = []) {
    fetch(`productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributosContainer = document.getElementById('atributos-list-modal');
            atributosContainer.innerHTML = '';

            if (data.atributos && data.atributos.length > 0) {
                document.getElementById('atributos-container-modal').style.display = 'block';

                data.atributos.forEach(attr => {
                    const valorPrevio = valoresExistentes.find(v => String(v.id) === String(attr.id));
                    const requerido = attr.es_obligatorio ? 'required' : '';
                    let inputHTML = '';

                    if (attr.tipo === 'text') {
                        inputHTML = `<input type="text" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'number') {
                        inputHTML = `<input type="number" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" step="0.01" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'color') {
                        const inputId = `modal_attr_${attr.id}`;
                        const previewId = `modal_color_preview_${attr.id}`;
                        inputHTML = `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="color" class="form-control form-control-color" id="${inputId}" data-attr-valor="${attr.id}" value="#000000" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">
                                <div class="border rounded" id="${previewId}" style="width: 28px; height: 28px; background-color: #000000;"></div>
                            </div>
                        `;
                    } else if (attr.tipo === 'select') {
                        const opciones = Array.isArray(attr.opciones) && attr.opciones.length > 0
                            ? attr.opciones.map(o => ({
                                valor: o.nombre,
                                color: o.color,
                                imagen: o.imagen,
                                costo: o.costo_adicional
                            }))
                            : (attr.valores ? attr.valores.split(',').map(v => ({ valor: v.trim() })) : []);

                        inputHTML = `
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                ${opciones.map((o, i) => {
                                    const hasColor = o.color && /^#[0-9A-Fa-f]{6}$/.test(o.color);
                                    const colorBox = hasColor ? `<div class="rounded" style="width: 80px; height: 80px; background-color: ${o.color}; border: 1px solid #ddd;"></div>` : '';
                                    const imgTag = o.imagen ? `<img src="../uploads/atributos/${o.imagen}" alt="${o.valor}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block;">` : '';
                                    const placeholder = !hasColor && !o.imagen ? `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 80px; height: 80px;"><small class="text-center text-muted">${o.valor}</small></div>` : '';
                                    const costoBadge = o.costo > 0 ? `<span class="badge bg-success position-absolute" style="top: -8px; right: -8px;">+$${parseFloat(o.costo).toFixed(2)}</span>` : '';
                                    const label = `<small class="d-block text-center mt-1 text-muted">${o.valor}</small>`;
                                    return `
                                        <div class="position-relative">
                                            <label class="cursor-pointer position-relative" style="cursor: pointer;">
                                                <input type="radio" name="modal_attr_${attr.id}" value="${o.valor}" class="d-none attr-radio" data-attr-id="${attr.id}" data-costo="${o.costo || 0}" ${requerido} onchange="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, this.dataset.costo, this.value); marcarOpcionAtributo(this);">
                                                <div class="attr-option position-relative" style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 4px; transition: all 0.2s ease; background: #fff;">
                                                    ${colorBox || imgTag || placeholder}
                                                    ${costoBadge}
                                                    ${label}
                                                </div>
                                            </label>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        `;
                    }

                    const attrHTML = `
                        <div class="mb-2 modal-attr-item" data-attr-id="${attr.id}" data-attr-nombre="${attr.nombre}" data-required="${attr.es_obligatorio ? 1 : 0}">
                            <label class="form-label small mb-1">
                                ${attr.nombre}
                                ${attr.costo_adicional > 0 ? `<span class="badge bg-warning text-dark">+$${parseFloat(attr.costo_adicional).toFixed(2)}</span>` : ''}
                            </label>
                            ${inputHTML}
                            <input type="hidden" id="modal_attr_costo_${attr.id}" value="0" data-base="${attr.costo_adicional}">
                        </div>
                    `;
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);

                    if (attr.tipo === 'color') {
                        const colorInput = document.getElementById(`modal_attr_${attr.id}`);
                        const preview = document.getElementById(`modal_color_preview_${attr.id}`);
                        if (colorInput && preview) {
                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                            colorInput.addEventListener('input', updatePreview);
                            updatePreview();
                        }
                    }
                });

                if (Array.isArray(valoresExistentes) && valoresExistentes.length > 0) {
                    valoresExistentes.forEach(v => {
                        const wrapper = document.querySelector(`.modal-attr-item[data-attr-id="${v.id}"]`);
                        if (!wrapper) return;
                        const radio = wrapper.querySelector(`input[type="radio"][value="${v.valor}"]`);
                        if (radio) {
                            radio.checked = true;
                            marcarOpcionAtributo(radio);
                        }
                        const input = wrapper.querySelector(`[data-attr-valor="${v.id}"]`);
                        if (input) {
                            input.value = v.valor || '';
                        }
                        const baseInput = document.getElementById(`modal_attr_costo_${v.id}`);
                        const baseCosto = baseInput ? baseInput.dataset.base : 0;
                        actualizarCostoAtributoModal(v.id, baseCosto || 0, v.costo || 0, v.valor);
                    });
                }
            } else {
                document.getElementById('atributos-container-modal').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando atributos:', error);
            document.getElementById('atributos-container-modal').style.display = 'none';
        });
}

function actualizarCostoAtributoModal(attrId, costoBase, costoOpcion, valorSeleccionado) {
    try { console.debug('actualizarCostoAtributoModal', attrId, costoBase, costoOpcion, valorSeleccionado); } catch(e){}
    const inputCosto = document.getElementById(`modal_attr_costo_${attrId}`);
    if (!inputCosto) return;

    const base = parseFloat(costoBase || 0);
    const opcion = parseFloat(costoOpcion || 0);
    const tieneValor = valorSeleccionado !== undefined && valorSeleccionado !== null && String(valorSeleccionado).trim() !== '';

    if (!tieneValor) {
        inputCosto.value = '0';
    } else if (opcion > 0) {
        inputCosto.value = opcion.toFixed(2);
    } else if (base > 0) {
        inputCosto.value = base.toFixed(2);
    } else {
        inputCosto.value = '0';
    }
}

function actualizarPrecioItemModal() {
    const productoId = document.getElementById('producto_id_modal')?.value;
    if (!productoId) return;

    const producto = productos.find(p => String(p.id) === String(productoId));
    if (producto?.tipo_precio === 'variable') {
        const ancho = parseFloat(document.getElementById('ancho_modal').value || 0);
        const alto = parseFloat(document.getElementById('alto_modal').value || 0);

        if (ancho > 0 && alto > 0) {
            fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        return;
                    }
                    const precioBase = parseFloat(data.precio || 0);
                    const esNuevo = (modalEditIndex === null || modalEditIndex === undefined);
                    const precioFinal = esNuevo ? aplicarDescuentoDefault(precioBase) : precioBase;
                    const precioInput = document.getElementById('precio_modal');
                    precioInput.dataset.base = precioFinal.toFixed(2);
                    precioInput.value = precioFinal.toFixed(2);
                    const descuentoInput = document.getElementById('descuento_pct_modal');
                    if (descuentoInput && esNuevo) {
                        descuentoInput.value = obtenerDescuentoInicialItem(productoId, precioBase).toFixed(2);
                    }
                    const info = document.getElementById('precio-info-modal');
                    if (info) {
                        const pct = obtenerDescuentoPorcentajeDefault();
                        info.innerHTML = (esNuevo && pct > 0)
                            ? ('✓ ' + data.precio_info + ' (con descuento por defecto ' + pct.toFixed(2) + '%)')
                            : ('✓ ' + data.precio_info);
                        info.style.display = 'block';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }
    }
}

function actualizarBasePrecioModal() {
    const precioInput = document.getElementById('precio_modal');
    if (!precioInput) return;
    precioInput.dataset.base = precioInput.value || '';
}

function obtenerItemDesdeDOM(index) {
    const row = document.getElementById(`item_${index}`);
    if (!row) return null;
    const getVal = (id) => document.getElementById(id)?.value || '';
    const productoId = getVal(`producto_id_${index}`);
    const producto = productos.find(p => String(p.id) === String(productoId));
    const atributos = [];

    row.querySelectorAll(`input[name^="items[${index}][atributos]"][name$="[nombre]"]`).forEach(input => {
        const match = input.name.match(/atributos\]\[(\d+)\]\[nombre\]/);
        if (!match) return;
        const attrId = match[1];
        const valorInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][valor]"]`);
        const costoInput = row.querySelector(`input[name="items[${index}][atributos][${attrId}][costo]"]`);
        const valor = valorInput?.value || '';
        const costo = parseFloat(costoInput?.value || 0) || 0;
        atributos.push({ id: attrId, nombre: input.value, valor, costo });
    });

    return {
        producto_id: productoId,
        productoLabel: producto ? productoLabel(producto) : '',
        nombre: getVal(`nombre_${index}`),
        descripcion: getVal(`descripcion_${index}`),
        ancho: getVal(`ancho_${index}`),
        alto: getVal(`alto_${index}`),
        cantidad: getVal(`cantidad_${index}`),
        precio: getVal(`precio_${index}`),
        descuento_pct: getVal(`descuento_pct_${index}`),
        atributos
    };
}

function obtenerAtributosDesdeModal() {
    const atributos = [];
    let faltan = false;

    document.querySelectorAll('#atributos-list-modal .modal-attr-item').forEach(wrapper => {
        const attrId = wrapper.dataset.attrId;
        const nombre = wrapper.dataset.attrNombre || '';
        const requerido = wrapper.dataset.required === '1';
        let valor = '';

        const radio = wrapper.querySelector('input[type="radio"]:checked');
        if (radio) {
            valor = radio.value;
        } else {
            const input = wrapper.querySelector(`[data-attr-valor="${attrId}"]`);
            if (input) valor = input.value;
        }

        if (requerido && !valor) {
            faltan = true;
        }

        const costoInput = document.getElementById(`modal_attr_costo_${attrId}`);
        const costo = parseFloat(costoInput?.value || 0) || 0;

        if (valor) {
            atributos.push({ id: attrId, nombre, valor, costo });
        }
    });

    if (faltan) {
        alert('Completa los atributos obligatorios antes de guardar.');
        return null;
    }

    return atributos;
}

function renderItemResumen(index, itemData) {
    const atributos = itemData.atributos || [];
    const atributosResumen = atributos.length
        ? atributos.map(a => `<span class="badge bg-light text-dark me-1">${a.nombre}: ${a.valor}${a.costo > 0 ? ` (+$${parseFloat(a.costo).toFixed(2)})` : ''}</span>`).join('')
        : '<span class="text-muted">Sin atributos</span>';

    const dimensiones = (itemData.ancho && itemData.alto)
        ? `${itemData.ancho} x ${itemData.alto} cm`
        : '—';
    const descuentoPct = Math.max(0, Math.min(100, parseFloat(itemData.descuento_pct || 0) || 0));

    const html = `
        <div class="card mb-3 item-row" id="item_${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <div class="item-resumen-title">${itemData.nombre || 'Producto sin nombre'}</div>
                        ${itemData.descripcion ? `<div class="item-resumen-meta">${itemData.descripcion}</div>` : ''}
                        <div class="item-resumen-meta">Cantidad: <strong>${itemData.cantidad}</strong> · Medidas: <strong>${dimensiones}</strong></div>
                        <div class="item-resumen-meta">Precio base: <strong>$${parseFloat(itemData.precio || 0).toFixed(2)}</strong></div>
                        <div class="item-resumen-meta">Descuento item: <strong id="descuento_pct_text_${index}">${descuentoPct.toFixed(2)}%</strong></div>
                        <div class="item-resumen-attrs mt-2">${atributosResumen}</div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-primary-subtle text-primary border" style="font-size: 0.95rem;">
                            Subtotal: $<span class="item-subtotal-text" id="item_subtotal_text_${index}">0.00</span>
                        </div>
                        <div class="mt-2">
                            <div class="input-group input-group-sm mb-2" style="max-width:160px; margin-left:auto;">
                                <span class="input-group-text">Desc %</span>
                                <input type="number" class="form-control text-end" id="descuento_pct_input_${index}" value="${descuentoPct.toFixed(2)}" min="0" max="100" step="0.01" onchange="actualizarDescuentoItem(${index}, this.value)">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalItem(${index})">✏️ Editar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem(${index})">🗑️ Eliminar</button>
                        </div>
                    </div>
                </div>

                <input type="hidden" class="item-nombre" id="nombre_${index}" name="items[${index}][nombre]" value="${itemData.nombre || ''}">
                <input type="hidden" id="descripcion_${index}" name="items[${index}][descripcion]" value="${itemData.descripcion || ''}">
                <input type="hidden" class="item-ancho" id="ancho_${index}" name="items[${index}][ancho]" value="${itemData.ancho || ''}">
                <input type="hidden" class="item-alto" id="alto_${index}" name="items[${index}][alto]" value="${itemData.alto || ''}">
                <input type="hidden" class="item-cantidad" id="cantidad_${index}" name="items[${index}][cantidad]" value="${itemData.cantidad || 1}">
                <input type="hidden" class="item-precio" id="precio_${index}" name="items[${index}][precio]" value="${itemData.precio || 0}" data-base="${itemData.precio || 0}">
                <input type="hidden" class="item-descuento-pct" id="descuento_pct_${index}" name="items[${index}][descuento_pct]" value="${descuentoPct.toFixed(2)}">
                <input type="hidden" id="producto_id_${index}" name="items[${index}][producto_id]" value="${itemData.producto_id || ''}">
                <input type="text" class="form-control item-subtotal" id="subtotal_${index}" readonly style="display:none;">
                <div id="precio-info-${index}" style="display:none;"></div>
                ${atributos.map(a => `
                    <input type="hidden" name="items[${index}][atributos][${a.id}][nombre]" value="${a.nombre}">
                    <input type="hidden" name="items[${index}][atributos][${a.id}][valor]" value="${a.valor}">
                    <input type="hidden" name="items[${index}][atributos][${a.id}][costo]" value="${parseFloat(a.costo || 0).toFixed(2)}">
                `).join('')}
            </div>
        </div>
    `;

    return html;
}


function guardarItemDesdeModal() {
    // Validar manualmente los campos requeridos del modal
    const nombre = document.getElementById('nombre_modal');
    const cantidad = document.getElementById('cantidad_modal');
    const precio = document.getElementById('precio_modal');
    if (!nombre.value.trim()) {
        alert('El nombre del producto es obligatorio.');
        nombre.focus();
        return;
    }
    if (!cantidad.value || parseInt(cantidad.value, 10) <= 0) {
        alert('Ingresá una cantidad válida.');
        cantidad.focus();
        return;
    }
    if (!precio.value || parseFloat(precio.value) <= 0) {
        alert('Ingresá un precio válido.');
        precio.focus();
        return;
    }

    const atributos = obtenerAtributosDesdeModal();
    if (atributos === null) return;

    const cantidadValue = parseInt(document.getElementById('cantidad_modal').value || '0', 10);
    const precioRaw = String(document.getElementById('precio_modal').value || '').replace(',', '.');
    const precioValue = parseFloat(precioRaw);

    if (!isFinite(cantidadValue) || cantidadValue <= 0) {
        alert('Ingresá una cantidad válida.');
        return;
    }
    if (!isFinite(precioValue) || precioValue <= 0) {
        alert('Ingresá un precio válido.');
        return;
    }

    const itemData = {
        producto_id: document.getElementById('producto_id_modal').value || '',
        nombre: document.getElementById('nombre_modal').value.trim(),
        descripcion: document.getElementById('descripcion_modal').value.trim(),
        ancho: document.getElementById('ancho_modal').value,
        alto: document.getElementById('alto_modal').value,
        cantidad: cantidadValue,
        precio: precioValue.toFixed(2),
        descuento_pct: Math.max(0, Math.min(100, parseFloat(document.getElementById('descuento_pct_modal')?.value || 0) || 0)),
        atributos
    };

    if (!itemData.nombre || !itemData.cantidad || !itemData.precio) {
        alert('Completa los campos obligatorios del item.');
        return;
    }

    try {
        let index = modalEditIndex;
        const itemsContainer = document.getElementById('itemsContainer');
        if (!itemsContainer) {
            alert('No se encontró el contenedor de items.');
            return;
        }
        if (!index) {
            itemIndex++;
            index = itemIndex;
            const html = renderItemResumen(index, itemData);
            itemsContainer.insertAdjacentHTML('beforeend', html);
        } else {
            const html = renderItemResumen(index, itemData);
            const row = document.getElementById(`item_${index}`);
            if (row) {
                row.outerHTML = html;
            }
        }
    } catch (err) {
        console.error('Error guardando item:', err);
        alert('No se pudo guardar el item.');
        return;
    }

    try {
        calcularTotales();
    } catch (err) {
        console.error('Error calculando totales:', err);
    }
    modalEditIndex = null;
    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
    if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
        // Antes de cerrar, restaurar o limpiar el foco
        try {
            const active = document.activeElement;
            if (active && modalEl.contains(active)) {
                const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
                if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
            }
        } catch(e){}
        if (modal) modal.hide();
        return;
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    // limpiar/restaurar foco antes de marcar como oculto
    try {
        const active = document.activeElement;
        if (active && modalEl.contains(active)) {
            const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
            if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
        }
    } catch(e){}
    // aplicar aria-hidden/inert en el siguiente tick, tras permitir que blur/focus se efectúe
    setTimeout(() => {
        try { modalEl.setAttribute('aria-hidden', 'true'); } catch(e){}
        try { if ('inert' in modalEl) modalEl.inert = true; } catch(e){}
    }, 0);
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
}

function eliminarItem(index) {
    try {
        const el = document.getElementById('item_' + index);
        if (el && typeof el.remove === 'function') el.remove();
    } catch(e) { console.error('eliminarItem error', e); }
    try { calcularTotales(); } catch(e) { console.error('calcularTotales error', e); }
}

function actualizarDescuentoItem(index, valor) {
    const pct = Math.max(0, Math.min(100, parseFloat(valor || 0) || 0));
    const hidden = document.getElementById(`descuento_pct_${index}`);
    const input = document.getElementById(`descuento_pct_input_${index}`);
    const text = document.getElementById(`descuento_pct_text_${index}`);
    if (hidden) hidden.value = pct.toFixed(2);
    if (input) input.value = pct.toFixed(2);
    if (text) text.textContent = pct.toFixed(2) + '%';
    calcularTotales();
}

const impuestosConfiguradosCotizacion = <?= json_encode($contabilidad_impuestos_activos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> || [];

function redondearMonedaCotizacion(valor) {
    return Math.round(((parseFloat(valor) || 0) + Number.EPSILON) * 100) / 100;
}

function calcularResumenImpuestosCotizacion(subtotalBase, totalBase) {
    let totalIncluidos = 0;
    let totalAdicionales = 0;
    const detalle = [];

    impuestosConfiguradosCotizacion.forEach((imp) => {
        if (!imp) return;
        const aplicaA = String(imp.aplica_a || 'ambos');
        if (aplicaA !== 'ambos' && aplicaA !== 'cotizacion') return;

        const base = String(imp.base_calculo || 'subtotal') === 'total' ? totalBase : subtotalBase;
        const valor = parseFloat(imp.valor || 0) || 0;
        const incluido = Number(imp.incluido_en_precio) === 1 || imp.incluido_en_precio === true || String(imp.incluido_en_precio) === '1';
        let monto = 0;

        if (String(imp.tipo_calculo || 'porcentaje') === 'fijo') {
            monto = valor;
        } else if (incluido) {
            monto = base > 0 ? base - (base / (1 + (valor / 100))) : 0;
        } else {
            monto = base * (valor / 100);
        }

        monto = redondearMonedaCotizacion(monto);
        if (monto <= 0) return;

        detalle.push({ nombre: imp.nombre || 'Impuesto', monto, incluido });
        if (incluido) {
            totalIncluidos += monto;
        } else {
            totalAdicionales += monto;
        }
    });

    totalIncluidos = redondearMonedaCotizacion(totalIncluidos);
    totalAdicionales = redondearMonedaCotizacion(totalAdicionales);

    return {
        detalle,
        totalIncluidos,
        totalAdicionales,
        totalConImpuestos: redondearMonedaCotizacion((parseFloat(totalBase) || 0) + totalAdicionales)
    };
}

function renderizarResumenImpuestosCotizacion(resumen) {
    const incluidosEl = document.getElementById('impuestos_incluidos');
    const adicionalesEl = document.getElementById('impuestos_adicionales');
    const detalleEl = document.getElementById('detalle_impuestos');

    if (incluidosEl) incluidosEl.textContent = '$' + Number(resumen.totalIncluidos || 0).toFixed(2);
    if (adicionalesEl) adicionalesEl.textContent = '$' + Number(resumen.totalAdicionales || 0).toFixed(2);
    if (detalleEl) {
        detalleEl.textContent = resumen.detalle && resumen.detalle.length
            ? resumen.detalle.map((imp) => `${imp.nombre}${imp.incluido ? ' (incluido)' : ''}: $${Number(imp.monto || 0).toFixed(2)}`).join(' · ')
            : 'Sin impuestos activos para esta cotización.';
    }
}

function calcularTotales() {
    let subtotal = 0;
    let descuentoListaTotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const precioInput = row.querySelector('.item-precio');
        if (precioInput && (precioInput.dataset.base === undefined || precioInput.dataset.base === '')) {
            precioInput.dataset.base = precioInput.value || '';
        }
        const precioBase = parseFloat(precioInput?.dataset.base || 0) || parseFloat(precioInput?.value || 0);
        let costoAtributos = 0;
        row.querySelectorAll('input[name*="[atributos]"][name$="[costo]"]').forEach(input => {
            costoAtributos += parseFloat(input.value || 0);
        });

        // NO modificar el input de precio - PHP se encargará de sumar atributos
        // Solo usar precioBase para el cálculo local
        const subtotalItem = cantidad * (precioBase + costoAtributos);

        // Actualizar subtotal del item
        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        const subtotalText = row.querySelector('.item-subtotal-text');
        if (subtotalText) {
            subtotalText.textContent = subtotalItem.toFixed(2);
        }

        subtotal += subtotalItem;

        const descuentoPct = parseFloat(row.querySelector('.item-descuento-pct')?.value || 0) || 0;
        const descuentoItem = (precioBase + costoAtributos) * cantidad * (descuentoPct / 100);
        descuentoListaTotal += descuentoItem;
    });

    const descuentoInput = document.getElementById('descuento');
    const descuentoInfo = document.getElementById('descuento_lista_info');

    if (descuentoInput) {
        descuentoInput.value = descuentoListaTotal.toFixed(2);
        if (descuentoInfo) {
            descuentoInfo.textContent = `Base: $${subtotal.toFixed(2)} | Descuento items: $${descuentoListaTotal.toFixed(2)}`;
        }
    } else if (descuentoInfo) {
        descuentoInfo.textContent = '';
    }

    const descuento = parseFloat(descuentoInput?.value || 0);
    const descuentoCupon = parseFloat(document.getElementById('cupon_descuento')?.value || 0);
    const baseFiscal = Math.max(0, subtotal - descuento - descuentoCupon);
    const resumenImpuestos = calcularResumenImpuestosCotizacion(baseFiscal, baseFiscal);

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    renderizarResumenImpuestosCotizacion(resumenImpuestos);
    document.getElementById('total').textContent = '$' + Number(resumenImpuestos.totalConImpuestos || 0).toFixed(2);
}

function aplicarCupon() {
    const codigo = document.getElementById('cupon_codigo')?.value?.trim() || '';
    const info = document.getElementById('cupon_info');
    const descuentoInput = document.getElementById('cupon_descuento');
    const subtotalText = document.getElementById('subtotal')?.textContent || '$0';
    const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;

    if (!codigo) {
        if (info) info.textContent = 'Ingresá un cupón.';
        if (descuentoInput) descuentoInput.value = '0';
        calcularTotales();
        return;
    }

    fetch(`cupones_validar.php?codigo=${encodeURIComponent(codigo)}&subtotal=${subtotal}`)
        .then(r => r.json())
        .then(data => {
            if (!data.valido) {
                if (info) info.textContent = data.mensaje || 'Cupón inválido.';
                if (descuentoInput) descuentoInput.value = '0';
            } else {
                if (descuentoInput) descuentoInput.value = data.descuento || 0;
                if (info) info.textContent = data.mensaje
                    ? `${data.mensaje} (-$${Number(data.descuento || 0).toFixed(2)})`
                    : `Descuento aplicado: $${Number(data.descuento || 0).toFixed(2)}`;
            }
            calcularTotales();
        })
        .catch(() => {
            if (info) info.textContent = 'No se pudo validar el cupón.';
        });
}

function marcarOpcionAtributo(radio) {
    try { console.debug('marcarOpcionAtributo', radio && (radio.name || radio)); } catch(e){}
    if (!radio || !radio.name) return;
    
    // Encontrar el contenedor de opciones del atributo (el padre más cercano con flex wrap)
    const label = radio.closest('label');
    if (!label) return;
    
    const divPadre = label.parentElement;
    if (!divPadre || !divPadre.parentElement) return;
    
    const contenedorOpciones = divPadre.parentElement;
    
    // Desmarcar todas las opciones del mismo atributo
    contenedorOpciones.querySelectorAll('input[type="radio"][name="' + radio.name + '"]').forEach(r => {
        const l = r.closest('label');
        if (l) {
            const divOpcion = l.querySelector('.attr-option');
            if (divOpcion) {
                divOpcion.style.borderColor = '#ddd';
                divOpcion.style.boxShadow = 'none';
                divOpcion.style.background = '#fff';
            }
        }
    });
    
    // Marcar la opción seleccionada
    if (radio.checked) {
        const divOpcion = label.querySelector('.attr-option');
        if (divOpcion) {
            divOpcion.style.borderColor = '#0d6efd';
            divOpcion.style.boxShadow = '0 0 0 2px rgba(13,110,253,.2)';
            divOpcion.style.background = '#e7f1ff';
        }
    }
}

document.addEventListener('change', function(e) {
    const radio = e.target;
    if (radio && radio.matches('input[type="radio"].attr-radio')) {
        marcarOpcionAtributo(radio);
    }
});



function normalizarClienteTexto(valor) {
    return String(valor || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function renderClienteOptions(filtro = '') {
    const select = document.getElementById('cliente_id');
    if (!select) return;

    const selectedValue = String(select.value || '');
    const termino = normalizarClienteTexto(filtro);
    const filtrados = !termino
        ? clientesCot
        : clientesCot.filter(cliente => {
            const texto = normalizarClienteTexto([
                cliente.nombre || '',
                cliente.email || '',
                cliente.telefono || '',
                cliente.direccion || '',
                cliente.cuit || ''
            ].join(' '));
            return texto.includes(termino);
        });

    select.innerHTML = '<option value="">-- Buscar / seleccionar cliente --</option>';
    filtrados.forEach(cliente => {
        const option = document.createElement('option');
        option.value = cliente.id;
        option.textContent = `${cliente.nombre || 'Cliente'}${cliente.telefono ? ' · ' + cliente.telefono : ''}${cliente.email ? ' · ' + cliente.email : ''}`;
        if (String(cliente.id) === selectedValue) {
            option.selected = true;
        }
        select.appendChild(option);
    });

    actualizarEstadoCliente(filtrados.length, termino);
}

function actualizarEstadoCliente(cantidad = null, termino = '') {
    const estado = document.getElementById('cliente_match_status');
    const select = document.getElementById('cliente_id');
    if (!estado || !select) return;

    if (select.value) {
        estado.innerHTML = '✅ Cliente existente seleccionado. Se reutilizará esta ficha para evitar duplicados.';
        return;
    }

    if (termino && cantidad === 0) {
        estado.innerHTML = '➕ No se encontró un cliente con esa búsqueda. Completá los datos y se dará de alta al guardar.';
        return;
    }

    estado.innerHTML = 'Primero buscá un cliente existente. Si no aparece, completá los datos de abajo y se dará de alta al guardar.';
}

function aplicarListaPrecios() {
    document.querySelectorAll('.item-row').forEach(row => {
        const idxMatch = (row.id || '').match(/item_(\d+)/);
        if (!idxMatch) return;
        const index = parseInt(idxMatch[1], 10);
        const productoId = document.getElementById(`producto_id_${index}`)?.value;
        const precioInput = document.getElementById(`precio_${index}`);
        const precioBase = parseFloat(precioInput?.dataset.base || precioInput?.value || 0) || 0;
        if (!productoId || precioBase <= 0) return;
        const pct = obtenerDescuentoInicialItem(productoId, precioBase);
        const hidden = document.getElementById(`descuento_pct_${index}`);
        const input = document.getElementById(`descuento_pct_input_${index}`);
        const text = document.getElementById(`descuento_pct_text_${index}`);
        if (hidden) hidden.value = pct.toFixed(2);
        if (input) input.value = pct.toFixed(2);
        if (text) text.textContent = pct.toFixed(2) + '%';
    });
    calcularTotales();
}

function autocompletarCliente() {
    const select = document.getElementById('cliente_id');
    const clienteId = select ? parseInt(select.value || '0', 10) : 0;
    if (!clienteId) {
        actualizarEstadoCliente();
        return;
    }
    const cliente = clientesCot.find(c => String(c.id) === String(clienteId));
    if (!cliente) {
        return;
    }
    const buscarInput = document.getElementById('buscar_cliente');
    const nombreInput = document.getElementById('nombre_cliente');
    const emailInput = document.getElementById('email');
    const telefonoInput = document.getElementById('telefono');
    const direccionInput = document.getElementById('direccion');
    const esEmpresaInput = document.getElementById('es_empresa');
    const cuitInput = document.getElementById('cuit');
    const facturaAInput = document.getElementById('factura_a');

    if (buscarInput && cliente.nombre) buscarInput.value = cliente.nombre;

    if (nombreInput && cliente.nombre) nombreInput.value = cliente.nombre;
    if (emailInput && cliente.email) emailInput.value = cliente.email;
    if (telefonoInput && cliente.telefono) telefonoInput.value = cliente.telefono;
    if (direccionInput && cliente.direccion) direccionInput.value = cliente.direccion;
    if (esEmpresaInput) esEmpresaInput.checked = String(cliente.es_empresa || '0') === '1';
    if (cuitInput) cuitInput.value = cliente.cuit || '';
    if (facturaAInput) facturaAInput.checked = String(cliente.factura_a || '0') === '1';
    toggleEmpresaFields();
    actualizarEstadoCliente();
}

function toggleEmpresaFields() {
    const esEmpresa = document.getElementById('es_empresa');
    const facturaA = document.getElementById('factura_a');
    const cuit = document.getElementById('cuit');
    const wrapper = document.getElementById('empresaFields');
    const comprobanteTipo = document.getElementById('comprobante_tipo');
    const esRecibo = !!(comprobanteTipo && comprobanteTipo.value === 'recibo');
    const activo = !!(esEmpresa && esEmpresa.checked);
    if (wrapper) {
        wrapper.style.display = activo ? '' : 'none';
    }
    if (facturaA) {
        if (!activo || esRecibo) {
            facturaA.checked = false;
        }
        facturaA.disabled = esRecibo;
    }
    if (cuit) {
        cuit.required = !!(activo && facturaA && facturaA.checked && !esRecibo);
    }
}

// Inicialización
document.addEventListener('DOMContentLoaded', function() {
    const comprobanteTipo = document.getElementById('comprobante_tipo');
    if (comprobanteTipo) {
        comprobanteTipo.addEventListener('change', toggleEmpresaFields);
    }
    toggleEmpresaFields();
    const clienteSearch = document.getElementById('buscar_cliente');
    const clienteSelect = document.getElementById('cliente_id');

    renderClienteOptions(clienteSearch ? clienteSearch.value : '');

    if (clienteSearch) {
        clienteSearch.addEventListener('input', function() {
            renderClienteOptions(this.value || '');
        });
    }

    if (clienteSelect && clienteSelect.value) {
        autocompletarCliente();
    } else {
        actualizarEstadoCliente();
    }

    aplicarListaPrecios();
    const btnGuardar = document.getElementById('guardarItemBtn');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarItemDesdeModal);
    }
    const formModal = document.getElementById('itemModalForm') || document.querySelector('#itemModal form');
    if (formModal && formModal.tagName === 'FORM') {
        formModal.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarItemDesdeModal();
        });
    }
    const formCot = document.getElementById('formCotizacion');
    if (formCot) {
        formCot.addEventListener('submit', function(e) {
            e.preventDefault();
            const items = document.querySelectorAll('.item-row');
            if (!items || items.length === 0) {
                alert('Debes agregar al menos un item antes de guardar la cotización.');
                return false;
            }
            try {
                const itemsArray = collectItemsForSubmit();
                const fd = new FormData(formCot);
                fd.set('items_json', JSON.stringify(itemsArray));
                // Indicador para backend si fuera necesario
                fd.set('ajax', '1');
                const submitBtn = formCot.querySelector('button[type="submit"]');
                if (submitBtn) submitBtn.disabled = true;
                console.debug('Enviando AJAX a cotizacion_save_ajax.php, items length:', itemsArray.length, 'formData keys:', Array.from(fd.keys()));
                fetch('./cotizacion_save_ajax.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(r => r.text().then(t => ({ status: r.status, text: t })))
                    .then(resp => {
                        if (submitBtn) submitBtn.disabled = false;
                        console.debug('AJAX response status', resp.status, 'text:', resp.text);
                        try {
                            const data = JSON.parse(resp.text);
                            if (!data || !data.success) {
                                alert(data && data.error ? data.error : 'Error guardando cotización');
                                return;
                            }
                            if (data.redirect) window.location.href = data.redirect; else window.location.href = 'cotizaciones.php?mensaje=creada';
                        } catch (e) {
                            alert('Respuesta inválida del servidor. Ver consola para detalles.');
                            console.error('Invalid JSON response from server:', resp.text);
                        }
                    })
                    .catch(err => {
                        if (submitBtn) submitBtn.disabled = false;
                        console.error('Error en AJAX save:', err);
                        alert('No se pudo guardar la cotización (error de red). Revisa la consola y admin/logs/cotizacion_save_ajax.log');
                    });
            } catch(e) {
                console.error('Error serializing items for submit', e);
                alert('Error preparando la cotización para enviar.');
            }
            return false;
        });
        // Force submit on button click to bypass other submit handlers that call preventDefault()
        try {
            const submitBtn = formCot.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.addEventListener('click', function(e) {
                    const items = document.querySelectorAll('.item-row');
                    if (!items || items.length === 0) {
                        e.preventDefault();
                        alert('Debes agregar al menos un item antes de guardar la cotización.');
                        return false;
                    }
                    // preparar items JSON para el servidor
                    try {
                        const itemsArray = collectItemsForSubmit();
                        let input = document.querySelector('input[name="items_json"]');
                        if (!input) {
                            input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'items_json';
                            formCot.appendChild(input);
                        }
                        input.value = JSON.stringify(itemsArray);
                    } catch(e) { console.error('Error serializing items for submit', e); }
                    // prevenir el submit normal y forzar envío nativo (evita submit listeners)
                    e.preventDefault();
                    try { setTimeout(() => formCot.submit(), 0); } catch(err) { formCot.submit(); }
                });
            }
        } catch(e) {}
    }
});

function collectItemsForSubmit() {
    const items = [];
    document.querySelectorAll('.item-row').forEach(row => {
        const idMatch = (row.id || '').split('_')[1];
        const idx = idMatch;
        const nombre = document.getElementById(`nombre_${idx}`)?.value || '';
        const descripcion = document.getElementById(`descripcion_${idx}`)?.value || '';
        const ancho = document.getElementById(`ancho_${idx}`)?.value || '';
        const alto = document.getElementById(`alto_${idx}`)?.value || '';
        const cantidad = document.getElementById(`cantidad_${idx}`)?.value || 0;
        const precio = document.getElementById(`precio_${idx}`)?.value || 0;
        const descuento_pct = document.getElementById(`descuento_pct_${idx}`)?.value || 0;
        const producto_id = document.getElementById(`producto_id_${idx}`)?.value || null;

        // recoger atributos asociados (si los hay)
        const atributos = [];
        // buscar inputs con nombre que empiece por items[idx][atributos]
        try {
            const attrInputs = row.querySelectorAll(`input[name^="items[${idx}][atributos]"]`);
            // agrupar por id
            const grouped = {};
            attrInputs.forEach(inp => {
                const name = inp.name; // e.g. items[1][atributos][3][nombre]
                const m = name.match(/items\[\d+\]\[atributos\]\[(\d+)\]\[(\w+)\]/);
                if (m) {
                    const aid = m[1]; const key = m[2];
                    grouped[aid] = grouped[aid] || {};
                    grouped[aid][key] = inp.value;
                }
            });
            Object.keys(grouped).forEach(aid => {
                const g = grouped[aid];
                atributos.push({ id: aid, nombre: g.nombre || '', valor: g.valor || '', costo: parseFloat(g.costo || 0) });
            });
        } catch(e) {}

        items.push({ producto_id: producto_id || null, nombre, descripcion, ancho: ancho || null, alto: alto || null, cantidad: parseInt(cantidad || 0,10), precio: parseFloat(precio || 0), descuento_pct: parseFloat(descuento_pct || 0), atributos });
    });
    return items;
}
</script>

<?php require 'includes/footer.php'; ?>
