<?php
require '../includes/header.php';

$col = $pdo->query("SHOW COLUMNS FROM empleados LIKE 'fichaje_rapido'");
if ($col->rowCount() === 0) {
    $pdo->exec("ALTER TABLE empleados ADD COLUMN fichaje_rapido TINYINT(1) NOT NULL DEFAULT 1 AFTER activo");
}

$stmt = $pdo->query("
    SELECT
        e.id,
        e.nombre,
        e.puesto,
        a.hora_entrada,
        a.hora_salida,
        a.estado
    FROM empleados e
    LEFT JOIN asistencias a ON a.empleado_id = e.id AND a.fecha = CURDATE()
    WHERE e.activo = 1 AND e.fichaje_rapido = 1
    ORDER BY e.nombre ASC
");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="mb-1">🖱️ Fichaje Rápido</h1>
            <p class="text-muted mb-0">Tocá tu nombre para marcar entrada. Tocalo de nuevo al final del día para marcar salida.</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <a href="asistencias.php" class="btn btn-outline-secondary">⬅️ Volver a Asistencias</a>
        </div>
    </div>

    <div id="fichaje-status" class="alert d-none" role="alert"></div>

    <?php if (empty($empleados)): ?>
        <div class="alert alert-info">No hay empleados activos.</div>
    <?php else: ?>
        <div class="row g-3" id="fichaje-grid">
            <?php foreach ($empleados as $emp): ?>
                <?php
                $sinMarcar = empty($emp['hora_entrada']);
                $enCurso = !empty($emp['hora_entrada']) && empty($emp['hora_salida']);
                $completo = !empty($emp['hora_entrada']) && !empty($emp['hora_salida']);

                if ($completo) {
                    $borde = 'border-primary';
                    $badge = '<span class="badge bg-primary">Jornada completa</span>';
                } elseif ($enCurso) {
                    $borde = $emp['estado'] === 'tarde' ? 'border-warning' : 'border-success';
                    $badge = $emp['estado'] === 'tarde'
                        ? '<span class="badge bg-warning text-dark">Tarde</span>'
                        : '<span class="badge bg-success">Presente</span>';
                } else {
                    $borde = 'border-secondary';
                    $badge = '<span class="badge bg-secondary">Sin marcar</span>';
                }
                ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <button
                        type="button"
                        class="btn w-100 h-100 text-start p-3 fichaje-card <?= $borde ?>"
                        style="border-width: 2px; min-height: 140px;"
                        data-empleado-id="<?= (int)$emp['id'] ?>"
                    >
                        <div class="fw-bold fs-5"><?= htmlspecialchars($emp['nombre']) ?></div>
                        <?php if (!empty($emp['puesto'])): ?>
                            <div class="text-muted small mb-2"><?= htmlspecialchars($emp['puesto']) ?></div>
                        <?php endif; ?>
                        <div class="mb-2"><?= $badge ?></div>
                        <div class="small text-muted">
                            <?php if ($completo): ?>
                                Entrada <?= date('H:i', strtotime($emp['hora_entrada'])) ?> · Salida <?= date('H:i', strtotime($emp['hora_salida'])) ?>
                            <?php elseif ($enCurso): ?>
                                Entrada <?= date('H:i', strtotime($emp['hora_entrada'])) ?> · Tocá para marcar salida
                            <?php else: ?>
                                Tocá para marcar entrada
                            <?php endif; ?>
                        </div>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var statusBox = document.getElementById('fichaje-status');
    var busy = false;

    function mostrarStatus(mensaje, ok) {
        statusBox.textContent = mensaje;
        statusBox.classList.remove('d-none', 'alert-success', 'alert-danger');
        statusBox.classList.add(ok ? 'alert-success' : 'alert-danger');
    }

    document.querySelectorAll('.fichaje-card').forEach(function (card) {
        card.addEventListener('click', function () {
            if (busy) return;
            busy = true;
            card.disabled = true;

            var empleadoId = card.getAttribute('data-empleado-id');
            var body = new URLSearchParams();
            body.set('empleado_id', empleadoId);

            fetch('fichaje_registrar.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    mostrarStatus(data.message || (data.success ? 'Registrado' : 'Error'), !!data.success);
                    if (data.success) {
                        setTimeout(function () { window.location.reload(); }, 1200);
                    } else {
                        busy = false;
                        card.disabled = false;
                    }
                })
                .catch(function () {
                    mostrarStatus('Error de conexión al registrar la asistencia', false);
                    busy = false;
                    card.disabled = false;
                });
        });
    });
});
</script>

<?php require '../includes/footer.php'; ?>
