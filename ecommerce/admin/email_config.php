<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_email_config WHERE id = 1 LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $config = null;
}

if (!$config) {
    $error = 'No existe la configuración de email. Ejecutá setup_ecommerce.php primero.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $config) {
    $from_email = trim($_POST['from_email'] ?? '');
    $from_name = trim($_POST['from_name'] ?? '');
    $smtp_host = trim($_POST['smtp_host'] ?? '');
    $smtp_port = (int)($_POST['smtp_port'] ?? 0);
    $smtp_user = trim($_POST['smtp_user'] ?? '');
    $smtp_pass = trim($_POST['smtp_pass'] ?? '');
    $smtp_secure = trim($_POST['smtp_secure'] ?? '');
    $smtp_auth = isset($_POST['smtp_auth']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? 1 : 0;

    if ($from_email === '' || !filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email remitente inválido.';
    } elseif ($smtp_host === '' || $smtp_port <= 0) {
        $error = 'Host y puerto SMTP son obligatorios.';
    } else {
        try {
            $sql = "UPDATE ecommerce_email_config
                    SET from_email = ?, from_name = ?, smtp_host = ?, smtp_port = ?, smtp_user = ?, smtp_secure = ?, smtp_auth = ?, activo = ?";
            $params = [$from_email, $from_name, $smtp_host, $smtp_port, $smtp_user, $smtp_secure, $smtp_auth, $activo];

            if ($smtp_pass !== '') {
                $sql .= ", smtp_pass = ?";
                $params[] = $smtp_pass;
            }

            $sql .= " WHERE id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $mensaje = 'Configuración guardada correctamente.';
            $stmt = $pdo->query("SELECT * FROM ecommerce_email_config WHERE id = 1 LIMIT 1");
            $config = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = 'Error al guardar: ' . $e->getMessage();
        }
    }
}
?>

<h1>Email (SMTP)</h1>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($config): ?>
<form method="POST" class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">From Email</label>
                <input type="email" name="from_email" class="form-control" value="<?= htmlspecialchars($config['from_email'] ?? '') ?>" required>
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">From Name</label>
                <input type="text" name="from_name" class="form-control" value="<?= htmlspecialchars($config['from_name'] ?? '') ?>">
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($config['smtp_host'] ?? '') ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">SMTP Port</label>
                <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars($config['smtp_port'] ?? '') ?>" required>
            </div>
            <div class="col-md-3 mb-3">
                <label class="form-label">SMTP Secure</label>
                <select name="smtp_secure" class="form-select">
                    <?php $secure = $config['smtp_secure'] ?? 'ssl'; ?>
                    <option value="ssl" <?= $secure === 'ssl' ? 'selected' : '' ?>>ssl</option>
                    <option value="tls" <?= $secure === 'tls' ? 'selected' : '' ?>>tls</option>
                    <option value="" <?= $secure === '' ? 'selected' : '' ?>>none</option>
                </select>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP User</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($config['smtp_user'] ?? '') ?>">
            </div>
            <div class="col-md-6 mb-3">
                <label class="form-label">SMTP Password</label>
                <input type="password" name="smtp_pass" class="form-control" placeholder="Dejar vacío para mantener la actual">
            </div>
        </div>

        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" name="smtp_auth" id="smtp_auth" <?= !empty($config['smtp_auth']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="smtp_auth">Usar autenticación SMTP</label>
        </div>
        <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !empty($config['activo']) ? 'checked' : '' ?>>
            <label class="form-check-label" for="activo">Activo</label>
        </div>

        <button type="submit" class="btn btn-primary">Guardar</button>
    </div>
</form>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
