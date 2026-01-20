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
