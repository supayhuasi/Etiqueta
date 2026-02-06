<?php
require 'config.php';
require 'includes/header.php';
require 'includes/mailer.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresá un email válido.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nombre, email_verificado FROM ecommerce_clientes WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cliente) {
                $error = 'No existe una cuenta con ese email.';
            } elseif ((int)$cliente['email_verificado'] === 1) {
                $mensaje = 'La cuenta ya está verificada. Podés ingresar.';
            } else {
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
                $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET email_verificacion_token = ?, email_verificacion_expira = ? WHERE id = ?");
                $stmt->execute([$token, $expira, $cliente['id']]);

                $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $verifyUrl = $baseUrl . '/ecommerce/cliente_verificar.php?token=' . urlencode($token);

                $asunto = 'Verificá tu cuenta';
                $html = "<p>Hola " . htmlspecialchars($cliente['nombre'] ?? 'cliente') . ",</p>"
                    . "<p>Para verificar tu cuenta, hacé clic en el siguiente enlace:</p>"
                    . "<p><a href=\"{$verifyUrl}\">Verificar cuenta</a></p>";

                if (enviar_email($email, $asunto, $html)) {
                    $mensaje = 'Te enviamos un nuevo email de verificación.';
                } else {
                    $error = 'No se pudo enviar el email de verificación.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="mb-4">Reenviar verificación</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <form method="POST" class="card">
                <div class="card-body">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" required>
                    <button type="submit" class="btn btn-primary mt-3">Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
