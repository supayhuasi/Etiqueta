<?php
require 'config.php';
require 'includes/header.php';

$token = trim($_GET['token'] ?? '');
$pedido = null;
$orden_produccion = null;
$pagos = [];
$total_pagado = 0.0;
$saldo = null;
$metodos_pago = [];
$empresa_email = '';
$whatsapp_url = '';

if ($token !== '') {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedidos WHERE public_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pedido) {
        $stmt = $pdo->prepare("SELECT estado FROM ecommerce_ordenes_produccion WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([(int)$pedido['id']]);
        $orden_produccion = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        try {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_pagos WHERE pedido_id = ? ORDER BY fecha_pago DESC");
            $stmt->execute([(int)$pedido['id']]);
            $pagos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT SUM(monto) AS total_pagado FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
            $stmt->execute([(int)$pedido['id']]);
            $total_pagado = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);
        } catch (Exception $e) {
            $pagos = [];
            $total_pagado = 0.0;
        }

        $saldo = (float)$pedido['total'] - $total_pagado;

        try {
            $stmt = $pdo->query("SELECT * FROM ecommerce_metodos_pago WHERE activo = 1 ORDER BY orden ASC, nombre ASC");
            $metodos_pago = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $metodos_pago = [];
        }

        $empresa_email = trim((string)($empresa_menu['email'] ?? ''));
        $wa_num = preg_replace('/\D+/', '', $whatsapp_num ?? '');
        if ($wa_num !== '') {
            $whatsapp_url = 'https://wa.me/' . $wa_num;
        }
    }
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Estado del pedido</h1>
            <p class="text-muted mb-0">Seguimiento del pedido en tiempo real.</p>
        </div>
        <a href="index.php" class="btn btn-secondary">Volver a la tienda</a>
    </div>

    <?php if ($token === '' || !$pedido): ?>
        <div class="alert alert-warning">No encontramos el pedido solicitado.</div>
    <?php else: ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <p><strong>Número:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                        <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_creacion'])) ?></p>
                        <p><strong>Estado del pedido:</strong> <span class="badge bg-secondary"><?= htmlspecialchars(str_replace('_', ' ', $pedido['estado'])) ?></span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Método de pago:</strong> <?= htmlspecialchars($pedido['metodo_pago'] ?? 'N/A') ?></p>
                        <p><strong>Total:</strong> $<?= number_format((float)$pedido['total'], 2, ',', '.') ?></p>
                        <p><strong>Pagado:</strong> $<?= number_format($total_pagado, 2, ',', '.') ?></p>
                        <p><strong>Saldo a pagar:</strong> $<?= number_format(max(0, (float)$saldo), 2, ',', '.') ?></p>
                        <?php if (!empty($orden_produccion['estado'])): ?>
                            <p><strong>Estado de producción:</strong> <span class="badge bg-info text-dark"><?= htmlspecialchars(str_replace('_', ' ', $orden_produccion['estado'])) ?></span></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($metodos_pago)): ?>
                    <div class="mb-4">
                        <h5 class="mb-3">Métodos de pago habilitados</h5>
                        <div class="accordion" id="metodosPago">
                            <?php foreach ($metodos_pago as $index => $metodo): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="metodoHeading<?= $index ?>">
                                        <button class="accordion-button <?= $index === 0 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#metodoCollapse<?= $index ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>" aria-controls="metodoCollapse<?= $index ?>">
                                            <?= htmlspecialchars($metodo['nombre']) ?>
                                        </button>
                                    </h2>
                                    <div id="metodoCollapse<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" aria-labelledby="metodoHeading<?= $index ?>" data-bs-parent="#metodosPago">
                                        <div class="accordion-body">
                                            <?= !empty($metodo['instrucciones_html']) ? $metodo['instrucciones_html'] : '<p>Contactanos para coordinar el pago.</p>' ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <h5 class="mb-3">Pagos registrados</h5>
                    <?php if (empty($pagos)): ?>
                        <div class="alert alert-info">Aún no hay pagos registrados.</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Monto</th>
                                        <th>Método</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pagos as $pago): ?>
                                        <?php
                                            $recibo_url = 'pedido_pago_recibo_publico.php?token=' . urlencode($token) . '&pago_id=' . (int)$pago['id'];
                                            $recibo_abs = $recibo_url;
                                            if (!empty($current_url)) {
                                                $recibo_abs = preg_replace('#/pedido_publico\.php.*$#', '/' . $recibo_url, $current_url);
                                            }
                                            $mensaje = 'Hola, adjunto comprobante de pago del pedido ' . ($pedido['numero_pedido'] ?? '') . '. Recibo: ' . $recibo_abs;
                                            $wa_link = $whatsapp_url ? ($whatsapp_url . '?text=' . urlencode($mensaje)) : '';
                                            $mail_link = $empresa_email !== ''
                                                ? 'mailto:' . rawurlencode($empresa_email) . '?subject=' . rawurlencode('Comprobante de pago ' . ($pedido['numero_pedido'] ?? '')) . '&body=' . rawurlencode($mensaje)
                                                : '';
                                        ?>
                                        <tr>
                                            <td><?= date('d/m/Y H:i', strtotime($pago['fecha_pago'])) ?></td>
                                            <td>$<?= number_format((float)$pago['monto'], 2, ',', '.') ?></td>
                                            <td><?= htmlspecialchars($pago['metodo']) ?></td>
                                            <td class="text-end">
                                                <a class="btn btn-sm btn-outline-secondary" href="<?= htmlspecialchars($recibo_url) ?>" target="_blank" rel="noopener">Descargar recibo</a>
                                                <?php if ($wa_link): ?>
                                                    <a class="btn btn-sm btn-success ms-1" href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener">WhatsApp</a>
                                                <?php endif; ?>
                                                <?php if ($mail_link): ?>
                                                    <a class="btn btn-sm btn-outline-primary ms-1" href="<?= htmlspecialchars($mail_link) ?>">Email</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="alert alert-info mb-0">Si necesitás ayuda con tu pedido, contactanos por WhatsApp o desde la sección de contacto.</div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
