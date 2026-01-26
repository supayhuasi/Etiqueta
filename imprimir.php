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
// Tamaño: 62x60 mm (papel de 62mm x 30,48mm - ajustado a 6cm de largo)
$pdf = new PDF_Code128('P', 'mm', [62, 60]);
$pdf->SetMargins(2, 2, 2);
$pdf->AddPage();

// ----------
// LOGO
// ----------
$logoPath = __DIR__ . '/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 8, 2, 46);
}

// ----------
// TEXTO
// ----------
$pdf->SetY(18);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, $p['codigo_barra'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->Cell(0, 4, "Orden: ".$p['numero_orden'], 0, 1, 'C');
$pdf->Cell(0, 4, "Medidas: ".$p['ancho_cm']." x ".$p['alto_cm']." cm", 0, 1, 'C');

if (!empty($p['tela'])) {
    $pdf->Cell(0, 4, "Tela: ".$p['tela'], 0, 1, 'C');
}
if (!empty($p['color'])) {
    $pdf->Cell(0, 4, "Color: ".$p['color'], 0, 1, 'C');
}

// ----------
// CÓDIGO DE BARRAS (AL FINAL)
// ----------
$pdf->Code128(
    5,
    42, // posición más baja
    $p['codigo_barra'],
    52,
    14
);

// Salida
$pdf->Output('I', 'etiqueta_'.$p['codigo_barra'].'.pdf');
exit;
