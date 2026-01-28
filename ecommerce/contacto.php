<?php
require 'config.php';
require 'includes/header.php';

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $email = $_POST['email'] ?? '';
    $asunto = $_POST['asunto'] ?? '';
    $mensaje_contenido = $_POST['mensaje'] ?? '';
    
    if (empty($nombre) || empty($email) || empty($asunto) || empty($mensaje_contenido)) {
        $error = "Por favor completa todos los campos";
    } else {
        // Guardar mensaje en la base de datos o enviar email
        // Por ahora solo mostramos confirmación
        $mensaje = "¡Gracias por tu mensaje! Nos pondremos en contacto pronto.";
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
                            <p><a href="mailto:info@tucuroller.com">info@tucuroller.com</a></p>
                        </div>
                    </div>
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5>📞 Teléfono</h5>
                            <p><a href="tel:+54XXXX">+54 (XXXX) XXX-XXXX</a></p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5>🕒 Horario de Atención</h5>
                            <p>Lunes a Viernes: 9:00 - 18:00<br>Sábados: 10:00 - 14:00</p>
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
