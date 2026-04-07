<?php
require '../../config.php';
require '../../fpdf.php';

function remito_asegurar_tablas(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_remitos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pedido_id INT NOT NULL,
        numero_remito VARCHAR(50) NOT NULL,
        tipo ENUM('completo','parcial') NOT NULL DEFAULT 'completo',
        observaciones TEXT NULL,
        creado_por INT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_numero_remito (numero_remito),
        KEY idx_pedido (pedido_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_remito_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        remito_id INT NOT NULL,
        pedido_item_id INT NOT NULL,
        cantidad DECIMAL(10,2) NOT NULL DEFAULT 0,
        KEY idx_remito_id (remito_id),
        KEY idx_pedido_item_id (pedido_item_id),
        UNIQUE KEY uniq_remito_item (remito_id, pedido_item_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function remito_cargar_items(PDO $pdo, int $pedidoId): array {
    $stmt = $pdo->prepare("
        SELECT pi.*, pr.nombre AS producto_nombre,
               COALESCE(rem.cantidad_remitida, 0) AS cantidad_remitida
        FROM ecommerce_pedido_items pi
        LEFT JOIN ecommerce_productos pr ON pi.producto_id = pr.id
        LEFT JOIN (
            SELECT pedido_item_id, SUM(cantidad) AS cantidad_remitida
            FROM ecommerce_remito_items
            GROUP BY pedido_item_id
        ) rem ON rem.pedido_item_id = pi.id
        WHERE pi.pedido_id = ?
        ORDER BY pi.id ASC
    ");
    $stmt->execute([$pedidoId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($items as &$item) {
        $cantidadTotal = (float)($item['cantidad'] ?? 0);
        $cantidadRemitida = min($cantidadTotal, (float)($item['cantidad_remitida'] ?? 0));
        $item['cantidad_remitida'] = $cantidadRemitida;
        $item['cantidad_pendiente'] = max(0, $cantidadTotal - $cantidadRemitida);
    }
    unset($item);

    return $items;
}

function remito_siguiente_numero(PDO $pdo): string {
    $anio = date('Y');
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING_INDEX(numero_remito, '-', -1) AS UNSIGNED)) FROM ecommerce_remitos WHERE numero_remito LIKE ?");
    $stmt->execute(['RTO-' . $anio . '-%']);
    $ultimo = (int)$stmt->fetchColumn();
    return 'RTO-' . $anio . '-' . str_pad((string)($ultimo + 1), 5, '0', STR_PAD_LEFT);
}

remito_asegurar_tablas($pdo);

$pedido_id = (int)($_REQUEST['id'] ?? 0);
$remito_id = (int)($_GET['remito_id'] ?? 0);

if ($pedido_id <= 0) {
    die('Pedido inválido');
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre, c.email, c.telefono, c.direccion, c.ciudad, c.provincia, c.codigo_postal
    FROM ecommerce_pedidos p
    LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
    WHERE p.id = ?
");
$stmt->execute([$pedido_id]);
$pedido = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    die('Pedido no encontrado');
}

$items = remito_cargar_items($pdo, $pedido_id);
if (empty($items)) {
    die('El pedido no tiene ítems para remitir');
}

$itemsIndexados = [];
$yaHabiaRemitos = false;
foreach ($items as $item) {
    $itemsIndexados[(int)$item['id']] = $item;
    if ((float)($item['cantidad_remitida'] ?? 0) > 0) {
        $yaHabiaRemitos = true;
    }
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($requestMethod === 'POST') {
    $cantidades = $_POST['cantidades'] ?? [];
    $observaciones = trim((string)($_POST['observaciones'] ?? ''));
    $seleccionados = [];
    $tipoRemito = $yaHabiaRemitos ? 'parcial' : 'completo';

    foreach ($itemsIndexados as $itemId => $item) {
        $pendiente = (float)($item['cantidad_pendiente'] ?? 0);
        $cantidadSolicitada = isset($cantidades[$itemId]) ? (float)str_replace(',', '.', (string)$cantidades[$itemId]) : 0;
        if ($cantidadSolicitada < 0) {
            $cantidadSolicitada = 0;
        }
        if ($cantidadSolicitada > $pendiente) {
            $cantidadSolicitada = $pendiente;
        }

        if ($pendiente > 0 && ($cantidadSolicitada <= 0 || $cantidadSolicitada < $pendiente)) {
            $tipoRemito = 'parcial';
        }

        if ($cantidadSolicitada > 0) {
            $item['cantidad_remito'] = $cantidadSolicitada;
            $seleccionados[] = $item;
        }
    }

    if (empty($seleccionados)) {
        die('Debe seleccionar al menos una cantidad para emitir el remito.');
    }

    $lockObtenido = false;
    try {
        $pdo->beginTransaction();
        $lockObtenido = (int)$pdo->query("SELECT GET_LOCK('ecommerce_numero_remito_lock', 10)")->fetchColumn() === 1;
        if (!$lockObtenido) {
            throw new Exception('No se pudo bloquear la numeración del remito. Intentá nuevamente.');
        }

        $numeroRemito = remito_siguiente_numero($pdo);
        $stmt = $pdo->prepare("INSERT INTO ecommerce_remitos (pedido_id, numero_remito, tipo, observaciones, creado_por) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([
            $pedido_id,
            $numeroRemito,
            $tipoRemito,
            $observaciones !== '' ? $observaciones : null,
            null,
        ]);
        $nuevoRemitoId = (int)$pdo->lastInsertId();

        $stmtItem = $pdo->prepare("INSERT INTO ecommerce_remito_items (remito_id, pedido_item_id, cantidad) VALUES (?, ?, ?)");
        foreach ($seleccionados as $item) {
            $stmtItem->execute([$nuevoRemitoId, (int)$item['id'], (float)$item['cantidad_remito']]);
        }

        $pdo->commit();
        if ($lockObtenido) {
            $pdo->query("SELECT RELEASE_LOCK('ecommerce_numero_remito_lock')");
        }

        header('Location: pedido_remito.php?id=' . $pedido_id . '&remito_id=' . $nuevoRemitoId);
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($lockObtenido) {
            $pdo->query("SELECT RELEASE_LOCK('ecommerce_numero_remito_lock')");
        }
        die('No se pudo generar el remito: ' . htmlspecialchars($e->getMessage()));
    }
}

$numero_remito = 'BORRADOR-' . ($pedido['numero_pedido'] ?? ('PED-' . $pedido_id));
$tipo_remito = $yaHabiaRemitos ? 'parcial' : 'completo';
$observaciones_remito = '';
$items_pdf = [];

if ($remito_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_remitos WHERE id = ? AND pedido_id = ? LIMIT 1");
    $stmt->execute([$remito_id, $pedido_id]);
    $remito = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$remito) {
        die('Remito no encontrado');
    }

    $numero_remito = $remito['numero_remito'] ?: $numero_remito;
    $tipo_remito = $remito['tipo'] ?: $tipo_remito;
    $observaciones_remito = (string)($remito['observaciones'] ?? '');

    $stmt = $pdo->prepare("SELECT pedido_item_id, cantidad FROM ecommerce_remito_items WHERE remito_id = ?");
    $stmt->execute([$remito_id]);
    $cantidadesRemito = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cantidadesRemito[(int)$row['pedido_item_id']] = (float)$row['cantidad'];
    }

    foreach ($items as $item) {
        $itemId = (int)$item['id'];
        if (!isset($cantidadesRemito[$itemId])) {
            continue;
        }
        $item['cantidad_remito'] = $cantidadesRemito[$itemId];
        $items_pdf[] = $item;
    }
} else {
    foreach ($items as $item) {
        $pendiente = (float)($item['cantidad_pendiente'] ?? 0);
        if ($pendiente <= 0) {
            continue;
        }
        $item['cantidad_remito'] = $pendiente;
        $items_pdf[] = $item;
    }
}

if (empty($items_pdf)) {
    die('No hay cantidades pendientes para remitir en este pedido.');
}

$es_parcial = ($tipo_remito === 'parcial');
foreach ($items_pdf as $item) {
    $cantidadRemito = (float)($item['cantidad_remito'] ?? 0);
    $pendienteOriginal = (float)($item['cantidad_pendiente'] ?? 0);
    $cantidadTotal = (float)($item['cantidad'] ?? 0);
    if ($cantidadRemito < $cantidadTotal || ($pendienteOriginal > 0 && $cantidadRemito < $pendienteOriginal)) {
        $es_parcial = true;
        break;
    }
}

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
            $logo_local = '../../ecommerce/uploads/' . $this->empresa['logo'];
            $logo_root = '../../uploads/' . $this->empresa['logo'];
            if (file_exists($logo_local)) {
                $this->Image($logo_local, 10, 6, 30);
            } elseif (file_exists($logo_root)) {
                $this->Image($logo_root, 10, 6, 30);
            }
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
$tituloRemito = $es_parcial ? 'REMITO PARCIAL' : 'REMITO';

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, utf8_decode($tituloRemito . ' - ' . $numero_remito), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode('Pedido N° ' . ($pedido['numero_pedido'] ?? $pedido_id)), 0, 1, 'C');
$pdf->Ln(2);

if ($empresa && !empty($empresa['cuit'])) {
    $pdf->SetFont('Arial', '', 8);
    $pdf->Cell(0, 4, utf8_decode('CUIT: ' . $empresa['cuit'] . ' | Responsabilidad: ' . ($empresa['responsabilidad_fiscal'] ?? '-') . ' | Régimen IVA: ' . ($empresa['regimen_iva'] ?? '-')), 0, 1, 'C');
    if (!empty($empresa['iibb'])) {
        $pdf->Cell(0, 4, utf8_decode('Ingresos Brutos: ' . $empresa['iibb']), 0, 1, 'C');
    }
}
$pdf->Ln(2);

if ($es_parcial) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetTextColor(180, 110, 0);
    $pdf->Cell(0, 6, utf8_decode('Entrega parcial del pedido. Este remito incluye solo una parte de las cantidades.'), 0, 1, 'C');
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(1);
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(95, 6, 'ENTREGA A', 1, 0, 'C', true);
$pdf->Cell(95, 6, utf8_decode('DATOS DEL REMITO'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$y_start = $pdf->GetY();

$pdf->SetXY(10, $y_start);
$pdf->MultiCell(95, 5, utf8_decode('Nombre: ' . ($pedido['nombre'] ?? '')), 1);
$pdf->SetX(10);
$pdf->MultiCell(95, 5, 'Email: ' . ($pedido['email'] ?? ''), 1);
if (!empty($pedido['telefono'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode('Teléfono: ' . $pedido['telefono']), 1);
}
if (!empty($pedido['direccion'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode('Dirección: ' . $pedido['direccion']), 1);
}
if (!empty($pedido['ciudad']) || !empty($pedido['provincia'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode('Ciudad/Provincia: ' . ($pedido['ciudad'] ?? '') . ' / ' . ($pedido['provincia'] ?? '')), 1);
}
if (!empty($pedido['codigo_postal'])) {
    $pdf->SetX(10);
    $pdf->MultiCell(95, 5, utf8_decode('CP: ' . $pedido['codigo_postal']), 1);
}
$y_end = $pdf->GetY();

$pdf->SetXY(105, $y_start);
$pdf->MultiCell(95, 5, utf8_decode('Fecha: ' . ($fecha_pedido ? date('d/m/Y H:i', strtotime($fecha_pedido)) : '-')), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode('N° remito: ' . $numero_remito), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode('Tipo: ' . ($es_parcial ? 'Parcial' : 'Completo')), 1);
$pdf->SetX(105);
$pdf->MultiCell(95, 5, utf8_decode('Estado pedido: ' . ($pedido['estado'] ?? '-')), 1);

$pdf->SetY(max($y_end, $pdf->GetY()));
$pdf->Ln(5);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(90, 7, 'PRODUCTO', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'MEDIDAS', 1, 0, 'C', true);
$pdf->Cell(30, 7, 'CANT.', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
foreach ($items_pdf as $item) {
    $medidas = '';
    if (!empty($item['ancho_cm']) || !empty($item['alto_cm'])) {
        $medidas = ($item['ancho_cm'] ?? '-') . 'x' . ($item['alto_cm'] ?? '-') . ' cm';
    }

    $cantidadMostrar = (float)($item['cantidad_remito'] ?? $item['cantidad'] ?? 0);
    $cantidadTexto = rtrim(rtrim(number_format($cantidadMostrar, 2, ',', '.'), '0'), ',');

    $pdf->Cell(90, 6, utf8_decode(substr($item['producto_nombre'] ?? 'Producto', 0, 45)), 1);
    $pdf->Cell(40, 6, $medidas, 1, 0, 'C');
    $pdf->Cell(30, 6, $cantidadTexto, 1, 1, 'C');

    if (!empty($item['atributos'])) {
        $atributos = json_decode((string)$item['atributos'], true);
        if (is_array($atributos) && count($atributos) > 0) {
            $pdf->SetFont('Arial', 'I', 8);
            $atributos_str = '  Atributos: ';
            foreach ($atributos as $attr) {
                $atributos_str .= ($attr['nombre'] ?? 'Attr') . ': ' . ($attr['valor'] ?? '');
                if (isset($attr['costo_adicional']) && $attr['costo_adicional'] > 0) {
                    $atributos_str .= ' (+$' . number_format((float)$attr['costo_adicional'], 2) . ')';
                }
                $atributos_str .= ' | ';
            }
            $atributos_str = rtrim($atributos_str, ' | ');
            $pdf->MultiCell(160, 5, utf8_decode($atributos_str), 1);
            $pdf->SetFont('Arial', '', 9);
        }
    }
}

if ($observaciones_remito !== '') {
    $pdf->Ln(4);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(0, 6, 'Observaciones del remito', 0, 1);
    $pdf->SetFont('Arial', '', 9);
    $pdf->MultiCell(0, 5, utf8_decode($observaciones_remito), 1);
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

$pdf->Output('I', 'Remito_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', $numero_remito) . '.pdf');
