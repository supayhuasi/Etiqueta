<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/presupuesto_helper.php';

$mes = trim((string)($_GET['mes'] ?? date('Y-m')));
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

$caja_actual = presupuestoCajaActual($pdo);
$clientes = presupuestoClientesConSaldo($pdo);
$sueldos = presupuestoSueldosPendientes($pdo, $mes);

$total_cxc = 0.0;
foreach ($clientes as $c) {
    $total_cxc += (float)$c['saldo'];
}
$total_sueldos_pend = 0.0;
foreach ($sueldos as $s) {
    $total_sueldos_pend += (float)$s['pendiente'];
}
?>
<style>
.presupuesto-sticky {
    position: sticky;
    top: 12px;
    z-index: 4;
}
.presupuesto-table input[type="number"] {
    max-width: 140px;
}
.presupuesto-row-off {
    opacity: .45;
}
</style>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="mb-1">Presupuesto / Estrategia de caja</h1>
        <p class="text-muted mb-0">Elegí qué vas a cobrar y qué vas a pagar para ver cuánta plata queda. No registra movimientos reales.</p>
    </div>
    <a href="finanzas.php" class="btn btn-outline-secondary">Ir a Estado Financiero</a>
</div>

<div class="card mb-4 presupuesto-sticky">
    <div class="card-body">
        <div class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label" for="caja_inicial">Caja inicial</label>
                <input type="number" step="0.01" class="form-control" id="caja_inicial" value="<?= htmlspecialchars((string)round($caja_actual, 2)) ?>">
                <small class="text-muted">Saldo actual de flujo de caja. Podés ajustarlo.</small>
            </div>
            <div class="col-md-3">
                <form method="GET" class="mb-0">
                    <label class="form-label" for="mes">Mes de sueldos</label>
                    <input type="month" name="mes" id="mes" class="form-control" value="<?= htmlspecialchars($mes) ?>" onchange="this.form.submit()">
                </form>
            </div>
            <div class="col-md-6">
                <div class="row text-center">
                    <div class="col-4">
                        <div class="small text-muted">A cobrar</div>
                        <div class="fs-5 text-success fw-bold" id="res-cobros">$0,00</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">A pagar</div>
                        <div class="fs-5 text-danger fw-bold" id="res-pagos">$0,00</div>
                    </div>
                    <div class="col-4">
                        <div class="small text-muted">Queda</div>
                        <div class="fs-4 fw-bold" id="res-queda">$0,00</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-end mt-3">
            <form id="form-reporte-estrategia" method="GET" action="presupuesto_estrategia_reporte.php" target="_blank">
                <input type="hidden" name="mes" value="<?= htmlspecialchars($mes) ?>">
                <input type="hidden" name="caja" id="rep_caja" value="">
                <input type="hidden" name="cobros" id="rep_cobros" value="">
                <input type="hidden" name="sueldos" id="rep_sueldos" value="">
                <input type="hidden" name="otros" id="rep_otros" value="">
                <button type="button" class="btn btn-danger" id="btn-imprimir-estrategia">Imprimir estrategia</button>
            </form>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Cobros desde facturación</strong>
                <span class="badge bg-success">Saldo total $<?= number_format($total_cxc, 2, ',', '.') ?></span>
            </div>
            <div class="card-body">
                <input type="search" class="form-control form-control-sm mb-3" id="filtro-clientes" placeholder="Buscar cliente...">
                <?php if (empty($clientes)): ?>
                    <div class="alert alert-info mb-0">No hay clientes con saldo pendiente.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover presupuesto-table" id="tabla-cobros">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:36px;"><input type="checkbox" class="form-check-input" id="sel-todos-cobros"></th>
                                    <th>Cliente</th>
                                    <th class="text-end">Saldo</th>
                                    <th class="text-end">Voy a cobrar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clientes as $c): ?>
                                    <tr class="fila-cobro presupuesto-row-off" data-nombre="<?= htmlspecialchars(mb_strtolower((string)$c['nombre'] . ' ' . ($c['email'] ?? ''))) ?>">
                                        <td>
                                            <input type="checkbox" class="form-check-input chk-cobro"
                                                   value="<?= (int)$c['id'] ?>"
                                                   data-nombre="<?= htmlspecialchars((string)$c['nombre']) ?>"
                                                   data-saldo="<?= (float)$c['saldo'] ?>">
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars((string)$c['nombre']) ?></strong>
                                            <div class="small text-muted"><?= htmlspecialchars((string)($c['email'] ?: ($c['telefono'] ?? ''))) ?></div>
                                        </td>
                                        <td class="text-end">$<?= number_format((float)$c['saldo'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string)$c['saldo']) ?>"
                                                   class="form-control form-control-sm input-cobro"
                                                   value="<?= htmlspecialchars((string)round((float)$c['saldo'], 2)) ?>"
                                                   disabled>
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

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Pagos a hacer</strong>
                <span class="badge bg-danger">Sueldos $<?= number_format($total_sueldos_pend, 2, ',', '.') ?></span>
            </div>
            <div class="card-body">
                <h6 class="text-muted">Sueldos pendientes — <?= htmlspecialchars($mes) ?></h6>
                <?php if (empty($sueldos)): ?>
                    <div class="alert alert-info">No hay sueldos pendientes en este mes.</div>
                <?php else: ?>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-hover presupuesto-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:36px;"><input type="checkbox" class="form-check-input" id="sel-todos-sueldos"></th>
                                    <th>Empleado</th>
                                    <th class="text-end">Pendiente</th>
                                    <th class="text-end">Voy a pagar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sueldos as $s): ?>
                                    <tr class="fila-sueldo presupuesto-row-off">
                                        <td>
                                            <input type="checkbox" class="form-check-input chk-sueldo"
                                                   value="<?= (int)$s['id'] ?>"
                                                   data-nombre="<?= htmlspecialchars((string)$s['nombre']) ?>"
                                                   data-pendiente="<?= (float)$s['pendiente'] ?>">
                                        </td>
                                        <td><strong><?= htmlspecialchars((string)$s['nombre']) ?></strong></td>
                                        <td class="text-end">$<?= number_format((float)$s['pendiente'], 2, ',', '.') ?></td>
                                        <td class="text-end">
                                            <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string)$s['pendiente']) ?>"
                                                   class="form-control form-control-sm input-sueldo"
                                                   value="<?= htmlspecialchars((string)round((float)$s['pendiente'], 2)) ?>"
                                                   disabled>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h6 class="text-muted">Otros pagos</h6>
                <div id="otros-pagos">
                    <div class="row g-2 align-items-center mb-2 fila-otro">
                        <div class="col-md-6">
                            <input type="text" class="form-control form-control-sm otro-desc" placeholder="Ej: alquiler, mercadería">
                        </div>
                        <div class="col-md-4">
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm otro-monto" placeholder="Monto">
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-quitar-otro" style="display:none;">Quitar</button>
                        </div>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-add-otro">+ Otro pago</button>
            </div>
        </div>
    </div>
</div>

<script>
function formatMonto(n) {
    var parts = (Number(n) || 0).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return '$' + parts[0] + ',' + parts[1];
}

function montoInput(el) {
    var n = parseFloat(el && el.value ? el.value : '0');
    return isNaN(n) ? 0 : n;
}

function actualizarEscenario() {
    var cobros = 0;
    document.querySelectorAll('.chk-cobro:checked').forEach(function(chk) {
        var input = chk.closest('tr').querySelector('.input-cobro');
        var max = parseFloat(chk.dataset.saldo) || 0;
        var val = Math.min(Math.max(montoInput(input), 0), max);
        cobros += val;
    });

    var pagos = 0;
    document.querySelectorAll('.chk-sueldo:checked').forEach(function(chk) {
        var input = chk.closest('tr').querySelector('.input-sueldo');
        var max = parseFloat(chk.dataset.pendiente) || 0;
        var val = Math.min(Math.max(montoInput(input), 0), max);
        pagos += val;
    });

    document.querySelectorAll('.otro-monto').forEach(function(input) {
        pagos += Math.max(montoInput(input), 0);
    });

    var caja = montoInput(document.getElementById('caja_inicial'));
    var queda = caja + cobros - pagos;

    document.getElementById('res-cobros').textContent = formatMonto(cobros);
    document.getElementById('res-pagos').textContent = formatMonto(pagos);
    var quedaEl = document.getElementById('res-queda');
    quedaEl.textContent = formatMonto(queda);
    quedaEl.className = 'fs-4 fw-bold ' + (queda >= 0 ? 'text-success' : 'text-danger');
}

function syncFila(chk, inputSelector) {
    var tr = chk.closest('tr');
    var input = tr.querySelector(inputSelector);
    input.disabled = !chk.checked;
    tr.classList.toggle('presupuesto-row-off', !chk.checked);
    actualizarEscenario();
}

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.chk-cobro').forEach(function(chk) {
        chk.addEventListener('change', function() { syncFila(chk, '.input-cobro'); });
    });
    document.querySelectorAll('.chk-sueldo').forEach(function(chk) {
        chk.addEventListener('change', function() { syncFila(chk, '.input-sueldo'); });
    });
    document.querySelectorAll('.input-cobro, .input-sueldo, .otro-monto').forEach(function(input) {
        input.addEventListener('input', actualizarEscenario);
    });
    document.getElementById('caja_inicial').addEventListener('input', actualizarEscenario);

    var selCobros = document.getElementById('sel-todos-cobros');
    if (selCobros) {
        selCobros.addEventListener('change', function() {
            document.querySelectorAll('.chk-cobro').forEach(function(chk) {
                chk.checked = selCobros.checked;
                syncFila(chk, '.input-cobro');
            });
        });
    }

    var selSueldos = document.getElementById('sel-todos-sueldos');
    if (selSueldos) {
        selSueldos.addEventListener('change', function() {
            document.querySelectorAll('.chk-sueldo').forEach(function(chk) {
                chk.checked = selSueldos.checked;
                syncFila(chk, '.input-sueldo');
            });
        });
    }

    var filtro = document.getElementById('filtro-clientes');
    if (filtro) {
        filtro.addEventListener('input', function() {
            var q = filtro.value.toLowerCase().trim();
            document.querySelectorAll('.fila-cobro').forEach(function(tr) {
                tr.style.display = (!q || (tr.dataset.nombre || '').indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    var otros = document.getElementById('otros-pagos');
    document.getElementById('btn-add-otro').addEventListener('click', function() {
        var fila = otros.querySelector('.fila-otro').cloneNode(true);
        fila.querySelector('.otro-desc').value = '';
        fila.querySelector('.otro-monto').value = '';
        fila.querySelector('.btn-quitar-otro').style.display = '';
        fila.querySelector('.otro-monto').addEventListener('input', actualizarEscenario);
        fila.querySelector('.btn-quitar-otro').addEventListener('click', function() {
            fila.remove();
            actualizarEscenario();
        });
        otros.appendChild(fila);
    });

    document.getElementById('btn-imprimir-estrategia').addEventListener('click', function() {
        var cobros = [];
        document.querySelectorAll('.chk-cobro:checked').forEach(function(chk) {
            var input = chk.closest('tr').querySelector('.input-cobro');
            var max = parseFloat(chk.dataset.saldo) || 0;
            var val = Math.min(Math.max(montoInput(input), 0), max);
            if (val > 0) cobros.push(chk.value + ':' + val.toFixed(2));
        });

        var sueldos = [];
        document.querySelectorAll('.chk-sueldo:checked').forEach(function(chk) {
            var input = chk.closest('tr').querySelector('.input-sueldo');
            var max = parseFloat(chk.dataset.pendiente) || 0;
            var val = Math.min(Math.max(montoInput(input), 0), max);
            if (val > 0) sueldos.push(chk.value + ':' + val.toFixed(2));
        });

        var otrosPairs = [];
        document.querySelectorAll('.fila-otro').forEach(function(fila) {
            var desc = (fila.querySelector('.otro-desc').value || '').trim().replace(/[|:]+/g, ' ');
            var monto = Math.max(montoInput(fila.querySelector('.otro-monto')), 0);
            if (desc && monto > 0) otrosPairs.push(desc + ':' + monto.toFixed(2));
        });

        if (cobros.length === 0 && sueldos.length === 0 && otrosPairs.length === 0) {
            alert('Seleccioná al menos un cobro o un pago para imprimir la estrategia.');
            return;
        }

        document.getElementById('rep_caja').value = montoInput(document.getElementById('caja_inicial')).toFixed(2);
        document.getElementById('rep_cobros').value = cobros.join(',');
        document.getElementById('rep_sueldos').value = sueldos.join(',');
        document.getElementById('rep_otros').value = otrosPairs.join('|');
        document.getElementById('form-reporte-estrategia').submit();
    });

    actualizarEscenario();
});
</script>

<?php require 'includes/footer.php'; ?>
