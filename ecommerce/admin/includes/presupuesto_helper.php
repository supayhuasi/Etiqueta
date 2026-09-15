<?php

function presupuestoClientesConSaldo(PDO $pdo): array
{
    if (!function_exists('admin_table_exists')
        || !admin_table_exists($pdo, 'ecommerce_clientes')
        || !admin_table_exists($pdo, 'ecommerce_pedidos')
        || !admin_table_exists($pdo, 'ecommerce_pedido_pagos')
    ) {
        return [];
    }

    $sql = "
        SELECT c.id, c.nombre, c.email, c.telefono,
               COALESCE(ped.total_pedidos, 0) AS total_pedidos,
               COALESCE(pag.total_pagado, 0) AS total_pagado,
               COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0) AS saldo
        FROM ecommerce_clientes c
        LEFT JOIN (
            SELECT cliente_id, SUM(total) AS total_pedidos
            FROM ecommerce_pedidos
            WHERE estado != 'cancelado'
            GROUP BY cliente_id
        ) ped ON ped.cliente_id = c.id
        LEFT JOIN (
            SELECT p.cliente_id, SUM(pp.monto) AS total_pagado
            FROM ecommerce_pedido_pagos pp
            JOIN ecommerce_pedidos p ON pp.pedido_id = p.id
            WHERE p.estado != 'cancelado'
            GROUP BY p.cliente_id
        ) pag ON pag.cliente_id = c.id
        WHERE COALESCE(ped.total_pedidos, 0) > 0
          AND (COALESCE(ped.total_pedidos, 0) - COALESCE(pag.total_pagado, 0)) > 0.009
        ORDER BY saldo DESC, c.nombre ASC
    ";

    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('presupuestoClientesConSaldo: ' . $e->getMessage());
        return [];
    }
}

function presupuestoCajaActual(PDO $pdo): float
{
    if (!function_exists('admin_table_exists') || !admin_table_exists($pdo, 'flujo_caja')) {
        return 0.0;
    }

    try {
        $ingresos = (float)$pdo->query("SELECT COALESCE(SUM(monto), 0) FROM flujo_caja WHERE tipo = 'ingreso'")->fetchColumn();
        $egresos = (float)$pdo->query("SELECT COALESCE(SUM(monto), 0) FROM flujo_caja WHERE tipo = 'egreso'")->fetchColumn();
        return $ingresos - $egresos;
    } catch (Throwable $e) {
        error_log('presupuestoCajaActual: ' . $e->getMessage());
        return 0.0;
    }
}

function presupuestoSueldosPendientes(PDO $pdo, string $mes): array
{
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)
        || !function_exists('admin_table_exists')
        || !admin_table_exists($pdo, 'empleados')
    ) {
        return [];
    }

    require_once __DIR__ . '/sueldos_helper.php';

    $tienePagos = admin_table_exists($pdo, 'pagos_sueldos');
    $tieneParciales = admin_table_exists($pdo, 'pagos_sueldos_parciales');

    $sql = "
        SELECT e.id, e.nombre, e.email, e.sueldo_base
        FROM empleados e
        WHERE e.activo = 1
        ORDER BY e.nombre ASC
    ";

    try {
        $empleados = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }

    $pagosPorEmpleado = [];
    $sueldoRegPorEmpleado = [];
    $parcialesPorEmpleado = [];

    if ($tienePagos) {
        try {
            $stmt = $pdo->prepare("
                SELECT empleado_id, COALESCE(monto_pagado, 0) AS monto_pagado, COALESCE(sueldo_total, 0) AS sueldo_total
                FROM pagos_sueldos
                WHERE mes_pago = ?
            ");
            $stmt->execute([$mes]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $empId = (int)$row['empleado_id'];
                $pagosPorEmpleado[$empId] = (float)$row['monto_pagado'];
                $sueldoRegPorEmpleado[$empId] = (float)$row['sueldo_total'];
            }
        } catch (Throwable $e) {
            // seguir sin pagos registrados
        }
    }

    if ($tieneParciales) {
        try {
            $stmt = $pdo->prepare("
                SELECT empleado_id, COALESCE(SUM(monto_pagado), 0) AS pagos_parciales
                FROM pagos_sueldos_parciales
                WHERE mes_pago = ?
                GROUP BY empleado_id
            ");
            $stmt->execute([$mes]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $parcialesPorEmpleado[(int)$row['empleado_id']] = (float)$row['pagos_parciales'];
            }
        } catch (Throwable $e) {
            // seguir sin parciales
        }
    }

    $resultado = [];
    foreach ($empleados as $emp) {
        $empId = (int)$emp['id'];
        $sueldoReg = $sueldoRegPorEmpleado[$empId] ?? 0.0;
        if ($sueldoReg <= 0) {
            $sueldoTotal = sueldosCalcularTotalMes($pdo, $empId, $mes);
        } else {
            $sueldoTotal = $sueldoReg;
        }
        $pagado = ($pagosPorEmpleado[$empId] ?? 0.0) + ($parcialesPorEmpleado[$empId] ?? 0.0);
        $pendiente = max(0, $sueldoTotal - $pagado);
        if ($pendiente <= 0.009) {
            continue;
        }

        $resultado[] = [
            'id' => $empId,
            'nombre' => $emp['nombre'],
            'email' => $emp['email'] ?? '',
            'sueldo_total' => $sueldoTotal,
            'pagado' => $pagado,
            'pendiente' => $pendiente,
        ];
    }

    return $resultado;
}

function presupuestoGastosAprobadosPendientes(PDO $pdo): array
{
    if (!function_exists('admin_table_exists')
        || !admin_table_exists($pdo, 'gastos')
        || !admin_table_exists($pdo, 'estados_gastos')
    ) {
        return [];
    }

    $joinTipo = admin_table_exists($pdo, 'tipos_gastos')
        ? 'LEFT JOIN tipos_gastos t ON t.id = g.tipo_gasto_id'
        : '';
    $tipoSelect = admin_table_exists($pdo, 'tipos_gastos')
        ? 't.nombre AS tipo_nombre'
        : 'NULL AS tipo_nombre';

    $sql = "
        SELECT g.id, g.numero_gasto, g.fecha, g.descripcion, g.monto, g.beneficiario,
               {$tipoSelect}
        FROM gastos g
        INNER JOIN estados_gastos e ON e.id = g.estado_gasto_id
        {$joinTipo}
        WHERE LOWER(e.nombre) = 'aprobado'
          AND COALESCE(g.monto, 0) > 0.009
        ORDER BY g.fecha ASC, g.id ASC
    ";

    try {
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('presupuestoGastosAprobadosPendientes: ' . $e->getMessage());
        return [];
    }
}

function presupuestoParseParesMonto(string $raw): array
{
    $pares = [];
    foreach (explode(',', $raw) as $parte) {
        $parte = trim($parte);
        if ($parte === '' || strpos($parte, ':') === false) {
            continue;
        }
        [$idRaw, $montoRaw] = explode(':', $parte, 2);
        $id = (int)$idRaw;
        $monto = (float)str_replace(',', '.', $montoRaw);
        if ($id > 0 && $monto > 0) {
            $pares[$id] = $monto;
        }
    }
    return $pares;
}

function presupuestoParseOtrosPagos(string $raw): array
{
    $items = [];
    foreach (explode('|', $raw) as $parte) {
        $parte = trim($parte);
        if ($parte === '' || strpos($parte, ':') === false) {
            continue;
        }
        [$desc, $montoRaw] = explode(':', $parte, 2);
        $monto = (float)str_replace(',', '.', $montoRaw);
        $desc = trim($desc);
        if ($desc !== '' && $monto > 0) {
            $items[] = ['descripcion' => $desc, 'monto' => $monto];
        }
    }
    return $items;
}
