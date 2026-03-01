<?php
/**
 * Generador de Tarjetas de Asistencia con Código de Barras
 * Genera un PDF con tarjetas de identificación para empleados
 * con código de barras para registro de asistencias
 */

require_once __DIR__ . '/../../../config.php';
require_once __DIR__ . '/../../../code128.php';

// Obtener parámetros
$empleado_id = $_GET['empleado_id'] ?? null;
$todos = $_GET['todos'] ?? false;

// Configurar PDF
$pdf = new PDF_Code128('P', 'mm', 'A4');
$pdf->SetMargins(10, 10, 10);
$pdf->AddPage();

// Función para generar una tarjeta
function generarTarjeta($pdf, $empleado, $x, $y) {
    $ancho_tarjeta = 85.6;  // Ancho estándar tarjeta (formato ISO)
    $alto_tarjeta = 53.98;   // Alto estándar tarjeta
    
    // Borde de la tarjeta
    $pdf->SetLineWidth(0.5);
    $pdf->Rect($x, $y, $ancho_tarjeta, $alto_tarjeta);
    
    // Logo o encabezado de empresa (opcional)
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->SetXY($x + 5, $y + 5);
    $pdf->Cell(0, 6, 'TARJETA DE ASISTENCIA', 0, 1, 'L');
    
    // Nombre del empleado
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetXY($x + 5, $y + 12);
    $pdf->Cell(0, 6, utf8_decode($empleado['nombre']), 0, 1, 'L');
    
    // Puesto
    if (!empty($empleado['puesto'])) {
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetXY($x + 5, $y + 18);
        $pdf->Cell(0, 5, utf8_decode($empleado['puesto']), 0, 1, 'L');
    }
    
    // Departamento
    if (!empty($empleado['departamento'])) {
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetXY($x + 5, $y + 23);
        $pdf->Cell(0, 4, utf8_decode('Dpto: ' . $empleado['departamento']), 0, 1, 'L');
    }
    
    // Código de barras (centrado en la parte inferior)
    $codigo = 'EMP' . str_pad($empleado['id'], 6, '0', STR_PAD_LEFT);
    
    // Posición del código de barras
    $barcode_y = $y + $alto_tarjeta - 18;
    $barcode_x = $x + ($ancho_tarjeta / 2) - 30; // Centrado
    
    $pdf->Code128($barcode_x, $barcode_y, $codigo, 60, 12);
    
    // Texto del código debajo del código de barras
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetXY($x + 5, $y + $alto_tarjeta - 5);
    $pdf->Cell($ancho_tarjeta - 10, 4, 'ID: ' . $codigo, 0, 0, 'C');
    
    // Instrucciones pequeñas
    $pdf->SetFont('Arial', 'I', 6);
    $pdf->SetXY($x + 5, $y + 28);
    $pdf->Cell($ancho_tarjeta - 10, 3, utf8_decode('Escanee este código para registrar su asistencia'), 0, 0, 'C');
}

try {
    // Obtener empleados
    if ($todos) {
        // Generar tarjetas para todos los empleados activos
        $stmt = $pdo->query("
            SELECT id, nombre, puesto, departamento, documento
            FROM empleados 
            WHERE activo = 1 
            ORDER BY nombre
        ");
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($empleado_id) {
        // Generar tarjeta para un empleado específico
        $stmt = $pdo->prepare("
            SELECT id, nombre, puesto, departamento, documento
            FROM empleados 
            WHERE id = ? AND activo = 1
        ");
        $stmt->execute([$empleado_id]);
        $empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        die('Debe especificar un empleado o solicitar todas las tarjetas');
    }
    
    if (empty($empleados)) {
        die('No se encontraron empleados para generar tarjetas');
    }
    
    // Generar tarjetas (2 por página en formato vertical)
    $posiciones = [
        ['x' => 15, 'y' => 20],   // Tarjeta superior
        ['x' => 15, 'y' => 140]   // Tarjeta inferior
    ];
    
    $contador = 0;
    foreach ($empleados as $empleado) {
        $pos_index = $contador % 2;
        
        // Si es la primera tarjeta de una nueva página y no es la primera
        if ($pos_index == 0 && $contador > 0) {
            $pdf->AddPage();
        }
        
        // Generar tarjeta
        generarTarjeta($pdf, $empleado, $posiciones[$pos_index]['x'], $posiciones[$pos_index]['y']);
        
        $contador++;
    }
    
    // Salida del PDF
    $filename = $todos ? 'Tarjetas_Todos_Empleados.pdf' : 'Tarjeta_Empleado_' . $empleado_id . '.pdf';
    $pdf->Output('D', $filename);
    
} catch (Exception $e) {
    die('Error al generar tarjetas: ' . $e->getMessage());
}
