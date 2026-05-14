<?php
require '../../config.php';
require '../../fpdf.php';

// Helpers
function reponer_pdf_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function reponer_pdf_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function reponer_pdf_fetch_all(PDO $pdo, string $sql): array
{
    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

$ver_colores = !empty($_GET['ver_colores']);

$materiales = [];
$materiales_error = '';
if (reponer_pdf_table_exists($pdo, 'ecommerce_materiales')) {
    try {
        $has_min_m = reponer_pdf_column_exists($pdo, 'ecommerce_materiales', 'stock_minimo');
        $where_m = $has_min_m
            ? "WHERE m.stock <= 0 OR (m.stock_minimo IS NOT NULL AND m.stock <= m.stock_minimo)"
            : "WHERE m.stock <= 0";
        $cantidad_m = $has_min_m ? "(COALESCE(m.stock_minimo, 0) - m.stock)" : "ABS(m.stock)";
        $minimo_m   = $has_min_m ? "m.stock_minimo" : "0";
        $stmt_m = $pdo->query("
            SELECT
                'material' as tipo_item,
                m.id, m.nombre, m.stock, {$minimo_m} as stock_minimo, m.unidad_medida, m.tipo_origen,
                {$cantidad_m} as cantidad_reponer,
                CASE
                    WHEN m.stock < 0 THEN 'negativo'
                    WHEN m.stock = 0 THEN 'sin_stock'
                    ELSE 'bajo_minimo'
                END as prioridad,
                NULL as proveedor_nombre
            FROM ecommerce_materiales m
            {$where_m}
            ORDER BY CASE WHEN m.stock < 0 THEN 1 WHEN m.stock = 0 THEN 2 ELSE 3 END, m.stock ASC
        ");
        $materiales_base = $stmt_m->fetchAll(PDO::FETCH_ASSOC);
        // Intentar agregar proveedor si la columna existe
        if (reponer_pdf_column_exists($pdo, 'ecommerce_materiales', 'proveedor_habitual_id')
            && reponer_pdf_table_exists($pdo, 'ecommerce_proveedores')) {
            $ids = array_column($materiales_base, 'id');
            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $prov_map = [];
                try {
                    $rows = $pdo->query("SELECT m.id, p.nombre as proveedor_nombre FROM ecommerce_materiales m LEFT JOIN ecommerce_proveedores p ON p.id = m.proveedor_habitual_id WHERE m.id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) $prov_map[(int)$r['id']] = $r['proveedor_nombre'];
                } catch (Throwable $e2) {}
                foreach ($materiales_base as &$row) {
                    $row['proveedor_nombre'] = $prov_map[(int)$row['id']] ?? '';
                }
                unset($row);
            }
        }
        $materiales = $materiales_base;
    } catch (Throwable $e) {
        $materiales_error = $e->getMessage();
    }
}

// Productos a reponer
$productos_reponer = [];
$productos_error = '';
if (reponer_pdf_table_exists($pdo, 'ecommerce_productos')) {
    try {
        $has_min_p = reponer_pdf_column_exists($pdo, 'ecommerce_productos', 'stock_minimo');
        $where_p = $has_min_p
            ? "WHERE pr.stock <= 0 OR (pr.stock_minimo IS NOT NULL AND pr.stock <= pr.stock_minimo)"
            : "WHERE pr.stock <= 0";
        $cantidad_p = $has_min_p ? "(COALESCE(pr.stock_minimo, 0) - pr.stock)" : "ABS(pr.stock)";
        $minimo_p   = $has_min_p ? "pr.stock_minimo" : "0";
        $stmt_p = $pdo->query("
            SELECT
                'producto' as tipo_item,
                pr.id, pr.nombre, pr.stock, {$minimo_p} as stock_minimo, 'unidad' as unidad_medida, pr.tipo_origen,
                {$cantidad_p} as cantidad_reponer,
                CASE
                    WHEN pr.stock < 0 THEN 'negativo'
                    WHEN pr.stock = 0 THEN 'sin_stock'
                    ELSE 'bajo_minimo'
                END as prioridad,
                NULL as proveedor_nombre
            FROM ecommerce_productos pr
            {$where_p}
            ORDER BY CASE WHEN pr.stock < 0 THEN 1 WHEN pr.stock = 0 THEN 2 ELSE 3 END, pr.stock ASC
        ");
        $productos_base = $stmt_p->fetchAll(PDO::FETCH_ASSOC);
        // Intentar agregar proveedor si la columna existe
        if (reponer_pdf_column_exists($pdo, 'ecommerce_productos', 'proveedor_habitual_id')
            && reponer_pdf_table_exists($pdo, 'ecommerce_proveedores')) {
            $ids = array_column($productos_base, 'id');
            if (!empty($ids)) {
                $in = implode(',', array_map('intval', $ids));
                $prov_map = [];
                try {
                    $rows = $pdo->query("SELECT pr.id, p.nombre as proveedor_nombre FROM ecommerce_productos pr LEFT JOIN ecommerce_proveedores p ON p.id = pr.proveedor_habitual_id WHERE pr.id IN ($in)")->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($rows as $r) $prov_map[(int)$r['id']] = $r['proveedor_nombre'];
                } catch (Throwable $e2) {}
                foreach ($productos_base as &$row) {
                    $row['proveedor_nombre'] = $prov_map[(int)$row['id']] ?? '';
                }
                unset($row);
            }
        }
        $productos_reponer = $productos_base;
    } catch (Throwable $e) {
        $productos_error = $e->getMessage();
    }
}

$items_reponer = array_merge($materiales, $productos_reponer);

// Colores sin stock
$opciones_color_reponer = [];
$tiene_opciones = reponer_pdf_table_exists($pdo, 'ecommerce_atributo_opciones');
if ($ver_colores && $tiene_opciones
    && reponer_pdf_column_exists($pdo, 'ecommerce_atributo_opciones', 'stock')
    && reponer_pdf_table_exists($pdo, 'ecommerce_producto_atributos')
    && reponer_pdf_table_exists($pdo, 'ecommerce_productos')) {
    $opciones_color_reponer = reponer_pdf_fetch_all($pdo, "
        SELECT p.nombre AS material_nombre, o.nombre AS opcion_nombre, o.stock
        FROM ecommerce_atributo_opciones o
        JOIN ecommerce_producto_atributos a ON a.id = o.atributo_id
        JOIN ecommerce_productos p ON p.id = a.producto_id
        WHERE a.tipo = 'select' AND LOWER(a.nombre) LIKE '%color%' AND o.stock <= 0
        ORDER BY p.nombre, o.nombre
    ");
}

// Estadísticas
$total_items     = count($items_reponer);
$items_negativos = count(array_filter($items_reponer, fn($i) => $i['prioridad'] === 'negativo'));
$items_sin_stock = count(array_filter($items_reponer, fn($i) => $i['prioridad'] === 'sin_stock'));

// Empresa
$empresa = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1");
    $empresa = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
} catch (Throwable $e) {}

// ────────────── PDF ──────────────

class PDFReponer extends FPDF
{
    private array $empresa;

    public function __construct(array $empresa)
    {
        parent::__construct('L', 'mm', 'A4'); // horizontal para la tabla ancha
        $this->empresa = $empresa;
        $this->SetAutoPageBreak(true, 18);
    }

    public function Header(): void
    {
        $logoPrinted = false;
        if (!empty($this->empresa['logo'])) {
            foreach (['../../ecommerce/uploads/', '../../uploads/'] as $base) {
                $path = $base . $this->empresa['logo'];
                if (file_exists($path)) {
                    $this->Image($path, 8, 5, 28);
                    $logoPrinted = true;
                    break;
                }
            }
        }

        $this->SetFont('Arial', 'B', 15);
        $this->SetY(6);
        $this->Cell(0, 8, utf8_decode($this->empresa['nombre'] ?? 'EMPRESA'), 0, 1, 'C');

        if (!empty($this->empresa['direccion']) || !empty($this->empresa['telefono'])) {
            $this->SetFont('Arial', '', 8);
            $datos = array_filter([
                $this->empresa['direccion'] ?? '',
                !empty($this->empresa['telefono']) ? 'Tel: ' . $this->empresa['telefono'] : '',
                !empty($this->empresa['email']) ? $this->empresa['email'] : '',
            ]);
            $this->Cell(0, 4, utf8_decode(implode('  |  ', $datos)), 0, 1, 'C');
        }

        // Título del reporte
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(44, 62, 80);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 8, utf8_decode('REPORTE DE REPOSICION DE STOCK  -  ' . date('d/m/Y H:i')), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(3);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, utf8_decode('Pagina ' . $this->PageNo() . ' / {nb}  |  Generado el ' . date('d/m/Y H:i:s')), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    public function statsRow(int $total, int $negativos, int $sin_stock): void
    {
        $this->SetFont('Arial', 'B', 9);
        $w = $this->GetPageWidth() - 16;
        $cw = $w / 3;

        $this->SetFillColor(231, 76, 60);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($cw, 8, utf8_decode('STOCK NEGATIVO (URGENTE): ' . $negativos), 0, 0, 'C', true);

        $this->SetFillColor(230, 126, 34);
        $this->Cell($cw, 8, utf8_decode('SIN STOCK: ' . $sin_stock), 0, 0, 'C', true);

        $this->SetFillColor(52, 73, 94);
        $this->Cell($cw, 8, utf8_decode('TOTAL A REPONER: ' . $total), 0, 1, 'C', true);

        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(4);
    }

    public function tableHeader(): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(255, 255, 255);
        $this->SetLineWidth(0.1);

        $cols = $this->colWidths();
        $this->Cell($cols[0], 7, 'Prioridad',         1, 0, 'C', true);
        $this->Cell($cols[1], 7, 'Tipo',              1, 0, 'C', true);
        $this->Cell($cols[2], 7, 'Nombre',            1, 0, 'C', true);
        $this->Cell($cols[3], 7, 'Stock actual',      1, 0, 'C', true);
        $this->Cell($cols[4], 7, 'Stock minimo',      1, 0, 'C', true);
        $this->Cell($cols[5], 7, 'A reponer',         1, 0, 'C', true);
        $this->Cell($cols[6], 7, 'Origen',            1, 0, 'C', true);
        $this->Cell($cols[7], 7, 'Proveedor',         1, 1, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(180, 180, 180);
    }

    private function colWidths(): array
    {
        // Total available = page width - margins
        $w = $this->GetPageWidth() - 16;
        return [24, 20, 68, 28, 28, 28, 28, $w - 24 - 20 - 68 - 28 - 28 - 28 - 28];
    }

    public function tableRow(array $item, int $rowNum): void
    {
        $cols = $this->colWidths();
        $rowH = 6;

        // Fondo alternado y colores de prioridad
        if ($item['prioridad'] === 'negativo') {
            $this->SetFillColor(255, 235, 232);
        } elseif ($item['prioridad'] === 'sin_stock') {
            $this->SetFillColor(255, 248, 220);
        } else {
            $this->SetFillColor($rowNum % 2 === 0 ? 245 : 255, $rowNum % 2 === 0 ? 245 : 255, $rowNum % 2 === 0 ? 245 : 255);
        }

        $this->SetFont('Arial', 'B', 8);

        // Prioridad
        $priorLabel = match($item['prioridad']) {
            'negativo'    => 'URGENTE',
            'sin_stock'   => 'ALTA',
            default       => 'MEDIA',
        };
        $this->Cell($cols[0], $rowH, $priorLabel, 'LRB', 0, 'C', true);

        // Tipo
        $tipoLabel = $item['tipo_item'] === 'material' ? 'Material' : 'Producto';
        $this->SetFont('Arial', '', 8);
        $this->Cell($cols[1], $rowH, $tipoLabel, 'LRB', 0, 'C', true);

        // Nombre (negrita)
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($cols[2], $rowH, utf8_decode(mb_strimwidth($item['nombre'], 0, 45, '...')), 'LRB', 0, 'L', true);

        $this->SetFont('Arial', '', 8);
        $um = ' ' . htmlspecialchars_decode($item['unidad_medida'] ?? '');

        // Stock actual
        $stockColor = (float)$item['stock'] < 0;
        if ($stockColor) $this->SetTextColor(180, 0, 0);
        $this->Cell($cols[3], $rowH, number_format((float)$item['stock'], 2, ',', '.') . $um, 'LRB', 0, 'R', true);
        $this->SetTextColor(0, 0, 0);

        // Stock mínimo
        $this->Cell($cols[4], $rowH, number_format((float)$item['stock_minimo'], 2, ',', '.') . $um, 'LRB', 0, 'R', true);

        // A reponer
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(0, 80, 160);
        $cant = max(0, (float)$item['cantidad_reponer']);
        $this->Cell($cols[5], $rowH, number_format($cant, 2, ',', '.') . $um, 'LRB', 0, 'R', true);
        $this->SetTextColor(0, 0, 0);

        // Origen
        $this->SetFont('Arial', '', 8);
        $origenLabel = $item['tipo_origen'] === 'fabricacion_propia' ? 'Fab. Propia' : 'Compra';
        $this->Cell($cols[6], $rowH, $origenLabel, 'LRB', 0, 'C', true);

        // Proveedor
        $prov = $item['proveedor_nombre'] ?? '';
        $this->Cell($cols[7], $rowH, utf8_decode(mb_strimwidth((string)$prov, 0, 28, '...')), 'LRB', 1, 'L', true);

        $this->SetFillColor(255, 255, 255);
    }

    public function coloresSection(array $opciones): void
    {
        $this->Ln(5);
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(52, 73, 94);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 7, utf8_decode('COLORES SIN STOCK'), 0, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);

        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(80, 100, 120);
        $this->SetTextColor(255, 255, 255);
        $wTotal = $this->GetPageWidth() - 16;
        $this->Cell($wTotal * 0.5, 6, 'Producto', 1, 0, 'C', true);
        $this->Cell($wTotal * 0.35, 6, 'Color', 1, 0, 'C', true);
        $this->Cell($wTotal * 0.15, 6, 'Stock', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);

        $rowNum = 0;
        foreach ($opciones as $opc) {
            $this->SetFillColor($rowNum % 2 === 0 ? 250 : 255, $rowNum % 2 === 0 ? 220 : 248, $rowNum % 2 === 0 ? 220 : 220);
            $this->SetFont('Arial', 'B', 8);
            $this->Cell($wTotal * 0.5, 6, utf8_decode(mb_strimwidth($opc['material_nombre'], 0, 50, '...')), 1, 0, 'L', true);
            $this->SetFont('Arial', '', 8);
            $this->Cell($wTotal * 0.35, 6, utf8_decode($opc['opcion_nombre']), 1, 0, 'L', true);
            $this->SetTextColor(180, 0, 0);
            $this->Cell($wTotal * 0.15, 6, number_format((float)$opc['stock'], 2, ',', '.'), 1, 1, 'R', true);
            $this->SetTextColor(0, 0, 0);
            $rowNum++;
        }
    }

    public function emptyMessage(string $errMat = '', string $errProd = ''): void
    {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(212, 237, 218);
        $this->Cell(0, 12, utf8_decode('Todo el inventario esta en niveles optimos. No hay items para reponer.'), 0, 1, 'C', true);
        if ($errMat !== '') {
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(180, 0, 0);
            $this->MultiCell(0, 5, utf8_decode('Error materiales: ' . $errMat), 0, 'L');
            $this->SetTextColor(0, 0, 0);
        }
        if ($errProd !== '') {
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(180, 0, 0);
            $this->MultiCell(0, 5, utf8_decode('Error productos: ' . $errProd), 0, 'L');
            $this->SetTextColor(0, 0, 0);
        }
    }
}

$pdf = new PDFReponer($empresa);
$pdf->AliasNbPages();
$pdf->AddPage();

// Estadísticas
$pdf->statsRow($total_items, $items_negativos, $items_sin_stock);

if (empty($items_reponer)) {
    $pdf->emptyMessage($materiales_error, $productos_error);
} else {
    $pdf->tableHeader();
    $rowNum = 0;
    foreach ($items_reponer as $item) {
        $pdf->tableRow($item, $rowNum);
        $rowNum++;
    }

    if ($ver_colores && !empty($opciones_color_reponer)) {
        $pdf->coloresSection($opciones_color_reponer);
    }
}

$pdf->Output('I', 'reporte_reposicion_' . date('Ymd_Hi') . '.pdf');
exit;
