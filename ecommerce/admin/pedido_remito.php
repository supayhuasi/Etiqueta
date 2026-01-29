<?php
require '../../config.php';
require '../../fpdf.php';

$pedido_id = intval($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.email, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die("Pedido no encontrado");
}

$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    private $empresa;

    function __construct($empresa) {
        parent::__construct();
        $this->empresa = $empresa;
    }

    function Header() {
        if ($this->empresa && $this->empresa['logo'] && file_exists('../../uploads/' . $this->empresa['logo'])) {
            $this->Image('../../uploads/' . $this->empresa['logo'], 10, 6, 30);
        }

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode($this->empresa['nombre'] ?? 'REMITO'), 0, 1, 'C');

        if ($this->empresa) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, utf8_decode($this->empresa['direccion'] ?? ''), 0, 1, 'C');
            $this->Cell(0, 5, 'Tel: ' . ($this->empresa['telefono'] ?? '') . ' - Email: ' . ($this->empresa['email'] ?? ''), 0, 1, 'C');
        }

        $this->Ln(5);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF($empresa);
$pdf->AddPage();

$fecha_pedido = $pedido['fecha_pedido'] ?? $pedido['fecha_creacion'] ?? '';

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('REMITO - PEDIDO N° ' . $pedido['numero_pedido']), 0, 1, 'C');
$pdf->Ln(3);

// Datos fiscales de la empresa
if ($empresa && !empty($empresa['cuit'])) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, utf8_decode('CUIT: ' . $empresa['cuit'] . ' | Responsabilidad: ' . ($empresa['responsabilidad_fiscal'] ?? '-') . ' | Régimen IVA: ' . ($empresa['regimen_iva'] ?? '-')), 0, 1, 'C');
    if (!empty($empresa['iibb'])) {
        $pdf->Cell(0, 4, utf8_decode('Ingresos Brutos: ' . $empresa['iibb']), 0, 1, 'C');
    }
}
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'ENTREGA A', 1, 0, 'C', true);
$pdf->Cell(95, 6, utf8_decode('DATOS DEL REMITO'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$y_start = $pdf->GetY();

$pdf->SetXY(10, $y_start);
$pdf->MultiCell(95, 5, utf8_decode("Nombre: " . ($pedido['nombre'] ?? '')), 1);
$pdf->SetX(10);
$pdf->MultiCell(95, 5, "Email: " . ($pedido['email'] ?? ''), 1);
if (!empty($pedido['telefono'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("Teléfono: " . $pedido['telefono']), 1);
}
if (!empty($pedido['direccion'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("Dirección: " . $pedido['direccion']), 1);
}
if (!empty($pedido['ciudad']) || !empty($pedido['provincia'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("Ciudad/Provincia: " . ($pedido['ciudad'] ?? '') . " / " . ($pedido['provincia'] ?? '')), 1);
}
if (!empty($pedido['codigo_postal'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("CP: " . $pedido['codigo_postal']), 1);
}
$y_end = $pdf->GetY();

$pdf->SetXY(105, $y_start);
$pdf->MultiCell(95, 5, "Fecha: " . ($fecha_pedido ? date('d/m/Y H:i', strtotime($fecha_pedido)) : '-'), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode("Estado: " . ($pedido['estado'] ?? '-')), 1);

$pdf->SetY(max($y_end, $pdf->GetY()));
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(90, 7, 'PRODUCTO', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'MEDIDAS', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'CANT.', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach ($items as $item) {
    $medidas = '';
    if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
        $medidas = ($item['alto_cm'] ?? '-') . 'x' . ($item['ancho_cm'] ?? '-') . ' cm';
    }

    $pdf->Cell(90, 6, utf8_decode(substr($item['producto_nombre'] ?? 'Producto', 0, 45)), 1);
    $pdf->Cell(40, 6, $medidas, 1, 0, 'C');
    $pdf->Cell(30, 6, $item['cantidad'], 1, 1, 'C');

    if (!empty($item['atributos'])) {
        $atributos = json_decode($item['atributos'], true);
        if (is_array($atributos) && count($atributos) > 0) {
            $pdf->SetFont('Arial', 'I', 8);
            $atributos_str = '  Atributos: ';
            foreach ($atributos as $attr) {
                $atributos_str .= ($attr['nombre'] ?? 'Attr') . ': ' . ($attr['valor'] ?? '');
                $atributos_str .= ' | ';
            }
            $atributos_str = rtrim($atributos_str, ' | ');
            $pdf->Cell(90, 5, utf8_decode(substr($atributos_str, 0, 60)), 1);
            $pdf->Cell(70, 5, '', 1, 1);
            $pdf->SetFont('Arial', '', 9);
        }
    }
}

$pdf->Ln(6);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(95, 6, 'Firma receptor:', 0, 0);
$pdf->Cell(95, 6, 'Aclaración:', 0, 1);
$pdf->Cell(95, 6, '______________________________', 0, 0);
$pdf->Cell(95, 6, '______________________________', 0, 1);

$pdf->Ln(4);
$pdf->Cell(95, 6, 'Fecha de entrega:', 0, 0);
$pdf->Cell(95, 6, 'Documento:', 0, 1);
$pdf->Cell(95, 6, '______________________________', 0, 0);
$pdf->Cell(95, 6, '______________________________', 0, 1);

$pdf->Output('I', 'Remito_' . $pedido['numero_pedido'] . '.pdf');
