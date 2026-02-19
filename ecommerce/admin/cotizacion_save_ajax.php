<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception('Método no permitido');

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

    // insertar
    $cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
    $has_empresa = in_array('empresa', $cols_cot, true);

    if ($has_empresa) {
        $stmt = $pdo->prepare("INSERT INTO ecommerce_cotizaciones
            (numero_cotizacion, nombre_cliente, email, telefono, direccion, cliente_id, lista_precio_id, items, subtotal, descuento, cupon_codigo, cupon_descuento, total, observaciones, validez_dias, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    } else {
        $stmt = $pdo->prepare("INSERT INTO ecommerce_cotizaciones
            (numero_cotizacion, nombre_cliente, email, telefono, cliente_id, lista_precio_id, items, subtotal, descuento, cupon_codigo, cupon_descuento, total, observaciones, validez_dias, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    }

    $items_json = json_encode($items);

    if ($has_empresa) {
        $stmt->execute([
            $numero_cotizacion,
            $nombre_cliente,
            $email ?: null,
            $telefono ?: null,
            $direccion ?: null,
            $cliente_id ?: null,
            $lista_precio_id,
            $items_json,
            $subtotal,
            $descuento,
            $cupon_codigo ?: null,
            $cupon_descuento,
            $total,
            $observaciones,
            $validez_dias,
            $_SESSION['user']['id'] ?? null
        ]);
    } else {
        $stmt->execute([
            $numero_cotizacion,
            $nombre_cliente,
            $email ?: null,
            $telefono ?: null,
            $cliente_id ?: null,
            $lista_precio_id,
            $items_json,
            $subtotal,
            $descuento,
            $cupon_codigo ?: null,
            $cupon_descuento,
            $total,
            $observaciones,
            $validez_dias,
            $_SESSION['user']['id'] ?? null
        ]);
    }

    $cotizacion_id = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'id' => $cotizacion_id, 'redirect' => 'cotizacion_detalle.php?id=' . $cotizacion_id]);
    exit;
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
