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

$cuentas = cuentas_listar($pdo, true);
$reparto = cuentas_reparto_listar($pdo);
$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        admin_require_csrf_post();
        $porcentajes = [];
        $ids = $_POST['cuenta_id'] ?? [];
        $pcts = $_POST['porcentaje'] ?? [];
        if (!is_array($ids) || !is_array($pcts)) {
            throw new Exception('Datos inválidos.');
        }
        foreach ($ids as $i => $idRaw) {
            $porcentajes[(int)$idRaw] = (float)($pcts[$i] ?? 0);
        }
        cuentas_reparto_guardar($pdo, $porcentajes);
        header('Location: cuentas_reparto.php?ok=1');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $reparto = [];
        $ids = $_POST['cuenta_id'] ?? [];
        $pcts = $_POST['porcentaje'] ?? [];
        if (is_array($ids) && is_array($pcts)) {
            foreach ($ids as $i => $idRaw) {
                $reparto[(int)$idRaw] = (float)($pcts[$i] ?? 0);
            }
        }
    }
}

if (isset($_GET['ok'])) {
    $exito = 'Porcentajes de reparto guardados. Los próximos pagos pueden usar esta distribución.';
}

$suma = round(array_sum($reparto), 2);
?>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>⚙️ Reparto de pagos por caja</h1>
            <p class="text-muted mb-0">Definí qué porcentaje de cada pago entra en cada caja. Al registrar un cobro se puede aplicar este reparto o ajustarlo en el momento.</p>
        </div>
        <div class="col-md-4 text-end">
            <a href="cuentas.php" class="btn btn-account-secondary">← Cuentas</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($exito): ?>
        <div class="alert alert-success"><?= htmlspecialchars($exito) ?></div>
    <?php endif; ?>

    <?php if (empty($cuentas)): ?>
        <div class="alert alert-warning">No hay cajas activas. Primero creá una cuenta.</div>
    <?php else: ?>
        <form method="POST" class="card">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Caja</th>
                                <th>Tipo</th>
                                <th style="width: 180px;">Porcentaje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cuentas as $c): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars((string)$c['nombre']) ?></strong>
                                        <input type="hidden" name="cuenta_id[]" value="<?= (int)$c['id'] ?>">
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars((string)$c['tipo']) ?></span></td>
                                    <td>
                                        <div class="input-group">
                                            <input type="number" class="form-control js-reparto-pct" name="porcentaje[]" step="0.01" min="0" max="100" value="<?= isset($reparto[(int)$c['id']]) ? htmlspecialchars((string)$reparto[(int)$c['id']]) : '0' ?>">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <strong>Total: <span id="repartoSuma"><?= number_format($suma, 2, ',', '.') ?></span>%</strong>
                    <button type="submit" class="btn btn-account-primary">Guardar porcentajes</button>
                </div>
                <small class="text-muted d-block mt-2">Dejá en 0 las cajas que no participan. Las que tengan valor tienen que sumar 100%.</small>
            </div>
        </form>
    <?php endif; ?>
</div>
<script>
(function () {
    const inputs = document.querySelectorAll('.js-reparto-pct');
    const sumaEl = document.getElementById('repartoSuma');
    if (!sumaEl) return;
    function recalc() {
        let t = 0;
        inputs.forEach(function (el) { t += parseFloat(el.value) || 0; });
        sumaEl.textContent = (Math.round(t * 100) / 100).toFixed(2).replace('.', ',');
        sumaEl.classList.toggle('text-danger', Math.abs(t - 100) > 0.05);
        sumaEl.classList.toggle('text-success', Math.abs(t - 100) <= 0.05);
    }
    inputs.forEach(function (el) { el.addEventListener('input', recalc); });
    recalc();
})();
</script>
<?php require 'includes/footer.php'; ?>
