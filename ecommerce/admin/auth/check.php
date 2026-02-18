<?php
// Definir constante de seguridad
define('SECURITY_CHECK', true);

session_start();

// Obtener config desde 4 niveles arriba
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require_once $base_path . '/config.php';
require_once $base_path . '/ecommerce/includes/security.php';

// Validar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// Validar token CSRF
if (!isset($_POST['csrf_token']) || !validate_csrf_token($_POST['csrf_token'])) {
    log_security_event('CSRF_TOKEN_INVALID', ['ip' => $_SERVER['REMOTE_ADDR']]);
    header("Location: login.php?error=1");
    exit;
}

$user = sanitize_input($_POST['usuario'] ?? '');
$pass = $_POST['password'] ?? '';

// Validar inputs básicos
if (empty($user) || empty($pass)) {
    header("Location: login.php?error=1");
    exit;
}

// Prevenir fuerza bruta
prevent_brute_force('login_' . $user);

$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ? AND u.activo = 1");
$stmt->execute([$user]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if ($u && password_verify($pass, $u['password'])) {
    // Login exitoso - regenerar session ID
    session_regenerate_id(true);
    
    $_SESSION['user'] = [
        'id' => $u['id'],
        'usuario' => $u['usuario'],
        'nombre' => $u['nombre']
    ];
    $_SESSION['rol'] = $u['rol'] ?? 'usuario';
    $_SESSION['rol_id'] = $u['rol_id'];
    $_SESSION['login_time'] = time();
    $_SESSION['ip'] = $_SERVER['REMOTE_ADDR'];
    
    // Limpiar rate limit en login exitoso
    unset($_SESSION['rate_limit']['login_' . $user]);
    
    log_security_event('LOGIN_SUCCESS', ['user' => $user]);
    
    // Redirigir al admin del ecommerce después del login
    header("Location: ../index.php");
} else {
    log_security_event('LOGIN_FAILED', ['user' => $user]);
    // Redirigir al login del admin con error
    header("Location: login.php?error=1");
}
