<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/simulacion_helper.php';

$errores = [];

try {
    ensureSimulacionSchema($pdo);
} catch (Throwable $e) {
    $errores[] = 'No se pudo preparar el módulo de simulación: ' . $e->getMessage();
}

function tabla_existe($pdo, $tabla) {
    // SHOW TABLES LIKE ? no se puede preparar como statement nativo en este servidor
    // (PDO::ATTR_EMULATE_PREPARES está desactivado en config.php); information_schema sí soporta placeholders.
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
    $stmt->execute([$tabla]);
    return $stmt->rowCount() > 0;
}

// Producto más vendido
$productos_mas_vendidos = [];
try {
    if (tabla_existe($pdo, 'ecommerce_pedido_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->query("
            SELECT 
                p.nombre,
                p.imagen,
                SUM(pi.cantidad) as total_vendido,
                SUM(pi.subtotal) as monto_total,
                COUNT(DISTINCT pi.pedido_id) as num_pedidos
            FROM ecommerce_pedido_items pi
            JOIN ecommerce_productos p ON pi.producto_id = p.id
            JOIN ecommerce_pedidos ped ON pi.pedido_id = ped.id
            WHERE ped.estado NOT IN ('cancelado')
            GROUP BY pi.producto_id, p.nombre, p.imagen
            ORDER BY total_vendido DESC
            LIMIT 5
        ");
        $productos_mas_vendidos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = "Productos más vendidos: " . $e->getMessage();
}

// Vendedor con más ventas (por cotizaciones convertidas)
$vendedores = [];
try {
    if (tabla_existe($pdo, 'ecommerce_cotizaciones') && tabla_existe($pdo, 'usuarios')) {
        $stmt = $pdo->query("
            SELECT 
                u.nombre as vendedor,
                COUNT(c.id) as total_cotizaciones,
                SUM(CASE WHEN c.estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
                SUM(CASE WHEN c.estado = 'convertida' THEN c.total ELSE 0 END) as monto_convertido,
                SUM(c.total) as monto_total_cotizaciones
            FROM ecommerce_cotizaciones c
            JOIN usuarios u ON c.creado_por = u.id
            GROUP BY c.creado_por, u.nombre
            ORDER BY cotizaciones_convertidas DESC, monto_convertido DESC
            LIMIT 5
        ");
        $vendedores = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = "Mejores vendedores: " . $e->getMessage();
}

// Cliente que más compró
$mejores_clientes = [];
try {
    if (tabla_existe($pdo, 'ecommerce_pedidos') && tabla_existe($pdo, 'ecommerce_clientes')) {
        $stmt = $pdo->query("
            SELECT 
                c.nombre,
                c.email,
                COUNT(p.id) as total_pedidos,
                SUM(p.total) as monto_total,
                MAX(p.fecha_pedido) as ultima_compra
            FROM ecommerce_pedidos p
            JOIN ecommerce_clientes c ON p.cliente_id = c.id
            WHERE p.estado NOT IN ('cancelado')
            GROUP BY p.cliente_id, c.nombre, c.email
            ORDER BY monto_total DESC
            LIMIT 5
        ");
        $mejores_clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = "Mejores clientes: " . $e->getMessage();
}

// Estadísticas generales
$stats_pedidos = ['total_pedidos' => 0, 'monto_total_pedidos' => 0, 'promedio_pedido' => 0];
$stats_cotizaciones = ['total_cotizaciones' => 0, 'cotizaciones_convertidas' => 0, 'monto_total_cotizaciones' => 0];
$stats_mes = ['pedidos_mes' => 0, 'monto_mes' => 0];

try {
    if (tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_pedidos,
                SUM(total) as monto_total_pedidos,
                AVG(total) as promedio_pedido
            FROM ecommerce_pedidos
            WHERE estado NOT IN ('cancelado')
        ");
        $stats_pedidos = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_pedidos;

        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as pedidos_mes,
                SUM(total) as monto_mes
            FROM ecommerce_pedidos
            WHERE MONTH(fecha_pedido) = MONTH(CURRENT_DATE())
            AND YEAR(fecha_pedido) = YEAR(CURRENT_DATE())
            AND estado NOT IN ('cancelado')
        ");
        $stats_mes = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_mes;
    }
} catch (Throwable $e) {
    $errores[] = "Estadísticas de pedidos: " . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_cotizaciones')) {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_cotizaciones,
                SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as cotizaciones_convertidas,
                SUM(total) as monto_total_cotizaciones
            FROM ecommerce_cotizaciones
        ");
        $stats_cotizaciones = $stmt->fetch(PDO::FETCH_ASSOC) ?: $stats_cotizaciones;
    }
} catch (Throwable $e) {
    $errores[] = "Estadísticas de cotizaciones: " . $e->getMessage();
}

$anio_seleccionado = (int)($_GET['anio'] ?? date('Y'));
if ($anio_seleccionado < 2000 || $anio_seleccionado > 2100) {
    $anio_seleccionado = (int)date('Y');
}

$mes_seleccionado = (int)($_GET['mes'] ?? 0);
if ($mes_seleccionado < 0 || $mes_seleccionado > 12) {
    $mes_seleccionado = 0;
}

if ($mes_seleccionado >= 1) {
    $periodo_inicio = new DateTime(sprintf('%04d-%02d-01', $anio_seleccionado, $mes_seleccionado));
    $periodo_fin = (clone $periodo_inicio)->modify('last day of this month')->setTime(23, 59, 59);
    $periodo_label = sprintf('%02d/%04d', $mes_seleccionado, $anio_seleccionado);
} else {
    $periodo_inicio = new DateTime(sprintf('%04d-01-01', $anio_seleccionado));
    $periodo_fin = new DateTime(sprintf('%04d-12-31 23:59:59', $anio_seleccionado));
    $periodo_label = (string)$anio_seleccionado;
}
$periodo_inicio_str = $periodo_inicio->format('Y-m-d H:i:s');
$periodo_fin_str = $periodo_fin->format('Y-m-d H:i:s');

$ventas_anio = 0.0;
$compras_anio = 0.0;
$gastos_anio = 0.0;
$sueldos_anio = 0.0;
$cobros_anio = 0.0;
$ingresos_periodo = 0.0;
$egresos_periodo = 0.0;
$neto_periodo = 0.0;
$pasivos_totales = 0.0;
$pasivos_gastos = 0.0;
$pasivos_sueldos = 0.0;
$pasivos_compras = 0.0;
$productos_ventas_ranking = [];
$productos_compra_ranking = [];
$proveedores_ranking = [];
$gastos_ranking = [];
$labels_meses = [];
$ventas_mensuales = [];
$compras_mensuales = [];
$gastos_mensuales = [];
$sueldos_mensuales = [];
$cobros_mensuales = [];
$egresos_mensuales = [];

try {
    if (tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $ventas_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Ventas del período: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(ci.subtotal),0) as total FROM ecommerce_compra_items ci JOIN ecommerce_compras c ON c.id = ci.compra_id WHERE c.fecha_compra BETWEEN ? AND ?");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $compras_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Compras del período: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'gastos')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM gastos WHERE fecha BETWEEN ? AND ?");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $gastos_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Gastos del período: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'pagos_sueldos')) {
        if ($mes_seleccionado >= 1) {
            $mes_key = sprintf('%04d-%02d', $anio_seleccionado, $mes_seleccionado);
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE mes_pago = ? OR fecha_pago BETWEEN ? AND ?");
            $stmt->execute([$mes_key, $periodo_inicio_str, $periodo_fin_str]);
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE DATE_FORMAT(fecha_pago, '%Y') = ? OR mes_pago LIKE ?");
            $stmt->execute([(string)$anio_seleccionado, $anio_seleccionado . '-%']);
        }
        $sueldos_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Sueldos del período: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_pedido_pagos')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM ecommerce_pedido_pagos WHERE fecha_pago BETWEEN ? AND ?");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $cobros_anio = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $errores[] = 'Cobros del período: ' . $e->getMessage();
}

$ingresos_periodo = $ventas_anio;
$egresos_periodo = $gastos_anio + $compras_anio + $sueldos_anio;
$neto_periodo = $ingresos_periodo - $egresos_periodo;

try {
    if (tabla_existe($pdo, 'gastos') && tabla_existe($pdo, 'estados_gastos')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(g.monto),0) as total FROM gastos g LEFT JOIN estados_gastos e ON e.id = g.estado_gasto_id WHERE (e.nombre IS NULL OR LOWER(e.nombre) <> 'pagado') AND g.fecha BETWEEN ? AND ?");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $pasivos_gastos = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_gastos = 0.0;
}

try {
    if (tabla_existe($pdo, 'pagos_sueldos')) {
        if ($mes_seleccionado >= 1) {
            $mes_key = sprintf('%04d-%02d', $anio_seleccionado, $mes_seleccionado);
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN sueldo_total > 0 THEN (sueldo_total - monto_pagado) ELSE 0 END),0) as total FROM pagos_sueldos WHERE mes_pago = ? OR fecha_pago BETWEEN ? AND ?");
            $stmt->execute([$mes_key, $periodo_inicio_str, $periodo_fin_str]);
        } else {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN sueldo_total > 0 THEN (sueldo_total - monto_pagado) ELSE 0 END),0) as total FROM pagos_sueldos WHERE DATE_FORMAT(fecha_pago, '%Y') = ? OR mes_pago LIKE ?");
            $stmt->execute([(string)$anio_seleccionado, $anio_seleccionado . '-%']);
        }
        $pasivos_sueldos = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_sueldos = 0.0;
}

try {
    if (tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_compras WHERE fecha_compra BETWEEN ? AND ? AND estado NOT IN ('cancelado', 'pagada', 'cerrada')");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $pasivos_compras = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }
} catch (Throwable $e) {
    $pasivos_compras = 0.0;
}

$pasivos_totales = $pasivos_gastos + $pasivos_sueldos + $pasivos_compras;

try {
    if (tabla_existe($pdo, 'ecommerce_pedido_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_pedidos')) {
        $stmt = $pdo->prepare("SELECT p.nombre, SUM(pi.cantidad) as cantidad, SUM(pi.subtotal) as total FROM ecommerce_pedido_items pi JOIN ecommerce_productos p ON p.id = pi.producto_id JOIN ecommerce_pedidos ped ON ped.id = pi.pedido_id WHERE ped.estado != 'cancelado' AND ped.fecha_pedido BETWEEN ? AND ? GROUP BY pi.producto_id, p.nombre ORDER BY cantidad DESC, total DESC LIMIT 5");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $productos_ventas_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking productos vendidos: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_productos') && tabla_existe($pdo, 'ecommerce_compras')) {
        $stmt = $pdo->prepare("SELECT p.nombre, SUM(ci.cantidad) as cantidad, SUM(ci.subtotal) as total FROM ecommerce_compra_items ci JOIN ecommerce_productos p ON p.id = ci.producto_id JOIN ecommerce_compras c ON c.id = ci.compra_id WHERE c.fecha_compra BETWEEN ? AND ? GROUP BY ci.producto_id, p.nombre ORDER BY cantidad DESC, total DESC LIMIT 5");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $productos_compra_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking productos comprados: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'ecommerce_compras') && tabla_existe($pdo, 'ecommerce_proveedores')) {
        $stmt = $pdo->prepare("SELECT pr.nombre, SUM(c.total) as total, COUNT(c.id) as compras FROM ecommerce_compras c JOIN ecommerce_proveedores pr ON pr.id = c.proveedor_id WHERE c.fecha_compra BETWEEN ? AND ? GROUP BY c.proveedor_id, pr.nombre ORDER BY total DESC LIMIT 5");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $proveedores_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking proveedores: ' . $e->getMessage();
}

try {
    if (tabla_existe($pdo, 'gastos') && tabla_existe($pdo, 'tipos_gastos')) {
        $stmt = $pdo->prepare("SELECT tg.nombre, SUM(g.monto) as total, COUNT(g.id) as cantidad FROM gastos g JOIN tipos_gastos tg ON tg.id = g.tipo_gasto_id WHERE g.fecha BETWEEN ? AND ? GROUP BY g.tipo_gasto_id, tg.nombre ORDER BY total DESC LIMIT 5");
        $stmt->execute([$periodo_inicio_str, $periodo_fin_str]);
        $gastos_ranking = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Throwable $e) {
    $errores[] = 'Ranking gastos: ' . $e->getMessage();
}

// Serie del gráfico comparativo: por mes si no hay mes elegido, por día si se eligió un mes puntual
// (esto también resuelve "ventas por día y cobros por día" cuando mes_seleccionado >= 1).
$tramos_periodo = [];
$meses_names = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
if ($mes_seleccionado === 0) {
    for ($i = 1; $i <= 12; $i++) {
        $tramo_inicio = new DateTime(sprintf('%04d-%02d-01', $anio_seleccionado, $i));
        $tramo_fin = (clone $tramo_inicio)->modify('last day of this month')->setTime(23, 59, 59);
        $tramos_periodo[] = [$meses_names[$i - 1], $tramo_inicio->format('Y-m-d H:i:s'), $tramo_fin->format('Y-m-d H:i:s')];
    }
} else {
    $dias_en_mes = (int)$periodo_inicio->format('t');
    for ($d = 1; $d <= $dias_en_mes; $d++) {
        $tramo_inicio = new DateTime(sprintf('%04d-%02d-%02d 00:00:00', $anio_seleccionado, $mes_seleccionado, $d));
        $tramo_fin = new DateTime(sprintf('%04d-%02d-%02d 23:59:59', $anio_seleccionado, $mes_seleccionado, $d));
        $tramos_periodo[] = [(string)$d, $tramo_inicio->format('Y-m-d H:i:s'), $tramo_fin->format('Y-m-d H:i:s')];
    }
}

foreach ($tramos_periodo as [$tramo_label, $tramo_ini, $tramo_fin]) {
    $labels_meses[] = $tramo_label;

    $v = 0.0;
    try {
        if (tabla_existe($pdo, 'ecommerce_pedidos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'");
            $stmt->execute([$tramo_ini, $tramo_fin]);
            $v = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $v = 0.0;
    }
    $ventas_mensuales[] = $v;

    $c = 0.0;
    try {
        if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_compras')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(ci.subtotal),0) as total FROM ecommerce_compra_items ci JOIN ecommerce_compras cc ON cc.id = ci.compra_id WHERE cc.fecha_compra BETWEEN ? AND ?");
            $stmt->execute([$tramo_ini, $tramo_fin]);
            $c = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $c = 0.0;
    }
    $compras_mensuales[] = $c;

    $g = 0.0;
    try {
        if (tabla_existe($pdo, 'gastos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM gastos WHERE fecha BETWEEN ? AND ?");
            $stmt->execute([$tramo_ini, $tramo_fin]);
            $g = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $g = 0.0;
    }
    $gastos_mensuales[] = $g;

    $s = 0.0;
    try {
        if (tabla_existe($pdo, 'pagos_sueldos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE fecha_pago BETWEEN ? AND ?");
            $stmt->execute([$tramo_ini, $tramo_fin]);
            $s = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $s = 0.0;
    }
    $sueldos_mensuales[] = $s;

    $co = 0.0;
    try {
        if (tabla_existe($pdo, 'ecommerce_pedido_pagos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM ecommerce_pedido_pagos WHERE fecha_pago BETWEEN ? AND ?");
            $stmt->execute([$tramo_ini, $tramo_fin]);
            $co = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $co = 0.0;
    }
    $cobros_mensuales[] = $co;

    $egresos_mensuales[] = $g + $c + $s;
}

// Punto de equilibrio: promedio de ingresos y egresos reales de los últimos 6 meses cerrados
$equilibrio_ingresos_prom = 0.0;
$equilibrio_egresos_prom = 0.0;
try {
    $suma_ing_eq = 0.0;
    $suma_egr_eq = 0.0;
    for ($k = 6; $k >= 1; $k--) {
        $eq_inicio = (new DateTime('first day of this month'))->modify("-{$k} months");
        $eq_fin = (clone $eq_inicio)->modify('last day of this month')->setTime(23, 59, 59);
        $eq_inicio_str = $eq_inicio->format('Y-m-d H:i:s');
        $eq_fin_str = $eq_fin->format('Y-m-d H:i:s');

        if (tabla_existe($pdo, 'ecommerce_pedidos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'");
            $stmt->execute([$eq_inicio_str, $eq_fin_str]);
            $suma_ing_eq += (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }

        if (tabla_existe($pdo, 'gastos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto),0) as total FROM gastos WHERE fecha BETWEEN ? AND ?");
            $stmt->execute([$eq_inicio_str, $eq_fin_str]);
            $suma_egr_eq += (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
        if (tabla_existe($pdo, 'ecommerce_compra_items') && tabla_existe($pdo, 'ecommerce_compras')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(ci.subtotal),0) as total FROM ecommerce_compra_items ci JOIN ecommerce_compras cc ON cc.id = ci.compra_id WHERE cc.fecha_compra BETWEEN ? AND ?");
            $stmt->execute([$eq_inicio_str, $eq_fin_str]);
            $suma_egr_eq += (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
        if (tabla_existe($pdo, 'pagos_sueldos')) {
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(monto_pagado),0) as total FROM pagos_sueldos WHERE fecha_pago BETWEEN ? AND ?");
            $stmt->execute([$eq_inicio_str, $eq_fin_str]);
            $suma_egr_eq += (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    }
    $equilibrio_ingresos_prom = $suma_ing_eq / 6;
    $equilibrio_egresos_prom = $suma_egr_eq / 6;
} catch (Throwable $e) {
    $errores[] = 'Punto de equilibrio: ' . $e->getMessage();
}

$punto_equilibrio_mensual = $equilibrio_egresos_prom;
$venta_diaria_promedio = $equilibrio_ingresos_prom > 0 ? ($equilibrio_ingresos_prom / 30) : 0;

if ($mes_seleccionado >= 1) {
    $ventas_mes_ref = $ventas_anio;
    $mes_ref_label = $periodo_label;
} else {
    $mes_ref_label = date('m/Y');
    $ventas_mes_ref = 0.0;
    try {
        if (tabla_existe($pdo, 'ecommerce_pedidos')) {
            $mr_inicio = (new DateTime('first day of this month'))->format('Y-m-d H:i:s');
            $mr_fin = (new DateTime('last day of this month'))->setTime(23, 59, 59)->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT COALESCE(SUM(total),0) as total FROM ecommerce_pedidos WHERE fecha_pedido BETWEEN ? AND ? AND estado != 'cancelado'");
            $stmt->execute([$mr_inicio, $mr_fin]);
            $ventas_mes_ref = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        }
    } catch (Throwable $e) {
        $ventas_mes_ref = 0.0;
    }
}

$pct_equilibrio = $punto_equilibrio_mensual > 0 ? (($ventas_mes_ref / $punto_equilibrio_mensual) * 100) : 0;
$dias_para_equilibrio = ($venta_diaria_promedio > 0 && $punto_equilibrio_mensual > 0)
    ? (int)ceil($punto_equilibrio_mensual / $venta_diaria_promedio)
    : null;

// Gastos simulados (proyección a futuro) y proyección de flujo a 6 meses
$simulados = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM dashboard_gastos_simulados WHERE fecha >= CURDATE() OR recurrente_mensual = 1 ORDER BY fecha ASC");
    $stmt->execute();
    $simulados = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $errores[] = 'Simulación de gastos: ' . $e->getMessage();
}

$proyeccion_labels = [];
$proyeccion_ingresos = [];
$proyeccion_egresos = [];
$proyeccion_saldo = [];
$saldo_acumulado_proyectado = $neto_periodo;
for ($p = 1; $p <= 6; $p++) {
    $pm_inicio = (new DateTime('first day of this month'))->modify("+{$p} months");
    $pm_fin = (clone $pm_inicio)->modify('last day of this month')->setTime(23, 59, 59);
    $proyeccion_labels[] = $meses_names[((int)$pm_inicio->format('n')) - 1] . ' ' . $pm_inicio->format('Y');

    $egreso_simulado_mes = 0.0;
    foreach ($simulados as $sim) {
        $sim_fecha = new DateTime($sim['fecha']);
        $es_recurrente = !empty($sim['recurrente_mensual']);
        $recurrente_hasta = !empty($sim['recurrente_hasta']) ? new DateTime($sim['recurrente_hasta']) : null;

        if ($es_recurrente) {
            if ($sim_fecha <= $pm_fin && (!$recurrente_hasta || $recurrente_hasta >= $pm_inicio)) {
                $egreso_simulado_mes += (float)$sim['monto'];
            }
        } elseif ($sim_fecha->format('Y-m') === $pm_inicio->format('Y-m')) {
            $egreso_simulado_mes += (float)$sim['monto'];
        }
    }

    $ing_proy = $equilibrio_ingresos_prom;
    $egr_proy = $equilibrio_egresos_prom + $egreso_simulado_mes;
    $proyeccion_ingresos[] = round($ing_proy, 2);
    $proyeccion_egresos[] = round($egr_proy, 2);
    $saldo_acumulado_proyectado += ($ing_proy - $egr_proy);
    $proyeccion_saldo[] = round($saldo_acumulado_proyectado, 2);
}
?>

<div class="row mb-4">
    <div class="col-md-12">
        <h1>📊 Panel de Control</h1>
        <p class="text-muted">Métricas operativas, compras, proveedores, gastos, sueldos, pasivos e ingresos para decisiones</p>
        <?php if (!empty($errores)): ?>
            <div class="alert alert-warning mt-3">
                <strong>Algunas métricas no pudieron cargarse:</strong>
                <ul class="mb-0">
                    <?php foreach ($errores as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $es_operario_dashboard = (strtolower((string)($role ?? '')) === 'operario'); ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Año</label>
                <select name="anio" class="form-select" onchange="this.form.submit()">
                    <?php for ($y = date('Y') - 3; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= $y == $anio_seleccionado ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Mes</label>
                <select name="mes" class="form-select" onchange="this.form.submit()">
                    <option value="0" <?= $mes_seleccionado === 0 ? 'selected' : '' ?>>Todos los meses</option>
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $mes_seleccionado ? 'selected' : '' ?>><?= $meses_names[$m - 1] ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-6 text-end">
                <div class="small text-muted">
                    <?= $mes_seleccionado === 0
                        ? 'Comparativa mensual del año ' . $anio_seleccionado . ': ingresos, egresos y cobros'
                        : 'Comparativa diaria de ' . htmlspecialchars($periodo_label) . ': ventas, cobros y egresos' ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Estadísticas generales -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h6 class="mb-2">📦 Total Pedidos</h6>
                <h3 class="mb-0"><?= number_format($stats_pedidos['total_pedidos']) ?></h3>
                <?php if ($es_operario_dashboard): ?>
                    <small class="text-white-50">Monto oculto</small>
                <?php else: ?>
                    <small>Monto: $<?= number_format($stats_pedidos['monto_total_pedidos'], 2) ?></small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <h6 class="mb-2">💰 Ventas del Mes</h6>
                <?php if ($es_operario_dashboard): ?>
                    <h3 class="mb-0">--</h3>
                    <small>Importe oculto</small>
                <?php else: ?>
                    <h3 class="mb-0">$<?= number_format($stats_mes['monto_mes'], 2) ?></h3>
                    <small><?= $stats_mes['pedidos_mes'] ?> pedidos</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-info text-white">
            <div class="card-body">
                <h6 class="mb-2">📋 Cotizaciones</h6>
                <h3 class="mb-0"><?= number_format($stats_cotizaciones['total_cotizaciones']) ?></h3>
                <small><?= $stats_cotizaciones['cotizaciones_convertidas'] ?> convertidas</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card bg-warning text-dark">
            <div class="card-body">
                <h6 class="mb-2">📊 Ticket Promedio</h6>
                <?php if ($es_operario_dashboard): ?>
                    <h3 class="mb-0">--</h3>
                    <small>Oculto</small>
                <?php else: ?>
                    <h3 class="mb-0">$<?= number_format($stats_pedidos['promedio_pedido'], 2) ?></h3>
                    <small>por pedido</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-primary">
            <div class="card-body text-center">
                <h6 class="text-primary">Ingresos <?= htmlspecialchars($periodo_label) ?></h6>
                <h3>$<?= number_format($ventas_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-info">
            <div class="card-body text-center">
                <h6 class="text-info">Cobros <?= htmlspecialchars($periodo_label) ?></h6>
                <h3>$<?= number_format($cobros_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-success">
            <div class="card-body text-center">
                <h6 class="text-success">Compras <?= htmlspecialchars($periodo_label) ?></h6>
                <h3>$<?= number_format($compras_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-danger">Gastos <?= htmlspecialchars($periodo_label) ?></h6>
                <h3>$<?= number_format($gastos_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-secondary">
            <div class="card-body text-center">
                <h6 class="text-secondary">Sueldos <?= htmlspecialchars($periodo_label) ?></h6>
                <h3>$<?= number_format($sueldos_anio, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-dark">
            <div class="card-body text-center">
                <h6 class="text-dark">Egresos totales</h6>
                <h3>$<?= number_format($egresos_periodo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-warning">
            <div class="card-body text-center">
                <h6 class="text-warning">Neto acumulado</h6>
                <h3>$<?= number_format($neto_periodo, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-danger">
            <div class="card-body text-center">
                <h6 class="text-danger">Pasivos totales</h6>
                <h3>$<?= number_format($pasivos_totales, 2, ',', '.') ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🏆 Productos Más Vendidos</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_ventas_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($productos_ventas_ranking as $i => $prod): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-primary rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((float)$prod['cantidad'], 0, ',', '.') ?> unidades<br>
                                            Monto: $<?= number_format((float)$prod['total'], 2, ',', '.') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">📦 Productos Comprados</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_compra_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($productos_compra_ranking as $i => $prod): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-success rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prod['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((float)$prod['cantidad'], 0, ',', '.') ?> unidades<br>
                                            Costo: $<?= number_format((float)$prod['total'], 2, ',', '.') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">🏭 Proveedores</h5>
            </div>
            <div class="card-body">
                <?php if (empty($proveedores_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($proveedores_ranking as $i => $prov): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-info rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($prov['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((int)$prov['compras'], 0, ',', '.') ?> compras<br>
                                            Total: $<?= number_format((float)$prov['total'], 2, ',', '.') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">💸 Gastos por categoría</h5>
            </div>
            <div class="card-body">
                <?php if (empty($gastos_ranking)): ?>
                    <p class="text-muted">No hay datos disponibles</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($gastos_ranking as $i => $gasto): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex align-items-center">
                                    <div class="me-3">
                                        <span class="badge bg-warning rounded-circle" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                                            <?= $i + 1 ?>
                                        </span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($gasto['nombre']) ?></h6>
                                        <small class="text-muted">
                                            <?= number_format((int)$gasto['cantidad'], 0, ',', '.') ?> movimientos<br>
                                            Total: $<?= number_format((float)$gasto['total'], 2, ',', '.') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">📈 <?= $mes_seleccionado === 0 ? 'Comparativa mensual ' . htmlspecialchars($periodo_label) . ': ingresos, cobros y egresos' : 'Ventas y cobros por día — ' . htmlspecialchars($periodo_label) ?></h5>
            </div>
            <div class="card-body">
                <canvas id="panelComparativoMensual" height="120"></canvas>

                <?php if ($mes_seleccionado >= 1): ?>
                <div class="table-responsive mt-4">
                    <table class="table table-sm table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Día</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Cobros</th>
                                <th class="text-end">Gastos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($labels_meses as $idx => $dia_label): ?>
                                <tr>
                                    <td><?= htmlspecialchars($dia_label) ?></td>
                                    <td class="text-end">$<?= number_format($ventas_mensuales[$idx] ?? 0, 2, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($cobros_mensuales[$idx] ?? 0, 2, ',', '.') ?></td>
                                    <td class="text-end">$<?= number_format($gastos_mensuales[$idx] ?? 0, 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0">🎨 Armá tu gráfico</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-5">
                        <label class="form-label">Métricas</label>
                        <div class="d-flex flex-wrap gap-3">
                            <?php
                            $metricas_disponibles = [
                                'ventas' => 'Ventas',
                                'cobros' => 'Cobros',
                                'compras' => 'Compras',
                                'gastos' => 'Gastos',
                                'sueldos' => 'Sueldos',
                                'pedidos' => 'Pedidos (cant.)',
                                'cotizaciones' => 'Cotizaciones (cant.)',
                            ];
                            foreach ($metricas_disponibles as $mk => $ml):
                            ?>
                            <div class="form-check">
                                <input class="form-check-input chart-builder-metric" type="checkbox" value="<?= $mk ?>" id="metric_<?= $mk ?>" <?= in_array($mk, ['ventas', 'gastos'], true) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="metric_<?= $mk ?>"><?= $ml ?></label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Granularidad</label>
                        <select id="chartBuilderGranularidad" class="form-select">
                            <option value="dia">Diario</option>
                            <option value="mes">Mensual</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Desde</label>
                        <input type="date" id="chartBuilderDesde" class="form-control" value="<?= (new DateTime('first day of this month'))->format('Y-m-d') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Hasta</label>
                        <input type="date" id="chartBuilderHasta" class="form-control" value="<?= (new DateTime('last day of this month'))->format('Y-m-d') ?>">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Tipo</label>
                        <select id="chartBuilderTipo" class="form-select">
                            <option value="line">Línea</option>
                            <option value="bar">Barra</option>
                            <option value="pie">Torta</option>
                        </select>
                    </div>
                </div>
                <button type="button" class="btn btn-primary" id="chartBuilderGenerar">Generar gráfico</button>
                <div id="chartBuilderMsg" class="text-danger small mt-2"></div>
                <div class="mt-3">
                    <canvas id="chartBuilderCanvas" height="120"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0">⚖️ Punto de equilibrio</h5>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Calculado como el promedio de egresos mensuales (gastos + compras + sueldos) de los últimos 6 meses reales.
                    Es el monto de ventas que necesitás alcanzar cada mes para cubrir tus costos históricos
                    (no incluye costo por unidad de producto, ya que no está cargado en el sistema).
                </p>
                <div class="row text-center g-3">
                    <div class="col-md-3">
                        <h6 class="text-warning">Punto de equilibrio mensual</h6>
                        <h4>$<?= number_format($punto_equilibrio_mensual, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-primary">Ventas <?= htmlspecialchars($mes_ref_label) ?></h6>
                        <h4>$<?= number_format($ventas_mes_ref, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-<?= $pct_equilibrio >= 100 ? 'success' : 'danger' ?>">% alcanzado</h6>
                        <h4><?= number_format($pct_equilibrio, 1, ',', '.') ?>%</h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-secondary">Día estimado de equilibrio</h6>
                        <h4><?= $dias_para_equilibrio !== null ? ('Día ~' . $dias_para_equilibrio) : '—' ?></h4>
                    </div>
                </div>
                <div class="progress mt-3" style="height: 22px;">
                    <div class="progress-bar <?= $pct_equilibrio >= 100 ? 'bg-success' : 'bg-warning' ?>" role="progressbar" style="width: <?= min(100, max(0, $pct_equilibrio)) ?>%;">
                        <?= number_format(min(100, max(0, $pct_equilibrio)), 0) ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-primary">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🔮 Simulación de gastos futuros</h5>
            </div>
            <div class="card-body">
                <?php if (($_GET['sim_ok'] ?? '') === '1'): ?>
                    <div class="alert alert-success py-2">Gasto simulado agregado.</div>
                <?php elseif (($_GET['sim_error'] ?? '') === '1'): ?>
                    <div class="alert alert-danger py-2">No se pudo agregar el gasto simulado. Revisá los datos (la fecha debe ser hoy o futura).</div>
                <?php endif; ?>

                <form method="POST" action="dashboard_simulacion_guardar.php" class="row g-2 align-items-end mb-4">
                    <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                    <div class="col-md-3">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control" required maxlength="255">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Monto</label>
                        <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="recurrente_mensual" value="1" id="simRecurrente">
                            <label class="form-check-label" for="simRecurrente">Repetir cada mes</label>
                        </div>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Agregar</button>
                    </div>
                </form>

                <?php if (empty($simulados)): ?>
                    <p class="text-muted">No hay gastos simulados cargados.</p>
                <?php else: ?>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Monto</th>
                                    <th>Recurrente</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($simulados as $sim): ?>
                                <tr>
                                    <td><?= htmlspecialchars((new DateTime($sim['fecha']))->format('d/m/Y')) ?></td>
                                    <td><?= htmlspecialchars($sim['descripcion']) ?></td>
                                    <td class="text-end">$<?= number_format((float)$sim['monto'], 2, ',', '.') ?></td>
                                    <td><?= !empty($sim['recurrente_mensual']) ? 'Sí' : 'No' ?></td>
                                    <td class="text-end">
                                        <form method="POST" action="dashboard_simulacion_eliminar.php" onsubmit="return confirm('¿Eliminar esta simulación?');">
                                            <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                                            <input type="hidden" name="id" value="<?= (int)$sim['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <p class="text-muted small mb-2">Proyección estimada en base al promedio de los últimos 6 meses reales + gastos simulados cargados.</p>
                <canvas id="panelProyeccion" height="100"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">💳 Sector de Finanzas y Pasivos</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h6 class="text-danger">Pasivos totales</h6>
                        <h4>$<?= number_format($pasivos_totales, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-warning">Gastos pendientes</h6>
                        <h4>$<?= number_format($pasivos_gastos, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-secondary">Sueldos pendientes</h6>
                        <h4>$<?= number_format($pasivos_sueldos, 2, ',', '.') ?></h4>
                    </div>
                    <div class="col-md-3">
                        <h6 class="text-success">Compras pendientes</h6>
                        <h4>$<?= number_format($pasivos_compras, 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0">📈 Resumen de Conversión</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <h3 class="text-primary"><?= number_format($stats_cotizaciones['total_cotizaciones']) ?></h3>
                        <p class="text-muted mb-0">Total Cotizaciones</p>
                    </div>
                    <div class="col-md-4">
                        <h3 class="text-success"><?= number_format($stats_cotizaciones['cotizaciones_convertidas']) ?></h3>
                        <p class="text-muted mb-0">Cotizaciones Convertidas</p>
                    </div>
                    <div class="col-md-4">
                        <?php 
                        $tasa_conversion = $stats_cotizaciones['total_cotizaciones'] > 0 
                            ? ($stats_cotizaciones['cotizaciones_convertidas'] / $stats_cotizaciones['total_cotizaciones']) * 100 
                            : 0;
                        ?>
                        <h3 class="text-warning"><?= number_format($tasa_conversion, 1) ?>%</h3>
                        <p class="text-muted mb-0">Tasa de Conversión</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const panelLabels = <?= json_encode($labels_meses) ?>;
const panelVentas = <?= json_encode($ventas_mensuales) ?>;
const panelCobros = <?= json_encode($cobros_mensuales) ?>;
const panelCompras = <?= json_encode($compras_mensuales) ?>;
const panelGastos = <?= json_encode($gastos_mensuales) ?>;
const panelSueldos = <?= json_encode($sueldos_mensuales) ?>;
const panelEgresos = <?= json_encode($egresos_mensuales) ?>;

new Chart(document.getElementById('panelComparativoMensual'), {
    type: 'line',
    data: {
        labels: panelLabels,
        datasets: [
            { label: 'Ventas', data: panelVentas, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.12)', tension: 0.3, fill: false },
            { label: 'Cobros', data: panelCobros, borderColor: '#20c997', backgroundColor: 'rgba(32, 201, 151, 0.12)', tension: 0.3, fill: false },
            { label: 'Egresos (gastos + sueldo + compras)', data: panelEgresos, borderColor: '#dc3545', backgroundColor: 'rgba(220, 53, 69, 0.12)', tension: 0.3, fill: false },
            { label: 'Compras', data: panelCompras, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.12)', tension: 0.3, fill: false, hidden: true },
            { label: 'Gastos', data: panelGastos, borderColor: '#fd7e14', backgroundColor: 'rgba(253, 126, 20, 0.12)', tension: 0.3, fill: false, hidden: true },
            { label: 'Sueldos', data: panelSueldos, borderColor: '#6c757d', backgroundColor: 'rgba(108, 117, 125, 0.12)', tension: 0.3, fill: false, hidden: true }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: false } }
    }
});

// Proyección de flujo a 6 meses (simulación de gastos futuros)
new Chart(document.getElementById('panelProyeccion'), {
    type: 'line',
    data: {
        labels: <?= json_encode($proyeccion_labels) ?>,
        datasets: [
            { label: 'Ingresos proyectados (estimado)', data: <?= json_encode($proyeccion_ingresos) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13, 110, 253, 0.12)', tension: 0.3, fill: false },
            { label: 'Egresos proyectados (estimado)', data: <?= json_encode($proyeccion_egresos) ?>, borderColor: '#dc3545', backgroundColor: 'rgba(220, 53, 69, 0.12)', tension: 0.3, fill: false },
            { label: 'Saldo acumulado proyectado', data: <?= json_encode($proyeccion_saldo) ?>, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.12)', tension: 0.3, fill: false, borderDash: [6, 6] }
        ]
    },
    options: {
        responsive: true,
        scales: { y: { beginAtZero: false } }
    }
});

// Constructor de gráficos personalizado
let chartBuilderInstance = null;
document.getElementById('chartBuilderGenerar').addEventListener('click', function () {
    const msgEl = document.getElementById('chartBuilderMsg');
    msgEl.textContent = '';

    const metrics = Array.from(document.querySelectorAll('.chart-builder-metric:checked')).map(el => el.value);
    if (metrics.length === 0) {
        msgEl.textContent = 'Elegí al menos una métrica.';
        return;
    }

    const granularidad = document.getElementById('chartBuilderGranularidad').value;
    const desde = document.getElementById('chartBuilderDesde').value;
    const hasta = document.getElementById('chartBuilderHasta').value;
    const tipo = document.getElementById('chartBuilderTipo').value;

    if (!desde || !hasta) {
        msgEl.textContent = 'Elegí un rango de fechas.';
        return;
    }

    const params = new URLSearchParams();
    metrics.forEach(m => params.append('metrics[]', m));
    params.append('granularidad', granularidad);
    params.append('desde', desde);
    params.append('hasta', hasta);

    fetch('dashboard_chart_data.php?' + params.toString(), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) {
                msgEl.textContent = data.msg || 'No se pudo generar el gráfico.';
                return;
            }

            const colores = ['#0d6efd', '#20c997', '#198754', '#dc3545', '#fd7e14', '#6c757d', '#6610f2'];
            const metricLabels = {
                ventas: 'Ventas', cobros: 'Cobros', compras: 'Compras', gastos: 'Gastos',
                sueldos: 'Sueldos', pedidos: 'Pedidos', cotizaciones: 'Cotizaciones'
            };

            let datasets;
            if (tipo === 'pie') {
                datasets = [{
                    data: metrics.map(m => (data.series[m] || []).reduce((a, b) => a + b, 0)),
                    backgroundColor: metrics.map((m, i) => colores[i % colores.length])
                }];
            } else {
                datasets = metrics.map((m, i) => ({
                    label: metricLabels[m] || m,
                    data: data.series[m] || [],
                    borderColor: colores[i % colores.length],
                    backgroundColor: colores[i % colores.length] + '33',
                    tension: 0.3,
                    fill: false
                }));
            }

            if (chartBuilderInstance) {
                chartBuilderInstance.destroy();
            }

            chartBuilderInstance = new Chart(document.getElementById('chartBuilderCanvas'), {
                type: tipo,
                data: {
                    labels: tipo === 'pie' ? metrics.map(m => metricLabels[m] || m) : data.labels,
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    scales: tipo === 'pie' ? {} : { y: { beginAtZero: true } }
                }
            });
        })
        .catch(() => {
            msgEl.textContent = 'Error al generar el gráfico.';
        });
});
</script>

<?php require 'includes/footer.php'; ?>
