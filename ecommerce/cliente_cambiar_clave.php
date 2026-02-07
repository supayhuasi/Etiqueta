<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

$cliente = require_cliente_login($pdo);

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'] ?? '';
    $password_nueva = $_POST['password_nueva'] ?? '';
    $password_nueva2 = $_POST['password_nueva2'] ?? '';

    if ($password_nueva === '' || $password_actual === '') {
        $error = 'Completá todos los campos.';
    } elseif ($password_nueva !== $password_nueva2) {
        $error = 'La nueva contraseña no coincide.';
    } elseif (strlen($password_nueva) < 6) {
        $error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } elseif (empty($cliente['password_hash'])) {
        $error = 'No hay una contraseña configurada. Contactá al administrador.';
    } elseif (!password_verify($password_actual, $cliente['password_hash'])) {
        $error = 'La contraseña actual es incorrecta.';
    } else {
        $nuevo_hash = password_hash($password_nueva, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET password_hash = ? WHERE id = ?");
        $stmt->execute([$nuevo_hash, $cliente['id']]);
        $mensaje = 'Contraseña actualizada correctamente.';
    }
}
?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1>Cambiar clave</h1>
            <p class="text-muted mb-0">Actualizá tu contraseña de acceso.</p>
        </div>
        <a href="mis_pedidos.php" class="btn btn-secondary">Volver</a>
    </div>

    <?php if (!empty($mensaje)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">Contraseña actual *</label>
                    <input type="password" name="password_actual" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nueva contraseña *</label>
                    <input type="password" name="password_nueva" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Repetir nueva contraseña *</label>
                    <input type="password" name="password_nueva2" class="form-control" required>
                </div>
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
