<?php
require 'includes/header.php';
header('Content-Type: application/json');

$codigo = trim($_GET['codigo'] ?? '');
$subtotal = floatval($_GET['subtotal'] ?? 0);

if ($codigo === '' || $subtotal <= 0) {
    echo json_encode(['valido' => false, 'descuento' => 0]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_cupones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('porcentaje','monto') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$hoy = date('Y-m-d');
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_cupones
    WHERE codigo = ? AND activo = 1
    AND (fecha_inicio IS NULL OR fecha_inicio <= ?)
    AND (fecha_fin IS NULL OR fecha_fin >= ?)
    LIMIT 1
");
$stmt->execute([$codigo, $hoy, $hoy]);
$cupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cupon) {
    echo json_encode(['valido' => false, 'descuento' => 0]);
    exit;
}

$descuento = 0;
if ($cupon['tipo'] === 'porcentaje') {
    $descuento = $subtotal * ((float)$cupon['valor'] / 100);
} else {
    $descuento = (float)$cupon['valor'];
}

$descuento = max(0, min($descuento, $subtotal));

echo json_encode([
    'valido' => true,
    'descuento' => round($descuento, 2),
    'tipo' => $cupon['tipo'],
    'valor' => (float)$cupon['valor']
]);
