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
    'from_email' => 'contacto@tucuroller.com.ar',
    'from_name' => 'Tucu Roller',
    'smtp_host' => 'c2331001.ferozo.com',
    'smtp_port' => 465,
    'smtp_user' => 'contacto@tucuroller.com.ar',
    'smtp_pass' => 'k6Af*@7vvisibility_off',
    'smtp_secure' => 'ssl',
    'smtp_auth' => true
];
