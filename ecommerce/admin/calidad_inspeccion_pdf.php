<?php
require '../../config.php';
require '../../fpdf.php';
require_once __DIR__ . '/includes/calidad_helper.php';

ensureCalidadSchema($pdo);

$pedidoId = (int)($_GET['pedido_id'] ?? 0);
if ($pedidoId <= 0) {
    die('Pedido inválido.');
}

$stmtPedido = $pdo->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email, c.telefono AS cliente_telefono FROM ecommerce_pedidos p LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id WHERE p.id = ? LIMIT 1");
$stmtPedido->execute([$pedidoId]);
$pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido no encontrado.');
}

$inspeccion = obtenerInspeccionCalidadPedido($pdo, $pedidoId);
if (!$inspeccion) {
    die('El pedido todavía no tiene un control de calidad cargado.');
}

$itemsPedido = [];
if (calidad_table_exists($pdo, 'ecommerce_pedido_items')) {
    $stmtItems = $pdo->prepare("SELECT pi.id, pi.cantidad, pi.ancho_cm, pi.alto_cm, COALESCE(pr.nombre, 'Producto') AS producto_nombre FROM ecommerce_pedido_items pi LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id WHERE pi.pedido_id = ? ORDER BY pi.id ASC");
    $stmtItems->execute([$pedidoId]);
    $itemsPedido = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$revisionMap = [];
foreach (($inspeccion['items_revision'] ?? []) as $itemRevision) {
    $revisionMap[(int)($itemRevision['item_id'] ?? 0)] = $itemRevision;
}

$stmtEmpresa = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmtEmpresa ? ($stmtEmpresa->fetch(PDO::FETCH_ASSOC) ?: []) : [];

class PDFCalidad extends FPDF
{
    private $empresa;

    public function __construct(array $empresa)
    {
        parent::__construct();
        $this->empresa = $empresa;
        $this->SetAutoPageBreak(true, 15);
    }

    public function Header()
    {
        $logoPrinted = false;
        if (!empty($this->empresa['logo'])) {
            $logoLocal = '../../ecommerce/uploads/' . $this->empresa['logo'];
            $logoRoot = '../../uploads/' . $this->empresa['logo'];
            if (file_exists($logoLocal)) {
                $this->Image($logoLocal, 10, 5, 36);
                $logoPrinted = true;
            } elseif (file_exists($logoRoot)) {
                $this->Image($logoRoot, 10, 5, 36);
                $logoPrinted = true;
            }
        }

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 8, utf8_decode($this->empresa['nombre'] ?? 'Tucu Roller'), 0, 1, 'C');
        $this->SetFont('Arial', 'I', 9);
        $this->Cell(0, 5, utf8_decode('Informe interno de control de calidad'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        if (!empty($this->empresa['direccion']) || !empty($this->empresa['telefono']) || !empty($this->empresa['email'])) {
            $linea = trim((string)($this->empresa['direccion'] ?? ''));
            if (!empty($this->empresa['telefono'])) {
                $linea .= ($linea !== '' ? ' · ' : '') . 'Tel: ' . $this->empresa['telefono'];
            }
            if (!empty($this->empresa['email'])) {
                $linea .= ($linea !== '' ? ' · ' : '') . 'Email: ' . $this->empresa['email'];
            }
            $this->Cell(0, 5, utf8_decode($linea), 0, 1, 'C');
        }

        $this->Ln($logoPrinted ? 3 : 2);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDFCalidad(is_array($empresa) ? $empresa : []);
$pdf->AddPage();

$estadoCalidadKey = strtolower(trim((string)($inspeccion['estado_calidad'] ?? 'pendiente')));
$estadoControl = ucfirst(str_replace('_', ' ', $estadoCalidadKey));
$pasoPruebaBool = (int)($inspeccion['prueba_aprobada'] ?? 0) === 1;
$pasoPrueba = $pasoPruebaBool ? 'Sí' : 'No';
$fechaRevision = !empty($inspeccion['fecha_revision']) ? date('d/m/Y H:i', strtotime((string)$inspeccion['fecha_revision'])) : '-';

$estadoFill = [108, 117, 125];
$estadoText = [255, 255, 255];
if ($estadoCalidadKey === 'aprobado') {
    $estadoFill = [25, 135, 84];
} elseif ($estadoCalidadKey === 'observado') {
    $estadoFill = [255, 193, 7];
    $estadoText = [33, 37, 41];
} elseif ($estadoCalidadKey === 'rechazado') {
    $estadoFill = [220, 53, 69];
}

$pruebaFill = $pasoPruebaBool ? [25, 135, 84] : [220, 53, 69];
$pruebaText = [255, 255, 255];
$responsableFirma = trim((string)($inspeccion['revisor_nombre'] ?? 'Responsable de calidad'));
$fechaEmisionDoc = date('d/m/Y H:i');
$codigoValidacion = 'QC-' . str_pad((string)$pedidoId, 6, '0', STR_PAD_LEFT) . '-' . date('YmdHi');

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 9, utf8_decode('INFORME DE CONTROL DE CALIDAD'), 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 7, utf8_decode('Pedido ' . ($pedido['numero_pedido'] ?? ('#' . $pedidoId))), 0, 1, 'C');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor($estadoFill[0], $estadoFill[1], $estadoFill[2]);
$pdf->SetTextColor($estadoText[0], $estadoText[1], $estadoText[2]);
$pdf->Cell(95, 8, utf8_decode('Estado calidad: ' . strtoupper($estadoControl)), 1, 0, 'C', true);
$pdf->SetFillColor($pruebaFill[0], $pruebaFill[1], $pruebaFill[2]);
$pdf->SetTextColor($pruebaText[0], $pruebaText[1], $pruebaText[2]);
$pdf->Cell(95, 8, utf8_decode('Prueba final: ' . strtoupper($pasoPrueba)), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(3);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(235, 240, 248);
$pdf->Cell(95, 7, 'DATOS DEL PEDIDO', 1, 0, 'C', true);
$pdf->Cell(95, 7, 'RESULTADO DEL CONTROL', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$yStart = $pdf->GetY();

$pdf->SetXY(10, $yStart);
$pdf->MultiCell(95, 6, utf8_decode(
    "Cliente: " . ($pedido['cliente_nombre'] ?? 'Sin cliente') .
    "\nFecha pedido: " . (!empty($pedido['fecha_pedido']) ? date('d/m/Y H:i', strtotime((string)$pedido['fecha_pedido'])) : '-') .
    "\nEstado pedido: " . ucfirst(str_replace('_', ' ', (string)($pedido['estado'] ?? '-'))) .
    "\nTotal: $" . number_format((float)($pedido['total'] ?? 0), 2, ',', '.')
), 1);

$pdf->SetXY(105, $yStart);
$pdf->MultiCell(95, 6, utf8_decode(
    "Estado calidad: " . $estadoControl .
    "\nPasó la prueba: " . $pasoPrueba .
    "\nFecha revisión: " . $fechaRevision .
    "\nRevisado por: " . ($inspeccion['revisor_nombre'] ?? 'Usuario admin')
), 1);

$pdf->SetY(max($pdf->GetY(), $yStart + 24));
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'DETALLE DETECTADO', 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 6, utf8_decode(trim((string)($inspeccion['detalle_revision'] ?? 'Sin detalle general cargado.'))), 1);
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'OBSERVACIONES FINALES', 1, 1, 'L', true);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 6, utf8_decode(trim((string)($inspeccion['observaciones'] ?? 'Sin observaciones finales.'))), 1);
$pdf->Ln(4);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(76, 7, 'ITEM', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'CANT.', 1, 0, 'C', true);
$pdf->Cell(34, 7, 'MEDIDAS', 1, 0, 'C', true);
$pdf->Cell(26, 7, 'RESULTADO', 1, 0, 'C', true);
$pdf->Cell(34, 7, 'OBSERVACION', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8);
if (empty($itemsPedido)) {
    $pdf->Cell(190, 7, utf8_decode('No hay ítems cargados para este pedido.'), 1, 1, 'C');
} else {
    foreach ($itemsPedido as $item) {
        $itemId = (int)($item['id'] ?? 0);
        $revision = $revisionMap[$itemId] ?? [];
        $medidas = '';
        if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
            $medidas = ($item['ancho_cm'] !== null && $item['ancho_cm'] !== '' ? $item['ancho_cm'] : '-') . 'x' . ($item['alto_cm'] !== null && $item['alto_cm'] !== '' ? $item['alto_cm'] : '-') . ' cm';
        }

        $resultadoItemKey = strtolower(trim((string)($revision['estado'] ?? 'ok')));
        $resultadoItem = ucfirst(str_replace('_', ' ', $resultadoItemKey));
        $observacionItem = trim((string)($revision['observacion'] ?? ''));
        if ($observacionItem === '') {
            $observacionItem = '-';
        }

        $fillResultado = [108, 117, 125];
        $textResultado = [255, 255, 255];
        if ($resultadoItemKey === 'ok') {
            $fillResultado = [25, 135, 84];
        } elseif ($resultadoItemKey === 'observado') {
            $fillResultado = [255, 193, 7];
            $textResultado = [33, 37, 41];
        } elseif ($resultadoItemKey === 'rechazado') {
            $fillResultado = [220, 53, 69];
        } elseif ($resultadoItemKey === 'no_terminada') {
            $fillResultado = [253, 126, 20];
        }

        $y = $pdf->GetY();
        $lineasObs = max(1, (int)ceil(strlen($observacionItem) / 24));
        $altoFila = max(8, $lineasObs * 5);

        $pdf->Cell(76, $altoFila, utf8_decode(substr((string)($item['producto_nombre'] ?? 'Producto'), 0, 45)), 1);
        $pdf->Cell(20, $altoFila, (string)($item['cantidad'] ?? '1'), 1, 0, 'C');
        $pdf->Cell(34, $altoFila, utf8_decode($medidas !== '' ? $medidas : '-'), 1, 0, 'C');
        $pdf->SetFillColor($fillResultado[0], $fillResultado[1], $fillResultado[2]);
        $pdf->SetTextColor($textResultado[0], $textResultado[1], $textResultado[2]);
        $pdf->Cell(26, $altoFila, utf8_decode($resultadoItem), 1, 0, 'C', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->MultiCell(34, 5, utf8_decode($observacionItem), 1);

        if ($pdf->GetY() < $y + $altoFila) {
            $pdf->SetY($y + $altoFila);
        }
    }
}

$pdf->Ln(8);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(232, 245, 233);
$pdf->Cell(0, 7, utf8_decode('VALIDACION DIGITAL AUTOMATICA'), 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 9);
$pdf->MultiCell(0, 6, utf8_decode('Responsable: ' . $responsableFirma . ' · Emitido: ' . $fechaEmisionDoc . ' · Código: ' . $codigoValidacion), 1);

$pdf->Ln(6);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(85, 6, utf8_decode('Firma responsable de calidad'), 0, 0, 'C');
$pdf->Cell(20, 6, '', 0, 0, 'C');
$pdf->Cell(85, 6, utf8_decode('Conformidad cliente / supervisor'), 0, 1, 'C');
$pdf->Ln(12);
$yFirma = $pdf->GetY();
$pdf->Line(20, $yFirma, 85, $yFirma);
$pdf->Line(125, $yFirma, 190, $yFirma);
$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(85, 5, utf8_decode($responsableFirma), 0, 0, 'C');
$pdf->Cell(20, 5, '', 0, 0, 'C');
$pdf->Cell(85, 5, utf8_decode('Firma y aclaración'), 0, 1, 'C');
$pdf->Cell(85, 5, utf8_decode('Documento emitido digitalmente'), 0, 0, 'C');
$pdf->Cell(20, 5, '', 0, 0, 'C');
$pdf->Cell(85, 5, utf8_decode('Recepción / conformidad'), 0, 1, 'C');

$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 9);
$pdf->MultiCell(0, 5, utf8_decode('Este informe resume el control de calidad realizado sobre el pedido y deja constancia del resultado de la prueba, observaciones detectadas, firma digital automática y estado final.'));

$nombreArchivo = 'Calidad_Pedido_' . preg_replace('/[^A-Za-z0-9_-]/', '_', (string)($pedido['numero_pedido'] ?? $pedidoId)) . '.pdf';
$pdf->Output('I', $nombreArchivo);
