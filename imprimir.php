<?php
// ⚠️ Activar errores SOLO para debug (después podés sacar esto)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/code128.php';

$id = $_GET['id'] ?? 0;

// Buscar producto
$stmt = $pdo->prepare("
    SELECT p.*,
           t.nombre AS tela,
           c.nombre AS color
    FROM productos p
    LEFT JOIN telas t ON p.tela_id = t.id
    LEFT JOIN colores c ON p.color_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$p = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$p) {
    die("Producto no encontrado");
}

// Crear PDF para impresora térmica (ajustado para Brother 800)
// Tamaño: 100x150 mm (4x6 pulgadas - estándar para Brother)
$pdf = new PDF_Code128('P', 'mm', [100, 150]);
$pdf->SetMargins(5, 5, 5);
$pdf->AddPage();

// ----------
// LOGO
// ----------
$logoPath = __DIR__ . '/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 30, 8, 40);
}

// ----------
// TEXTO
// ----------
$pdf->SetY(50);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 8, $p['codigo_barra'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 11);
$pdf->Cell(0, 6, "Orden: ".$p['numero_orden'], 0, 1, 'C');
$pdf->Cell(0, 6, "Medidas: ".$p['ancho_cm']." x ".$p['alto_cm']." cm", 0, 1, 'C');

if (!empty($p['tela'])) {
    $pdf->Cell(0, 6, "Tela: ".$p['tela'], 0, 1, 'C');
}
if (!empty($p['color'])) {
    $pdf->Cell(0, 6, "Color: ".$p['color'], 0, 1, 'C');
}

// ----------
// CÓDIGO DE BARRAS (AL FINAL)
// ----------
$pdf->Code128(
    10,
    115, // posición más baja
    $p['codigo_barra'],
    80,
    22
);

// Salida
$pdf->Output('I', 'etiqueta_'.$p['codigo_barra'].'.pdf');
exit;
