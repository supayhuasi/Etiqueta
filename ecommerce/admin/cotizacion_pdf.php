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
$impuestosCotizacion = !empty($cotizacion['impuestos_json']) ? (json_decode((string)$cotizacion['impuestos_json'], true) ?: []) : [];

$listasParaPdf = [];
$listaItemsMap = [];
$listaCategoriasMap = [];
$productoCategoriaMap = [];
$productoPrecioBaseMap = [];
$productoTipoPrecioMap = [];

function calcular_subtotal_items_para_lista(
    array $items,
    int $listaId,
    array $listaItemsMap,
    array $listaCategoriasMap,
    array $productoCategoriaMap,
    array $productoPrecioBaseMap,
    array $productoTipoPrecioMap
): float {
    $subtotal = 0.0;

    foreach ($items as $item) {
        $cantidad = max(1, (int)($item['cantidad'] ?? 1));
        $productoId = !empty($item['producto_id']) ? (int)$item['producto_id'] : 0;

        $precioItemGuardado = isset($item['precio_base'])
            ? (float)$item['precio_base']
            : (float)($item['precio_unitario'] ?? 0);

        $costoAtributos = 0.0;
        if (!empty($item['atributos']) && is_array($item['atributos'])) {
            foreach ($item['atributos'] as $attr) {
                $costoAtributos += (float)($attr['costo_adicional'] ?? 0);
            }
        }

        // Si el precio unitario ya incluye atributos, los quitamos para trabajar sobre base del producto.
        if (!isset($item['precio_base']) && isset($item['precio_unitario'])) {
            $precioItemGuardado = max(0, $precioItemGuardado - $costoAtributos);
        }

        $precioBaseOriginal = $precioItemGuardado;
        $tipoPrecioProducto = (string)($productoTipoPrecioMap[$productoId] ?? '');
        if ($productoId > 0 && $tipoPrecioProducto === 'fijo' && isset($productoPrecioBaseMap[$productoId])) {
            // Para precio fijo usamos el precio original del producto y evitamos dobles descuentos.
            $precioBaseOriginal = (float)$productoPrecioBaseMap[$productoId];
        }

        $precioLista = $precioBaseOriginal;

        if ($productoId > 0 && isset($listaItemsMap[$listaId][$productoId])) {
            $cfg = $listaItemsMap[$listaId][$productoId];
            $precioNuevo = (float)($cfg['precio_nuevo'] ?? 0);
            $descItem = (float)($cfg['descuento_porcentaje'] ?? 0);

            if ($precioNuevo > 0) {
                $precioLista = $precioNuevo;
            } elseif ($descItem > 0) {
                $precioLista = $precioBaseOriginal * (1 - ($descItem / 100));
            }
        } elseif ($productoId > 0) {
            $categoriaId = (int)($productoCategoriaMap[$productoId] ?? 0);
            if ($categoriaId > 0 && isset($listaCategoriasMap[$listaId][$categoriaId])) {
                $descCat = (float)$listaCategoriasMap[$listaId][$categoriaId];
                if ($descCat > 0) {
                    $precioLista = $precioBaseOriginal * (1 - ($descCat / 100));
                }
            }
        }

        $precioUnitarioFinal = max(0, $precioLista + $costoAtributos);
        $subtotal += $precioUnitarioFinal * $cantidad;
    }

    return round($subtotal, 2);
}

try {
    $columnasListas = $pdo->query("SHOW COLUMNS FROM ecommerce_listas_precios")->fetchAll(PDO::FETCH_COLUMN);
    $tieneMostrarEnPdf = in_array('mostrar_en_cotizacion_pdf', $columnasListas, true);
    $tieneCantidadCuotas = in_array('cantidad_cuotas', $columnasListas, true);

    $selectCuotas = $tieneCantidadCuotas ? 'COALESCE(cantidad_cuotas, 1)' : '1';
    $whereMostrar = $tieneMostrarEnPdf ? ' AND mostrar_en_cotizacion_pdf = 1' : '';

    $stmt = $pdo->query("SELECT id, nombre, {$selectCuotas} AS cantidad_cuotas FROM ecommerce_listas_precios WHERE activo = 1{$whereMostrar} ORDER BY nombre ASC");
    $listasParaPdf = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    if (!empty($listasParaPdf)) {
        $listasIds = array_values(array_unique(array_map(static function ($row) {
            return (int)($row['id'] ?? 0);
        }, $listasParaPdf)));
        $listasIds = array_values(array_filter($listasIds, static function ($id) {
            return $id > 0;
        }));

        if (!empty($listasIds)) {
            $placeholdersListas = implode(',', array_fill(0, count($listasIds), '?'));

            $stmt = $pdo->prepare("SELECT lista_precio_id, producto_id, precio_nuevo, descuento_porcentaje FROM ecommerce_lista_precio_items WHERE activo = 1 AND lista_precio_id IN ($placeholdersListas)");
            $stmt->execute($listasIds);
            $rowsItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsItems as $row) {
                $lp = (int)$row['lista_precio_id'];
                $pid = (int)$row['producto_id'];
                $listaItemsMap[$lp][$pid] = [
                    'precio_nuevo' => (float)($row['precio_nuevo'] ?? 0),
                    'descuento_porcentaje' => (float)($row['descuento_porcentaje'] ?? 0),
                ];
            }

            $stmt = $pdo->prepare("SELECT lista_precio_id, categoria_id, descuento_porcentaje FROM ecommerce_lista_precio_categorias WHERE activo = 1 AND lista_precio_id IN ($placeholdersListas)");
            $stmt->execute($listasIds);
            $rowsCats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsCats as $row) {
                $lp = (int)$row['lista_precio_id'];
                $cid = (int)$row['categoria_id'];
                $listaCategoriasMap[$lp][$cid] = (float)($row['descuento_porcentaje'] ?? 0);
            }
        }

        $productoIds = [];
        foreach ($items as $item) {
            if (!empty($item['producto_id'])) {
                $productoIds[] = (int)$item['producto_id'];
            }
        }
        $productoIds = array_values(array_unique(array_filter($productoIds, static function ($id) {
            return $id > 0;
        })));

        if (!empty($productoIds)) {
            $placeholdersProductos = implode(',', array_fill(0, count($productoIds), '?'));
            $stmt = $pdo->prepare("SELECT id, categoria_id, precio_base, tipo_precio FROM ecommerce_productos WHERE id IN ($placeholdersProductos)");
            $stmt->execute($productoIds);
            $rowsProductos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rowsProductos as $row) {
                $pid = (int)$row['id'];
                $productoCategoriaMap[$pid] = (int)($row['categoria_id'] ?? 0);
                $productoPrecioBaseMap[$pid] = (float)($row['precio_base'] ?? 0);
                $productoTipoPrecioMap[$pid] = (string)($row['tipo_precio'] ?? '');
            }
        }
    }
} catch (Exception $e) {
    $listasParaPdf = [];
}

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
        if ($this->empresa && !empty($this->empresa['logo'])) {
            $logo_local = '../../ecommerce/uploads/' . $this->empresa['logo'];
            $logo_root = '../../uploads/' . $this->empresa['logo'];
            if (file_exists($logo_local)) {
                $this->Image($logo_local, 10, 6, 30);
            } elseif (file_exists($logo_root)) {
                $this->Image($logo_root, 10, 6, 30);
            }
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
    
    // Mostrar atributos si existen
    if (!empty($item['atributos']) && is_array($item['atributos'])) {
        $pdf->SetFont('Arial', 'I', 8);
        $atributos_str = '  🎨 Atributos: ';
        foreach ($item['atributos'] as $attr) {
            $atributos_str .= $attr['nombre'] . ': ' . $attr['valor'];
            if ($attr['costo_adicional'] > 0) {
                $atributos_str .= ' (+$' . number_format($attr['costo_adicional'], 2) . ')';
            }
            $atributos_str .= ' | ';
        }
        $atributos_str = rtrim($atributos_str, ' | ');
        $pdf->Cell(70, 5, utf8_decode(substr($atributos_str, 0, 50)), 1);
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

if (!empty($cotizacion['cupon_descuento'])) {
    $pdf->Cell(120, 6, '', 0);
    $pdf->SetTextColor(0, 102, 204);
    $label = 'CUPÓN';
    if (!empty($cotizacion['cupon_codigo'])) {
        $label .= ' (' . $cotizacion['cupon_codigo'] . ')';
    }
    $pdf->Cell(35, 6, $label . ':', 1, 0, 'R');
    $pdf->Cell(35, 6, '-$' . number_format($cotizacion['cupon_descuento'], 2), 1, 1, 'R');
    $pdf->SetTextColor(0, 0, 0);
}

if (!empty($impuestosCotizacion) && is_array($impuestosCotizacion)) {
    foreach ($impuestosCotizacion as $impuesto) {
        $montoImpuesto = (float)($impuesto['monto'] ?? 0);
        if ($montoImpuesto <= 0) {
            continue;
        }
        $pdf->Cell(120, 6, '', 0);
        if (!empty($impuesto['incluido_en_precio'])) {
            $pdf->SetTextColor(120, 120, 120);
        } else {
            $pdf->SetTextColor(180, 0, 0);
        }
        $labelImpuesto = utf8_decode((string)($impuesto['nombre'] ?? 'Impuesto'));
        if (!empty($impuesto['incluido_en_precio'])) {
            $labelImpuesto .= ' (incl.)';
        }
        $pdf->Cell(35, 6, $labelImpuesto . ':', 1, 0, 'R');
        $pdf->Cell(35, 6, (!empty($impuesto['incluido_en_precio']) ? '' : '+') . '$' . number_format($montoImpuesto, 2), 1, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }
}

$pdf->Cell(120, 6, '', 0);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(200, 230, 255);
$pdf->Cell(35, 8, 'TOTAL:', 1, 0, 'R', true);
$pdf->Cell(35, 8, '$' . number_format($cotizacion['total'], 2), 1, 1, 'R', true);

if (!empty($listasParaPdf)) {
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 6, utf8_decode('Opciones por Lista de Precios'), 0, 1);

    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(230, 230, 230);
    $pdf->Cell(90, 7, 'LISTA', 1, 0, 'C', true);
    $pdf->Cell(35, 7, 'CUOTAS', 1, 0, 'C', true);
    $pdf->Cell(65, 7, 'IMPORTE POR CUOTA', 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 9);
    $totalCotizacionBase = (float)($cotizacion['total'] ?? 0);
    $listaSeleccionadaCotizacion = (int)($cotizacion['lista_precio_id'] ?? 0);
    $cuponCotizacion = (float)($cotizacion['cupon_descuento'] ?? 0);

    $baseCotizacionSinImpuestos = max(
        0,
        (float)($cotizacion['subtotal'] ?? 0)
        - (float)($cotizacion['descuento'] ?? 0)
        - $cuponCotizacion
    );

    $impuestosAdicionalesMonto = 0.0;
    if (!empty($impuestosCotizacion) && is_array($impuestosCotizacion)) {
        foreach ($impuestosCotizacion as $impuesto) {
            if (!empty($impuesto['incluido_en_precio'])) {
                continue;
            }
            $impuestosAdicionalesMonto += max(0, (float)($impuesto['monto'] ?? 0));
        }
    }
    if ($impuestosAdicionalesMonto <= 0 && $baseCotizacionSinImpuestos > 0) {
        $impuestosAdicionalesMonto = max(0, $totalCotizacionBase - $baseCotizacionSinImpuestos);
    }
    $factorImpuestosAdicionales = $baseCotizacionSinImpuestos > 0
        ? ($impuestosAdicionalesMonto / $baseCotizacionSinImpuestos)
        : 0;

    foreach ($listasParaPdf as $listaPdf) {
        $listaId = (int)($listaPdf['id'] ?? 0);
        $cuotas = max(1, (int)($listaPdf['cantidad_cuotas'] ?? 1));

        if ($listaSeleccionadaCotizacion > 0 && $listaId === $listaSeleccionadaCotizacion) {
            // Nunca recalcular la misma lista de la cotización para evitar doble descuento.
            $totalLista = $totalCotizacionBase;
        } else {
            $subtotalLista = calcular_subtotal_items_para_lista(
                $items,
                $listaId,
                $listaItemsMap,
                $listaCategoriasMap,
                $productoCategoriaMap,
                $productoPrecioBaseMap,
                $productoTipoPrecioMap
            );

            if ($subtotalLista > 0) {
                $baseListaSinImpuestos = max(0, $subtotalLista - $cuponCotizacion);
                $totalLista = $baseListaSinImpuestos * (1 + $factorImpuestosAdicionales);
            } else {
                $totalLista = $totalCotizacionBase;
            }
        }

        $importeCuota = $totalLista / $cuotas;

        $pdf->Cell(90, 6, utf8_decode((string)($listaPdf['nombre'] ?? 'Lista')), 1);
        $pdf->Cell(35, 6, (string)$cuotas, 1, 0, 'C');
        $pdf->Cell(65, 6, '$' . number_format($importeCuota, 2), 1, 1, 'R');
    }
}

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
