<?php
// Prevenir acceso directo si no está definido desde archivo autorizado
if (!defined('SECURITY_CHECK') && !defined('CONFIG_LOADED')) {
    // Permitir carga pero con flag
    define('CONFIG_LOADED', true);
}

$host = "149.50.133.145";
$db   = "tucuroller_produccion";
$user = "Roco";
$pass = 'R$oco4508';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false, // Prevenir SQL injection
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    // No mostrar detalles del error en producción
    error_log("Error DB: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor contacte al administrador.");
}

// Configuración de correo (SMTP)
$email_config = [
    'from_email' => getenv('SMTP_FROM_EMAIL') ?: 'contacto@tucuroller.com.ar',
    'from_name' => getenv('SMTP_FROM_NAME') ?: 'Tucu Roller',
    'smtp_host' => getenv('SMTP_HOST') ?: 'c2331001.ferozo.com',
    'smtp_port' => (int)(getenv('SMTP_PORT') ?: 465),
    'smtp_user' => getenv('SMTP_USER') ?: 'contacto@tucuroller.com.ar',
    'smtp_pass' => getenv('SMTP_PASS') ?: '',
    'smtp_secure' => getenv('SMTP_SECURE') ?: 'ssl',
    'smtp_auth' => (getenv('SMTP_AUTH') === false) ? true : filter_var(getenv('SMTP_AUTH'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true
];
