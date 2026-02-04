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
        $this->Cell(0, 10, utf8_decode($this->empresa['nombre'] ?? 'PEDIDO'), 0, 1, 'C');

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
$pdf->Cell(0, 10, utf8_decode('PEDIDO N° ' . $pedido['numero_pedido']), 0, 1, 'C');
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
$pdf->Cell(95, 6, 'DATOS DEL CLIENTE', 1, 0, 'C', true);
$pdf->Cell(95, 6, utf8_decode('DATOS DEL PEDIDO'), 1, 1, 'C', true);

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
$pdf->MultiCell(95, 5, utf8_decode("Método de Pago: " . ($pedido['metodo_pago'] ?? '-')), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode("Estado: " . ($pedido['estado'] ?? '-')), 1);

$pdf->SetY(max($y_end, $pdf->GetY()));
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(70, 7, 'PRODUCTO', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'MEDIDAS', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'CANT.', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'PRECIO UNIT.', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'SUBTOTAL', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach ($items as $item) {
    $medidas = '';
    if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
        $medidas = ($item['ancho_cm'] ?? '-') . 'x' . ($item['alto_cm'] ?? '-') . ' cm';
    }

    $pdf->Cell(70, 6, utf8_decode(substr($item['producto_nombre'] ?? 'Producto', 0, 35)), 1);
    $pdf->Cell(30, 6, $medidas, 1, 0, 'C');
    $pdf->Cell(20, 6, $item['cantidad'], 1, 0, 'C');
    $pdf->Cell(35, 6, '$' . number_format($item['precio_unitario'], 2), 1, 0, 'R');
    $pdf->Cell(35, 6, '$' . number_format($item['subtotal'], 2), 1, 1, 'R');

    if (!empty($item['atributos'])) {
        $atributos = json_decode($item['atributos'], true);
        if (is_array($atributos) && count($atributos) > 0) {
            $pdf->SetFont('Arial', 'I', 8);
            $atributos_str = '  Atributos: ';
            foreach ($atributos as $attr) {
                $atributos_str .= ($attr['nombre'] ?? 'Attr') . ': ' . ($attr['valor'] ?? '');
                if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                    $atributos_str .= ' (+$' . number_format($attr['costo_adicional'], 2) . ')';
                }
                $atributos_str .= ' | ';
            }
            $atributos_str = rtrim($atributos_str, ' | ');
            // Usar MultiCell para permitir múltiples líneas si el texto es muy largo
            $pdf->MultiCell(190, 5, utf8_decode($atributos_str), 1);
            $pdf->SetFont('Arial', '', 9);
        }
    }
}

$pdf->Ln(2);
$pdf->Cell(120, 6, '', 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 6, 'TOTAL:', 1, 0, 'R');
$pdf->Cell(35, 6, '$' . number_format($pedido['total'], 2), 1, 1, 'R');

$pdf->Output('I', 'Pedido_' . $pedido['numero_pedido'] . '.pdf');
