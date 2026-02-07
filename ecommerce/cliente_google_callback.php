<?php
require 'config.php';
require 'includes/google_oauth.php';
require 'includes/cliente_auth.php';

if (!google_oauth_enabled()) {
    $_SESSION['cliente_login_error'] = 'Google Login no está configurado.';
    header('Location: cliente_login.php');
    exit;
}

if (!empty($_GET['error'])) {
    $_SESSION['cliente_login_error'] = 'No se pudo completar el login con Google.';
    header('Location: cliente_login.php');
    exit;
}

$state = $_GET['state'] ?? '';
if (empty($state) || empty($_SESSION['google_oauth_state']) || !hash_equals($_SESSION['google_oauth_state'], $state)) {
    unset($_SESSION['google_oauth_state']);
    $_SESSION['cliente_login_error'] = 'Estado inválido en autenticación con Google.';
    header('Location: cliente_login.php');
    exit;
}

unset($_SESSION['google_oauth_state']);

$code = $_GET['code'] ?? '';
if (empty($code)) {
    $_SESSION['cliente_login_error'] = 'Falta el código de autorización de Google.';
    header('Location: cliente_login.php');
    exit;
}

$token_data = google_oauth_exchange_code($code);
if (empty($token_data['access_token'])) {
    $_SESSION['cliente_login_error'] = 'No se pudo obtener el token de Google.';
    header('Location: cliente_login.php');
    exit;
}

$userinfo = google_oauth_fetch_userinfo($token_data['access_token']);
if (empty($userinfo['email']) || empty($userinfo['sub'])) {
    $_SESSION['cliente_login_error'] = 'No se pudo obtener el usuario de Google.';
    header('Location: cliente_login.php');
    exit;
}

$email = strtolower(trim($userinfo['email']));
$google_id = $userinfo['sub'];
$nombre = trim($userinfo['name'] ?? '') ?: 'Cliente';
$email_verificado = !empty($userinfo['email_verified']) ? 1 : 0;

try {
    $stmt = $pdo->prepare("SELECT id, nombre, activo, email_verificado, email_verificado_en, google_id FROM ecommerce_clientes WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        if ((int)$cliente['activo'] !== 1) {
            $_SESSION['cliente_login_error'] = 'La cuenta está inactiva.';
            header('Location: cliente_login.php');
            exit;
        }

        $update_fields = [
            'google_id' => $google_id,
            'auth_provider' => 'google'
        ];

        if ($email_verificado && (int)($cliente['email_verificado'] ?? 0) !== 1) {
            $update_fields['email_verificado'] = 1;
            $update_fields['email_verificado_en'] = date('Y-m-d H:i:s');
        }

        $set_parts = [];
        $values = [];
        foreach ($update_fields as $field => $value) {
            $set_parts[] = $field . ' = ?';
            $values[] = $value;
        }
        $values[] = $cliente['id'];

        $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET " . implode(', ', $set_parts) . " WHERE id = ?");
        $stmt->execute($values);

        $_SESSION['cliente_id'] = $cliente['id'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'] ?: $nombre;
    } else {
        $stmt = $pdo->prepare("INSERT INTO ecommerce_clientes (email, password_hash, nombre, email_verificado, email_verificado_en, activo, google_id, auth_provider) VALUES (?, NULL, ?, ?, ?, 1, ?, 'google')");
        $stmt->execute([
            $email,
            $nombre,
            $email_verificado ? 1 : 0,
            $email_verificado ? date('Y-m-d H:i:s') : null,
            $google_id
        ]);

        $_SESSION['cliente_id'] = (int)$pdo->lastInsertId();
        $_SESSION['cliente_nombre'] = $nombre;
    }

    header('Location: index.php');
    exit;
} catch (Exception $e) {
    $_SESSION['cliente_login_error'] = 'Error al crear o actualizar el usuario.';
    header('Location: cliente_login.php');
    exit;
}
?>
