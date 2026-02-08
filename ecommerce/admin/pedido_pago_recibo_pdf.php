<?php
require '../../config.php';
require '../../fpdf.php';

$pago_id = (int)($_GET['pago_id'] ?? 0);
if ($pago_id <= 0) {
    die('Pago no encontrado');
}

$stmt = $pdo->prepare("
    SELECT pp.*, p.numero_pedido, p.total, c.nombre, c.email, c.telefono, p.id AS pedido_id
    FROM ecommerce_pedido_pagos pp
    JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE pp.id = ?
");
$stmt->execute([$pago_id]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    die('Pago no encontrado');
}

$stmt = $pdo->prepare("SELECT SUM(monto) AS total_pagado FROM ecommerce_pedido_pagos WHERE pedido_id = ?");
$stmt->execute([(int)$pago['pedido_id']]);
$total_pagado = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_pagado'] ?? 0);
$saldo = (float)$pago['total'] - $total_pagado;

$stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

class PDF extends FPDF {
    private $empresa;

    function __construct($empresa) {
        parent::__construct();
        $this->empresa = $empresa;
    }

    function Header() {
        if ($this->empresa && !empty($this->empresa['logo'])) {
            $logo_local = __DIR__ . '/../uploads/' . $this->empresa['logo'];
            $logo_root = __DIR__ . '/../../uploads/' . $this->empresa['logo'];
            if (file_exists($logo_local)) {
                $this->Image($logo_local, 10, 6, 30);
            } elseif (file_exists($logo_root)) {
                $this->Image($logo_root, 10, 6, 30);
            }
        }

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode($this->empresa['nombre'] ?? 'RECIBO'), 0, 1, 'C');

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

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode('RECIBO DE PAGO'), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode('Pedido N°: ' . ($pago['numero_pedido'] ?? '-')), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Cliente: ' . ($pago['nombre'] ?? 'N/A')), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Email: ' . ($pago['email'] ?? '-')), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Teléfono: ' . ($pago['telefono'] ?? '-')), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Fecha de pago: ' . date('d/m/Y H:i', strtotime($pago['fecha_pago']))), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Método: ' . ($pago['metodo'] ?? '-')), 0, 1);
$pdf->Cell(0, 6, utf8_decode('Referencia: ' . ($pago['referencia'] ?? '-')), 0, 1);
if (!empty($pago['notas'])) {
    $pdf->MultiCell(0, 6, utf8_decode('Notas: ' . $pago['notas']), 0);
}

$pdf->Ln(2);
$pdf->SetDrawColor(220, 220, 220);
$pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(60, 7, utf8_decode('Detalle'), 1, 0, 'C', true);
$pdf->Cell(60, 7, utf8_decode('Monto'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(60, 7, utf8_decode('Monto recibido'), 1, 0);
$pdf->Cell(60, 7, '$' . number_format((float)$pago['monto'], 2, ',', '.'), 1, 1, 'R');
$pdf->Cell(60, 7, utf8_decode('Total pedido'), 1, 0);
$pdf->Cell(60, 7, '$' . number_format((float)$pago['total'], 2, ',', '.'), 1, 1, 'R');
$pdf->Cell(60, 7, utf8_decode('Total pagado'), 1, 0);
$pdf->Cell(60, 7, '$' . number_format($total_pagado, 2, ',', '.'), 1, 1, 'R');
$pdf->Cell(60, 7, utf8_decode('Saldo'), 1, 0);
$pdf->Cell(60, 7, '$' . number_format($saldo, 2, ',', '.'), 1, 1, 'R');

$pdf->Output('I', 'Recibo_Pago_' . ($pago['numero_pedido'] ?? $pago_id) . '.pdf');
