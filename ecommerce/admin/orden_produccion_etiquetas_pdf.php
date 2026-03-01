<?php
/**
 * Generador de Etiquetas Individuales por Producto
 * Genera códigos de barras únicos para cada item de la orden de producción
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../code128.php';

$pedido_id = $_GET['pedido_id'] ?? 0;
$generar = $_GET['generar'] ?? false;

// Obtener orden de producción
$stmt = $pdo->prepare("
    SELECT op.*, p.numero_pedido
    FROM ecommerce_ordenes_produccion op
    JOIN ecommerce_pedidos p ON op.pedido_id = p.id
    WHERE op.pedido_id = ?
");
$stmt->execute([$pedido_id]);
$orden = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orden) {
    die('Orden de producción no encontrada');
}

// Obtener items del pedido
$stmt = $pdo->prepare("
    SELECT pi.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo
    FROM ecommerce_pedido_items pi
    JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$stmt->execute([$pedido_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Si se solicita generar los códigos
if ($generar) {
    $pdo->beginTransaction();
    try {
        // Eliminar códigos existentes si hay (regeneración)
        $stmt = $pdo->prepare("DELETE FROM ecommerce_produccion_items_barcode WHERE orden_produccion_id = ?");
        $stmt->execute([$orden['id']]);
        
        $stmt_insert = $pdo->prepare("
            INSERT INTO ecommerce_produccion_items_barcode 
            (orden_produccion_id, pedido_item_id, numero_item, codigo_barcode, estado)
            VALUES (?, ?, ?, ?, 'pendiente')
        ");
        
        foreach ($items as $item) {
            $cantidad = (int)$item['cantidad'];
            
            for ($i = 1; $i <= $cantidad; $i++) {
                // Generar código único: OP{orden_id}-IT{item_id}-{numero}
                $codigo = sprintf('OP%06d-IT%06d-%03d', $orden['id'], $item['id'], $i);
                
                $stmt_insert->execute([
                    $orden['id'],
                    $item['id'],
                    $i,
                    $codigo
                ]);
            }
        }
        
        // Marcar que los items fueron generados
        $stmt = $pdo->prepare("UPDATE ecommerce_ordenes_produccion SET items_generados = 1 WHERE id = ?");
        $stmt->execute([$orden['id']]);
        
        $pdo->commit();
        
        // Recargar para obtener los códigos generados
        header("Location: ?pedido_id=$pedido_id");
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        die('Error al generar códigos: ' . $e->getMessage());
    }
}

// Obtener códigos generados
$stmt = $pdo->prepare("
    SELECT pib.*, pi.producto_id, pr.nombre as producto_nombre
    FROM ecommerce_produccion_items_barcode pib
    JOIN ecommerce_pedido_items pi ON pib.pedido_item_id = pi.id
    JOIN ecommerce_productos pr ON pi.producto_id = pr.id
    WHERE pib.orden_produccion_id = ?
    ORDER BY pib.pedido_item_id, pib.numero_item
");
$stmt->execute([$orden['id']]);
$codigos_generados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por item
$items_con_codigos = [];
foreach ($codigos_generados as $codigo) {
    $item_id = $codigo['pedido_item_id'];
    if (!isset($items_con_codigos[$item_id])) {
        $items_con_codigos[$item_id] = [
            'producto_nombre' => $codigo['producto_nombre'],
            'codigos' => []
        ];
    }
    $items_con_codigos[$item_id]['codigos'][] = $codigo;
}

// Generar PDF con todas las etiquetas
$pdf = new PDF_Code128('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Encabezado
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, utf8_decode('Etiquetas de Producción'), 0, 1, 'C');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, utf8_decode('Orden: ' . $orden['numero_pedido']), 0, 1, 'C');
$pdf->Ln(5);

// Configuración de etiquetas
$etiqueta_ancho = 90;
$etiqueta_alto = 40;
$margen_x = 10;
$margen_y = 35;
$espacio_x = 5;
$espacio_y = 5;
$columnas = 2;

$contador = 0;
$items_por_pagina = 12; // 2 columnas x 6 filas

foreach ($codigos_generados as $codigo) {
    // Calcular posición
    $col = $contador % $columnas;
    $fila = floor(($contador % $items_por_pagina) / $columnas);
    
    // Nueva página si es necesario
    if ($contador > 0 && $contador % $items_por_pagina == 0) {
        $pdf->AddPage();
    }
    
    $x = $margen_x + ($col * ($etiqueta_ancho + $espacio_x));
    $y = $margen_y + ($fila * ($etiqueta_alto + $espacio_y));
    
    // Dibujar borde de etiqueta
    $pdf->Rect($x, $y, $etiqueta_ancho, $etiqueta_alto);
    
    // Producto
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetXY($x + 2, $y + 2);
    $producto_corto = substr($codigo['producto_nombre'], 0, 30);
    $pdf->Cell($etiqueta_ancho - 4, 5, utf8_decode($producto_corto), 0, 0, 'L');
    
    // Número de item
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetXY($x + 2, $y + 8);
    $pdf->Cell($etiqueta_ancho - 4, 4, utf8_decode('Item ' . $codigo['numero_item']), 0, 0, 'L');
    
    // Código de barras
    $barcode_y = $y + 14;
    $pdf->Code128($x + 5, $barcode_y, $codigo['codigo_barcode'], $etiqueta_ancho - 10, 12);
    
    // Texto del código
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetXY($x + 2, $y + $etiqueta_alto - 7);
    $pdf->Cell($etiqueta_ancho - 4, 4, $codigo['codigo_barcode'], 0, 0, 'C');
    
    // Orden de producción
    $pdf->SetFont('Arial', 'I', 6);
    $pdf->SetXY($x + 2, $y + $etiqueta_alto - 3);
    $pdf->Cell($etiqueta_ancho - 4, 3, utf8_decode('Orden: ' . $orden['numero_pedido']), 0, 0, 'C');
    
    $contador++;
}

// Salida del PDF
$filename = 'Etiquetas_Produccion_' . $orden['numero_pedido'] . '.pdf';
$pdf->Output('D', $filename);
exit;
