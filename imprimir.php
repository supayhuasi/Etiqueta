<?php
// 🔧 Mostrar errores SOLO para debug (podés quitar luego)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================
// INCLUDES SEGUROS (LINUX)
// ============================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/code128.php';

$id = $_GET['id'] ?? 0;

// ============================
// BUSCAR PRODUCTO
// ============================
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

// ============================
// PDF 62 x 100 mm
// ============================
$pdf = new PDF_Code128('P', 'mm', [62, 100]);
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(false); // 🔴 CLAVE PARA ETIQUETAS
$pdf->AddPage();

// ============================
// LOGO
// ============================
$pdf->Image(
    __DIR__ . '/logo.png', // ruta absoluta
    6,   // X
    4,   // Y
    50   // ancho
);

// ============================
// TEXTO
// ============================
$pdf->SetY(28);

$pdf->SetFont('Helvetica', 'B', 10);
$pdf->Cell(0, 6, $p['codigo_barra'], 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 9);
$pdf->Cell(0, 5, "Orden: " . $p['numero_orden'], 0, 1, 'C');
$pdf->Cell(0, 5, "Medidas: " . $p['ancho_cm'] . " x " . $p['alto_cm'] . " cm", 0, 1, 'C');

if (!empty($p['tela'])) {
    $pdf->Cell(0, 5, "Tela: " . $p['tela'], 0, 1, 'C');
}

if (!empty($p['color'])) {
    $pdf->Cell(0, 5, "Color: " . $p['color'], 0, 1, 'C');
}

// ============================
// CÓDIGO DE BARRAS (AL FINAL)
// ============================
$pdf->Code128(
    5,                      // X
    70,                     // Y SEGURO (no se corta)
    $p['codigo_barra'],     // código
    52,                     // ancho
    16                      // alto
);

// ============================
// SALIDA
// ============================
$pdf->Output('I', 'etiqueta_' . $p['codigo_barra'] . '.pdf');
