<?php
$pedidoId = max(0, (int)($_GET['pedido_id'] ?? 0));
if ($pedidoId <= 0) {
    die('Pedido inválido.');
}

require __DIR__ . '/calidad_inspeccion_pdf.php';
