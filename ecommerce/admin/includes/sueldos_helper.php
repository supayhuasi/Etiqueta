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

function asistenciasParseTs(string $fecha, ?string $hora): ?int
{
    $fecha = trim($fecha);
    $hora = trim((string)$hora);
    if ($fecha === '' || $hora === '') {
        return null;
    }

    $ts = strtotime($fecha . ' ' . $hora);
    return $ts === false ? null : $ts;
}

function asistenciasAjustarSalida(?int $entradaTs, ?int $salidaTs): ?int
{
    if ($salidaTs === null) {
        return null;
    }
    if ($entradaTs !== null && $salidaTs < $entradaTs) {
        return $salidaTs + 86400;
    }
    return $salidaTs;
}

/**
 * Balance del día respecto al horario contratado.
 * Importa completar las horas del rango:
 * - Si llega tarde, lo que se quede después de la salida primero cubre esa tardanza.
 * - Recién cuando cubre esos minutos empiezan a contar extras.
 * - Si se va antes de completar el rango o de recuperar la tardanza, cuenta minutos faltantes.
 * - Llegar temprano no suma extras ni adelanta la salida.
 *
 * @return array{calculable:bool,minutos_extra:int,minutos_faltantes:int,minutos_neto:int,minutos_tarde:int}
 */
function asistenciasCalcularBalanceDia(
    string $fecha,
    ?string $horaEntradaReal,
    ?string $horaSalidaReal,
    ?string $horaEntradaHorario,
    ?string $horaSalidaHorario
): array {
    $vacio = [
        'calculable' => false,
        'minutos_extra' => 0,
        'minutos_faltantes' => 0,
        'minutos_neto' => 0,
        'minutos_tarde' => 0,
    ];

    $tsEntradaHorario = asistenciasParseTs($fecha, $horaEntradaHorario);
    $tsSalidaHorario = asistenciasAjustarSalida($tsEntradaHorario, asistenciasParseTs($fecha, $horaSalidaHorario));
    $tsEntradaReal = asistenciasParseTs($fecha, $horaEntradaReal);
    $tsSalidaReal = asistenciasAjustarSalida($tsEntradaReal ?? $tsEntradaHorario, asistenciasParseTs($fecha, $horaSalidaReal));

    if ($tsSalidaReal === null || $tsSalidaHorario === null) {
        return $vacio;
    }

    $minutosTarde = 0;
    if ($tsEntradaReal !== null && $tsEntradaHorario !== null) {
        $minutosTarde = (int)floor(($tsEntradaReal - $tsEntradaHorario) / 60);
        if ($minutosTarde < 0) {
            $minutosTarde = 0;
        }
    }

    $neto = (int)floor(($tsSalidaReal - ($tsSalidaHorario + ($minutosTarde * 60))) / 60);

    return [
        'calculable' => true,
        'minutos_extra' => $neto > 0 ? $neto : 0,
        'minutos_faltantes' => $neto < 0 ? abs($neto) : 0,
        'minutos_neto' => $neto,
        'minutos_tarde' => $minutosTarde,
    ];
}

/**
 * Minutos netos del día respecto al horario (positivo = extra, negativo = faltante).
 */
function sueldosCalcularMinutosDiaAsistencia(
    string $fecha,
    ?string $horaEntradaReal,
    ?string $horaSalidaReal,
    ?string $horaEntradaHorario,
    ?string $horaSalidaHorario
): ?int {
    $balance = asistenciasCalcularBalanceDia(
        $fecha,
        $horaEntradaReal,
        $horaSalidaReal,
        $horaEntradaHorario,
        $horaSalidaHorario
    );

    return $balance['calculable'] ? (int)$balance['minutos_neto'] : null;
}

/**
 * @return array<int,int> empleado_id => minutos del mes (puede ser negativo)
 */
function sueldosCalcularMinutosExtrasMesPorEmpleado(PDO $pdo, string $mes, ?int $empleado_id = null): array
{
    $resultado = [];

    try {
        $sql = "
            SELECT
                a.empleado_id,
                a.fecha,
                a.hora_entrada,
                a.hora_salida,
                COALESCE(hd.hora_entrada, h.hora_entrada) AS horario_entrada,
                COALESCE(hd.hora_salida, h.hora_salida) AS horario_salida
            FROM asistencias a
            LEFT JOIN empleados_horarios h
                ON a.empleado_id = h.empleado_id
               AND h.activo = 1
            LEFT JOIN empleados_horarios_dias hd
                ON a.empleado_id = hd.empleado_id
               AND hd.dia_semana = DAYOFWEEK(a.fecha) - 1
               AND hd.activo = 1
            WHERE DATE_FORMAT(a.fecha, '%Y-%m') = ?
              AND a.hora_salida IS NOT NULL
              AND a.hora_salida <> ''
        ";
        $params = [$mes];
        if ($empleado_id !== null && $empleado_id > 0) {
            $sql .= " AND a.empleado_id = ?";
            $params[] = $empleado_id;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($filas as $fila) {
            $empId = (int)($fila['empleado_id'] ?? 0);
            if ($empId <= 0) {
                continue;
            }

            $minutos = sueldosCalcularMinutosDiaAsistencia(
                (string)($fila['fecha'] ?? ''),
                $fila['hora_entrada'] ?? null,
                $fila['hora_salida'] ?? null,
                $fila['horario_entrada'] ?? null,
                $fila['horario_salida'] ?? null
            );
            if ($minutos === null) {
                continue;
            }

            if (!isset($resultado[$empId])) {
                $resultado[$empId] = 0;
            }
            $resultado[$empId] += $minutos;
        }
    } catch (Exception $e) {
        return [];
    }

    return $resultado;
}

function sueldosCalcularMinutosExtrasMesEmpleado(PDO $pdo, int $empleado_id, string $mes): int
{
    $mapa = sueldosCalcularMinutosExtrasMesPorEmpleado($pdo, $mes, $empleado_id);
    return (int)($mapa[$empleado_id] ?? 0);
}
