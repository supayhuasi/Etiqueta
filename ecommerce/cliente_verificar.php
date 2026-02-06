<?php
require 'config.php';
require 'includes/header.php';

$token = trim($_GET['token'] ?? '');
$mensaje = '';
$error = '';

if ($token === '') {
    $error = 'Token inválido.';
} else {
    try {
        $stmt = $pdo->prepare("SELECT id, email_verificacion_expira FROM ecommerce_clientes WHERE email_verificacion_token = ? LIMIT 1");
        $stmt->execute([$token]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            $error = 'Token inválido o ya utilizado.';
        } else {
            $expira = $cliente['email_verificacion_expira'];
            if ($expira && strtotime($expira) < time()) {
                $error = 'El token expiró. Solicitá uno nuevo.';
            } else {
                $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET email_verificado = 1, email_verificado_en = NOW(), email_verificacion_token = NULL, email_verificacion_expira = NULL WHERE id = ?");
                $stmt->execute([$cliente['id']]);
                $mensaje = 'Cuenta verificada correctamente. Ya podés ingresar.';
            }
        }
    } catch (Exception $e) {
        $error = 'Error al verificar: ' . $e->getMessage();
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="mb-4">Verificación de cuenta</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <a href="cliente_reenviar_verificacion.php" class="btn btn-outline-primary">Reenviar verificación</a>
            <?php endif; ?>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
                <a href="cliente_login.php" class="btn btn-primary">Ingresar</a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
