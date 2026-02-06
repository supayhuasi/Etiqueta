<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../config.php';
require '../../code128.php';

$role = $_SESSION['rol'] ?? '';
$allowed_roles = ['admin', 'usuario', 'operario'];
if (!isset($_SESSION['user']) || !in_array($role, $allowed_roles, true)) {
    die('Acceso denegado');
}

$pedido_id = $_GET['pedido_id'] ?? 0;

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido no encontrado');
}

// Items
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pdf = new PDF_Code128('P','mm','A4');
$pdf->AddPage();
$pdf->SetFont('Arial','B',14);
$pdf->Cell(0,8,utf8_decode('Orden de Producción'),0,1,'L');
$pdf->SetFont('Arial','',10);
$pdf->Cell(0,6,utf8_decode('Pedido: ' . $pedido['numero_pedido']),0,1,'L');
$pdf->Cell(0,6,utf8_decode('Cliente: ' . ($pedido['nombre'] ?? 'N/A')),0,1,'L');
$pdf->Cell(0,6,utf8_decode('Dirección: ' . ($pedido['direccion'] ?? 'N/A')),0,1,'L');
$pdf->Ln(2);

$codigo = $pedido['numero_pedido'];
$pdf->Code128(10, $pdf->GetY(), $codigo, 80, 16);
$pdf->Ln(20);

$pdf->SetFont('Arial','B',10);
$pdf->Cell(80,7,utf8_decode('Producto'),1);
$pdf->Cell(35,7,utf8_decode('Medidas'),1);
$pdf->Cell(20,7,utf8_decode('Cant.'),1,1);

$pdf->SetFont('Arial','',10);
foreach ($items as $it) {
    $medidas = '-';
    if (!empty($it['alto_cm']) && !empty($it['ancho_cm'])) {
        $medidas = $it['ancho_cm'] . ' x ' . $it['alto_cm'] . ' cm';
    }
    $pdf->Cell(80,6,utf8_decode($it['producto_nombre'] ?? 'Producto'),1);
    $pdf->Cell(35,6,utf8_decode($medidas),1);
    $pdf->Cell(20,6,utf8_decode($it['cantidad']),1,1);

    $atributos = !empty($it['atributos']) ? json_decode($it['atributos'], true) : [];
    if (is_array($atributos) && count($atributos) > 0) {
        $pdf->SetFont('Arial','',9);
        foreach ($atributos as $attr) {
            $line = ($attr['nombre'] ?? 'Attr') . ': ' . ($attr['valor'] ?? '');
            $pdf->Cell(135,5,utf8_decode('  - ' . $line),1,1);
        }
        $pdf->SetFont('Arial','',10);
    }
}

$pdf->Output('I', 'orden_produccion_'.$pedido['numero_pedido'].'.pdf');
exit;
