<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/cuenta_corriente_helper.php';
require_once __DIR__ . '/includes/cuentas_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

cc_asegurar_tablas($pdo);
ensureCuentasSchema($pdo);

$clienteId = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM ecommerce_clientes WHERE id = ?');
$stmt->execute([$clienteId]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cliente) {
    die("<div class='alert alert-danger'>Cliente no encontrado</div>");
}

$cuentas = cuentas_listar($pdo);
$repartoDefault = cuentas_reparto_listar($pdo);
$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_require_csrf_post();
        $accion = (string)($_POST['accion'] ?? '');
        $usuarioId = (int)($_SESSION['user']['id'] ?? 0) ?: null;

        if ($accion === 'cobrar') {
            $monto = (float)str_replace(',', '.', (string)($_POST['monto'] ?? '0'));
            $partes = cuentas_reparto_partir_monto($monto, cuentas_reparto_desde_post($_POST));
            $mov = cc_registrar_ingreso(
                $pdo,
                $clienteId,
                $monto,
                trim((string)($_POST['metodo'] ?? '')),
                trim((string)($_POST['referencia'] ?? '')),
                trim((string)($_POST['notas'] ?? '')),
                trim((string)($_POST['fecha'] ?? date('Y-m-d'))),
                $usuarioId,
                $partes
            );
            header('Location: cuenta_corriente_recibo.php?id=' . (int)$mov['id'] . '&print=1');
            exit;
        }

        if ($accion === 'aplicar') {
            cc_aplicar_a_pedido(
                $pdo,
                $clienteId,
                (int)($_POST['pedido_id'] ?? 0),
                (float)str_replace(',', '.', (string)($_POST['monto'] ?? '0')),
                $usuarioId,
                trim((string)($_POST['notas'] ?? ''))
            );
            header('Location: cuenta_corriente_detalle.php?id=' . $clienteId . '&ok=aplicado');
            exit;
        }

        throw new Exception('Acción no válida.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['ok'])) {
    $oks = [
        'cliente' => 'Cliente listo. Registrá un cobro cuando quieras.',
        'aplicado' => 'El saldo se aplicó al pedido.',
    ];
    $ok = $oks[(string)$_GET['ok']] ?? '';
}

$saldo = cc_saldo($pdo, $clienteId);
$movimientos = cc_movimientos($pdo, $clienteId);
$pedidosPendientes = cc_pedidos_con_saldo($pdo, $clienteId);
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="mb-1"><?= htmlspecialchars((string)$cliente['nombre']) ?></h1>
        <p class="text-muted mb-0">
            <?= htmlspecialchars((string)($cliente['telefono'] ?? '')) ?>
            <?php
            $emailCliente = (string)($cliente['email'] ?? '');
            if ($emailCliente !== '' && substr($emailCliente, -16) !== '@sin-email.local'):
            ?>
            · <?= htmlspecialchars($emailCliente) ?>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="cuentas_corrientes.php">← Cuentas corrientes</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="small text-muted">Saldo a favor</div>
                <div class="fs-3 fw-bold <?= $saldo > 0 ? 'text-success' : '' ?>"><?= cc_fmt_money($saldo) ?></div>
                <div class="small text-muted">Disponible para aplicar a pedidos</div>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card h-100">
            <div class="card-header">Nuevo cobro / recibo</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                    <input type="hidden" name="accion" value="cobrar">
                    <div class="col-6 col-md-3">
                        <label class="form-label">Monto *</label>
                        <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Método *</label>
                        <input type="text" name="metodo" class="form-control" required>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <input type="text" name="notas" class="form-control">
                    </div>
                    <div class="col-12">
                        <?php cuentas_reparto_render_campos($cuentas, $repartoDefault, ['required' => true, 'monto_selector' => '#monto']); ?>
                    </div>
                    <div class="col-12">
                        <button class="btn btn-success" type="submit">Cobrar y emitir recibo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">Aplicar saldo a un pedido</div>
    <div class="card-body">
        <?php if ($saldo <= 0): ?>
            <div class="text-muted">Este cliente no tiene saldo a favor.</div>
        <?php elseif (empty($pedidosPendientes)): ?>
            <div class="text-muted">No hay pedidos con saldo de este cliente.</div>
        <?php else: ?>
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="accion" value="aplicar">
                <div class="col-md-6">
                    <label class="form-label">Pedido</label>
                    <select name="pedido_id" class="form-select" required>
                        <?php foreach ($pedidosPendientes as $ped): ?>
                            <option value="<?= (int)$ped['id'] ?>" data-saldo="<?= htmlspecialchars((string)$ped['saldo']) ?>">
                                <?= htmlspecialchars((string)$ped['numero_pedido']) ?>
                                · saldo <?= cc_fmt_money((float)$ped['saldo']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Monto a aplicar</label>
                    <input type="number" step="0.01" min="0.01" name="monto" class="form-control" value="<?= htmlspecialchars((string)min($saldo, (float)($pedidosPendientes[0]['saldo'] ?? $saldo))) ?>" required>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-primary w-100" type="submit">Aplicar</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <div class="card-header">Movimientos</div>
    <div class="card-body p-0">
        <?php if (empty($movimientos)): ?>
            <div class="p-4 text-muted">Todavía no hay movimientos.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Detalle</th>
                            <th class="text-end">Monto</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($movimientos as $mov): ?>
                            <?php $esIngreso = ($mov['tipo'] ?? '') === 'ingreso'; ?>
                            <tr>
                                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$mov['fecha']))) ?></td>
                                <td>
                                    <?php if ($esIngreso): ?>
                                        <span class="badge text-bg-success">Cobro</span>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Aplicado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($esIngreso): ?>
                                        <?= htmlspecialchars((string)($mov['numero_recibo'] ?? '')) ?>
                                        · <?= htmlspecialchars((string)($mov['metodo'] ?? '')) ?>
                                    <?php else: ?>
                                        Pedido <?= htmlspecialchars((string)($mov['numero_pedido'] ?? $mov['referencia'] ?? '')) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($mov['notas'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string)$mov['notas']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end <?= $esIngreso ? 'text-success' : '' ?>">
                                    <?= $esIngreso ? '+' : '−' ?><?= cc_fmt_money((float)$mov['monto']) ?>
                                </td>
                                <td class="text-end">
                                    <?php if ($esIngreso): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="cuenta_corriente_recibo.php?id=<?= (int)$mov['id'] ?>" target="_blank">Recibo</a>
                                    <?php elseif (!empty($mov['pedido_id'])): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="pedidos_detalle.php?pedido_id=<?= (int)$mov['pedido_id'] ?>">Pedido</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
