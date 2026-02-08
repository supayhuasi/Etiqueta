<?php
require 'includes/header.php';

$pago_id = intval($_GET['pago_id'] ?? 0);
if ($pago_id <= 0) {
    die('Pago no encontrado');
}

$stmt = $pdo->prepare("
    SELECT pp.*, p.numero_pedido, p.total, c.nombre, c.email, c.telefono
    FROM ecommerce_pedido_pagos pp
    JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE pp.id = ?
");
$stmt->execute([$pago_id]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    die('Pago no encontrado');
}

$stmt = $pdo->prepare("SELECT SUM(monto) AS total_pagado FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
$stmt->execute([$pago['pedido_id']]);
$total_pagado = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);
$saldo = (float)$pago['total'] - $total_pagado;
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>🧾 Recibo de Pago</h1>
        <a class="btn btn-outline-secondary" href="pedido_pago_recibo_pdf.php?pago_id=<?= (int)$pago_id ?>" target="_blank" rel="noopener">Descargar PDF</a>
    </div>

    <div class="card">
        <div class="card-body">
            <p><strong>Pedido:</strong> <?= htmlspecialchars($pago['numero_pedido']) ?></p>
            <p><strong>Cliente:</strong> <?= htmlspecialchars($pago['nombre'] ?? 'N/A') ?></p>
            <p><strong>Email:</strong> <?= htmlspecialchars($pago['email'] ?? '-') ?></p>
            <p><strong>Teléfono:</strong> <?= htmlspecialchars($pago['telefono'] ?? '-') ?></p>
            <hr>
            <p><strong>Fecha de pago:</strong> <?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></p>
            <p><strong>Método:</strong> <?= htmlspecialchars($pago['metodo']) ?></p>
            <p><strong>Referencia:</strong> <?= htmlspecialchars($pago['referencia'] ?? '-') ?></p>
            <?php if (!empty($pago['notas'])): ?>
                <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars($pago['notas'])) ?></p>
            <?php endif; ?>
            <hr>
            <p><strong>Monto recibido:</strong> $<?= number_format($pago['monto'], 2, ',', '.') ?></p>
            <p><strong>Total pedido:</strong> $<?= number_format($pago['total'], 2, ',', '.') ?></p>
            <p><strong>Total pagado:</strong> $<?= number_format($total_pagado, 2, ',', '.') ?></p>
            <p><strong>Saldo:</strong> $<?= number_format($saldo, 2, ',', '.') ?></p>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
