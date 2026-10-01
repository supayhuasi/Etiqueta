<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/cuenta_corriente_helper.php';
require_once __DIR__ . '/includes/cuentas_helper.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die('Error: No hay conexión a la base de datos');
}

cc_asegurar_tablas($pdo);
ensureCuentasSchema($pdo);
$cuentas = cuentas_listar($pdo);
$repartoDefault = cuentas_reparto_listar($pdo);

$buscar = trim((string)($_GET['q'] ?? ''));
$error = '';
$ok = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_require_csrf_post();
        $accion = (string)($_POST['accion'] ?? '');
        $usuarioId = (int)($_SESSION['user']['id'] ?? 0) ?: null;

        if ($accion === 'crear_cliente') {
            $clienteId = cc_crear_cliente($pdo, $_POST);
            header('Location: cuenta_corriente_detalle.php?id=' . $clienteId . '&ok=cliente');
            exit;
        }

        if ($accion === 'cobrar') {
            $clienteId = (int)($_POST['cliente_id'] ?? 0);
            if ($clienteId <= 0 && trim((string)($_POST['nombre'] ?? '')) !== '') {
                $clienteId = cc_crear_cliente($pdo, $_POST);
            }
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

        throw new Exception('Acción no válida.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['ok']) && (string)$_GET['ok'] === 'cliente') {
    $ok = 'Cliente creado. Ya podés cobrarle a cuenta corriente.';
}

$cuentasCc = cc_listar_cuentas($pdo, $buscar);
$clientesSelect = [];
try {
    $clientesSelect = $pdo->query("SELECT id, nombre, telefono, email FROM ecommerce_clientes ORDER BY nombre LIMIT 400")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $clientesSelect = [];
}
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
        <h1 class="mb-1">Cuentas corrientes</h1>
        <p class="text-muted mb-0">Cobrale a un cliente sin pedido y después aplicá ese saldo a un pedido.</p>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($ok): ?>
    <div class="alert alert-success"><?= htmlspecialchars($ok) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Nuevo cliente</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                    <input type="hidden" name="accion" value="crear_cliente">
                    <div class="col-md-6">
                        <label class="form-label">Nombre *</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="direccion" class="form-control">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Crear cliente</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header">Recibo de cobro (sin pedido)</div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                    <input type="hidden" name="accion" value="cobrar">
                    <div class="col-12">
                        <label class="form-label">Cliente existente</label>
                        <select name="cliente_id" class="form-select">
                            <option value="0">— Elegí o cargá uno nuevo abajo —</option>
                            <?php foreach ($clientesSelect as $cli): ?>
                                <option value="<?= (int)$cli['id'] ?>">
                                    <?= htmlspecialchars((string)$cli['nombre']) ?>
                                    <?= !empty($cli['telefono']) ? ' · ' . htmlspecialchars((string)$cli['telefono']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nombre (si es nuevo)</label>
                        <input type="text" name="nombre" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="telefono" class="form-control">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Monto *</label>
                        <input type="number" step="0.01" min="0.01" name="monto" id="monto" class="form-control" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Método *</label>
                        <input type="text" name="metodo" class="form-control" placeholder="Efectivo, Transferencia..." required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-6">
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
                        <button type="submit" class="btn btn-success">Cobrar y emitir recibo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h5 class="mb-0">Cuentas</h5>
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar cliente" value="<?= htmlspecialchars($buscar) ?>">
            <button class="btn btn-sm btn-outline-primary">Buscar</button>
        </form>
    </div>
    <div class="card-body p-0">
        <?php if (empty($cuentasCc)): ?>
            <div class="p-4 text-muted">No hay movimientos todavía. Creá un cliente y registrá un cobro.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th class="text-end">Acreditado</th>
                            <th class="text-end">Aplicado</th>
                            <th class="text-end">Saldo a favor</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cuentasCc as $row): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars((string)$row['nombre']) ?></strong></td>
                                <td>
                                    <?= htmlspecialchars((string)($row['telefono'] ?? '-')) ?>
                                    <?php
                                    $emailRow = (string)($row['email'] ?? '');
                                    if ($emailRow !== '' && substr($emailRow, -16) !== '@sin-email.local'):
                                    ?>
                                    <div class="small text-muted"><?= htmlspecialchars($emailRow) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= cc_fmt_money((float)$row['acreditado']) ?></td>
                                <td class="text-end"><?= cc_fmt_money((float)$row['aplicado']) ?></td>
                                <td class="text-end fw-semibold <?= (float)$row['saldo'] > 0 ? 'text-success' : '' ?>"><?= cc_fmt_money((float)$row['saldo']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="cuenta_corriente_detalle.php?id=<?= (int)$row['id'] ?>">Abrir</a>
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
