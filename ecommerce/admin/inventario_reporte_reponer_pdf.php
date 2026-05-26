<?php
require_once dirname(__DIR__, 2) . '/config.php';
require_once dirname(__DIR__, 2) . '/fpdf.php';

function reponer_pdf_trim(string $value, int $width, string $suffix = '...'): string
{
    if (function_exists('mb_strimwidth')) {
        return mb_strimwidth($value, 0, $width, $suffix);
    }

    if (strlen($value) <= $width) {
        return $value;
    }

    return substr($value, 0, max(0, $width - strlen($suffix))) . $suffix;
}

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

// ── Helper: columnas disponibles en una tabla ──
function reponer_pdf_get_columns(PDO $pdo, string $table): array
{
    try {
        return $pdo->query("SHOW COLUMNS FROM `{$table}`")
                   ->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        return [];
    }
}

$materiales = [];
$materiales_error = '';
if (reponer_pdf_table_exists($pdo, 'ecommerce_materiales')) {
    try {
        // Consulta principal: igual que inventario_reporte_reponer.php
        $stmt_m = $pdo->query(" 
            SELECT
                'material' as tipo_item,
                m.id,
                m.nombre,
                m.stock,
                m.stock_minimo,
                m.unidad_medida,
                m.tipo_origen,
                m.proveedor_habitual_id,
                p.nombre as proveedor_nombre,
                CASE
                    WHEN m.stock_minimo IS NULL THEN ABS(m.stock)
                    ELSE (m.stock_minimo - m.stock)
                END as cantidad_reponer,
                CASE
                    WHEN m.stock < 0 THEN 'negativo'
                    WHEN m.stock = 0 THEN 'sin_stock'
                    WHEN m.stock_minimo IS NOT NULL AND m.stock <= m.stock_minimo THEN 'bajo_minimo'
                    ELSE 'sin_stock'
                END as prioridad
            FROM ecommerce_materiales m
            LEFT JOIN ecommerce_proveedores p ON m.proveedor_habitual_id = p.id
            WHERE m.stock <= 0 OR (m.stock_minimo IS NOT NULL AND m.stock <= m.stock_minimo)
            ORDER BY
                CASE
                    WHEN m.stock < 0 THEN 1
                    WHEN m.stock = 0 THEN 2
                    ELSE 3
                END,
                m.stock ASC
        ");
        $materiales = $stmt_m->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Fallback de compatibilidad para instalaciones con esquema parcial.
        try {
            $cols_m = reponer_pdf_get_columns($pdo, 'ecommerce_materiales');

            $has_stock_min  = in_array('stock_minimo',          $cols_m, true);
            $has_unidad     = in_array('unidad_medida',         $cols_m, true);
            $has_origen     = in_array('tipo_origen',           $cols_m, true);
            $has_proveedor  = in_array('proveedor_habitual_id', $cols_m, true);

            $sel_stock_min  = $has_stock_min ? "m.stock_minimo" : "0";
            $sel_unidad     = $has_unidad    ? "m.unidad_medida" : "''";
            $sel_origen     = $has_origen    ? "m.tipo_origen"   : "'compra'";
            $sel_cantidad   = $has_stock_min ? "(COALESCE(m.stock_minimo,0) - m.stock)" : "ABS(m.stock)";

            $where_m = $has_stock_min
                ? "WHERE m.stock <= 0 OR (m.stock_minimo IS NOT NULL AND m.stock <= m.stock_minimo)"
                : "WHERE m.stock <= 0";

            $stmt_m = $pdo->query(" 
                SELECT
                    'material' AS tipo_item,
                    m.id,
                    m.nombre,
                    m.stock,
                    {$sel_stock_min}  AS stock_minimo,
                    {$sel_unidad}     AS unidad_medida,
                    {$sel_origen}     AS tipo_origen,
                    {$sel_cantidad}   AS cantidad_reponer,
                    CASE
                        WHEN m.stock < 0 THEN 'negativo'
                        WHEN m.stock = 0 THEN 'sin_stock'
                        ELSE 'bajo_minimo'
                    END AS prioridad,
                    NULL AS proveedor_nombre
                FROM ecommerce_materiales m
                {$where_m}
                ORDER BY CASE WHEN m.stock < 0 THEN 1 WHEN m.stock = 0 THEN 2 ELSE 3 END, m.stock ASC
            ");
            $materiales_base = $stmt_m->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($has_proveedor && reponer_pdf_table_exists($pdo, 'ecommerce_proveedores') && !empty($materiales_base)) {
                $ids = implode(',', array_map('intval', array_column($materiales_base, 'id')));
                $rows = $pdo->query(" 
                    SELECT m.id, p.nombre AS proveedor_nombre
                    FROM ecommerce_materiales m
                    LEFT JOIN ecommerce_proveedores p ON p.id = m.proveedor_habitual_id
                    WHERE m.id IN ({$ids})
                ")->fetchAll(PDO::FETCH_ASSOC);
                $prov_map = array_column($rows ?: [], 'proveedor_nombre', 'id');
                foreach ($materiales_base as &$row) {
                    $row['proveedor_nombre'] = $prov_map[$row['id']] ?? '';
                }
                unset($row);
            }

            $materiales = $materiales_base;
        } catch (Throwable $e2) {
            $materiales_error = $e2->getMessage();
        }
    }
}

// Productos a reponer
$productos_reponer = [];
$productos_error = '';
if (reponer_pdf_table_exists($pdo, 'ecommerce_productos')) {
    try {
        // Consulta principal: igual que inventario_reporte_reponer.php
        $stmt_p = $pdo->query(" 
            SELECT
                'producto' as tipo_item,
                pr.id,
                pr.nombre,
                pr.stock,
                pr.stock_minimo,
                'unidad' as unidad_medida,
                pr.tipo_origen,
                pr.proveedor_habitual_id,
                p.nombre as proveedor_nombre,
                CASE
                    WHEN pr.stock_minimo IS NULL THEN ABS(pr.stock)
                    ELSE (pr.stock_minimo - pr.stock)
                END as cantidad_reponer,
                CASE
                    WHEN pr.stock < 0 THEN 'negativo'
                    WHEN pr.stock = 0 THEN 'sin_stock'
                    WHEN pr.stock_minimo IS NOT NULL AND pr.stock <= pr.stock_minimo THEN 'bajo_minimo'
                    ELSE 'sin_stock'
                END as prioridad
            FROM ecommerce_productos pr
            LEFT JOIN ecommerce_proveedores p ON pr.proveedor_habitual_id = p.id
            WHERE pr.stock <= 0 OR (pr.stock_minimo IS NOT NULL AND pr.stock <= pr.stock_minimo)
            ORDER BY
                CASE
                    WHEN pr.stock < 0 THEN 1
                    WHEN pr.stock = 0 THEN 2
                    ELSE 3
                END,
                pr.stock ASC
        ");
        $productos_reponer = $stmt_p->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        // Fallback de compatibilidad para instalaciones con esquema parcial.
        try {
            $cols_p = reponer_pdf_get_columns($pdo, 'ecommerce_productos');

            $has_stock_min_p = in_array('stock_minimo',          $cols_p, true);
            $has_origen_p    = in_array('tipo_origen',           $cols_p, true);
            $has_proveedor_p = in_array('proveedor_habitual_id', $cols_p, true);

            $sel_stock_min_p = $has_stock_min_p ? "pr.stock_minimo" : "0";
            $sel_origen_p    = $has_origen_p    ? "pr.tipo_origen"  : "'compra'";
            $sel_cantidad_p  = $has_stock_min_p ? "(COALESCE(pr.stock_minimo,0) - pr.stock)" : "ABS(pr.stock)";

            $where_p = $has_stock_min_p
                ? "WHERE pr.stock <= 0 OR (pr.stock_minimo IS NOT NULL AND pr.stock <= pr.stock_minimo)"
                : "WHERE pr.stock <= 0";

            $stmt_p = $pdo->query(" 
                SELECT
                    'producto' AS tipo_item,
                    pr.id,
                    pr.nombre,
                    pr.stock,
                    {$sel_stock_min_p} AS stock_minimo,
                    'unidad'           AS unidad_medida,
                    {$sel_origen_p}    AS tipo_origen,
                    {$sel_cantidad_p}  AS cantidad_reponer,
                    CASE
                        WHEN pr.stock < 0 THEN 'negativo'
                        WHEN pr.stock = 0 THEN 'sin_stock'
                        ELSE 'bajo_minimo'
                    END AS prioridad,
                    NULL AS proveedor_nombre
                FROM ecommerce_productos pr
                {$where_p}
                ORDER BY CASE WHEN pr.stock < 0 THEN 1 WHEN pr.stock = 0 THEN 2 ELSE 3 END, pr.stock ASC
            ");
            $productos_base = $stmt_p->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($has_proveedor_p && reponer_pdf_table_exists($pdo, 'ecommerce_proveedores') && !empty($productos_base)) {
                $ids = implode(',', array_map('intval', array_column($productos_base, 'id')));
                $rows = $pdo->query(" 
                    SELECT pr.id, p.nombre AS proveedor_nombre
                    FROM ecommerce_productos pr
                    LEFT JOIN ecommerce_proveedores p ON p.id = pr.proveedor_habitual_id
                    WHERE pr.id IN ({$ids})
                ")->fetchAll(PDO::FETCH_ASSOC);
                $prov_map = array_column($rows ?: [], 'proveedor_nombre', 'id');
                foreach ($productos_base as &$row) {
                    $row['proveedor_nombre'] = $prov_map[$row['id']] ?? '';
                }
                unset($row);
            }

            $productos_reponer = $productos_base;
        } catch (Throwable $e2) {
            $productos_error = $e2->getMessage();
        }
    }
}

$items_reponer = array_merge($materiales, $productos_reponer);

// Fallback final: replica criterio del modulo inventario para evitar PDF vacio en esquemas mixtos.
if (empty($items_reponer)) {
    $fallback_items = [];

    if (reponer_pdf_table_exists($pdo, 'ecommerce_materiales')) {
        $cols_m_fb = reponer_pdf_get_columns($pdo, 'ecommerce_materiales');
        $stock_m_fb = in_array('stock', $cols_m_fb, true) ? 'stock' : '0';
        $min_m_fb = in_array('stock_minimo', $cols_m_fb, true) ? 'stock_minimo' : '0';
        $unidad_m_fb = in_array('unidad_medida', $cols_m_fb, true) ? 'unidad_medida' : "'unidad'";
        $origen_m_fb = in_array('tipo_origen', $cols_m_fb, true) ? 'tipo_origen' : "'compra'";

        $sql_m_fb = "
            SELECT
                'material' AS tipo_item,
                id,
                nombre,
                {$stock_m_fb} AS stock,
                {$min_m_fb} AS stock_minimo,
                {$unidad_m_fb} AS unidad_medida,
                {$origen_m_fb} AS tipo_origen,
                NULL AS proveedor_nombre,
                CASE
                    WHEN {$stock_m_fb} < 0 THEN ABS({$stock_m_fb})
                    WHEN {$stock_m_fb} <= COALESCE({$min_m_fb}, 0) THEN (COALESCE({$min_m_fb}, 0) - {$stock_m_fb})
                    ELSE 0
                END AS cantidad_reponer,
                CASE
                    WHEN {$stock_m_fb} < 0 THEN 'negativo'
                    WHEN {$stock_m_fb} = 0 THEN 'sin_stock'
                    WHEN {$stock_m_fb} <= COALESCE({$min_m_fb}, 0) THEN 'bajo_minimo'
                    ELSE 'normal'
                END AS prioridad
            FROM ecommerce_materiales
            WHERE {$stock_m_fb} < 0
               OR {$stock_m_fb} = 0
               OR {$stock_m_fb} <= COALESCE({$min_m_fb}, 0)
        ";

        foreach (reponer_pdf_fetch_all($pdo, $sql_m_fb) as $row) {
            if (($row['prioridad'] ?? 'normal') !== 'normal') {
                $fallback_items[] = $row;
            }
        }
    }

    if (reponer_pdf_table_exists($pdo, 'ecommerce_productos')) {
        $cols_p_fb = reponer_pdf_get_columns($pdo, 'ecommerce_productos');
        $stock_p_fb = in_array('stock', $cols_p_fb, true) ? 'stock' : '0';
        $min_p_fb = in_array('stock_minimo', $cols_p_fb, true) ? 'stock_minimo' : '0';
        $origen_p_fb = in_array('tipo_origen', $cols_p_fb, true) ? 'tipo_origen' : "'fabricacion_propia'";

        $sql_p_fb = "
            SELECT
                'producto' AS tipo_item,
                id,
                nombre,
                {$stock_p_fb} AS stock,
                {$min_p_fb} AS stock_minimo,
                'unidad' AS unidad_medida,
                {$origen_p_fb} AS tipo_origen,
                NULL AS proveedor_nombre,
                CASE
                    WHEN {$stock_p_fb} < 0 THEN ABS({$stock_p_fb})
                    WHEN {$stock_p_fb} <= COALESCE({$min_p_fb}, 0) THEN (COALESCE({$min_p_fb}, 0) - {$stock_p_fb})
                    ELSE 0
                END AS cantidad_reponer,
                CASE
                    WHEN {$stock_p_fb} < 0 THEN 'negativo'
                    WHEN {$stock_p_fb} = 0 THEN 'sin_stock'
                    WHEN {$stock_p_fb} <= COALESCE({$min_p_fb}, 0) THEN 'bajo_minimo'
                    ELSE 'normal'
                END AS prioridad
            FROM ecommerce_productos
            WHERE {$stock_p_fb} < 0
               OR {$stock_p_fb} = 0
               OR {$stock_p_fb} <= COALESCE({$min_p_fb}, 0)
        ";

        foreach (reponer_pdf_fetch_all($pdo, $sql_p_fb) as $row) {
            if (($row['prioridad'] ?? 'normal') !== 'normal') {
                $fallback_items[] = $row;
            }
        }
    }

    if (!empty($fallback_items)) {
        usort($fallback_items, static function (array $a, array $b): int {
            $order = ['negativo' => 1, 'sin_stock' => 2, 'bajo_minimo' => 3, 'normal' => 4];
            $pa = $order[$a['prioridad'] ?? 'normal'] ?? 9;
            $pb = $order[$b['prioridad'] ?? 'normal'] ?? 9;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }
            return ((float)($a['stock'] ?? 0)) <=> ((float)($b['stock'] ?? 0));
        });

        $items_reponer = $fallback_items;
        $materiales = array_values(array_filter($fallback_items, static fn(array $i): bool => ($i['tipo_item'] ?? '') === 'material'));
        $productos_reponer = array_values(array_filter($fallback_items, static fn(array $i): bool => ($i['tipo_item'] ?? '') === 'producto'));
    }
}

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

if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'total_items' => $total_items,
        'items_negativos' => $items_negativos,
        'items_sin_stock' => $items_sin_stock,
        'materiales_count' => count($materiales),
        'productos_count' => count($productos_reponer),
        'materiales_error' => $materiales_error,
        'productos_error' => $productos_error,
        'sample_items' => array_slice($items_reponer, 0, 10),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

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
        $this->Cell($cols[2], $rowH, utf8_decode(reponer_pdf_trim((string)$item['nombre'], 45, '...')), 'LRB', 0, 'L', true);

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
        $this->Cell($cols[7], $rowH, utf8_decode(reponer_pdf_trim((string)$prov, 28, '...')), 'LRB', 1, 'L', true);

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
            $this->Cell($wTotal * 0.5, 6, utf8_decode(reponer_pdf_trim((string)$opc['material_nombre'], 50, '...')), 1, 0, 'L', true);
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
        $hayError = ($errMat !== '' || $errProd !== '');
        $this->SetFont('Arial', 'B', 12);
        if ($hayError) {
            $this->SetFillColor(248, 215, 218); // rojo claro si hay error
        } else {
            $this->SetFillColor(212, 237, 218); // verde si realmente no hay items
        }
        $msg = $hayError
            ? 'Error al obtener datos. Revise los errores abajo.'
            : 'Todo el inventario esta en niveles optimos. No hay items para reponer.';
        $this->Cell(0, 12, utf8_decode($msg), 0, 1, 'C', true);
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
