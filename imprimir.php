<?php
require 'config.php';
require 'fpdf.php';
require 'code128.php';

$id = $_GET['id'];

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
$p = $stmt->fetch();

if (!$p) {
    die("Producto no encontrado");
}

// PDF 62x100 mm
$pdf = new PDF_Code128('P', 'mm', [62, 100]);
$pdf->AddPage();
$pdf->SetMargins(3,3,3);

// LOGO
$pdf->Image('logo.png', 6, 4, 50);
$pdf->Ln(28);

// CÓDIGO DE BARRAS
$pdf->Code128(5, 34, $p['codigo_barra'], 52, 16);
$pdf->Ln(18);

// TEXTO
$pdf->SetFont('Arial','B',10);
$pdf->Cell(0,6,$p['codigo_barra'],0,1,'C');

$pdf->SetFont('Arial','',9);
$pdf->Cell(0,5,"Orden: ".$p['numero_orden'],0,1,'C');
$pdf->Cell(0,5,"Medidas: ".$p['ancho_cm']." x ".$p['alto_cm']." cm",0,1,'C');

if ($p['tela']) {
    $pdf->Cell(0,5,"Tela: ".$p['tela'],0,1,'C');
}
if ($p['color']) {
    $pdf->Cell(0,5,"Color: ".$p['color'],0,1,'C');
}

$pdf->Output();
