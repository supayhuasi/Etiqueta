<?php
$host = "149.50.133.145";
$db   = "tucuroller_produccion";
$user = "Roco";
$pass = 'R$oco4508';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$db;charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Error DB: " . $e->getMessage());
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
