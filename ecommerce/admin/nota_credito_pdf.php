<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/nota_credito_helper.php';
require_once __DIR__ . '/../config.php';

// Obtener NC
$nc_id = (int)($_GET['id'] ?? 0);
if ($nc_id <= 0) {
    die('Nota de crédito no especificada');
}

$nc = nota_credito_obtener($pdo, $nc_id);
if (!$nc || $nc['estado'] === 'borrador') {
    die('Nota de crédito no encontrada o aún no emitida');
}

// Incorporar FPDF si existe
require_once __DIR__ . '/../fpdf.php';

class NCPDF extends FPDF
{
    public $titulo = 'NOTA DE CRÉDITO';
    public $empresa = [];
    
    public function Header()
    {
        // Encabezado
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $this->titulo, 0, 1, 'C');
        
        if (!empty($this->empresa['nombre'])) {
            $this->SetFont('Arial', '', 10);
            $this->Cell(0, 5, htmlspecialchars($this->empresa['nombre']), 0, 1, 'C');
        }
        
        $this->Ln(3);
    }
    
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Página ' . $this->PageNo(), 0, 0, 'C');
    }
}

// Obtener datos de la empresa y cliente
$empresa = [];
try {
    $stmt = $pdo->query("SELECT id, nombre, cuit, email, provincia, ciudad FROM ecommerce_empresa LIMIT 1");
    $empresa = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {
}

$cliente = [];
if ($nc['pedido_id']) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.* FROM ecommerce_clientes c
            JOIN ecommerce_pedidos p ON c.id = p.cliente_id
            WHERE p.id = ?
        ");
        $stmt->execute([$nc['pedido_id']]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }
}

// Determinar tipo de comprobante
$es_fiscal = $nc['comprobante_tipo'] === 'factura';
$tipo_label = $es_fiscal ? ($nc['tipo_nc'] === '03' ? 'Nota de Crédito A' : ($nc['tipo_nc'] === '08' ? 'Nota de Crédito B' : 'Nota de Crédito C')) : 'Recibo de Nota de Crédito';

// Crear PDF
$pdf = new NCPDF();
$pdf->empresa = $empresa;
$pdf->AddPage();
$pdf->SetFont('Arial', '', 11);

// Información de la NC
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Tipo:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, $tipo_label, 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Número:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, htmlspecialchars($nc['numero_nc'] ?? 'N/A'), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Fecha:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, date('d/m/Y', strtotime($nc['fecha_emision'] ?? 'now')), 0, 1);

if (!empty($nc['cae'])) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'CAE:', 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, htmlspecialchars($nc['cae']), 0, 1);
}

$pdf->Ln(5);

// Datos de la empresa y cliente
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'EMPRESA', 1);
$pdf->Cell(95, 6, 'CLIENTE', 1, 1);

$pdf->SetFont('Arial', '', 9);
$empresa_text = (!empty($empresa['nombre']) ? htmlspecialchars($empresa['nombre']) : 'Empresa') . "\n";
if (!empty($empresa['cuit'])) $empresa_text .= "CUIT: " . htmlspecialchars($empresa['cuit']) . "\n";
if (!empty($empresa['email'])) $empresa_text .= htmlspecialchars($empresa['email']) . "\n";

$cliente_text = (!empty($cliente['nombre']) ? htmlspecialchars($cliente['nombre']) : 'Cliente Desconocido') . "\n";
if (!empty($cliente['documento_tipo']) && !empty($cliente['documento_numero'])) {
    $cliente_text .= htmlspecialchars($cliente['documento_tipo']) . ": " . htmlspecialchars($cliente['documento_numero']) . "\n";
}
if (!empty($cliente['email'])) $cliente_text .= htmlspecialchars($cliente['email']) . "\n";

$pdf->MultiCell(95, 6, $empresa_text, 1, 'L');
$pdf->SetXY(105, $pdf->GetY() - 18);
$pdf->MultiCell(95, 6, $cliente_text, 1, 'L');

$pdf->Ln(3);

// Información de referencia
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Factura Original:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, htmlspecialchars($nc['factura_original'] ?? 'Sin referencia'), 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Pedido ID:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, '#' . $nc['pedido_id'], 0, 1);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 6, 'Motivo:', 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, htmlspecialchars($nc['motivo'] ?? ''), 0, 1);

if (!empty($nc['descripcion'])) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(40, 6, 'Descripción:', 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 4, htmlspecialchars($nc['descripcion']), 0, 'L');
}

$pdf->Ln(3);

// Tabla de items
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(100, 7, 'DESCRIPCIÓN', 1);
$pdf->Cell(30, 7, 'CANTIDAD', 1);
$pdf->Cell(30, 7, 'PRECIO UNIT.', 1);
$pdf->Cell(30, 7, 'SUBTOTAL', 1, 1, 'R');

$pdf->SetFont('Arial', '', 9);
if (!empty($nc['items'])) {
    foreach ($nc['items'] as $item) {
        $pdf->Cell(100, 6, htmlspecialchars(substr($item['descripcion'], 0, 50)), 1);
        $pdf->Cell(30, 6, number_format($item['cantidad'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$' . number_format($item['precio_unitario'], 2, ',', '.'), 1, 0, 'R');
        $pdf->Cell(30, 6, '$' . number_format($item['subtotal'], 2, ',', '.'), 1, 1, 'R');
    }
}

// Total
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(160, 8, 'TOTAL NOTA DE CRÉDITO:', 1, 0, 'R');
$pdf->Cell(30, 8, '$' . number_format($nc['monto_total'], 2, ',', '.'), 1, 1, 'R');

// Pie
$pdf->Ln(10);
$pdf->SetFont('Arial', 'I', 8);
$pdf->Cell(0, 4, 'Esta nota de crédito ha sido emitida el ' . date('d/m/Y H:i'), 0, 1, 'C');
if ($es_fiscal && !empty($nc['cae'])) {
    $pdf->Cell(0, 4, 'CAE válido hasta: ' . ($nc['cae_vencimiento'] ? date('d/m/Y', strtotime($nc['cae_vencimiento'])) : 'N/A'), 0, 1, 'C');
}

// Imprimir o descargar
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="NotaCredito_' . htmlspecialchars($nc['numero_nc']) . '_' . date('Ymd_His') . '.pdf"');
echo $pdf->Output('D');
