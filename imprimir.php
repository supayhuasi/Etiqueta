<?php
require_once 'config.php';
require_once 'code128.php';

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

// Crear PDF tamaño 62x100 mm
$pdf = new PDF_Code128('P', 'mm', [62, 100]);
$pdf->SetMargins(3, 3, 3);
$pdf->AddPage();

// --------------------
// LOGO
// --------------------
$pdf->Image('logo.png', 6, 4, 50); // centrado arriba

// --------------------
// CÓDIGO DE BARRAS
// --------------------
$pdf->Code128(
    5,                 // X
    30,                // Y
    $p['codigo_barra'],// Código
    52,                // Ancho
    16                 // Alto
);

// --------------------
// TEXTO
// --------------------
$pdf->SetY(48);

// Código en texto
$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, $p['codigo_barra'], 0, 1, 'C');

// Datos
$pdf->SetFont('Helvetica', '', 9);
$pdf->Cell(0, 5, "Orden: " . $p['numero_orden'], 0, 1, 'C');
$pdf->Cell(0, 5, "Medidas: " . $p['ancho_cm'] . " x " . $p['alto_cm'] . " cm", 0, 1, 'C');

if (!empty($p['tela'])) {
    $pdf->Cell(0, 5, "Tela: " . $p['tela'], 0, 1, 'C');
}
if (!empty($p['color'])) {
    $pdf->Cell(0, 5, "Color: " . $p['color'], 0, 1, 'C');
}

// Salida
$pdf->Output('I', 'etiqueta_'.$p['codigo_barra'].'.pdf');
