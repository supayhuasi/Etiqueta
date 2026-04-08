<?php
ob_start();
require dirname(__DIR__, 2) . '/config.php';
require dirname(__DIR__, 2) . '/fpdf.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

ensureContabilidadSchema($pdo);

$pedidoId = (int)($_GET['pedido_id'] ?? 0);
if ($pedidoId <= 0) {
    die('Pedido inválido.');
}

$stmtPedido = $pdo->prepare("
    SELECT
        p.*,
        c.nombre AS cliente_nombre,
        c.email AS cliente_email,
        c.telefono AS cliente_telefono,
        c.direccion AS cliente_direccion,
        c.localidad AS cliente_localidad,
        c.ciudad AS cliente_ciudad,
        c.provincia AS cliente_provincia,
        c.codigo_postal AS cliente_codigo_postal,
        c.responsabilidad_fiscal AS cliente_responsabilidad_fiscal,
        c.documento_tipo AS cliente_documento_tipo,
        c.documento_numero AS cliente_documento_numero
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id
    WHERE p.id = ?
    LIMIT 1
");
$stmtPedido->execute([$pedidoId]);
$pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido no encontrado.');
}

$stmtItems = $pdo->prepare("
    SELECT
        pi.*,
        COALESCE(pr.nombre, 'Producto') AS producto_nombre
    FROM ecommerce_pedido_items pi
    LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id ASC
");
$stmtItems->execute([$pedidoId]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

$stmtEmpresa = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
$empresa = $stmtEmpresa ? ($stmtEmpresa->fetch(PDO::FETCH_ASSOC) ?: []) : [];
$configContable = contabilidad_get_config($pdo);

$condicionEmisor = trim((string)($configContable['condicion_fiscal'] ?? ''));
if ($condicionEmisor === '') {
    $condicionEmisor = trim((string)($empresa['regimen_iva'] ?? ($empresa['responsabilidad_fiscal'] ?? 'Responsable Inscripto')));
}
if ($condicionEmisor === '') {
    $condicionEmisor = 'Responsable Inscripto';
}

$documentoTipo = strtoupper(trim((string)($pedido['cliente_documento_tipo'] ?? '')));
$documentoNumero = preg_replace('/\D+/', '', (string)($pedido['cliente_documento_numero'] ?? ''));
if ($documentoTipo === '') {
    $documentoTipo = !empty($pedido['factura_a']) ? 'CUIT' : 'DNI';
}
if ($documentoNumero === '') {
    $documentoNumero = preg_replace('/\D+/', '', (string)($pedido['cuit'] ?? ''));
}

$condicionCliente = trim((string)($pedido['cliente_responsabilidad_fiscal'] ?? ''));
if ($condicionCliente === '') {
    $condicionCliente = !empty($pedido['factura_a']) ? 'Responsable Inscripto' : 'Consumidor Final';
}

$solicitaFacturaA = !empty($pedido['factura_a']) && $documentoTipo === 'CUIT' && strlen($documentoNumero) >= 11;
$tipoFacturaInfo = contabilidad_determinar_tipo_factura($condicionEmisor, $condicionCliente, $solicitaFacturaA);
$tipoFactura = (string)($pedido['tipo_factura'] ?? '');
if ($tipoFactura === '') {
    $tipoFactura = (string)($tipoFacturaInfo['tipo'] ?? 'B');
}

$numeroFactura = trim((string)($pedido['numero_factura'] ?? ''));
if ($numeroFactura === '') {
    $numeroFactura = contabilidad_generar_numero_factura($pdo, $tipoFactura, 1);
}

$fechaFacturacion = !empty($pedido['fecha_facturacion']) ? (string)$pedido['fecha_facturacion'] : date('Y-m-d H:i:s');

$baseSubtotal = max(0, (float)($pedido['subtotal'] ?? 0) - (float)($pedido['descuento_monto'] ?? 0));
$baseTotal = max(0, (float)($pedido['subtotal'] ?? 0) + (float)($pedido['envio'] ?? 0) - (float)($pedido['descuento_monto'] ?? 0));
$resumenImpuestos = !empty($pedido['impuestos_json'])
    ? [
        'detalle' => (json_decode((string)$pedido['impuestos_json'], true) ?: []),
        'total_incluidos' => (float)($pedido['impuestos_incluidos'] ?? 0),
        'total_adicionales' => (float)($pedido['impuestos_adicionales'] ?? 0),
        'total_con_impuestos' => (float)($pedido['total'] ?? $baseTotal),
    ]
    : contabilidad_calcular_impuestos(contabilidad_get_impuestos($pdo, true), $baseSubtotal, $baseTotal, 'pedido');

$moneda = trim((string)($configContable['moneda'] ?? 'ARS')) ?: 'ARS';
$notasFiscales = trim((string)($configContable['notas_fiscales'] ?? ''));
$clienteNombre = trim((string)($pedido['cliente_nombre'] ?? $pedido['envio_nombre'] ?? 'Cliente')) ?: 'Cliente';
$clienteDireccion = trim((string)($pedido['cliente_direccion'] ?? $pedido['envio_direccion'] ?? ''));
$clienteCiudad = trim((string)($pedido['cliente_ciudad'] ?? $pedido['cliente_localidad'] ?? $pedido['envio_localidad'] ?? ''));
$clienteProvincia = trim((string)($pedido['cliente_provincia'] ?? $pedido['envio_provincia'] ?? ''));
$clienteCodigoPostal = trim((string)($pedido['cliente_codigo_postal'] ?? $pedido['envio_codigo_postal'] ?? ''));

class PDFFacturaArgentina extends FPDF
{
    private array $empresa;
    private string $tipoFactura;
    private string $numeroFactura;

    public function __construct(array $empresa, string $tipoFactura, string $numeroFactura)
    {
        parent::__construct();
        $this->empresa = $empresa;
        $this->tipoFactura = $tipoFactura;
        $this->numeroFactura = $numeroFactura;
        $this->SetMargins(10, 10, 10);
        $this->SetAutoPageBreak(true, 15);
    }

    public function Header()
    {
        if (!empty($this->empresa['logo'])) {
            $logoLocal = __DIR__ . '/../../uploads/' . $this->empresa['logo'];
            $logoAlt = __DIR__ . '/../uploads/' . $this->empresa['logo'];
            if (file_exists($logoLocal)) {
                $this->Image($logoLocal, 10, 8, 28);
            } elseif (file_exists($logoAlt)) {
                $this->Image($logoAlt, 10, 8, 28);
            }
        }

        $this->SetFont('Arial', 'B', 16);
        $this->Cell(130, 8, utf8_decode($this->empresa['nombre'] ?? 'Factura'), 0, 0, 'L');
        $this->SetFont('Arial', 'B', 22);
        $this->Cell(20, 12, $this->tipoFactura, 1, 0, 'C');
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(30, 6, utf8_decode('FACTURA'), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->Cell(130, 5, utf8_decode(trim((string)($this->empresa['direccion'] ?? ''))), 0, 0, 'L');
        $this->Cell(50, 5, utf8_decode('N° ' . $this->numeroFactura), 0, 1, 'R');

        $lineaFiscal = 'CUIT: ' . trim((string)($this->empresa['cuit'] ?? '-'));
        if (!empty($this->empresa['iibb'])) {
            $lineaFiscal .= ' · IIBB: ' . $this->empresa['iibb'];
        }
        $this->Cell(130, 5, utf8_decode($lineaFiscal), 0, 0, 'L');
        $this->Cell(50, 5, utf8_decode('Original'), 0, 1, 'R');

        $condicion = trim((string)($this->empresa['regimen_iva'] ?? ($this->empresa['responsabilidad_fiscal'] ?? '')));
        if ($condicion !== '') {
            $this->Cell(0, 5, utf8_decode('Condición frente al IVA: ' . $condicion), 0, 1, 'L');
        }

        $this->Ln(3);
        $this->SetDrawColor(180, 180, 180);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    public function Footer()
    {
        $this->SetY(-18);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(90, 90, 90);
        $this->MultiCell(0, 4, utf8_decode('Comprobante emitido según condición fiscal argentina. Si se requiere validez fiscal electrónica oficial, debe integrarse CAE/AFIP WSFE.'));
    }
}

$pdf = new PDFFacturaArgentina($empresa, $tipoFactura, $numeroFactura);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(95, 7, utf8_decode('Datos del cliente'), 1, 0, 'C', true);
$pdf->Cell(95, 7, utf8_decode('Datos del comprobante'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$yStart = $pdf->GetY();
$pdf->MultiCell(95, 5,
    utf8_decode(
        'Cliente: ' . $clienteNombre
        . "\nCondición fiscal: " . $condicionCliente
        . "\n" . $documentoTipo . ': ' . ($documentoNumero !== '' ? $documentoNumero : '-')
        . "\nDirección: " . ($clienteDireccion !== '' ? $clienteDireccion : '-')
        . "\nLocalidad: " . ($clienteCiudad !== '' ? $clienteCiudad : '-') . ' - ' . ($clienteProvincia !== '' ? $clienteProvincia : '-')
        . "\nCP: " . ($clienteCodigoPostal !== '' ? $clienteCodigoPostal : '-')
    ),
    1
);
$yLeftEnd = $pdf->GetY();

$pdf->SetXY(105, $yStart);
$pdf->MultiCell(95, 5,
    utf8_decode(
        'Pedido: ' . ($pedido['numero_pedido'] ?? ('#' . $pedidoId))
        . "\nFecha de emisión: " . date('d/m/Y H:i', strtotime($fechaFacturacion))
        . "\nTipo: Factura " . $tipoFactura
        . "\nMoneda: " . $moneda
        . "\nEstado pedido: " . str_replace('_', ' ', (string)($pedido['estado'] ?? 'pendiente'))
        . "\nCAE: Pendiente / no integrado"
    ),
    1
);
$yRightEnd = $pdf->GetY();
$pdf->SetY(max($yLeftEnd, $yRightEnd) + 4);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(80, 7, 'Detalle', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Medidas', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Cant.', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'P. Unitario', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Subtotal', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach ($items as $item) {
    $nombreItem = trim((string)($item['producto_nombre'] ?? 'Producto'));
    $medidas = '-';
    if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
        $medidas = (string)($item['ancho_cm'] ?? '-') . 'x' . (string)($item['alto_cm'] ?? '-') . ' cm';
    }
    $cantidad = (float)($item['cantidad'] ?? 0);
    $precioUnitario = (float)($item['precio_unitario'] ?? 0);
    $subtotalItem = (float)($item['subtotal'] ?? ($cantidad * $precioUnitario));

    $pdf->Cell(80, 6, utf8_decode(substr($nombreItem, 0, 40)), 1);
    $pdf->Cell(25, 6, utf8_decode($medidas), 1, 0, 'C');
    $pdf->Cell(20, 6, number_format($cantidad, 0, ',', '.'), 1, 0, 'C');
    $pdf->Cell(30, 6, '$' . number_format($precioUnitario, 2, ',', '.'), 1, 0, 'R');
    $pdf->Cell(35, 6, '$' . number_format($subtotalItem, 2, ',', '.'), 1, 1, 'R');
}

$pdf->Ln(3);
$pdf->Cell(115, 6, '', 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 6, 'Subtotal neto', 1, 0, 'R');
$pdf->Cell(35, 6, '$' . number_format((float)($pedido['subtotal'] ?? 0), 2, ',', '.'), 1, 1, 'R');

if ((float)($pedido['envio'] ?? 0) > 0) {
    $pdf->Cell(115, 6, '', 0);
    $pdf->SetFont('Arial', '', 9);
    $pdf->Cell(40, 6, 'Envío', 1, 0, 'R');
    $pdf->Cell(35, 6, '$' . number_format((float)$pedido['envio'], 2, ',', '.'), 1, 1, 'R');
}

if ((float)($pedido['descuento_monto'] ?? 0) > 0) {
    $pdf->Cell(115, 6, '', 0);
    $pdf->SetTextColor(0, 128, 0);
    $pdf->Cell(40, 6, 'Descuento', 1, 0, 'R');
    $pdf->Cell(35, 6, '-$' . number_format((float)$pedido['descuento_monto'], 2, ',', '.'), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
}

foreach (($resumenImpuestos['detalle'] ?? []) as $impuesto) {
    $montoImpuesto = (float)($impuesto['monto'] ?? 0);
    if ($montoImpuesto <= 0) {
        continue;
    }
    $pdf->Cell(115, 6, '', 0);
    if (!empty($impuesto['incluido_en_precio'])) {
        $pdf->SetTextColor(110, 110, 110);
    } else {
        $pdf->SetTextColor(180, 0, 0);
    }
    $pdf->Cell(40, 6, utf8_decode((string)($impuesto['nombre'] ?? 'Impuesto') . (!empty($impuesto['incluido_en_precio']) ? ' (incl.)' : '')), 1, 0, 'R');
    $pdf->Cell(35, 6, (!empty($impuesto['incluido_en_precio']) ? '' : '+') . '$' . number_format($montoImpuesto, 2, ',', '.'), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
}

$pdf->Cell(115, 7, '', 0);
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(210, 230, 255);
$pdf->Cell(40, 7, 'TOTAL', 1, 0, 'R', true);
$pdf->Cell(35, 7, '$' . number_format((float)($pedido['total'] ?? 0), 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(4);
$pdf->SetFont('Arial', '', 8);
if ($notasFiscales !== '') {
    $pdf->MultiCell(0, 4, utf8_decode('Notas fiscales: ' . $notasFiscales));
    $pdf->Ln(2);
}
$pdf->MultiCell(0, 4, utf8_decode('Este documento se genera automáticamente respetando la condición fiscal del emisor y del cliente (Factura A/B/C).'));

$uploadsRoot = __DIR__ . '/../../uploads';
if (!is_dir($uploadsRoot)) {
    @mkdir($uploadsRoot, 0755, true);
}
$facturasDir = $uploadsRoot . '/facturas_pedidos';
if (!is_dir($facturasDir)) {
    @mkdir($facturasDir, 0755, true);
}

$numeroFacturaArchivo = str_replace(['/', '\\', ' '], '_', $numeroFactura);
$nombreArchivo = 'Factura_' . $tipoFactura . '_' . $numeroFacturaArchivo . '_pedido_' . $pedidoId . '.pdf';
$destinoAbs = $facturasDir . '/' . $nombreArchivo;
$rutaRel = 'uploads/facturas_pedidos/' . $nombreArchivo;

$pdfContent = $pdf->Output('S');
$posicionPdf = strpos($pdfContent, '%PDF');
if ($posicionPdf !== false && $posicionPdf > 0) {
    $pdfContent = substr($pdfContent, $posicionPdf);
}
@file_put_contents($destinoAbs, $pdfContent);

try {
    $stmtUpdate = $pdo->prepare("UPDATE ecommerce_pedidos SET tipo_factura = ?, numero_factura = ?, fecha_facturacion = ?, factura_archivo = ?, factura_nombre_original = ? WHERE id = ?");
    $stmtUpdate->execute([
        $tipoFactura,
        $numeroFactura,
        $fechaFacturacion,
        $rutaRel,
        $nombreArchivo,
        $pedidoId,
    ]);
} catch (Throwable $e) {
    // Si falla el guardado del metadata, igual devolver el PDF.
}

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $nombreArchivo . '"');
echo $pdfContent;
exit;
