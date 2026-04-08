<?php
ob_start();
require 'includes/header.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

if (!isset($can_access) || !$can_access('finanzas')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(403);
    die('Acceso denegado.');
}

ensureContabilidadSchema($pdo);
$pedidoId = (int)($_GET['pedido_id'] ?? 0);
if ($pedidoId <= 0) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(400);
    die('Pedido inválido.');
}

try {
    $resultado = contabilidad_facturar_pedido_afip($pdo, $pedidoId);
    if (($resultado['modo'] ?? 'factura') === 'recibo') {
        $mensaje = 'Se generó un recibo interno sin conexión a ARCA/AFIP.';
    } else {
        $mensaje = !empty($resultado['ya_autorizado'])
            ? 'El pedido ya tenía CAE autorizado en ARCA/AFIP.'
            : 'Comprobante autorizado en ARCA/AFIP con CAE ' . ($resultado['cae'] ?? '');
    }
    $redirect = 'pedido_factura_pdf.php?pedido_id=' . $pedidoId . '&afip_ok=1&afip_msg=' . urlencode($mensaje);
} catch (Throwable $e) {
    $redirect = 'pedidos_detalle.php?pedido_id=' . $pedidoId . '&afip_error=' . urlencode($e->getMessage());
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Location: ' . $redirect);
exit;
