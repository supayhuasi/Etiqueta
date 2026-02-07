<?php
require 'config.php';
require 'includes/header.php';
require 'includes/cliente_auth.php';
require 'includes/mailer.php';

if (!empty($_SESSION['cliente_id'])) {
    header('Location: mis_pedidos.php');
    exit;
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $localidad = trim($_POST['localidad'] ?? '');
    $ciudad = trim($_POST['ciudad'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $codigo_postal = trim($_POST['codigo_postal'] ?? '');
    $responsabilidad_fiscal = trim($_POST['responsabilidad_fiscal'] ?? '');
    $documento_tipo = trim($_POST['documento_tipo'] ?? '');
    $documento_numero = trim($_POST['documento_numero'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if (empty($nombre) || empty($email) || empty($password) || empty($provincia) || empty($localidad) || empty($responsabilidad_fiscal) || empty($documento_tipo) || empty($documento_numero)) {
        $error = 'Completá los campos obligatorios (incluyendo provincia, localidad y datos fiscales).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Email no válido.';
    } elseif ($password !== $password2) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'Ya existe una cuenta con ese email.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                if ($ciudad === '' && $localidad !== '') {
                    $ciudad = $localidad;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_clientes (email, password_hash, nombre, telefono, provincia, localidad, ciudad, direccion, codigo_postal, responsabilidad_fiscal, documento_tipo, documento_numero)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$email, $hash, $nombre, $telefono, $provincia, $localidad, $ciudad, $direccion, $codigo_postal, $responsabilidad_fiscal, $documento_tipo, $documento_numero]);

                $cliente_id = $pdo->lastInsertId();
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', time() + 24 * 60 * 60);
                $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET email_verificacion_token = ?, email_verificacion_expira = ? WHERE id = ?");
                $stmt->execute([$token, $expira, $cliente_id]);

                $baseUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $verifyUrl = $baseUrl . '/ecommerce/cliente_verificar.php?token=' . urlencode($token);

                $asunto = 'Verificá tu cuenta';
                $html = "<p>Hola " . htmlspecialchars($nombre) . ",</p>"
                    . "<p>Para verificar tu cuenta, hacé clic en el siguiente enlace:</p>"
                    . "<p><a href=\"{$verifyUrl}\">Verificar cuenta</a></p>"
                    . "<p>Si no creaste esta cuenta, podés ignorar este mensaje.</p>";

                if (enviar_email($email, $asunto, $html)) {
                    $mensaje = 'Cuenta creada. Revisá tu email para verificarla.';
                } else {
                    $mensaje = 'Cuenta creada, pero no se pudo enviar el email de verificación.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error al registrar: ' . $e->getMessage();
        }
    }
}
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7">
            <h1 class="mb-4">Crear cuenta</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Nombre Completo *</label>
                                <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($nombre ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email *</label>
                                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($telefono ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Código Postal</label>
                                <input type="text" name="codigo_postal" class="form-control" value="<?= htmlspecialchars($codigo_postal ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="direccion" class="form-control" value="<?= htmlspecialchars($direccion ?? '') ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Localidad *</label>
                                <input type="text" name="localidad" class="form-control" value="<?= htmlspecialchars($localidad ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Provincia *</label>
                                <select name="provincia" class="form-select" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach (['Buenos Aires','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'] as $prov): ?>
                                        <option value="<?= htmlspecialchars($prov) ?>" <?= ($provincia ?? '') === $prov ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Responsabilidad Fiscal *</label>
                                <select class="form-select" name="responsabilidad_fiscal" required>
                                    <option value="">Seleccionar</option>
                                    <?php foreach (['Consumidor Final','Monotributista','Responsable Inscripto','Exento','No Responsable','Sujeto No Categorizado'] as $rf): ?>
                                        <option value="<?= htmlspecialchars($rf) ?>" <?= ($responsabilidad_fiscal ?? '') === $rf ? 'selected' : '' ?>><?= htmlspecialchars($rf) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Tipo Documento *</label>
                                <select class="form-select" name="documento_tipo" required>
                                    <option value="">Seleccionar</option>
                                    <option value="DNI" <?= ($documento_tipo ?? '') === 'DNI' ? 'selected' : '' ?>>DNI</option>
                                    <option value="CUIT" <?= ($documento_tipo ?? '') === 'CUIT' ? 'selected' : '' ?>>CUIT</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Número *</label>
                                <input type="text" name="documento_numero" class="form-control" value="<?= htmlspecialchars($documento_numero ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contraseña *</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Repetir Contraseña *</label>
                                <input type="password" name="password2" class="form-control" required>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center">
                            <a href="cliente_login.php" class="text-muted">¿Ya tienes cuenta? Ingresar</a>
                            <button type="submit" class="btn btn-primary">Crear cuenta</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
