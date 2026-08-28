<?php

function sueldosObtenerSueldoBaseMes(PDO $pdo, int $empleado_id, string $mes): float
{
    $stmt = $pdo->prepare("SELECT sueldo_base FROM sueldo_base_mensual WHERE empleado_id = ? AND mes = ? LIMIT 1");
    $stmt->execute([$empleado_id, $mes]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        return (float)$row['sueldo_base'];
    }

    $stmt = $pdo->prepare("SELECT sueldo_base FROM empleados WHERE id = ?");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch(PDO::FETCH_ASSOC);
    return $empleado ? (float)$empleado['sueldo_base'] : 0.0;
}

function sueldosGuardarSueldoBaseMes(PDO $pdo, int $empleado_id, string $mes, float $sueldo_base): void
{
    $stmt = $pdo->prepare("
        INSERT INTO sueldo_base_mensual (empleado_id, mes, sueldo_base)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE sueldo_base = VALUES(sueldo_base)
    ");
    $stmt->execute([$empleado_id, $mes, $sueldo_base]);
}

function sueldosEliminarSueldoBaseMes(PDO $pdo, int $empleado_id, string $mes): void
{
    $stmt = $pdo->prepare("DELETE FROM sueldo_base_mensual WHERE empleado_id = ? AND mes = ?");
    $stmt->execute([$empleado_id, $mes]);
}

function sueldosEvaluarFormula(?string $formula, float $sueldo_base): ?float
{
    if (!$formula) {
        return null;
    }
    $formula = str_replace('sueldo_base', (string)$sueldo_base, $formula);
    try {
        $resultado = @eval("return " . $formula . ";");
        return $resultado !== false ? (float)$resultado : null;
    } catch (Exception $e) {
        return null;
    }
}

function sueldosCalcularDetalleMes(PDO $pdo, int $empleado_id, string $mes): array
{
    $sueldo_base = sueldosObtenerSueldoBaseMes($pdo, $empleado_id, $mes);

    $bonificaciones = 0.0;
    $descuentos = 0.0;

    $stmt_conceptos = $pdo->prepare("
        SELECT sc.monto, sc.formula, sc.es_porcentaje, c.tipo
        FROM sueldo_conceptos sc
        JOIN conceptos c ON sc.concepto_id = c.id
        WHERE sc.empleado_id = ? AND (sc.mes = ? OR sc.mes IS NULL OR sc.mes = '')
    ");
    $stmt_conceptos->execute([$empleado_id, $mes]);
    $conceptos = $stmt_conceptos->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conceptos as $c) {
        $monto_concepto = (float)$c['monto'];
        if (!empty($c['formula'])) {
            $calc = sueldosEvaluarFormula($c['formula'], $sueldo_base);
            if ($calc !== null) {
                $monto_concepto = $calc;
            }
        } elseif (!empty($c['es_porcentaje'])) {
            $monto_concepto = ($sueldo_base * $monto_concepto) / 100;
        }

        if ($c['tipo'] === 'descuento') {
            $descuentos += $monto_concepto;
        } else {
            $bonificaciones += $monto_concepto;
        }
    }

    $sueldo_total = max(0, $sueldo_base + $bonificaciones - $descuentos);

    return [
        'sueldo_base' => $sueldo_base,
        'bonificaciones' => $bonificaciones,
        'descuentos' => $descuentos,
        'sueldo_total' => $sueldo_total,
    ];
}

function sueldosCalcularTotalMes(PDO $pdo, int $empleado_id, string $mes): float
{
    return sueldosCalcularDetalleMes($pdo, $empleado_id, $mes)['sueldo_total'];
}
