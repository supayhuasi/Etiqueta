<?php
require 'includes/header.php';

if (($role ?? '') !== 'admin') {
    http_response_code(403);
    die('Acceso denegado. Solo administradores.');
}

$mensaje_ok = '';
$error = '';
$usuario_id_actual = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_admin_mensajes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remitente_id INT NOT NULL,
        destinatario_id INT NOT NULL,
        asunto VARCHAR(180) NOT NULL,
        mensaje TEXT NOT NULL,
        leido TINYINT(1) NOT NULL DEFAULT 0,
        fecha_leido DATETIME NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_destinatario (destinatario_id, leido),
        INDEX idx_fecha (fecha_creacion),
        CONSTRAINT fk_admin_msg_remitente FOREIGN KEY (remitente_id) REFERENCES usuarios(id) ON DELETE CASCADE,
        CONSTRAINT fk_admin_msg_destinatario FOREIGN KEY (destinatario_id) REFERENCES usuarios(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Throwable $e) {
    $error = 'No se pudo preparar la mensajería interna: ' . $e->getMessage();
}

$admins = [];
try {
    $stmt = $pdo->query("\n        SELECT u.id, COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario) AS nombre_mostrar\n        FROM usuarios u\n        INNER JOIN roles r ON r.id = u.rol_id\n        WHERE LOWER(r.nombre) = 'admin'\n          AND COALESCE(u.activo, 1) = 1\n        ORDER BY nombre_mostrar ASC\n    ");
    $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $admins = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enviar_mensaje') {
    try {
        $destinatario_id = (int)($_POST['destinatario_id'] ?? 0);
        $asunto = trim($_POST['asunto'] ?? '');
        $mensaje = trim($_POST['mensaje'] ?? '');

        if ($usuario_id_actual <= 0) {
            throw new Exception('Sesión inválida para enviar mensajes.');
        }
        if ($destinatario_id <= 0) {
            throw new Exception('Seleccioná un administrador destinatario.');
        }
        if ($destinatario_id === $usuario_id_actual) {
            throw new Exception('No podés enviarte un mensaje a vos mismo.');
        }
        if ($asunto === '' || $mensaje === '') {
            throw new Exception('Asunto y mensaje son obligatorios.');
        }

        $destinatario_valido = false;
        foreach ($admins as $a) {
            if ((int)$a['id'] === $destinatario_id) {
                $destinatario_valido = true;
                break;
            }
        }
        if (!$destinatario_valido) {
            throw new Exception('El destinatario no es un administrador válido.');
        }

        $stmt = $pdo->prepare("INSERT INTO ecommerce_admin_mensajes (remitente_id, destinatario_id, asunto, mensaje) VALUES (?, ?, ?, ?)");
        $stmt->execute([$usuario_id_actual, $destinatario_id, $asunto, $mensaje]);

        $mensaje_ok = 'Mensaje enviado correctamente.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['leer']) && is_numeric($_GET['leer'])) {
    $leer_id = (int)$_GET['leer'];
    if ($leer_id > 0 && $usuario_id_actual > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE ecommerce_admin_mensajes SET leido = 1, fecha_leido = NOW() WHERE id = ? AND destinatario_id = ?");
            $stmt->execute([$leer_id, $usuario_id_actual]);
        } catch (Throwable $e) {
        }
    }
}

$recibidos = [];
$enviados = [];

try {
    $stmt = $pdo->prepare("\n        SELECT m.*,\n               COALESCE(NULLIF(TRIM(ur.nombre), ''), ur.usuario) AS remitente_nombre,\n               COALESCE(NULLIF(TRIM(ud.nombre), ''), ud.usuario) AS destinatario_nombre\n        FROM ecommerce_admin_mensajes m\n        INNER JOIN usuarios ur ON ur.id = m.remitente_id\n        INNER JOIN usuarios ud ON ud.id = m.destinatario_id\n        WHERE m.destinatario_id = ?\n        ORDER BY m.fecha_creacion DESC\n        LIMIT 50\n    ");
    $stmt->execute([$usuario_id_actual]);
    $recibidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}

try {
    $stmt = $pdo->prepare("\n        SELECT m.*,\n               COALESCE(NULLIF(TRIM(ur.nombre), ''), ur.usuario) AS remitente_nombre,\n               COALESCE(NULLIF(TRIM(ud.nombre), ''), ud.usuario) AS destinatario_nombre\n        FROM ecommerce_admin_mensajes m\n        INNER JOIN usuarios ur ON ur.id = m.remitente_id\n        INNER JOIN usuarios ud ON ud.id = m.destinatario_id\n        WHERE m.remitente_id = ?\n        ORDER BY m.fecha_creacion DESC\n        LIMIT 50\n    ");
    $stmt->execute([$usuario_id_actual]);
    $enviados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Mensajes entre Admins</h1>
        <p class="text-muted mb-0">Comunicación interna rápida entre administradores.</p>
    </div>
</div>

<?php if ($mensaje_ok !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje_ok) ?></div>
<?php endif; ?>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">Nuevo mensaje</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="action" value="enviar_mensaje">

            <div class="col-md-4">
                <label class="form-label">Para</label>
                <select class="form-select" name="destinatario_id" required>
                    <option value="">Seleccionar administrador...</option>
                    <?php foreach ($admins as $admin): ?>
                        <?php if ((int)$admin['id'] === $usuario_id_actual) { continue; } ?>
                        <option value="<?= (int)$admin['id'] ?>"><?= htmlspecialchars($admin['nombre_mostrar']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-8">
                <label class="form-label">Asunto</label>
                <input type="text" class="form-control" name="asunto" maxlength="180" required>
            </div>

            <div class="col-12">
                <label class="form-label">Mensaje</label>
                <textarea class="form-control" name="mensaje" rows="4" required></textarea>
            </div>

            <div class="col-12">
                <button class="btn btn-success" type="submit">Enviar mensaje</button>
            </div>
        </form>
    </div>
</div>

<div class="row">
    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">Recibidos</div>
            <div class="card-body p-0">
                <?php if (empty($recibidos)): ?>
                    <div class="p-3 text-muted">Sin mensajes recibidos.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recibidos as $m): ?>
                            <a href="admin_mensajes.php?leer=<?= (int)$m['id'] ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($m['asunto']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars(date('d/m H:i', strtotime($m['fecha_creacion']))) ?></small>
                                </div>
                                <div class="small text-muted">De: <?= htmlspecialchars($m['remitente_nombre']) ?></div>
                                <div class="small mt-1"><?= htmlspecialchars(mb_strimwidth((string)$m['mensaje'], 0, 120, '...')) ?></div>
                                <?php if (empty($m['leido'])): ?>
                                    <span class="badge bg-warning text-dark mt-1">No leído</span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6 mb-4">
        <div class="card h-100">
            <div class="card-header">Enviados</div>
            <div class="card-body p-0">
                <?php if (empty($enviados)): ?>
                    <div class="p-3 text-muted">Sin mensajes enviados.</div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($enviados as $m): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between">
                                    <strong><?= htmlspecialchars($m['asunto']) ?></strong>
                                    <small class="text-muted"><?= htmlspecialchars(date('d/m H:i', strtotime($m['fecha_creacion']))) ?></small>
                                </div>
                                <div class="small text-muted">Para: <?= htmlspecialchars($m['destinatario_nombre']) ?></div>
                                <div class="small mt-1"><?= htmlspecialchars(mb_strimwidth((string)$m['mensaje'], 0, 120, '...')) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
