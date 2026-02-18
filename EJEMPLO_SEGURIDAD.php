<?php
/**
 * Ejemplo de uso del sistema de seguridad
 * Agrega este código al inicio de tus archivos PHP para protegerlos
 */

// === OPCIÓN 1: Archivo admin (requiere login) ===
define('SECURITY_CHECK', true);
session_start();

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user'])) {
    header("Location: /ecommerce/admin/auth/login.php");
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ecommerce/includes/security.php';

// === OPCIÓN 2: Archivo público (no requiere login) ===
define('SECURITY_CHECK', true);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/ecommerce/includes/security.php';

// === PROTEGER FORMULARIOS ===
// En el HTML del formulario:
?>
<form method="post" action="procesar.php">
    <?= csrf_field() ?>
    <!-- resto del formulario -->
</form>

<?php
// En el archivo que procesa el formulario:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF token
    if (!validate_csrf_token($_POST['csrf_token'] ?? '')) {
        die('Token inválido');
    }
    
    // Sanitizar inputs
    $nombre = sanitize_input($_POST['nombre'] ?? '');
    $email = sanitize_input($_POST['email'] ?? '');
    
    // Validar email
    if (!validate_email($email)) {
        die('Email inválido');
    }
    
    // Validar ID
    $id = validate_id($_POST['id'] ?? 0);
    if (!$id) {
        die('ID inválido');
    }
    
    // Procesar...
}

// === PROTEGER SUBIDA DE ARCHIVOS ===
if (isset($_FILES['imagen'])) {
    $validation = validate_upload($_FILES['imagen']);
    
    if (!$validation['success']) {
        die($validation['error']);
    }
    
    $filename = sanitize_filename($_FILES['imagen']['name']);
    $upload_path = __DIR__ . '/uploads/' . $filename;
    
    move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_path);
}

// === ESCAPAR OUTPUT EN HTML ===
?>
<h1><?= escape_html($titulo) ?></h1>
<p><?= escape_html($descripcion) ?></p>

<?php
// === RATE LIMITING (Prevenir spam) ===
if (!check_rate_limit('contacto_' . $_SERVER['REMOTE_ADDR'], 3, 60)) {
    die('Demasiadas solicitudes. Espera 1 minuto.');
}

// === LOGS DE SEGURIDAD ===
// Registrar eventos importantes
log_security_event('USUARIO_CREADO', ['user_id' => $nuevo_id]);
log_security_event('COMPRA_REALIZADA', ['pedido_id' => $pedido_id, 'monto' => $total]);
?>
