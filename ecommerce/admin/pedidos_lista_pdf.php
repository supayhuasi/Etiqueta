<?php
ob_start();
require dirname(__DIR__, 2) . '/config.php';
require dirname(__DIR__, 2) . '/fpdf.php';

// Filtros (mismos que pedidos.php)
$estado_filter    = trim($_GET['estado']   ?? '');
$fecha_desde      = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta      = trim($_GET['fecha_hasta'] ?? '');
$cliente_busqueda = trim($_GET['cliente']  ?? '');

$estados_validos = ['pendiente', 'confirmado', 'preparando', 'enviado', 'entregado', 'cancelado', 'pagado'];
if (!in_array($estado_filter, $estados_validos, true)) $estado_filter = '';

// Empresa
$empresa = [];
try {
    $s = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
    $empresa = $s ? ($s->fetch(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {}

// Query pedidos (sin paginación, todos)
$query = "
    SELECT
        p.id,
        p.numero_pedido,
        p.fecha_pedido,
        p.total,
        p.estado,
        c.nombre  AS cliente_nombre,
        c.email   AS cliente_email,
        COALESCE(pagos.total_pagado, 0) AS total_pagado
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    LEFT JOIN (
        SELECT pedido_id, SUM(monto) AS total_pagado
        FROM ecommerce_pedido_pagos
        GROUP BY pedido_id
    ) pagos ON pagos.pedido_id = p.id
    WHERE p.estado != 'cancelado'
";
$params = [];

if ($estado_filter !== '') {
    $query .= " AND p.estado = ?";
    $params[] = $estado_filter;
}
if ($fecha_desde !== '') {
    $query .= " AND DATE(p.fecha_pedido) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $query .= " AND DATE(p.fecha_pedido) <= ?";
    $params[] = $fecha_hasta;
}
if ($cliente_busqueda !== '') {
    $query .= " AND (c.nombre LIKE ? OR c.email LIKE ? OR p.numero_pedido LIKE ?)";
    $w = '%' . $cliente_busqueda . '%';
    $params[] = $w;
    $params[] = $w;
    $params[] = $w;
}

$query .= " ORDER BY p.fecha_pedido DESC";

$pedidos = [];
try {
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    ob_end_clean();
    die('Error al obtener pedidos: ' . htmlspecialchars($e->getMessage()));
}

// Totales por estado para el resumen
$total_general = 0.0;
$total_pagado  = 0.0;
$por_estado    = [];
foreach ($pedidos as $p) {
    $est = $p['estado'];
    $por_estado[$est] = ($por_estado[$est] ?? 0) + 1;
    $total_general += (float)$p['total'];
    $total_pagado  += (float)$p['total_pagado'];
}

// Colores de estado para el PDF
$colores_estado = [
    'pendiente'  => [255, 193, 7],
    'confirmado' => [13, 202, 240],
    'preparando' => [13, 110, 253],
    'enviado'    => [108, 117, 125],
    'entregado'  => [25, 135, 84],
    'cancelado'  => [220, 53, 69],
    'pagado'     => [25, 135, 84],
];

// ── PDF ──
class PDFPedidos extends FPDF
{
    private array $empresa;

    public function __construct(array $empresa)
    {
        parent::__construct('L', 'mm', 'A4');
        $this->empresa = $empresa;
        $this->SetAutoPageBreak(true, 16);
    }

    public function Header(): void
    {
        // Logo
        if (!empty($this->empresa['logo'])) {
            foreach (['../../ecommerce/uploads/', '../../uploads/'] as $base) {
                $path = $base . $this->empresa['logo'];
                if (file_exists($path)) {
                    $this->Image($path, 8, 4, 26);
                    break;
                }
            }
        }

        $this->SetFont('Arial', 'B', 14);
        $this->SetY(6);
        $this->Cell(0, 7, utf8_decode($this->empresa['nombre'] ?? 'EMPRESA'), 0, 1, 'C');

        $this->SetFont('Arial', '', 7);
        $datos = array_filter([
            $this->empresa['direccion'] ?? '',
            !empty($this->empresa['telefono']) ? 'Tel: ' . $this->empresa['telefono'] : '',
            $this->empresa['email'] ?? '',
        ]);
        if ($datos) {
            $this->Cell(0, 4, utf8_decode(implode('  |  ', $datos)), 0, 1, 'C');
        }

        $this->SetFont('Arial', 'B', 11);
        $this->SetFillColor(44, 62, 80);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 7, utf8_decode('LISTADO DE PEDIDOS  —  ' . date('d/m/Y H:i')), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(130, 130, 130);
        $this->Cell(0, 5, utf8_decode('Página ' . $this->PageNo() . ' / {nb}  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    public function filtrosRow(array $filtros): void
    {
        if (empty(array_filter($filtros))) return;
        $parts = [];
        if (!empty($filtros['estado']))       $parts[] = 'Estado: ' . ucfirst($filtros['estado']);
        if (!empty($filtros['fecha_desde']))  $parts[] = 'Desde: ' . date('d/m/Y', strtotime($filtros['fecha_desde']));
        if (!empty($filtros['fecha_hasta']))  $parts[] = 'Hasta: ' . date('d/m/Y', strtotime($filtros['fecha_hasta']));
        if (!empty($filtros['cliente']))      $parts[] = 'Cliente: ' . $filtros['cliente'];
        $this->SetFont('Arial', 'I', 8);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(0, 6, utf8_decode('Filtros: ' . implode('  |  ', $parts)), 0, 1, 'L', true);
        $this->Ln(2);
    }

    public function statsRow(int $total, float $importe, float $pagado, array $porEstado): void
    {
        $w = ($this->GetPageWidth() - 16) / 4;
        $this->SetFont('Arial', 'B', 8);

        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($w, 8, utf8_decode('Total pedidos: ' . $total), 0, 0, 'C', true);

        $this->SetFillColor(25, 135, 84);
        $this->Cell($w, 8, utf8_decode('Importe total: $' . number_format($importe, 2, ',', '.')), 0, 0, 'C', true);

        $this->SetFillColor(13, 110, 253);
        $this->Cell($w, 8, utf8_decode('Total pagado: $' . number_format($pagado, 2, ',', '.')), 0, 0, 'C', true);

        $saldo = $importe - $pagado;
        $this->SetFillColor($saldo > 0 ? 220 : 25, $saldo > 0 ? 53 : 135, $saldo > 0 ? 69 : 84);
        $this->Cell($w, 8, utf8_decode('Saldo pendiente: $' . number_format($saldo, 2, ',', '.')), 0, 1, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    public function tableHeader(): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.1);
        $cols = $this->colWidths();
        $headers = ['#', 'Número', 'Fecha', 'Cliente', 'Email', 'Estado', 'Total', 'Pagado', 'Saldo'];
        foreach ($headers as $i => $h) {
            $this->Cell($cols[$i], 7, $h, 1, $i === count($headers) - 1 ? 1 : 0, 'C', true);
        }
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(180, 180, 180);
    }

    private function colWidths(): array
    {
        $w = $this->GetPageWidth() - 16;
        // #, numero, fecha, cliente, email, estado, total, pagado, saldo
        return [10, 28, 26, 52, 52, 24, 30, 30, $w - 10 - 28 - 26 - 52 - 52 - 24 - 30 - 30];
    }

    public function tableRow(array $pedido, int $n, array $coloresEstado): void
    {
        $cols = $this->colWidths();
        $h    = 6;

        $estado = $pedido['estado'];
        $total  = (float)$pedido['total'];
        $pagado = (float)$pedido['total_pagado'];
        $saldo  = $total - $pagado;

        if ($n % 2 === 0) {
            $this->SetFillColor(245, 245, 245);
        } else {
            $this->SetFillColor(255, 255, 255);
        }

        $this->SetFont('Arial', '', 8);

        // #
        $this->Cell($cols[0], $h, $n, 'LRB', 0, 'C', true);
        // Número
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($cols[1], $h, utf8_decode(mb_strimwidth((string)$pedido['numero_pedido'], 0, 20, '..')), 'LRB', 0, 'L', true);
        $this->SetFont('Arial', '', 8);
        // Fecha
        $fecha = !empty($pedido['fecha_pedido']) ? date('d/m/Y H:i', strtotime($pedido['fecha_pedido'])) : '-';
        $this->Cell($cols[2], $h, $fecha, 'LRB', 0, 'C', true);
        // Cliente
        $this->Cell($cols[3], $h, utf8_decode(mb_strimwidth((string)($pedido['cliente_nombre'] ?? 'Sin cliente'), 0, 35, '..')), 'LRB', 0, 'L', true);
        // Email
        $this->SetFont('Arial', '', 7);
        $this->Cell($cols[4], $h, utf8_decode(mb_strimwidth((string)($pedido['cliente_email'] ?? ''), 0, 38, '..')), 'LRB', 0, 'L', true);
        $this->SetFont('Arial', '', 8);
        // Estado (con color de fondo)
        $rgb = $coloresEstado[$estado] ?? [108, 117, 125];
        $this->SetFillColor(...$rgb);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 7);
        $this->Cell($cols[5], $h, strtoupper($estado), 'LRB', 0, 'C', true);
        // Volver a fondo alternado
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
        if ($n % 2 === 0) {
            $this->SetFillColor(245, 245, 245);
        } else {
            $this->SetFillColor(255, 255, 255);
        }
        // Total
        $this->Cell($cols[6], $h, '$' . number_format($total, 2, ',', '.'), 'LRB', 0, 'R', true);
        // Pagado
        $this->SetTextColor(25, 135, 84);
        $this->Cell($cols[7], $h, '$' . number_format($pagado, 2, ',', '.'), 'LRB', 0, 'R', true);
        $this->SetTextColor(0, 0, 0);
        // Saldo
        if ($saldo > 0.01) $this->SetTextColor(180, 0, 0);
        $this->Cell($cols[8], $h, '$' . number_format($saldo, 2, ',', '.'), 'LRB', 1, 'R', true);
        $this->SetTextColor(0, 0, 0);

        $this->SetFillColor(255, 255, 255);
    }

    public function resumenEstados(array $porEstado, array $coloresEstado): void
    {
        if (empty($porEstado)) return;
        $this->Ln(4);
        $this->SetFont('Arial', 'B', 9);
        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 7, utf8_decode('RESUMEN POR ESTADO'), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);

        $w = ($this->GetPageWidth() - 16) / min(count($porEstado), 5);
        $this->SetFont('Arial', 'B', 8);
        $count = 0;
        foreach ($porEstado as $estado => $cant) {
            $rgb = $coloresEstado[$estado] ?? [108, 117, 125];
            $this->SetFillColor(...$rgb);
            $this->SetTextColor(255, 255, 255);
            $nl = (++$count % 5 === 0 || $count === count($porEstado)) ? 1 : 0;
            $this->Cell($w, 8, utf8_decode(strtoupper($estado) . ': ' . $cant), 0, $nl, 'C', true);
        }
        $this->SetTextColor(0, 0, 0);
    }
}

ob_end_clean();

$pdf = new PDFPedidos($empresa);
$pdf->AliasNbPages();
$pdf->AddPage();

// Filtros aplicados
$pdf->filtrosRow([
    'estado'      => $estado_filter,
    'fecha_desde' => $fecha_desde,
    'fecha_hasta' => $fecha_hasta,
    'cliente'     => $cliente_busqueda,
]);

// Estadísticas
$pdf->statsRow(count($pedidos), $total_general, $total_pagado, $por_estado);

if (empty($pedidos)) {
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(212, 237, 218);
    $pdf->Cell(0, 12, utf8_decode('No se encontraron pedidos con los filtros aplicados.'), 0, 1, 'C', true);
} else {
    $pdf->tableHeader();
    foreach ($pedidos as $i => $pedido) {
        $pdf->tableRow($pedido, $i + 1, $colores_estado);
    }
    $pdf->resumenEstados($por_estado, $colores_estado);
}

$filename = 'pedidos_' . date('Ymd_Hi') . '.pdf';
$pdf->Output('D', $filename);
exit;
