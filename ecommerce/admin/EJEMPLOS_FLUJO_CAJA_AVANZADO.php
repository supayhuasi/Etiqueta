<?php
/**
 * EJEMPLOS DE USO AVANZADO - Flujo de Caja
 * 
 * Este archivo contiene ejemplos de cómo utilizar el módulo de flujo de caja
 * en diferentes escenarios y cómo integrarlo con otros sistemas.
 */

// ============================================
// EJEMPLO 1: Registrar ingreso automáticamente cuando se aprueba un pedido
// ============================================
/*
// En tu archivo de actualización de pedidos (ej: pedidos.php)

if ($_POST['accion'] === 'aprobar_pedido') {
    $pedido_id = intval($_POST['pedido_id']);
    
    // Actualizar estado del pedido
    $stmt = $pdo->prepare("UPDATE ecommerce_pedidos SET estado = 'aprobado' WHERE id = ?");
    $stmt->execute([$pedido_id]);
    
    // Obtener datos del pedido
    $stmt = $pdo->prepare("
        SELECT ep.numero_pedido, ep.monto_pagado, ep.total, ec.nombre
        FROM ecommerce_pedidos ep
        JOIN ecommerce_clientes ec ON ep.cliente_id = ec.id
        WHERE ep.id = ?
    ");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch();
    
    // Si tiene monto pagado, registrarlo en flujo de caja
    if ($pedido['monto_pagado'] > 0) {
        $stmt = $pdo->prepare("
            INSERT INTO flujo_caja 
            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
            VALUES (NOW(), 'ingreso', 'Pago Pedido', ?, 'Pedido ' . ?, ?, ?, 'Registrado automáticamente')
        ");
        $stmt->execute([
            'Pago pedido #' . $pedido['numero_pedido'] . ' - ' . $pedido['nombre'],
            $pedido['numero_pedido'],
            $pedido_id,
            $_SESSION['user_id'] ?? null
        ]);
    }
}
*/

// ============================================
// EJEMPLO 2: Integración con Mercado Pago
// ============================================
/*
// En tu archivo de notificaciones de Mercado Pago (ej: mp_success.php)

function registrarPagoMercadoPago($pago_data) {
    global $pdo;
    
    // Cuando Mercado Pago confirma un pago
    if ($pago_data['status'] === 'approved') {
        $stmt = $pdo->prepare("
            INSERT INTO flujo_caja 
            (fecha, tipo, categoria, descripcion, monto, referencia, usuario_id, observaciones)
            VALUES (NOW(), 'ingreso', 'Pago Pedido', ?, 'Mercado Pago', ?, null, 'ID MP: ' . ?)
        ");
        $stmt->execute([
            'Pago MP - Pedido #' . $pago_data['pedido_numero'],
            $pago_data['monto'],
            $pago_data['id']
        ]);
    }
}
*/

// ============================================
// EJEMPLO 3: Reporte de Flujo de Caja Automático (Email)
// ============================================
/*
function enviarReporteFlujoDelDia() {
    global $pdo;
    
    $hoy = date('Y-m-d');
    
    // Obtener transacciones de hoy
    $stmt = $pdo->prepare("
        SELECT 
            tipo,
            SUM(monto) as total
        FROM flujo_caja
        WHERE fecha = ?
        GROUP BY tipo
    ");
    $stmt->execute([$hoy]);
    $transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ingresos = 0;
    $egresos = 0;
    
    foreach ($transacciones as $trans) {
        if ($trans['tipo'] === 'ingreso') {
            $ingresos = $trans['total'];
        } else {
            $egresos = $trans['total'];
        }
    }
    
    $saldo = $ingresos - $egresos;
    
    $mensaje = "Reporte de Flujo de Caja - " . date('d/m/Y') . "\n";
    $mensaje .= "Ingresos: \$" . number_format($ingresos, 2) . "\n";
    $mensaje .= "Egresos: \$" . number_format($egresos, 2) . "\n";
    $mensaje .= "Saldo: \$" . number_format($saldo, 2) . "\n";
    
    // Enviar email
    mail("admin@empresa.com", "Reporte Diario Flujo de Caja", $mensaje);
}

// Ejecutar en cron job:
// 0 18 * * * php /ruta/al/proyecto/flujo_caja_reporte_diario.php
*/

// ============================================
// EJEMPLO 4: Consultar saldo en tiempo real
// ============================================
/*
function obtenerSaldoActual() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END), 0) as ingresos_total,
            COALESCE(SUM(CASE WHEN tipo='egreso' THEN monto ELSE 0 END), 0) as egresos_total
        FROM flujo_caja
    ");
    
    $resultado = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return [
        'ingresos' => $resultado['ingresos_total'],
        'egresos' => $resultado['egresos_total'],
        'saldo' => $resultado['ingresos_total'] - $resultado['egresos_total']
    ];
}

// Uso:
$saldo_actual = obtenerSaldoActual();
echo "Saldo: $" . number_format($saldo_actual['saldo'], 2);
*/

// ============================================
// EJEMPLO 5: Dashboard con Widget de Flujo de Caja
// ============================================
/*
// Agregar en dashboard.php

<?php
function mostrarWidgetFlujoCaja() {
    global $pdo;
    
    $mes_actual = date('Y-m');
    
    $stmt = $pdo->prepare("
        SELECT 
            tipo,
            SUM(monto) as total
        FROM flujo_caja
        WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
        GROUP BY tipo
    ");
    $stmt->execute([$mes_actual]);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ingresos = 0;
    $egresos = 0;
    
    foreach ($datos as $d) {
        if ($d['tipo'] === 'ingreso') {
            $ingresos = $d['total'];
        } else {
            $egresos = $d['total'];
        }
    }
    
    $saldo = $ingresos - $egresos;
    ?>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Flujo de Caja - <?= date('M/Y') ?></h5>
                <div class="mb-3">
                    <small class="text-muted">Ingresos</small>
                    <div class="h6 text-success">+$<?= number_format($ingresos, 2) ?></div>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Egresos</small>
                    <div class="h6 text-danger">-$<?= number_format($egresos, 2) ?></div>
                </div>
                <div class="border-top pt-3">
                    <small class="text-muted">Saldo Neto</small>
                    <div class="h5" style="color: <?= $saldo >= 0 ? '#28A745' : '#DC3545' ?>">
                        $<?= number_format($saldo, 2) ?>
                    </div>
                </div>
                <a href="flujo_caja.php" class="btn btn-sm btn-primary mt-3">Ver Detalle</a>
            </div>
        </div>
    </div>
    
    <?php
}

// Llamar en dashboard.php:
<?php mostrarWidgetFlujoCaja(); ?>
*/

// ============================================
// EJEMPLO 6: Generar Alerta de Saldo Bajo
// ============================================
/*
function verificarSaldoBajo($saldo_minimo = 10000) {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN tipo='egreso' THEN monto ELSE 0 END), 0)
            as saldo
        FROM flujo_caja
    ");
    
    $saldo = $stmt->fetch()['saldo'];
    
    if ($saldo < $saldo_minimo) {
        return [
            'alerta' => true,
            'saldo' => $saldo,
            'mensaje' => "⚠️ ALERTA: Saldo bajo. Saldo actual: $" . number_format($saldo, 2)
        ];
    }
    
    return ['alerta' => false];
}

// Uso:
$verificacion = verificarSaldoBajo(10000);
if ($verificacion['alerta']) {
    echo '<div class="alert alert-danger">' . $verificacion['mensaje'] . '</div>';
}
*/

// ============================================
// EJEMPLO 7: Proyección de Flujo Futuro
// ============================================
/*
function proyectarFlujoPorDia($dias = 30) {
    global $pdo;
    
    // Obtener promedio diario de últimos 90 días
    $stmt = $pdo->query("
        SELECT 
            tipo,
            AVG(monto_diario) as promedio
        FROM (
            SELECT 
                tipo,
                fecha,
                SUM(monto) as monto_diario
            FROM flujo_caja
            WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
            GROUP BY tipo, fecha
        ) as promedios
        GROUP BY tipo
    ");
    
    $promedios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $promedio_ingreso = 0;
    $promedio_egreso = 0;
    
    foreach ($promedios as $p) {
        if ($p['tipo'] === 'ingreso') {
            $promedio_ingreso = $p['promedio'];
        } else {
            $promedio_egreso = $p['promedio'];
        }
    }
    
    // Calcular saldo actual
    $stmt = $pdo->query("
        SELECT 
            COALESCE(SUM(CASE WHEN tipo='ingreso' THEN monto ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN tipo='egreso' THEN monto ELSE 0 END), 0)
            as saldo
        FROM flujo_caja
    ");
    
    $saldo_actual = $stmt->fetch()['saldo'];
    
    // Proyección
    $flujo_diario_neto = $promedio_ingreso - $promedio_egreso;
    $saldo_proyectado = $saldo_actual + ($flujo_diario_neto * $dias);
    
    return [
        'saldo_actual' => $saldo_actual,
        'flujo_diario_neto' => $flujo_diario_neto,
        'dias' => $dias,
        'saldo_proyectado' => $saldo_proyectado,
        'estado' => $saldo_proyectado > 0 ? 'positivo' : 'negativo'
    ];
}

// Uso:
$proyeccion = proyectarFlujoPorDia(30);
echo "Saldo en 30 días: $" . number_format($proyeccion['saldo_proyectado'], 2);
*/

// ============================================
// EJEMPLO 8: Validar Pago de Sueldo Antes de Guardar
// ============================================
/*
function validarPagoSueldo($empleado_id, $mes, $monto) {
    global $pdo;
    
    // Obtener datos del empleado
    $stmt = $pdo->prepare("SELECT sueldo_base FROM empleados WHERE id = ?");
    $stmt->execute([$empleado_id]);
    $empleado = $stmt->fetch();
    
    if (!$empleado) {
        return ['valido' => false, 'error' => 'Empleado no encontrado'];
    }
    
    // Obtener total pagado en este mes
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(monto_pagado), 0) as total_pagado
        FROM pagos_sueldos_parciales
        WHERE empleado_id = ? AND mes_pago = ?
    ");
    $stmt->execute([$empleado_id, $mes]);
    $resultado = $stmt->fetch();
    
    $total_pagado = $resultado['total_pagado'] + $monto;
    
    if ($total_pagado > $empleado['sueldo_base']) {
        return [
            'valido' => false,
            'error' => "El monto total ($" . number_format($total_pagado, 2) . ") supera el sueldo base ($" . number_format($empleado['sueldo_base'], 2) . ")"
        ];
    }
    
    return [
        'valido' => true,
        'sueldo_base' => $empleado['sueldo_base'],
        'total_pagado' => $total_pagado,
        'pendiente' => $empleado['sueldo_base'] - $total_pagado
    ];
}

// Uso:
$validacion = validarPagoSueldo(1, '2024-01', 30000);
if (!$validacion['valido']) {
    echo "Error: " . $validacion['error'];
} else {
    echo "Pendiente: $" . number_format($validacion['pendiente'], 2);
}
*/

// ============================================
// EJEMPLO 9: Exportar Flujo de Caja a CSV
// ============================================
/*
function exportarFlujoCajaCSV($fecha_inicio, $fecha_fin) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT *
        FROM flujo_caja
        WHERE fecha BETWEEN ? AND ?
        ORDER BY fecha DESC
    ");
    $stmt->execute([$fecha_inicio, $fecha_fin]);
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Crear archivo CSV
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="flujo_caja_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Encabezados
    fputcsv($output, ['Fecha', 'Tipo', 'Categoría', 'Descripción', 'Monto', 'Referencia']);
    
    // Datos
    foreach ($datos as $fila) {
        fputcsv($output, [
            $fila['fecha'],
            $fila['tipo'],
            $fila['categoria'],
            $fila['descripcion'],
            $fila['monto'],
            $fila['referencia']
        ]);
    }
    
    fclose($output);
}

// Uso:
// exportarFlujoCajaCSV('2024-01-01', '2024-01-31');
*/

// ============================================
// EJEMPLO 10: Query para Análisis Avanzado
// ============================================
/*
-- Resumen por categoría y tipo (ultimos 30 días)
SELECT 
    DATE(fecha) as fecha,
    tipo,
    categoria,
    COUNT(*) as cantidad,
    SUM(monto) as total,
    AVG(monto) as promedio
FROM flujo_caja
WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(fecha), tipo, categoria
ORDER BY fecha DESC, tipo DESC;

-- Saldo acumulado día a día
SELECT 
    fecha,
    SUM(CASE WHEN tipo='ingreso' THEN monto ELSE -monto END) 
        OVER (ORDER BY fecha) as saldo_acumulado
FROM flujo_caja
ORDER BY fecha DESC;

-- Empleados con pagos pendientes
SELECT 
    e.id,
    e.nombre,
    e.sueldo_base,
    psp.mes_pago,
    psp.sueldo_total,
    psp.sueldo_pendiente
FROM empleados e
LEFT JOIN pagos_sueldos_parciales psp ON e.id = psp.empleado_id
WHERE psp.sueldo_pendiente > 0
ORDER BY psp.mes_pago DESC;

-- Comparación mes a mes
SELECT 
    DATE_FORMAT(fecha, '%Y-%m') as mes,
    tipo,
    SUM(monto) as total
FROM flujo_caja
WHERE fecha >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(fecha, '%Y-%m'), tipo
ORDER BY mes DESC;
*/

?>

<!-- 
NOTAS IMPORTANTES:

1. Estos ejemplos están comentados para que puedas adaptarlos a tu proyecto

2. Cuando uses estos fragmentos de código:
   - Verifica que las variables $pdo y $_SESSION estén disponibles
   - Adapta los nombres de tablas/columnas a tu schema
   - Agrega validación y sanitización de inputs
   - Usa prepared statements (como se muestra)

3. Para integrar en tu proyecto:
   - Copia el código que necesites
   - Ponlo en los lugares apropiados (pedidos.php, dashboard.php, etc.)
   - Prueba con datos de prueba primero
   - Verifica que no haya conflictos con código existente

4. Para automatización:
   - Usa cron jobs para enviar reportes diarios
   - Integra con APIs externas si lo necesitas
   - Mantén logs de cambios importantes

5. Para seguridad:
   - Siempre valida inputs
   - Usa transacciones para operaciones críticas
   - Registra quién hizo cada cambio
   - Limita acceso según permisos

-->
