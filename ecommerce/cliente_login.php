<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';

$empresa = null;
try {
    $stmt = $pdo->query("SELECT nombre, logo FROM ecommerce_empresa LIMIT 1");
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $empresa = null;
}

$logo_src = null;
if (!empty($empresa['logo'])) {
    $logo_path = __DIR__ . '/uploads/' . $empresa['logo'];
    if (file_exists($logo_path)) {
        $logo_src = 'uploads/' . $empresa['logo'];
    }
}

if (!empty($_SESSION['cliente_id'])) {
    header('Location: mis_pedidos.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Email y contraseña son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT id, nombre, password_hash, activo FROM ecommerce_clientes WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cliente || empty($cliente['password_hash'])) {
            $error = 'No existe una cuenta con ese email.';
        } elseif ((int)$cliente['activo'] !== 1) {
            $error = 'La cuenta está inactiva.';
        } elseif (!password_verify($password, $cliente['password_hash'])) {
            $error = 'Credenciales inválidas.';
        } else {
            $_SESSION['cliente_id'] = $cliente['id'];
            $_SESSION['cliente_nombre'] = $cliente['nombre'];
            header('Location: mis_pedidos.php');
            exit;
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <h1 class="mb-4">Ingresar</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <?php if ($logo_src): ?>
                        <div class="text-center mb-3">
                            <img src="<?= htmlspecialchars($logo_src) ?>" alt="<?= htmlspecialchars($empresa['nombre'] ?? 'Logo') ?>" style="max-height: 90px; max-width: 100%;">
                        </div>
                    <?php endif; ?>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
