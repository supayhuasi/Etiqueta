<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/crm_config_helper.php';

if (($role ?? '') !== 'admin') {
    die('Acceso denegado. Solo un administrador puede configurar el CRM.');
}

$mensaje = '';
$error = '';
$config = crm_config_load($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    $accion = trim((string)($_POST['accion'] ?? 'guardar'));

    try {
        if ($accion === 'enviar_avisos') {
            $envio = crm_enviar_avisos_vencidos($pdo, true);
            if ($envio['error'] !== '') {
                $error = $envio['error'];
            } elseif ((int)$envio['enviados'] > 0) {
                $mensaje = 'Se enviaron avisos a ' . (int)$envio['enviados'] . ' vendedor(es).';
            } else {
                $mensaje = 'No había vendedores con email y contactos vencidos para avisar.';
            }
        } else {
            $config = crm_config_save($pdo, [
                'dias_vencido' => (int)($_POST['dias_vencido'] ?? 1),
                'notificar_campana' => isset($_POST['notificar_campana']) ? 1 : 0,
                'notificar_email' => isset($_POST['notificar_email']) ? 1 : 0,
            ]);
            $mensaje = 'Configuración del CRM guardada.';
        }
    } catch (Throwable $e) {
        $error = 'No se pudo guardar la configuración.';
        error_log('crm_configuracion: ' . $e->getMessage());
    }
}

$vencidos = crm_contactos_vencidos($pdo, 0, 80);
$vencidos_por_vendedor = [];
foreach ($vencidos as $lead) {
    $nombre = trim((string)($lead['asignado_nombre'] ?? '')) !== '' ? (string)$lead['asignado_nombre'] : 'Sin asignar';
    $vencidos_por_vendedor[$nombre][] = $lead;
}
?>
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="mb-1">Configuración del CRM</h1>
        <p class="text-muted mb-0">Definí a los cuántos días un contacto queda vencido y cómo se avisa a los vendedores.</p>
    </div>
    <a href="crm.php" class="btn btn-outline-secondary"><i class="bi bi-person-lines-fill"></i> Volver al CRM</a>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-6">
        <form method="POST" class="card h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0">Regla de vencimiento</h5>
            </div>
            <div class="card-body">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                <input type="hidden" name="accion" value="guardar">

                <div class="mb-3">
                    <label class="form-label" for="dias_vencido">Días para que esté vencido</label>
                    <input type="number" min="1" max="365" name="dias_vencido" id="dias_vencido" class="form-control" value="<?= (int)$config['dias_vencido'] ?>" required>
                    <div class="form-text">
                        Un contacto abierto se marca vencido cuando pasan estos días desde la fecha de próximo contacto.
                        Si no tiene fecha, se cuenta desde la última gestión o la creación.
                    </div>
                </div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="notificar_campana" id="notificar_campana" value="1" <?= !empty($config['notificar_campana']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notificar_campana">Mostrar avisos en la campana del panel</label>
                </div>
                <div class="form-text mb-3">Cada vendedor ve sus contactos vencidos. El administrador ve todos.</div>

                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="notificar_email" id="notificar_email" value="1" <?= !empty($config['notificar_email']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="notificar_email">Enviar email diario a los vendedores</label>
                </div>
                <div class="form-text mb-4">Usa el email del usuario asignado y el SMTP de Email. Se manda una vez por día al abrir el CRM, o con el botón de avisos.</div>

                <button type="submit" class="btn btn-primary">Guardar configuración</button>
            </div>
        </form>
    </div>

    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Vencidos ahora (<?= count($vencidos) ?>)</h5>
                <form method="POST" class="m-0">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                    <input type="hidden" name="accion" value="enviar_avisos">
                    <button type="submit" class="btn btn-sm btn-outline-danger" <?= empty($config['notificar_email']) ? 'disabled' : '' ?>>
                        Enviar avisos ahora
                    </button>
                </form>
            </div>
            <div class="card-body">
                <?php if (empty($vencidos)): ?>
                    <p class="text-muted mb-0">No hay contactos vencidos con la regla actual.</p>
                <?php else: ?>
                    <?php foreach ($vencidos_por_vendedor as $vendedor => $leads): ?>
                        <h6 class="mt-3 mb-2"><?= htmlspecialchars($vendedor) ?> <span class="badge bg-danger"><?= count($leads) ?></span></h6>
                        <ul class="list-group list-group-flush mb-2">
                            <?php foreach ($leads as $lead): ?>
                                <?php
                                $cliente = trim((string)($lead['cliente_nombre'] ?? '')) !== ''
                                    ? (string)$lead['cliente_nombre']
                                    : (string)($lead['titulo'] ?? 'Contacto');
                                ?>
                                <li class="list-group-item px-0 d-flex justify-content-between gap-2">
                                    <div>
                                        <a href="crm.php?lead=<?= (int)$lead['id'] ?>&vencidos=1"><?= htmlspecialchars($cliente) ?></a>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($lead['telefono'] ?? '')) ?></div>
                                    </div>
                                    <div class="text-end small">
                                        <div>Próximo: <?= !empty($lead['proximo_contacto']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$lead['proximo_contacto']))) : '—' ?></div>
                                        <div class="text-danger">Vencido hace <?= max(0, (int)($lead['dias_atraso'] ?? 0)) ?> día(s)</div>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
