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

// Crear PDF 62x100 mm
$pdf = new PDF_Code128('P', 'mm', [62, 100]);
$pdf->SetMargins(3, 3, 3);
$pdf->AddPage();

// --------------------
// LOGO
// --------------------
$logoPath = __DIR__ . '/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 16, 10, 20);
}

// --------------------
// TEXTO
// --------------------
$pdf->SetY(32);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 6, $p['codigo_barra'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(0, 5, "Orden: ".$p['numero_orden'], 0, 1, 'C');
$pdf->Cell(0, 5, "Medidas: ".$p['ancho_cm']." x ".$p['alto_cm']." cm", 0, 1, 'C');

if (!empty($p['tela'])) {
    $pdf->Cell(0, 5, "Tela: ".$p['tela'], 0, 1, 'C');
}
if (!empty($p['color'])) {
    $pdf->Cell(0, 5, "Color: ".$p['color'], 0, 1, 'C');
}

// --------------------
// CÓDIGO DE BARRAS (AL FINAL)
// --------------------
$pdf->Code128(
    5,
    75, // ⬅️ abajo, sin solapar
    $p['codigo_barra'],
    52,
    16
);

// Salida
$pdf->Output('I', 'etiqueta_'.$p['codigo_barra'].'.pdf');
exit;
