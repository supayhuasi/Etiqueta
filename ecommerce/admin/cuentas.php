<?php
require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require 'includes/header.php';
require_once 'includes/cuentas_helper.php';
ensureCuentasSchema($pdo);

$mensaje = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'transferir') {
    try {
        admin_require_csrf_post();
        cuentas_transferir(
            $pdo,
            (int)($_POST['origen_id'] ?? 0),
            (int)($_POST['destino_id'] ?? 0),
            (float)str_replace(',', '.', (string)($_POST['monto'] ?? '0')),
            trim((string)($_POST['fecha'] ?? date('Y-m-d'))),
            trim((string)($_POST['nota'] ?? '')),
            (int)($_SESSION['user']['id'] ?? 0) ?: null
        );
        header('Location: cuentas.php?ok=transferido');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if (isset($_GET['ok']) && (string)$_GET['ok'] === 'transferido') {
    $mensaje = 'El saldo se movió correctamente de una caja a la otra.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'config_gastos') {
    try {
        admin_require_csrf_post();
        $cajaGastosId = (int)($_POST['gastos_cuenta_id'] ?? 0);
        if ($cajaGastosId <= 0 || !cuentas_get($pdo, $cajaGastosId)) {
            throw new Exception('Elegí una caja válida para los gastos.');
        }
        cuentas_config_set($pdo, 'gastos_cuenta_id', (string)$cajaGastosId);
        header('Location: cuentas.php?ok=caja_gastos');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
if (isset($_GET['ok']) && (string)$_GET['ok'] === 'caja_gastos') {
    $mensaje = 'La caja de gastos quedó configurada. Los gastos pagados se descuentan de ahí.';
}

$cuentas = cuentas_listar($pdo, false);
$reparto = cuentas_reparto_listar($pdo);
foreach ($cuentas as &$c) {
    $c['saldo'] = cuentas_saldo_total($pdo, (int)$c['id']);
    $c['porcentaje_reparto'] = (float)($reparto[(int)$c['id']] ?? 0);
}
unset($c);

$saldo_total_general = array_sum(array_column($cuentas, 'saldo'));
$caja_gastos_id = cuentas_gastos_id($pdo);
$cuentas_activas = array_values(array_filter($cuentas, static fn($c) => (int)($c['activo'] ?? 0) === 1));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cuentas</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>🏦 Cuentas</h1>
            <p class="text-muted">Organizá el flujo de caja en distintas cuentas (caja, inversión, producción, etc.). El saldo de cada cuenta se calcula solo, sumando todos sus movimientos históricos.</p>
        </div>
        <div class="col-md-6 text-end">
            <a href="flujo_caja.php" class="btn btn-account-secondary me-2">
                <i class="bi bi-arrow-left-circle me-1"></i> Flujo de Caja
            </a>
            <button type="button" class="btn btn-account-secondary me-2" data-bs-toggle="modal" data-bs-target="#modalTransferir">
                <i class="bi bi-arrow-left-right me-1"></i> Mover saldo
            </button>
            <a href="cuentas_reparto.php" class="btn btn-account-secondary me-2">
                <i class="bi bi-percent me-1"></i> % por caja
            </a>
            <a href="cuentas_crear.php" class="btn btn-account-primary">
                <i class="bi bi-plus-circle me-1"></i> Nueva Cuenta
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-body">
            <form method="POST" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="accion" value="config_gastos">
                <div class="col-md-8">
                    <label class="form-label fw-semibold" for="gastos_cuenta_id">Caja de gastos</label>
                    <select class="form-select" id="gastos_cuenta_id" name="gastos_cuenta_id" required>
                        <?php foreach ($cuentas_activas as $c): ?>
                            <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === $caja_gastos_id ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string)$c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Todos los gastos pagados se descuentan de esta caja.</small>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-account-primary w-100">Guardar caja de gastos</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="h6 text-muted mb-1">SALDO TOTAL (todas las cuentas activas)</div>
            <div class="h3" style="color: <?= $saldo_total_general >= 0 ? '#28A745' : '#DC3545' ?>">
                $<?= number_format($saldo_total_general, 2, ',', '.') ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($cuentas)): ?>
                <p class="text-muted text-center">No hay cuentas creadas todavía</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th>Tipo</th>
                                <th>Descripción</th>
                                <th class="text-end">Saldo actual</th>
                                <th class="text-end">% pagos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cuentas as $c): ?>
                                <tr class="<?= (int)$c['activo'] === 0 ? 'text-muted' : '' ?>">
                                    <td><strong><?= htmlspecialchars($c['nombre']) ?></strong></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($c['tipo']) ?></span></td>
                                    <td><?= htmlspecialchars($c['descripcion'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <strong style="color: <?= $c['saldo'] >= 0 ? '#28A745' : '#DC3545' ?>">
                                            $<?= number_format($c['saldo'], 2, ',', '.') ?>
                                        </strong>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((float)($c['porcentaje_reparto'] ?? 0) > 0): ?>
                                            <span class="badge bg-info text-dark"><?= number_format((float)$c['porcentaje_reparto'], 2, ',', '.') ?>%</span>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <span class="badge bg-success">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-mover-desde" data-origen="<?= (int)$c['id'] ?>">
                                                Mover
                                            </button>
                                        <?php endif; ?>
                                        <a href="cuentas_crear.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-account-primary">
                                            <i class="bi bi-pencil-square me-1"></i> Editar
                                        </a>
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <a href="cuentas_eliminar.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-account-danger">
                                                <i class="bi bi-slash-circle me-1"></i> Desactivar
                                            </a>
                                        <?php else: ?>
                                            <a href="cuentas_eliminar.php?id=<?= (int)$c['id'] ?>&accion=activar" class="btn btn-sm btn-account-secondary">
                                                <i class="bi bi-check-circle me-1"></i> Activar
                                            </a>
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
</div>

<div class="modal fade" id="modalTransferir" tabindex="-1" aria-labelledby="modalTransferirLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="accion" value="transferir">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTransferirLabel">Mover saldo entre cajas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Sale de una caja y entra en la otra. El total general no cambia.</p>
                    <div class="mb-3">
                        <label class="form-label" for="origen_id">Desde</label>
                        <select class="form-select" id="origen_id" name="origen_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($cuentas as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" data-saldo="<?= htmlspecialchars((string)$c['saldo']) ?>">
                                    <?= htmlspecialchars((string)$c['nombre']) ?> ($<?= number_format((float)$c['saldo'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="destino_id">Hacia</label>
                        <select class="form-select" id="destino_id" name="destino_id" required>
                            <option value="">Seleccionar...</option>
                            <?php foreach ($cuentas as $c): ?>
                                <?php if ((int)$c['activo'] === 1): ?>
                                    <option value="<?= (int)$c['id'] ?>">
                                        <?= htmlspecialchars((string)$c['nombre']) ?> ($<?= number_format((float)$c['saldo'], 2, ',', '.') ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="monto_transferencia">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="monto_transferencia" name="monto" required>
                        </div>
                        <small class="text-muted">Disponible en origen: $<span id="saldoOrigenLabel">—</span></small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="fecha_transferencia">Fecha</label>
                        <input type="date" class="form-control" id="fecha_transferencia" name="fecha" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-0">
                        <label class="form-label" for="nota_transferencia">Nota (opcional)</label>
                        <input type="text" class="form-control" id="nota_transferencia" name="nota" placeholder="Ej: pasar efectivo a banco">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-account-primary">Mover saldo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
(function () {
    const modalEl = document.getElementById('modalTransferir');
    const origen = document.getElementById('origen_id');
    const destino = document.getElementById('destino_id');
    const saldoLabel = document.getElementById('saldoOrigenLabel');
    if (!modalEl || !origen) return;

    function actualizarSaldo() {
        const opt = origen.options[origen.selectedIndex];
        const saldo = opt && opt.dataset.saldo ? parseFloat(opt.dataset.saldo) : NaN;
        saldoLabel.textContent = isNaN(saldo) ? '—' : saldo.toFixed(2).replace('.', ',');
    }
    origen.addEventListener('change', actualizarSaldo);

    document.querySelectorAll('.js-mover-desde').forEach(function (btn) {
        btn.addEventListener('click', function () {
            origen.value = btn.getAttribute('data-origen') || '';
            actualizarSaldo();
            if (window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
            }
        });
    });
    modalEl.addEventListener('shown.bs.modal', actualizarSaldo);

    document.querySelector('#modalTransferir form').addEventListener('submit', function (ev) {
        if (origen.value && destino.value && origen.value === destino.value) {
            ev.preventDefault();
            alert('Elegí dos cajas distintas.');
        }
    });
})();
</script>
<?php require 'includes/footer.php'; ?>
