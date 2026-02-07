<?php
require 'config.php';
require 'includes/header.php';
require 'includes/mailer.php';

$mensaje = '';
$error = '';

$empresa = [
    'nombre' => 'Tucu Roller',
    'email' => 'info@tucuroller.com',
    'telefono' => '+54 (XXXX) XXX-XXXX',
    'direccion' => '',
    'ciudad' => '',
    'provincia' => '',
    'horario_atencion' => 'Lunes a Viernes: 9:00 - 18:00<br>Sábados: 10:00 - 14:00'
];

try {
    $stmt = $pdo->query("SELECT nombre, email, telefono, direccion, ciudad, provincia, horario_atencion FROM ecommerce_empresa LIMIT 1");
    $empresa_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa_db) {
        $empresa = array_merge($empresa, array_filter($empresa_db, static fn($v) => $v !== null && $v !== ''));
    }
} catch (Exception $e) {
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $provincia = trim($_POST['provincia'] ?? '');
    $localidad = trim($_POST['localidad'] ?? '');
    $empresa_solicitante = trim($_POST['empresa'] ?? '');
    $rubro = trim($_POST['rubro'] ?? '');
    $mensaje_contenido = trim($_POST['mensaje'] ?? '');

    if ($nombre === '' || $email === '' || $telefono === '' || $provincia === '' || $localidad === '') {
        $error = "Por favor completa los campos obligatorios";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido";
    } elseif (empty($empresa['email'])) {
        $error = "No está configurado el email de la empresa";
    } else {
        $asunto_final = "Solicitud de distribuidor";
        $html = "<h2>Nueva solicitud de distribuidor</h2>"
            . "<p><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>"
            . "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>"
            . "<p><strong>Teléfono:</strong> " . htmlspecialchars($telefono) . "</p>"
            . "<p><strong>Provincia:</strong> " . htmlspecialchars($provincia) . "</p>"
            . "<p><strong>Localidad:</strong> " . htmlspecialchars($localidad) . "</p>"
            . "<p><strong>Empresa:</strong> " . htmlspecialchars($empresa_solicitante) . "</p>"
            . "<p><strong>Rubro:</strong> " . htmlspecialchars($rubro) . "</p>"
            . "<p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($mensaje_contenido)) . "</p>"
            . "<hr><small>Enviado desde el sitio web</small>";

        $text = "Nueva solicitud de distribuidor\n"
            . "Nombre: $nombre\n"
            . "Email: $email\n"
            . "Teléfono: $telefono\n"
            . "Provincia: $provincia\n"
            . "Localidad: $localidad\n"
            . "Empresa: $empresa_solicitante\n"
            . "Rubro: $rubro\n"
            . "Mensaje: $mensaje_contenido\n";

        $enviado = enviar_email($empresa['email'], $asunto_final, $html, $text);
        if ($enviado) {
            $mensaje = "¡Gracias por tu interés! Nos pondremos en contacto pronto.";
        } else {
            $error = "No pudimos enviar el correo. Intentá nuevamente.";
        }
    }
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1>Distribuidores</h1>
            <p class="text-muted">Sumate como distribuidor y llevá nuestros productos a tu zona</p>

            <?php if (!empty($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    ✓ <?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>

            <div class="row mt-5">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>📧 Email</h5>
                            <?php if (!empty($empresa['email'])): ?>
                                <p><a href="mailto:<?= htmlspecialchars($empresa['email']) ?>"><?= htmlspecialchars($empresa['email']) ?></a></p>
                            <?php else: ?>
                                <p class="text-muted">No disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>📞 Teléfono</h5>
                            <?php if (!empty($empresa['telefono'])): ?>
                                <p><a href="tel:<?= htmlspecialchars(preg_replace('/\s+/', '', $empresa['telefono'])) ?>"><?= htmlspecialchars($empresa['telefono']) ?></a></p>
                            <?php else: ?>
                                <p class="text-muted">No disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5>🕒 Horario de Atención</h5>
                            <p><?= !empty($empresa['horario_atencion']) ? $empresa['horario_atencion'] : 'No disponible' ?></p>
                            <?php if (!empty($empresa['direccion']) || !empty($empresa['ciudad']) || !empty($empresa['provincia'])): ?>
                                <hr>
                                <p class="mb-0">
                                    <?= htmlspecialchars($empresa['direccion'] ?? '') ?><br>
                                    <?= htmlspecialchars($empresa['ciudad'] ?? '') ?><?= !empty($empresa['provincia']) ? ', ' . htmlspecialchars($empresa['provincia']) : '' ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <form method="POST" class="card p-4">
                        <h5 class="mb-4">Formulario para Distribuidores</h5>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="telefono" class="form-label">Teléfono *</label>
                            <input type="text" class="form-control" id="telefono" name="telefono" required>
                        </div>

                        <div class="mb-3">
                            <label for="provincia" class="form-label">Provincia *</label>
                            <input type="text" class="form-control" id="provincia" name="provincia" required>
                        </div>

                        <div class="mb-3">
                            <label for="localidad" class="form-label">Localidad *</label>
                            <input type="text" class="form-control" id="localidad" name="localidad" required>
                        </div>

                        <div class="mb-3">
                            <label for="empresa" class="form-label">Empresa</label>
                            <input type="text" class="form-control" id="empresa" name="empresa">
                        </div>

                        <div class="mb-3">
                            <label for="rubro" class="form-label">Rubro</label>
                            <input type="text" class="form-control" id="rubro" name="rubro">
                        </div>

                        <div class="mb-3">
                            <label for="mensaje" class="form-label">Mensaje</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="4"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Enviar Solicitud</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
