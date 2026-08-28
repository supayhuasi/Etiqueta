<?php
require '../../../config.php';
require '../../../fpdf.php';
require_once __DIR__ . '/../includes/sueldos_helper.php';

function calcularMinutosExtrasMesEmpleadoRecibo(PDO $pdo, int $empleado_id, string $mes): int
{
    $total = 0;

    try {
        $stmt = $pdo->prepare(" 
            SELECT
                a.fecha,
                a.hora_salida,
                COALESCE(hd.hora_salida, h.hora_salida) AS horario_salida
            FROM asistencias a
            LEFT JOIN empleados_horarios h
                ON a.empleado_id = h.empleado_id
               AND h.activo = 1
            LEFT JOIN empleados_horarios_dias hd
                ON a.empleado_id = hd.empleado_id
               AND hd.dia_semana = DAYOFWEEK(a.fecha) - 1
               AND hd.activo = 1
            WHERE a.empleado_id = ?
              AND DATE_FORMAT(a.fecha, '%Y-%m') = ?
              AND a.hora_salida IS NOT NULL
              AND a.hora_salida <> ''
        ");
        $stmt->execute([$empleado_id, $mes]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $fila) {
            $fecha = trim((string)($fila['fecha'] ?? ''));
            $horaSalidaReal = trim((string)($fila['hora_salida'] ?? ''));
            $horaSalidaHorario = trim((string)($fila['horario_salida'] ?? ''));

            if ($fecha === '' || $horaSalidaReal === '' || $horaSalidaHorario === '') {
                continue;
            }

            $tsReal = strtotime($fecha . ' ' . $horaSalidaReal);
            $tsHorario = strtotime($fecha . ' ' . $horaSalidaHorario);
            if ($tsReal === false || $tsHorario === false) {
                continue;
            }

            $minExtra = (int)floor(($tsReal - $tsHorario) / 60);
            if ($minExtra > 0) {
                $total += $minExtra;
            }
        }
    } catch (Exception $e) {
        return 0;
    }

    return $total;
}

function evaluarFormula($formula, $sueldo_base)
{
    if (!$formula) {
        return null;
    }

    $formula = str_replace('sueldo_base', $sueldo_base, $formula);

    try {
        $resultado = @eval('return ' . $formula . ';');
        return $resultado !== false ? $resultado : null;
    } catch (Exception $e) {
        return null;
    }
}

$id = (int)($_GET['id'] ?? 0);
$mes = $_GET['mes'] ?? date('Y-m');

if ($id <= 0) {
    die('Empleado no encontrado');
}

$stmt = $pdo->prepare('SELECT * FROM empleados WHERE id = ?');
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die('Empleado no encontrado');
}

$stmt = $pdo->prepare("
    SELECT sc.*, c.nombre, c.tipo 
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
    ORDER BY c.tipo DESC, c.nombre
");
$stmt->execute([$id, $mes]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sueldo_base = sueldosObtenerSueldoBaseMes($pdo, $id, $mes);
$bonificaciones = 0;
$descuentos = 0;
$minutos_extras_mes = calcularMinutosExtrasMesEmpleadoRecibo($pdo, $id, $mes);

foreach ($conceptos as $c) {
    $monto = (float)($c['monto'] ?? 0);

    if (!empty($c['formula'])) {
        $monto = (float)(evaluarFormula($c['formula'], $sueldo_base) ?? 0);
    } elseif (!empty($c['es_porcentaje'])) {
        $monto = ($sueldo_base * (float)$c['monto']) / 100;
    }

    if (($c['tipo'] ?? '') === 'descuento') {
        $descuentos += $monto;
    } else {
        $bonificaciones += $monto;
    }
}

$sueldo_neto = $sueldo_base + $bonificaciones - $descuentos;

class PDF extends FPDF
{
    public function Header()
    {
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, utf8_decode('RECIBO DE SUELDO'), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, utf8_decode('Documento para firma y conformidad'), 0, 1, 'C');
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 25);

$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 8, utf8_decode('Empleado: ' . ($empleado['nombre'] ?? '')), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 7, utf8_decode('ID: #' . ($empleado['id'] ?? $id)), 0, 1);
$pdf->Cell(0, 7, utf8_decode('Mes: ' . date('F Y', strtotime($mes . '-01'))), 0, 1);
$pdf->Cell(0, 7, utf8_decode('Fecha de emisión: ' . date('d/m/Y')), 0, 1);
$pdf->Ln(4);

$pdf->SetDrawColor(200, 200, 200);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(232, 240, 255);
$pdf->Cell(120, 8, utf8_decode('Detalle'), 1, 0, 'C', true);
$pdf->Cell(60, 8, utf8_decode('Monto'), 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(120, 8, utf8_decode('Sueldo base'), 1, 0);
$pdf->Cell(60, 8, '$' . number_format($sueldo_base, 2, ',', '.'), 1, 1, 'R');

$pdf->Cell(120, 8, utf8_decode('Minutos extra del mes'), 1, 0);
$pdf->Cell(60, 8, (int)$minutos_extras_mes . ' min', 1, 1, 'R');

if ($bonificaciones > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(180, 8, utf8_decode('BONIFICACIONES'), 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 10);

    foreach ($conceptos as $c) {
        if (($c['tipo'] ?? '') !== 'bonificacion') {
            continue;
        }

        $monto = (float)($c['monto'] ?? 0);
        if (!empty($c['formula'])) {
            $monto = (float)(evaluarFormula($c['formula'], $sueldo_base) ?? 0);
        } elseif (!empty($c['es_porcentaje'])) {
            $monto = ($sueldo_base * (float)$c['monto']) / 100;
        }

        $pdf->Cell(120, 7, utf8_decode($c['nombre'] ?? 'Concepto'), 1, 0);
        $pdf->Cell(60, 7, '+ $' . number_format($monto, 2, ',', '.'), 1, 1, 'R');
    }
}

if ($descuentos > 0) {
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(180, 8, utf8_decode('DESCUENTOS'), 1, 1, 'C', true);
    $pdf->SetFont('Arial', '', 10);

    foreach ($conceptos as $c) {
        if (($c['tipo'] ?? '') !== 'descuento') {
            continue;
        }

        $monto = (float)($c['monto'] ?? 0);
        if (!empty($c['formula'])) {
            $monto = (float)(evaluarFormula($c['formula'], $sueldo_base) ?? 0);
        } elseif (!empty($c['es_porcentaje'])) {
            $monto = ($sueldo_base * (float)$c['monto']) / 100;
        }

        $pdf->Cell(120, 7, utf8_decode($c['nombre'] ?? 'Descuento'), 1, 0);
        $pdf->Cell(60, 7, '- $' . number_format($monto, 2, ',', '.'), 1, 1, 'R');
    }
}

$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(245, 245, 245);
$pdf->Cell(120, 9, utf8_decode('TOTAL A PAGAR'), 1, 0, 'R', true);
$pdf->Cell(60, 9, '$' . number_format($sueldo_neto, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(90, 6, utf8_decode('Empleado'), 0, 0, 'C');
$pdf->Cell(90, 6, utf8_decode('Recibe conforme'), 0, 1, 'C');

$yFirma = $pdf->GetY() + 6;
$pdf->Line(20, $yFirma, 90, $yFirma);
$pdf->Line(110, $yFirma, 190, $yFirma);
$pdf->Ln(1);
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 6, utf8_decode($empleado['nombre'] ?? 'Empleado'), 0, 0, 'C');
$pdf->Cell(90, 6, utf8_decode('Firma y aclaración'), 0, 1, 'C');

$pdf->Ln(8);
$pdf->SetFont('Arial', 'I', 8);
$pdf->MultiCell(0, 5, utf8_decode('Este recibo refleja el total de haberes correspondientes al período indicado y debe ser firmado por el empleado para su conformidad.'), 0, 'L');

$nombreArchivo = 'Recibo_Sueldo_' . preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($empleado['nombre'] ?? 'empleado')) . '_' . str_replace('-', '_', $mes) . '.pdf';
$pdf->Output('I', $nombreArchivo);
