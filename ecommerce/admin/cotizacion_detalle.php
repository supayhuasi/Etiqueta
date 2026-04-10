<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

try {
    $cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('dni', $cols_cot, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN dni VARCHAR(20) NULL AFTER telefono");
    }
    if (!in_array('cuit', $cols_cot, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER dni");
    }
    if (!in_array('factura_a', $cols_cot, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
    }
    if (!in_array('es_empresa', $cols_cot, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
    }
} catch (Exception $e) {
}

try {
    $cols_cli_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('dni', $cols_cli_cot, true)) {
        $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN dni VARCHAR(20) NULL AFTER telefono");
    }
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
} catch (Exception $e) {
}

try {
    $cols_ped = $pdo->query("SHOW COLUMNS FROM ecommerce_pedidos")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!in_array('factura_archivo', $cols_ped, true)) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN factura_archivo VARCHAR(255) NULL AFTER public_token");
    }
    if (!in_array('factura_nombre_original', $cols_ped, true)) {
        $pdo->exec("ALTER TABLE ecommerce_pedidos ADD COLUMN factura_nombre_original VARCHAR(255) NULL AFTER factura_archivo");
    }
} catch (Exception $e) {
}

if (!function_exists('cotizacion_detalle_table_exists')) {
    function cotizacion_detalle_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('cotizacion_detalle_column_exists')) {
    function cotizacion_detalle_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
            $stmt->execute([$table, $column]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('cotizacion_sync_crm_estado')) {
    function cotizacion_sync_crm_estado(PDO $pdo, array $cotizacion, string $nuevoEstado, ?int $pedidoId = null): void
    {
        $crmId = (int)($cotizacion['crm_id'] ?? 0);
        if ($crmId <= 0 || !cotizacion_detalle_table_exists($pdo, 'ecommerce_crm_visitas')) {
            return;
        }

        $mapEstado = [
            'convertida' => 'ganado',
            'rechazada' => 'perdido',
            'aceptada' => 'negociacion',
            'enviada' => 'propuesta',
            'pendiente' => 'propuesta',
        ];

        if (!isset($mapEstado[$nuevoEstado])) {
            return;
        }

        $estadoCrm = $mapEstado[$nuevoEstado];
        $set = ['estado = ?'];
        $params = [$estadoCrm];

        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'ultima_gestion')) {
            $set[] = 'ultima_gestion = NOW()';
        }
        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'fecha_cierre')) {
            $set[] = 'fecha_cierre = ?';
            $params[] = in_array($estadoCrm, ['ganado', 'perdido'], true) ? date('Y-m-d') : null;
        }
        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_id')) {
            $set[] = 'ultima_cotizacion_id = ?';
            $params[] = (int)($cotizacion['id'] ?? 0);
        }
        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_numero')) {
            $set[] = 'ultima_cotizacion_numero = ?';
            $params[] = (string)($cotizacion['numero_cotizacion'] ?? ('COT-' . (int)($cotizacion['id'] ?? 0)));
        }
        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'fecha_ultima_cotizacion')) {
            $set[] = 'fecha_ultima_cotizacion = NOW()';
        }
        if (cotizacion_detalle_column_exists($pdo, 'ecommerce_crm_visitas', 'monto_estimado')) {
            $set[] = 'monto_estimado = CASE WHEN ? > 0 THEN ? ELSE monto_estimado END';
            $params[] = (float)($cotizacion['total'] ?? 0);
            $params[] = (float)($cotizacion['total'] ?? 0);
        }

        $params[] = $crmId;
        $stmt = $pdo->prepare('UPDATE ecommerce_crm_visitas SET ' . implode(', ', $set) . ' WHERE id = ?');
        $stmt->execute($params);

        if (($nuevoEstado === 'convertida' || $nuevoEstado === 'rechazada') && cotizacion_detalle_table_exists($pdo, 'ecommerce_crm_seguimientos')) {
            $stmt = $pdo->prepare('SELECT visita_id FROM ecommerce_crm_visitas WHERE id = ? LIMIT 1');
            $stmt->execute([$crmId]);
            $visitaId = (int)$stmt->fetchColumn();

            $resultado = $nuevoEstado === 'convertida' ? 'cerrado' : 'descartado';
            $comentario = $nuevoEstado === 'convertida'
                ? 'Cotización ' . (string)($cotizacion['numero_cotizacion'] ?? ('COT-' . (int)($cotizacion['id'] ?? 0))) . ' convertida a pedido' . ($pedidoId ? ' #' . $pedidoId : '') . '.'
                : 'Cotización ' . (string)($cotizacion['numero_cotizacion'] ?? ('COT-' . (int)($cotizacion['id'] ?? 0))) . ' marcada como rechazada.';

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM ecommerce_crm_seguimientos WHERE crm_id = ? AND canal = 'cotizacion' AND resultado = ? AND comentario = ?");
            $stmt->execute([$crmId, $resultado, $comentario]);
            $existe = (int)$stmt->fetchColumn() > 0;

            if (!$existe) {
                $stmt = $pdo->prepare("INSERT INTO ecommerce_crm_seguimientos (crm_id, visita_id, usuario_id, canal, resultado, comentario, proximo_contacto)
                    VALUES (?, ?, ?, 'cotizacion', ?, ?, NULL)");
                $stmt->execute([
                    $crmId,
                    $visitaId > 0 ? $visitaId : 0,
                    !empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null,
                    $resultado,
                    $comentario,
                ]);
            }
        }
    }
}

$id = intval($_GET['id'] ?? 0);

// Obtener cotización (compatible con empresa o direccion)
$stmt = $pdo->prepare("SELECT c.*, cc.nombre AS cliente_nombre, cc.email AS cliente_email, cc.telefono AS cliente_telefono FROM ecommerce_cotizaciones c LEFT JOIN ecommerce_cotizacion_clientes cc ON c.cliente_id = cc.id WHERE c.id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

// Agregar campo empresa/direccion según qué columna exista
if ($cotizacion && !empty($cotizacion['cliente_id'])) {
    try {
        $cols_stmt = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes");
        $cols = $cols_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        $select_cols = [];
        if (in_array('empresa', $cols, true)) {
            $select_cols[] = 'empresa';
        }
        if (in_array('direccion', $cols, true)) {
            $select_cols[] = 'direccion';
        }
        if (in_array('dni', $cols, true)) {
            $select_cols[] = 'dni';
        }
        if (in_array('cuit', $cols, true)) {
            $select_cols[] = 'cuit';
        }
        if (in_array('factura_a', $cols, true)) {
            $select_cols[] = 'factura_a';
        }
        if (in_array('es_empresa', $cols, true)) {
            $select_cols[] = 'es_empresa';
        }

        if (!empty($select_cols)) {
            $stmt_extra = $pdo->prepare("SELECT " . implode(', ', $select_cols) . " FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
            $stmt_extra->execute([$cotizacion['cliente_id']]);
            $extra_data = $stmt_extra->fetch(PDO::FETCH_ASSOC) ?: [];

            if (array_key_exists('empresa', $extra_data)) {
                $cotizacion['cliente_empresa'] = $extra_data['empresa'];
            }
            if (array_key_exists('direccion', $extra_data)) {
                $cotizacion['cliente_direccion'] = $extra_data['direccion'];
            }
            if (array_key_exists('dni', $extra_data)) {
                $cotizacion['cliente_dni'] = $extra_data['dni'];
            }
            if (array_key_exists('cuit', $extra_data)) {
                $cotizacion['cliente_cuit'] = $extra_data['cuit'];
            }
            if (array_key_exists('factura_a', $extra_data)) {
                $cotizacion['cliente_factura_a'] = $extra_data['factura_a'];
            }
            if (array_key_exists('es_empresa', $extra_data)) {
                $cotizacion['cliente_es_empresa'] = $extra_data['es_empresa'];
            }
        }
    } catch (Exception $e) {
        // Ignorar si la tabla/columnas no están disponibles
    }
}
// Agregar validaciones y conversión a pedido

if (!$cotizacion) {
    die("Cotización no encontrada");
}

$lista_precio = null;
if (!empty($cotizacion['lista_precio_id'])) {
    $stmt = $pdo->prepare("SELECT nombre FROM ecommerce_listas_precios WHERE id = ?");
    $stmt->execute([$cotizacion['lista_precio_id']]);
    $lista_precio = $stmt->fetch(PDO::FETCH_ASSOC);
}

$items = json_decode($cotizacion['items'], true) ?? [];
$mensaje = $_GET['mensaje'] ?? '';
$error = '';
$impuestos_cotizacion = [];
$impuestos_incluidos_cotizacion = (float)($cotizacion['impuestos_incluidos'] ?? 0);
$impuestos_adicionales_cotizacion = (float)($cotizacion['impuestos_adicionales'] ?? 0);
if (!empty($cotizacion['impuestos_json'])) {
    $impuestos_cotizacion = json_decode((string)$cotizacion['impuestos_json'], true) ?: [];
}
if (empty($impuestos_cotizacion)) {
    $baseCotizacionFiscal = max(0, (float)($cotizacion['subtotal'] ?? 0) - (float)($cotizacion['descuento'] ?? 0) - (float)($cotizacion['cupon_descuento'] ?? 0));
    $resumenCotizacionFiscal = contabilidad_calcular_impuestos(contabilidad_get_impuestos($pdo, true), $baseCotizacionFiscal, $baseCotizacionFiscal, 'cotizacion');
    $impuestos_cotizacion = $resumenCotizacionFiscal['detalle'] ?? [];
    $impuestos_incluidos_cotizacion = (float)($resumenCotizacionFiscal['total_incluidos'] ?? 0);
    $impuestos_adicionales_cotizacion = (float)($resumenCotizacionFiscal['total_adicionales'] ?? 0);
}

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'convertir_pedido') {
                if ($cotizacion['estado'] === 'convertida') {
                    throw new Exception('La cotización ya fue convertida');
                }

                $items = json_decode($cotizacion['items'], true) ?? [];
                if (empty($items)) {
                    throw new Exception('La cotización no tiene items');
                }

                foreach ($items as $it) {
                    if (empty($it['producto_id'])) {
                        throw new Exception('Todos los items deben pertenecer a un producto para convertir');
                    }
                }

                $pdo->beginTransaction();

                // Resolver cliente - usar siempre los datos de la cotización
                // Los datos pueden venir del JOIN con cotizacion_clientes o de los campos directos de la cotización
                $email = trim((string)($cotizacion['cliente_email'] ?? $cotizacion['email'] ?? ''));
                $nombre = trim((string)($cotizacion['cliente_nombre'] ?? $cotizacion['nombre_cliente'] ?? ''));
                $telefono = trim((string)($cotizacion['cliente_telefono'] ?? $cotizacion['telefono'] ?? ''));
                // Compatibilidad con empresa/direccion
                $direccion = trim((string)($cotizacion['cliente_direccion'] ?? $cotizacion['direccion'] ?? $cotizacion['cliente_empresa'] ?? $cotizacion['empresa'] ?? ''));
                $dni = preg_replace('/\D+/', '', trim((string)($cotizacion['cliente_dni'] ?? $cotizacion['dni'] ?? '')));
                $cuit = preg_replace('/\D+/', '', trim((string)($cotizacion['cliente_cuit'] ?? $cotizacion['cuit'] ?? '')));
                $es_empresa = (int)($cotizacion['cliente_es_empresa'] ?? $cotizacion['es_empresa'] ?? 0);
                $factura_a = (int)($cotizacion['cliente_factura_a'] ?? $cotizacion['factura_a'] ?? 0);

                if ($direccion === '') {
                    throw new Exception('Domicilio obligatorio para convertir la cotización en pedido');
                }
                if ($es_empresa && $factura_a && strlen($cuit) !== 11) {
                    throw new Exception('Para empresa con Factura A se requiere CUIT válido (11 dígitos)');
                }

                $comprobante_tipo = contabilidad_normalizar_comprobante_tipo((string)($cotizacion['comprobante_tipo'] ?? 'factura'));
                if ($comprobante_tipo === 'recibo') {
                    $factura_a = 0;
                }

                $documento_tipo = null;
                $documento_numero = null;
                if ($es_empresa && $cuit !== '') {
                    $documento_tipo = 'CUIT';
                    $documento_numero = $cuit;
                } elseif ($dni !== '') {
                    $documento_tipo = 'DNI';
                    $documento_numero = $dni;
                }

                $email_normalizado = strtolower(trim((string)$email));
                if ($email_normalizado === '') {
                    $email_normalizado = 'cotizacion-' . date('YmdHis') . '-' . rand(1000, 9999) . '@cliente.local';
                }

                $facturaAdjunta = $_FILES['factura_adjunto'] ?? null;
                $subirFactura = $facturaAdjunta && isset($facturaAdjunta['error']) && (int)$facturaAdjunta['error'] !== UPLOAD_ERR_NO_FILE;
                $facturaOriginal = null;
                $facturaExt = null;
                if ($subirFactura) {
                    if ((int)$facturaAdjunta['error'] !== UPLOAD_ERR_OK) {
                        throw new Exception('No se pudo cargar el archivo de factura');
                    }
                    if ((int)$facturaAdjunta['size'] > 5 * 1024 * 1024) {
                        throw new Exception('La factura adjunta no puede superar 5MB');
                    }
                    $facturaOriginal = trim((string)$facturaAdjunta['name']);
                    $ext = strtolower(pathinfo($facturaOriginal, PATHINFO_EXTENSION));
                    $permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                    if (!in_array($ext, $permitidas, true)) {
                        throw new Exception('Formato de factura no permitido. Usá PDF/JPG/PNG/WEBP');
                    }
                    $facturaExt = $ext;
                }

                $row = null;
                if ($email_normalizado !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE email = ? LIMIT 1");
                    $stmt->execute([$email_normalizado]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!$row && $telefono !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE telefono = ? LIMIT 1");
                    $stmt->execute([$telefono]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }
                if (!$row && $documento_numero !== null) {
                    $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE documento_numero = ? LIMIT 1");
                    $stmt->execute([$documento_numero]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                }

                if ($row) {
                    // Cliente existe, usar ese ID
                    $cliente_id = (int)$row['id'];
                    // Actualizar datos del cliente con la info más reciente
                    try {
                        $stmt = $pdo->prepare("
                            UPDATE ecommerce_clientes 
                            SET nombre = ?, email = ?, telefono = ?, direccion = ?, responsabilidad_fiscal = ?, documento_tipo = ?, documento_numero = ?
                            WHERE id = ?
                        ");
                        $stmt->execute([
                            $nombre ?: ($telefono ?: 'Cliente'),
                            $email_normalizado,
                            $telefono ?: null,
                            $direccion,
                            $factura_a ? 'Responsable Inscripto' : 'Consumidor Final',
                            $documento_tipo,
                            $documento_numero,
                            $cliente_id,
                        ]);
                    } catch (Exception $e) {
                        $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET nombre = ?, email = ? WHERE id = ?");
                        $stmt->execute([$nombre ?: ($telefono ?: 'Cliente'), $email_normalizado, $cliente_id]);
                    }
                } else {
                    // Cliente no existe, crear uno nuevo
                    try {
                        $stmt = $pdo->prepare("
                            INSERT INTO ecommerce_clientes (telefono, nombre, email, direccion, responsabilidad_fiscal, documento_tipo, documento_numero)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $telefono ?: null,
                            $nombre ?: ($telefono ?: 'Cliente'),
                            $email_normalizado,
                            $direccion,
                            $factura_a ? 'Responsable Inscripto' : 'Consumidor Final',
                            $documento_tipo,
                            $documento_numero,
                        ]);
                    } catch (Exception $e) {
                        $stmt = $pdo->prepare("INSERT INTO ecommerce_clientes (telefono, nombre, email) VALUES (?, ?, ?)");
                        $stmt->execute([$telefono ?: null, $nombre ?: ($telefono ?: 'Cliente'), $email_normalizado]);
                    }
                    $cliente_id = (int)$pdo->lastInsertId();
                }

                $numero_pedido = 'PED-COT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $metodo_pago = 'Cotización';
                $estado_pedido = 'pendiente_pago';

                $subtotalPedido = (float)($cotizacion['subtotal'] ?? $cotizacion['total'] ?? 0);
                $descuentoPedido = (float)($cotizacion['descuento'] ?? 0) + (float)($cotizacion['cupon_descuento'] ?? 0);
                $basePedidoFiscal = max(0, $subtotalPedido - $descuentoPedido);
                $resumenPedidoFiscal = contabilidad_calcular_impuestos(
                    contabilidad_get_impuestos($pdo, true),
                    $basePedidoFiscal,
                    $basePedidoFiscal,
                    'pedido'
                );
                $totalPedido = max(0, (float)($resumenPedidoFiscal['total_con_impuestos'] ?? $basePedidoFiscal));

                $public_token = bin2hex(random_bytes(16));
                $pedidoObservaciones = 'Creado desde cotización ' . ($cotizacion['numero_cotizacion'] ?? '') . ($documento_numero ? ' | ' . $documento_tipo . ': ' . $documento_numero : '');
                $pedidoCols = ['numero_pedido', 'cliente_id', 'total', 'metodo_pago', 'estado', 'public_token'];
                $pedidoVals = [$numero_pedido, $cliente_id, $totalPedido, $metodo_pago, $estado_pedido, $public_token];

                if (in_array('subtotal', $cols_ped, true)) {
                    $pedidoCols[] = 'subtotal';
                    $pedidoVals[] = $subtotalPedido;
                }
                if (in_array('envio', $cols_ped, true)) {
                    $pedidoCols[] = 'envio';
                    $pedidoVals[] = 0;
                }
                if (in_array('descuento_monto', $cols_ped, true)) {
                    $pedidoCols[] = 'descuento_monto';
                    $pedidoVals[] = $descuentoPedido;
                }
                if (in_array('codigo_descuento', $cols_ped, true)) {
                    $pedidoCols[] = 'codigo_descuento';
                    $pedidoVals[] = $cotizacion['cupon_codigo'] ?? null;
                }
                if (in_array('factura_a', $cols_ped, true)) {
                    $pedidoCols[] = 'factura_a';
                    $pedidoVals[] = $factura_a;
                }
                if (in_array('comprobante_tipo', $cols_ped, true)) {
                    $pedidoCols[] = 'comprobante_tipo';
                    $pedidoVals[] = $comprobante_tipo;
                }
                if (in_array('envio_nombre', $cols_ped, true)) {
                    $pedidoCols[] = 'envio_nombre';
                    $pedidoVals[] = $nombre ?: ($telefono ?: 'Cliente');
                }
                if (in_array('envio_telefono', $cols_ped, true)) {
                    $pedidoCols[] = 'envio_telefono';
                    $pedidoVals[] = $telefono ?: null;
                }
                if (in_array('envio_direccion', $cols_ped, true)) {
                    $pedidoCols[] = 'envio_direccion';
                    $pedidoVals[] = $direccion;
                }
                if (in_array('observaciones', $cols_ped, true)) {
                    $pedidoCols[] = 'observaciones';
                    $pedidoVals[] = $pedidoObservaciones;
                }
                if (in_array('impuestos_json', $cols_ped, true)) {
                    $pedidoCols[] = 'impuestos_json';
                    $pedidoVals[] = !empty($resumenPedidoFiscal['detalle']) ? json_encode($resumenPedidoFiscal['detalle'], JSON_UNESCAPED_UNICODE) : null;
                }
                if (in_array('impuestos_incluidos', $cols_ped, true)) {
                    $pedidoCols[] = 'impuestos_incluidos';
                    $pedidoVals[] = (float)($resumenPedidoFiscal['total_incluidos'] ?? 0);
                }
                if (in_array('impuestos_adicionales', $cols_ped, true)) {
                    $pedidoCols[] = 'impuestos_adicionales';
                    $pedidoVals[] = (float)($resumenPedidoFiscal['total_adicionales'] ?? 0);
                }

                $pedidoPlaceholders = implode(', ', array_fill(0, count($pedidoCols), '?'));
                $stmt = $pdo->prepare("INSERT INTO ecommerce_pedidos (" . implode(', ', $pedidoCols) . ") VALUES (" . $pedidoPlaceholders . ")");
                $stmt->execute($pedidoVals);
                $pedido_id = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare("
                    INSERT INTO ecommerce_pedido_items (pedido_id, producto_id, cantidad, precio_unitario, alto_cm, ancho_cm, subtotal, atributos)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($items as $it) {
                    $cantidad = (int)($it['cantidad'] ?? 1);
                    $precio_unitario = (float)($it['precio_unitario'] ?? $it['precio_base'] ?? 0);
                    $alto = !empty($it['alto']) ? (int)$it['alto'] : null;
                    $ancho = !empty($it['ancho']) ? (int)$it['ancho'] : null;
                    $atributos_json = !empty($it['atributos']) ? json_encode($it['atributos']) : null;
                    $subtotal_item = $precio_unitario * $cantidad;

                    $stmtItem->execute([
                        $pedido_id,
                        (int)$it['producto_id'],
                        $cantidad,
                        $precio_unitario,
                        $alto,
                        $ancho,
                        $subtotal_item,
                        $atributos_json
                    ]);
                }

                if ($subirFactura) {
                    $dirFacturas = realpath(__DIR__ . '/../../uploads');
                    if ($dirFacturas === false) {
                        throw new Exception('No se encontró la carpeta de uploads');
                    }
                    $dirFacturas .= '/facturas_pedidos';
                    if (!is_dir($dirFacturas) && !mkdir($dirFacturas, 0755, true)) {
                        throw new Exception('No se pudo crear carpeta de facturas');
                    }
                    $nombreArchivo = 'pedido_' . $pedido_id . '_' . date('YmdHis') . '.' . $facturaExt;
                    $destinoAbs = $dirFacturas . '/' . $nombreArchivo;
                    if (!move_uploaded_file($facturaAdjunta['tmp_name'], $destinoAbs)) {
                        throw new Exception('No se pudo guardar el archivo adjunto de factura');
                    }
                    $rutaRel = 'uploads/facturas_pedidos/' . $nombreArchivo;
                    $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET factura_archivo = ?, factura_nombre_original = ? WHERE id = ?");
                    $stmt->execute([$rutaRel, $facturaOriginal ?: $nombreArchivo, $pedido_id]);
                }

                $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET estado = 'convertida' WHERE id = ?");
                $stmt->execute([$id]);
                cotizacion_sync_crm_estado($pdo, $cotizacion, 'convertida', $pedido_id);

                $pdo->commit();

                header("Location: pedidos_detalle.php?pedido_id=" . $pedido_id . "&mensaje=convertida");
                exit;
    } elseif ($accion === 'cambiar_estado') {
            $nuevo_estado = $_POST['estado'];
            $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id]);
            cotizacion_sync_crm_estado($pdo, $cotizacion, (string)$nuevo_estado);
            
            if ($nuevo_estado === 'enviada') {
                $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET fecha_envio = NOW() WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            $mensaje = "Estado actualizado";

            // Actualizar datos en memoria para reflejar cambios sin recargar página
            $cotizacion['estado'] = $nuevo_estado;
            if ($nuevo_estado === 'enviada') {
                $cotizacion['fecha_envio'] = date('Y-m-d H:i:s');
            }
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💼 Cotización <?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></h1>
        <p class="text-muted">
            <?php
            $badges = [
                'pendiente' => 'warning',
                'enviada' => 'info',
                'aceptada' => 'success',
                'rechazada' => 'danger',
                'convertida' => 'secondary'
            ];
            $badge = $badges[$cotizacion['estado']] ?? 'secondary';
            ?>
            Estado: <span class="badge bg-<?= $badge ?>"><?= ucfirst($cotizacion['estado']) ?></span>
        </p>
    </div>
    <div>
        <a href="cotizacion_pdf.php?id=<?= $id ?>" class="btn btn-info" target="_blank">📄 Descargar PDF</a>
        <a href="cotizacion_editar.php?id=<?= $id ?>" class="btn btn-warning">✏️ Editar</a>
        <a href="cotizaciones.php" class="btn btn-secondary">← Volver</a>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success">
        <?php if ($mensaje === 'creada'): ?>
            ✅ Cotización creada exitosamente
        <?php else: ?>
            <?= htmlspecialchars($mensaje) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Información del Cliente -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">👤 Información del Cliente</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="30%">Nombre:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_nombre'] ?? $cotizacion['nombre_cliente']) ?></td>
                    </tr>
                    <?php if (!empty($cotizacion['cliente_empresa'] ?? '') || !empty($cotizacion['empresa'] ?? '')): ?>
                    <tr>
                        <th>Empresa:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_empresa'] ?? ($cotizacion['empresa'] ?? '')) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Email:</th>
                        <td><a href="mailto:<?= htmlspecialchars($cotizacion['cliente_email'] ?? $cotizacion['email']) ?>"><?= htmlspecialchars($cotizacion['cliente_email'] ?? $cotizacion['email']) ?></a></td>
                    </tr>
                    <?php if (!empty($cotizacion['cliente_telefono'] ?? '') || !empty($cotizacion['telefono'] ?? '')): ?>
                    <tr>
                        <th>Teléfono:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_telefono'] ?? ($cotizacion['telefono'] ?? '')) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php $direccion_ui = trim((string)($cotizacion['cliente_direccion'] ?? $cotizacion['direccion'] ?? $cotizacion['cliente_empresa'] ?? $cotizacion['empresa'] ?? '')); ?>
                    <?php if ($direccion_ui !== ''): ?>
                    <tr>
                        <th>Domicilio:</th>
                        <td><?= htmlspecialchars($direccion_ui) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php $dni_ui = trim((string)($cotizacion['cliente_dni'] ?? $cotizacion['dni'] ?? '')); ?>
                    <?php if ($dni_ui !== ''): ?>
                    <tr>
                        <th>DNI:</th>
                        <td><?= htmlspecialchars($dni_ui) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php $cuit_ui = preg_replace('/\D+/', '', trim((string)($cotizacion['cliente_cuit'] ?? $cotizacion['cuit'] ?? ''))); ?>
                    <?php if ($cuit_ui !== ''): ?>
                    <tr>
                        <th>CUIT:</th>
                        <td><?= htmlspecialchars($cuit_ui) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php $factura_a_ui = (int)($cotizacion['cliente_factura_a'] ?? $cotizacion['factura_a'] ?? 0); ?>
                    <?php if ($factura_a_ui === 1): ?>
                    <tr>
                        <th>Factura:</th>
                        <td><span class="badge bg-success">A</span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Información de la Cotización -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">📋 Detalles de la Cotización</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="40%">Número:</th>
                        <td><?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></td>
                    </tr>
                    <tr>
                        <th>Fecha Creación:</th>
                        <td><?= date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])) ?></td>
                    </tr>
                    <?php if ($cotizacion['fecha_envio']): ?>
                    <tr>
                        <th>Fecha Envío:</th>
                        <td><?= date('d/m/Y H:i', strtotime($cotizacion['fecha_envio'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Validez:</th>
                        <td><?= $cotizacion['validez_dias'] ?> días</td>
                    </tr>
                    <tr>
                        <th>Vence:</th>
                        <td>
                            <?php
                            $fecha_vence = date('d/m/Y', strtotime($cotizacion['fecha_creacion'] . ' + ' . $cotizacion['validez_dias'] . ' days'));
                            echo $fecha_vence;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Lista de Precios:</th>
                        <td><?= htmlspecialchars($lista_precio['nombre'] ?? 'Sin lista') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Items de la Cotización -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">📦 Items</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Medidas</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($item['descripcion'] ?? '') ?></td>
                            <td>
                                <?php if ($item['ancho'] || $item['alto']): ?>
                                    <?= $item['ancho'] ?? '-' ?> x <?= $item['alto'] ?? '-' ?> cm
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['cantidad'] ?></td>
                            <td class="text-end">$<?= number_format((float)($item['precio_unitario'] ?? $item['precio_base'] ?? 0), 2) ?></td>
                            <td class="text-end"><strong>$<?= number_format((float)($item['precio_total'] ?? (($item['precio_unitario'] ?? $item['precio_base'] ?? 0) * ($item['cantidad'] ?? 1))), 2) ?></strong></td>
                        </tr>
                        <?php if (!empty($item['atributos']) && is_array($item['atributos'])): ?>
                            <tr class="table-light">
                                <td colspan="6" class="p-2">
                                    <small class="text-muted">
                                        <strong>🎨 Atributos:</strong><br>
                                        <?php foreach ($item['atributos'] as $attr): ?>
                                            • <?= htmlspecialchars($attr['nombre']) ?>: <?= htmlspecialchars($attr['valor']) ?>
                                            <?php if ($attr['costo_adicional'] > 0): ?>
                                                <span class="badge bg-warning text-dark">+$<?= number_format($attr['costo_adicional'], 2) ?></span>
                                            <?php endif; ?>
                                            <br>
                                        <?php endforeach; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($cotizacion['subtotal'], 2) ?></strong></td>
                    </tr>
                    <?php if ($cotizacion['descuento'] > 0): ?>
                    <tr>
                        <td colspan="5" class="text-end text-success"><strong>Descuento:</strong></td>
                        <td class="text-end text-success"><strong>-$<?= number_format($cotizacion['descuento'], 2) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($cotizacion['cupon_descuento'])): ?>
                    <tr>
                        <td colspan="5" class="text-end text-primary"><strong>Cupón<?= !empty($cotizacion['cupon_codigo']) ? ' (' . htmlspecialchars($cotizacion['cupon_codigo']) . ')' : '' ?>:</strong></td>
                        <td class="text-end text-primary"><strong>-$<?= number_format($cotizacion['cupon_descuento'], 2) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($impuestos_cotizacion) && is_array($impuestos_cotizacion)): ?>
                        <?php foreach ($impuestos_cotizacion as $impuestoCot): ?>
                            <?php $montoImpCot = (float)($impuestoCot['monto'] ?? 0); ?>
                            <?php if ($montoImpCot <= 0) { continue; } ?>
                            <tr>
                                <td colspan="5" class="text-end <?= !empty($impuestoCot['incluido_en_precio']) ? 'text-muted' : 'text-danger' ?>"><strong><?= htmlspecialchars((string)($impuestoCot['nombre'] ?? 'Impuesto')) ?><?= !empty($impuestoCot['incluido_en_precio']) ? ' (incluido)' : '' ?>:</strong></td>
                                <td class="text-end <?= !empty($impuestoCot['incluido_en_precio']) ? 'text-muted' : 'text-danger' ?>"><strong><?= !empty($impuestoCot['incluido_en_precio']) ? '' : '+' ?>$<?= number_format($montoImpCot, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end"><strong style="font-size: 1.2em;">$<?= number_format($cotizacion['total'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Observaciones -->
<?php if ($cotizacion['observaciones']): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">📝 Observaciones</h5>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(htmlspecialchars($cotizacion['observaciones'])) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Acciones -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">⚙️ Acciones</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="accion" value="cambiar_estado">
            <div class="col-md-4">
                <label class="form-label">Cambiar Estado</label>
                <select name="estado" class="form-select">
                    <option value="pendiente" <?= $cotizacion['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="enviada" <?= $cotizacion['estado'] === 'enviada' ? 'selected' : '' ?>>Enviada</option>
                    <option value="aceptada" <?= $cotizacion['estado'] === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                    <option value="rechazada" <?= $cotizacion['estado'] === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="convertida" <?= $cotizacion['estado'] === 'convertida' ? 'selected' : '' ?>>Convertida a Pedido</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">💾 Actualizar Estado</button>
            </div>
        </form>
        
        <hr>
        
        <div class="d-flex gap-2">
            <a href="cotizacion_pdf.php?id=<?= $id ?>" class="btn btn-info" target="_blank">📄 Ver/Descargar PDF</a>
            <a href="cotizacion_editar.php?id=<?= $id ?>" class="btn btn-warning">✏️ Editar Cotización</a>
            <?php if ($cotizacion['estado'] !== 'convertida'): ?>
                <?php
                    $dir_oblig = trim((string)($cotizacion['cliente_direccion'] ?? $cotizacion['direccion'] ?? $cotizacion['cliente_empresa'] ?? $cotizacion['empresa'] ?? ''));
                    $es_empresa_oblig = (int)($cotizacion['cliente_es_empresa'] ?? $cotizacion['es_empresa'] ?? 0);
                    $factura_a_oblig = (int)($cotizacion['cliente_factura_a'] ?? $cotizacion['factura_a'] ?? 0);
                    $cuit_oblig = preg_replace('/\D+/', '', trim((string)($cotizacion['cliente_cuit'] ?? $cotizacion['cuit'] ?? '')));
                ?>
                <?php if ($dir_oblig === '' || ($es_empresa_oblig && $factura_a_oblig && strlen($cuit_oblig) !== 11)): ?>
                    <div class="alert alert-warning mb-0 py-2 px-3 d-flex align-items-center">
                        ⚠️ Para convertir a pedido se requiere domicilio y, si corresponde Factura A de empresa, CUIT válido.
                    </div>
                <?php endif; ?>
                <form method="POST" enctype="multipart/form-data" style="display:inline;">
                    <input type="hidden" name="accion" value="convertir_pedido">
                    <div class="d-inline-block me-2 align-middle">
                        <input type="file" name="factura_adjunto" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png,.webp" title="Adjuntar factura (opcional)">
                    </div>
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Convertir esta cotización a pedido?')">🛒 Convertir a Pedido</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
