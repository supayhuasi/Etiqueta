<?php
require '../../config.php';
require '../../fpdf.php';

$id = intval($_GET['id'] ?? 0);

// Obtener cotización
$stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("Cotización no encontrada");
}

$items = json_decode($cotizacion['items'], true) ?? [];

// Obtener información de la empresa
$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    private $empresa;
    private $cotizacion;
    
    function __construct($empresa, $cotizacion) {
        parent::__construct();
        $this->empresa = $empresa;
        $this->cotizacion = $cotizacion;
    }
    
    function Header() {
        // Logo si existe
        if ($this->empresa && $this->empresa['logo'] && file_exists('../../uploads/' . $this->empresa['logo'])) {
            $this->Image('../../uploads/' . $this->empresa['logo'], 10, 6, 30);
        }
        
        // Nombre de empresa
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode($this->empresa['nombre'] ?? 'PRESUPUESTO'), 0, 1, 'C');
        
        if ($this->empresa) {
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, utf8_decode($this->empresa['direccion'] ?? ''), 0, 1, 'C');
            $this->Cell(0, 5, 'Tel: ' . ($this->empresa['telefono'] ?? '') . ' - Email: ' . ($this->empresa['email'] ?? ''), 0, 1, 'C');
        }
        
        $this->Ln(5);
        
        // Línea
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

$pdf = new PDF($empresa, $cotizacion);
$pdf->AddPage();

// Información de la cotización
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('COTIZACIÓN N° ' . $cotizacion['numero_cotizacion']), 0, 1, 'C');
$pdf->Ln(5);

// Datos del cliente y cotización en dos columnas
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'DATOS DEL CLIENTE', 1, 0, 'C', true);
$pdf->Cell(95, 6, utf8_decode('DATOS DE LA COTIZACIÓN'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);

// Cliente - Columna izquierda
$y_start = $pdf->GetY();
$pdf->SetXY(10, $y_start);
$pdf->MultiCell(95, 5, utf8_decode("Nombre: " . $cotizacion['nombre_cliente']), 1);
if ($cotizacion['empresa']) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("Empresa: " . $cotizacion['empresa']), 1);
}
$pdf->SetX(10);
$pdf->MultiCell(95, 5, "Email: " . $cotizacion['email'], 1);
if ($cotizacion['telefono']) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode("Teléfono: " . $cotizacion['telefono']), 1);
}
$y_end = $pdf->GetY();

// Cotización - Columna derecha
$pdf->SetXY(105, $y_start);
$pdf->MultiCell(95, 5, "Fecha: " . date('d/m/Y', strtotime($cotizacion['fecha_creacion'])), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode("Validez: " . $cotizacion['validez_dias'] . " días"), 1);
$fecha_vence = date('d/m/Y', strtotime($cotizacion['fecha_creacion'] . ' + ' . $cotizacion['validez_dias'] . ' days'));
$pdf->SetX(105);
$pdf->MultiCell(95, 5, "Vence: " . $fecha_vence, 1);

// Ajustar posición
$pdf->SetY(max($y_end, $pdf->GetY()));
$pdf->Ln(5);

// Tabla de items
$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(70, 7, 'PRODUCTO', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'MEDIDAS', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'CANT.', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'PRECIO UNIT.', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'TOTAL', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach ($items as $item) {
    $medidas = '';
    if ($item['ancho'] || $item['alto']) {
        $medidas = ($item['ancho'] ?? '-') . 'x' . ($item['alto'] ?? '-') . ' cm';
    }
    
    $pdf->Cell(70, 6, utf8_decode(substr($item['nombre'], 0, 35)), 1);
    $pdf->Cell(30, 6, $medidas, 1, 0, 'C');
    $pdf->Cell(20, 6, $item['cantidad'], 1, 0, 'C');
    $pdf->Cell(35, 6, '$' . number_format($item['precio_unitario'], 2), 1, 0, 'R');
    $pdf->Cell(35, 6, '$' . number_format($item['precio_total'], 2), 1, 1, 'R');
    
    // Descripción si existe
    if (!empty($item['descripcion'])) {
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->Cell(70, 5, utf8_decode('  ' . substr($item['descripcion'], 0, 50)), 1);
        $pdf->Cell(120, 5, '', 1, 1);
        $pdf->SetFont('Arial', '', 9);
    }
}

// Totales
$pdf->Ln(2);
$pdf->Cell(120, 6, '', 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(35, 6, 'SUBTOTAL:', 1, 0, 'R');
$pdf->Cell(35, 6, '$' . number_format($cotizacion['subtotal'], 2), 1, 1, 'R');

if ($cotizacion['descuento'] > 0) {
    $pdf->Cell(120, 6, '', 0);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(35, 6, 'DESCUENTO:', 1, 0, 'R');
    $pdf->Cell(35, 6, '-$' . number_format($cotizacion['descuento'], 2), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Cell(120, 6, '', 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(200, 230, 255);
$pdf->Cell(35, 8, 'TOTAL:', 1, 0, 'R', true);
$pdf->Cell(35, 8, '$' . number_format($cotizacion['total'], 2), 1, 1, 'R', true);

// Observaciones
if ($cotizacion['observaciones']) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, 'OBSERVACIONES:', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, utf8_decode($cotizacion['observaciones']));
}

// Condiciones
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, utf8_decode("Este presupuesto tiene una validez de {$cotizacion['validez_dias']} días desde la fecha de emisión. Los precios están sujetos a cambios sin previo aviso."));

$pdf->Output('D', 'Cotizacion_' . $cotizacion['numero_cotizacion'] . '.pdf');
