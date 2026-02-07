<?php
require 'config.php';
require 'includes/google_oauth.php';

if (!google_oauth_enabled()) {
    $_SESSION['cliente_login_error'] = 'Google Login no está configurado.';
    header('Location: cliente_login.php');
    exit;
}

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

$auth_url = google_oauth_build_auth_url($state);
header('Location: ' . $auth_url);
exit;
?>
