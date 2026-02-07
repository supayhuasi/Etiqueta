<?php
require 'config.php';
require 'includes/header.php';

$token = trim($_GET['token'] ?? '');
$pago_id = (int)($_GET['pago_id'] ?? 0);

$pago = null;
$total_pagado = 0.0;
$saldo = 0.0;
$empresa_email = '';
$whatsapp_url = '';

if ($token !== '' && $pago_id > 0) {
    $stmt = $pdo->prepare("
        SELECT pp.*, p.numero_pedido, p.total, p.id AS pedido_id
        FROM ecommerce_pedido_pagos pp
        JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
        WHERE pp.id = ? AND p.public_token = ?
        LIMIT 1
    ");
    $stmt->execute([$pago_id, $token]);
    $pago = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pago) {
        $stmt = $pdo->prepare("SELECT SUM(monto) AS total_pagado FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
        $stmt->execute([(int)$pago['pedido_id']]);
        $total_pagado = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);
        $saldo = (float)$pago['total'] - $total_pagado;
    }
}

$empresa_email = trim((string)($empresa_menu['email'] ?? ''));
$wa_num = preg_replace('/\D+/', '', $whatsapp_num ?? '');
if ($wa_num !== '') {
    $whatsapp_url = 'https://wa.me/' . $wa_num;
}
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>🧾 Recibo de Pago</h1>
        <?php if ($pago): ?>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" onclick="window.print()">Descargar / Imprimir</button>
                <?php
                    $mensaje = 'Hola, adjunto comprobante de pago del pedido ' . ($pago['numero_pedido'] ?? '') . '. Recibo: ' . ($current_url ?? '');
                    $wa_link = $whatsapp_url ? ($whatsapp_url . '?text=' . urlencode($mensaje)) : '';
                    $mail_link = $empresa_email !== ''
                        ? 'mailto:' . rawurlencode($empresa_email) . '?subject=' . rawurlencode('Comprobante de pago ' . ($pago['numero_pedido'] ?? '')) . '&body=' . rawurlencode($mensaje)
                        : '';
                ?>
                <?php if ($wa_link): ?>
                    <a class="btn btn-success" href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener">WhatsApp</a>
                <?php endif; ?>
                <?php if ($mail_link): ?>
                    <a class="btn btn-outline-primary" href="<?= htmlspecialchars($mail_link) ?>">Email</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$pago): ?>
        <div class="alert alert-warning">No encontramos el recibo solicitado.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <p><strong>Pedido:</strong> <?= htmlspecialchars($pago['numero_pedido']) ?></p>
                <p><strong>Fecha de pago:</strong> <?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></p>
                <p><strong>Método:</strong> <?= htmlspecialchars($pago['metodo']) ?></p>
                <p><strong>Referencia:</strong> <?= htmlspecialchars($pago['referencia'] ?? '-') ?></p>
                <?php if (!empty($pago['notas'])): ?>
                    <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars($pago['notas'])) ?></p>
                <?php endif; ?>
                <hr>
                <p><strong>Monto recibido:</strong> $<?= number_format((float)$pago['monto'], 2, ',', '.') ?></p>
                <p><strong>Total pedido:</strong> $<?= number_format((float)$pago['total'], 2, ',', '.') ?></p>
                <p><strong>Total pagado:</strong> $<?= number_format($total_pagado, 2, ',', '.') ?></p>
                <p><strong>Saldo:</strong> $<?= number_format($saldo, 2, ',', '.') ?></p>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
