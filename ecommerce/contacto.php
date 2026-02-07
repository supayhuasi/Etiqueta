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
    'horario_atencion' => 'Lunes a Viernes: 9:00 - 18:00<br>Sábados: 10:00 - 14:00',
    'redes_sociales' => '{}'
];

try {
    $stmt = $pdo->query("SELECT nombre, email, telefono, direccion, ciudad, provincia, horario_atencion, redes_sociales FROM ecommerce_empresa LIMIT 1");
    $empresa_db = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa_db) {
        $empresa = array_merge($empresa, array_filter($empresa_db, static fn($v) => $v !== null && $v !== ''));
    }
} catch (Exception $e) {
}

$redes = json_decode($empresa['redes_sociales'] ?? '{}', true) ?? [];
$whatsapp_num = preg_replace('/\D+/', '', (string)($redes['whatsapp'] ?? ''));
$whatsapp_msg = trim((string)($redes['whatsapp_mensaje'] ?? ''));
$whatsapp_link = '';
if ($whatsapp_num !== '') {
    $whatsapp_link = 'https://wa.me/' . $whatsapp_num;
    if ($whatsapp_msg !== '') {
        $whatsapp_link .= '?text=' . urlencode($whatsapp_msg);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $provincia_contacto = trim($_POST['provincia_contacto'] ?? '');
    $mensaje_contenido = trim($_POST['mensaje'] ?? '');

    if ($nombre === '' || $email === '' || $asunto === '' || $mensaje_contenido === '') {
        $error = "Por favor completa todos los campos";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Email inválido";
    } elseif (empty($empresa['email'])) {
        $error = "No está configurado el email de la empresa";
    } else {
        $asunto_final = "Contacto: " . $asunto;
        $html = "<h2>Nuevo mensaje desde el formulario de contacto</h2>"
            . "<p><strong>Nombre:</strong> " . htmlspecialchars($nombre) . "</p>"
            . "<p><strong>Email:</strong> " . htmlspecialchars($email) . "</p>"
            . "<p><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>"
            . "<p><strong>Provincia:</strong> " . htmlspecialchars($provincia_contacto) . "</p>"
            . "<p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($mensaje_contenido)) . "</p>"
            . "<hr><small>Enviado desde el sitio web</small>";

        $text = "Nuevo mensaje desde el formulario de contacto\n"
            . "Nombre: $nombre\n"
            . "Email: $email\n"
            . "Asunto: $asunto\n"
            . "Provincia: $provincia_contacto\n"
            . "Mensaje: $mensaje_contenido\n";

        $enviado = enviar_email($empresa['email'], $asunto_final, $html, $text);
        if ($enviado) {
            $mensaje = "¡Gracias por tu mensaje! Nos pondremos en contacto pronto.";
        } else {
            $error = "No pudimos enviar el correo. Intentá nuevamente.";
        }
    }
}
?>

<div class="container py-5">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h1>Contacto</h1>
            <p class="text-muted">¿Tienes preguntas? Estamos aquí para ayudarte</p>

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
                            <?php if ($whatsapp_link): ?>
                                <p>
                                    <a href="<?= htmlspecialchars($whatsapp_link) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                                        <span style="font-size: 1.1rem;">🟢 WhatsApp</span>
                                    </a>
                                </p>
                            <?php elseif (!empty($empresa['telefono'])): ?>
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
                                <?php if (!empty($empresa['provincia'])): ?>
                                    <small class="text-muted d-block">Provincia: <?= htmlspecialchars($empresa['provincia']) ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <form method="POST" class="card p-4">
                        <h5 class="mb-4">Formulario de Contacto</h5>
                        
                        <div class="mb-3">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>

                        <div class="mb-3">
                            <label for="asunto" class="form-label">Asunto *</label>
                            <input type="text" class="form-control" id="asunto" name="asunto" required>
                        </div>

                        <div class="mb-3">
                            <label for="provincia_contacto" class="form-label">Provincia</label>
                            <select class="form-select" id="provincia_contacto" name="provincia_contacto">
                                <option value="">Seleccionar</option>
                                <?php foreach (['Buenos Aires','Catamarca','Chaco','Chubut','Córdoba','Corrientes','Entre Ríos','Formosa','Jujuy','La Pampa','La Rioja','Mendoza','Misiones','Neuquén','Río Negro','Salta','San Juan','San Luis','Santa Cruz','Santa Fe','Santiago del Estero','Tierra del Fuego','Tucumán'] as $prov): ?>
                                    <option value="<?= htmlspecialchars($prov) ?>"><?= htmlspecialchars($prov) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="mensaje" class="form-label">Mensaje *</label>
                            <textarea class="form-control" id="mensaje" name="mensaje" rows="5" required></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Enviar Mensaje</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
