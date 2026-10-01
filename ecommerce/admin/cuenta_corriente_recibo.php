<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/cuenta_corriente_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

cc_asegurar_tablas($pdo);

$movId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("
    SELECT m.*, c.nombre AS cliente_nombre, c.email, c.telefono, c.direccion
    FROM ecommerce_cuenta_corriente m
    LEFT JOIN ecommerce_clientes c ON c.id = m.cliente_id
    WHERE m.id = ? AND m.tipo = 'ingreso'
");
$stmt->execute([$movId]);
$mov = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$mov) {
    die("<div class='alert alert-danger'>Recibo no encontrado</div>");
}

$empresa = cc_empresa_membrete($pdo);
$saldo = cc_saldo($pdo, (int)$mov['cliente_id']);
$autoPrint = isset($_GET['print']) && (string)$_GET['print'] === '1';
$usuarioNombre = trim((string)($_SESSION['user']['nombre'] ?? $_SESSION['user']['usuario'] ?? ''));
?>
<style>
html.admin-is-mobile .sidebar,
.sidebar,
.top-navbar,
.admin-sidebar-backdrop,
#chatWidgetBtn,
#chatWidgetPanel { display: none !important; }
.container-fluid, .container-fluid > .row { display: block !important; margin: 0 !important; padding: 0 !important; }
.main-content {
    width: 100% !important;
    max-width: 820px !important;
    margin: 0 auto !important;
    padding: 28px 20px 48px !important;
    background: #fff;
}
body { background: #e8edf3 !important; }
.cc-doc {
    background: #fff;
    color: #111827;
    font-family: "Segoe UI", Calibri, Arial, sans-serif;
}
.cc-actions { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
.cc-letterhead {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    border-bottom: 2px solid #111827;
    padding-bottom: 14px;
    margin-bottom: 18px;
}
.cc-letterhead img { max-height: 56px; max-width: 160px; object-fit: contain; }
.cc-letterhead h1 { font-size: 1.4rem; margin: 0 0 4px; font-weight: 700; }
.cc-meta { font-size: 12px; color: #4b5563; line-height: 1.45; }
.cc-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px; }
.cc-box { border: 1px solid #d1d5db; padding: 12px 14px; }
.cc-box h2 { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; margin: 0 0 8px; color: #6b7280; }
.cc-amount { font-size: 1.8rem; font-weight: 700; margin: 8px 0; }
.cc-note { font-size: 12px; color: #4b5563; margin-top: 20px; }
@page { size: A4; margin: 14mm 12mm 16mm; }
@media print {
    html, body { background: #fff !important; }
    .cc-actions, .sidebar, .top-navbar, #chatWidgetBtn, #chatWidgetPanel { display: none !important; }
    .main-content { max-width: none !important; padding: 0 !important; }
}
</style>

<div class="cc-doc">
    <div class="cc-actions no-print">
        <a class="btn btn-outline-secondary btn-sm" href="cuenta_corriente_detalle.php?id=<?= (int)$mov['cliente_id'] ?>">← Cuenta</a>
        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <header class="cc-letterhead">
        <div>
            <?php if (!empty($empresa['logo'])): ?>
                <img src="<?= htmlspecialchars((string)$empresa['logo']) ?>" alt="<?= htmlspecialchars($empresa['nombre']) ?>">
            <?php endif; ?>
            <div class="cc-meta mt-2">
                <strong><?= htmlspecialchars($empresa['nombre']) ?></strong><br>
                <?php if ($empresa['cuit'] !== ''): ?>CUIT <?= htmlspecialchars($empresa['cuit']) ?><br><?php endif; ?>
                <?php if ($empresa['direccion'] !== ''): ?><?= htmlspecialchars($empresa['direccion']) ?><br><?php endif; ?>
                <?php if ($empresa['telefono'] !== '' || $empresa['email'] !== ''): ?>
                    <?= htmlspecialchars(trim($empresa['telefono'] . ' · ' . $empresa['email'], ' ·')) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-end">
            <h1>Recibo de cobro</h1>
            <div class="cc-meta">
                Nº <strong><?= htmlspecialchars((string)$mov['numero_recibo']) ?></strong><br>
                Fecha: <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$mov['fecha']))) ?><br>
                Cuenta corriente · sin pedido
            </div>
        </div>
    </header>

    <div class="cc-grid">
        <div class="cc-box">
            <h2>Recibí de</h2>
            <strong><?= htmlspecialchars((string)($mov['cliente_nombre'] ?? 'Cliente')) ?></strong><br>
            <?= htmlspecialchars((string)($mov['telefono'] ?? '-')) ?>
            <?php
            $emailRecibo = (string)($mov['email'] ?? '');
            if ($emailRecibo !== '' && substr($emailRecibo, -16) !== '@sin-email.local'):
            ?>
                <br><?= htmlspecialchars($emailRecibo) ?>
            <?php endif; ?>
            <?php if (!empty($mov['direccion'])): ?>
                <div><?= htmlspecialchars((string)$mov['direccion']) ?></div>
            <?php endif; ?>
        </div>
        <div class="cc-box">
            <h2>Importe recibido</h2>
            <div class="cc-amount"><?= cc_fmt_money((float)$mov['monto']) ?></div>
            <div class="cc-meta">
                Método: <?= htmlspecialchars((string)($mov['metodo'] ?? '-')) ?><br>
                Referencia: <?= htmlspecialchars((string)($mov['referencia'] ?? '-')) ?><br>
                Saldo a favor actual: <strong><?= cc_fmt_money($saldo) ?></strong>
            </div>
        </div>
    </div>

    <?php if (!empty($mov['notas'])): ?>
        <p><strong>Notas:</strong> <?= nl2br(htmlspecialchars((string)$mov['notas'])) ?></p>
    <?php endif; ?>

    <p class="cc-note">
        Este recibo acredita un pago a cuenta corriente. El saldo se puede aplicar después a un pedido del mismo cliente.
        <?php if ($usuarioNombre !== ''): ?>
            Emitido por <?= htmlspecialchars($usuarioNombre) ?>.
        <?php endif; ?>
    </p>
</div>

<?php if ($autoPrint): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
