<?php
/**
 * Script para importar transacciones existentes a flujo de caja
 * Sincroniza datos de otros módulos automáticamente
 */

require '../../config.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

// Incluir header AQUÍ, antes de enviar HTML
require 'includes/header.php';

$error = '';
$exito = '';
$resumen = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['importar'])) {
    try {
        $pdo->beginTransaction();

        // 1. Importar pagos aprobados de gastos
        if (isset($_POST['importar_gastos'])) {
            $stmt = $pdo->prepare("
                SELECT g.id, g.numero_gasto, g.fecha, g.descripcion, g.monto, tg.nombre as tipo_gasto
                FROM gastos g
                LEFT JOIN tipos_gastos tg ON g.tipo_gasto_id = tg.id
                WHERE g.estado_gasto_id = (SELECT id FROM estados_gastos WHERE nombre = 'Aprobado')
                AND NOT EXISTS (
                    SELECT 1 FROM flujo_caja WHERE id_referencia = g.id AND categoria = 'Gasto'
                )
            ");
            $stmt->execute();
            $gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($gastos as $gasto) {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO flujo_caja 
                    (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                    VALUES (?, 'egreso', ?, ?, ?, ?, ?, ?, 'Importado de gastos')
                ");
                $stmt_insert->execute([
                    $gasto['fecha'],
                    $gasto['tipo_gasto'] ?? 'Gasto',
                    $gasto['descripcion'],
                    $gasto['monto'],
                    'Gasto ' . $gasto['numero_gasto'],
                    $gasto['id'],
                    $_SESSION['user']['id']
                ]);
            }

            $resumen['gastos'] = count($gastos);
        }

        // 2. Importar pagos de compras (si existe tabla)
        if (isset($_POST['importar_compras'])) {
            try {
                $stmt = $pdo->prepare("
                    SELECT c.id, c.numero_compra, c.fecha, c.proveedor, c.total
                    FROM compras c
                    WHERE c.estado = 'pagada'
                    AND NOT EXISTS (
                        SELECT 1 FROM flujo_caja WHERE id_referencia = c.id AND categoria = 'Compra'
                    )
                ");
                $stmt->execute();
                $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($compras as $compra) {
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO flujo_caja 
                        (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                        VALUES (?, 'egreso', 'Compra', ?, ?, ?, ?, 'Importado de compras')
                    ");
                    $stmt_insert->execute([
                        $compra['fecha'],
                        'Compra a ' . $compra['proveedor'],
                        $compra['total'],
                        'Compra ' . $compra['numero_compra'],
                        $compra['id'],
                        $_SESSION['user']['id']
                    ]);
                }

                $resumen['compras'] = count($compras);
            } catch (Exception $e) {
                // Tabla de compras no existe
                $resumen['compras'] = 0;
            }
        }

        // 3. Importar pagos de pedidos
        if (isset($_POST['importar_pedidos'])) {
            $stmt = $pdo->prepare("
                SELECT ep.id, ep.numero_pedido, ep.fecha_creacion, ep.monto_pagado, ec.nombre as cliente
                FROM ecommerce_pedidos ep
                LEFT JOIN ecommerce_clientes ec ON ep.cliente_id = ec.id
                WHERE ep.monto_pagado > 0
                AND ep.monto_pagado > 0
                AND NOT EXISTS (
                    SELECT 1 FROM flujo_caja WHERE id_referencia = ep.id AND categoria = 'Pago Pedido'
                )
            ");
            $stmt->execute();
            $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pedidos as $pedido) {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO flujo_caja 
                    (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                    VALUES (?, 'ingreso', 'Pago Pedido', ?, ?, ?, ?, 'Importado de pedidos')
                ");
                $stmt_insert->execute([
                    $pedido['fecha_creacion'],
                    'Pago pedido #' . $pedido['numero_pedido'] . ' - ' . $pedido['cliente'],
                    $pedido['monto_pagado'],
                    'Pedido ' . $pedido['numero_pedido'],
                    $pedido['id'],
                    $_SESSION['user']['id']
                ]);
            }

            $resumen['pedidos'] = count($pedidos);
        }

        // 4. Importar pagos de sueldos (que no sean parciales)
        if (isset($_POST['importar_sueldos'])) {
            try {
                    // Normalizar importaciones viejas: usar el id del pago de sueldos como referencia
                    $stmt_fix_ref = $pdo->prepare("
                        UPDATE flujo_caja fc
                        JOIN pagos_sueldos ps
                          ON fc.categoria = 'Pago de Sueldo'
                         AND fc.observaciones = 'Importado de sueldos'
                         AND fc.referencia = CONCAT('Sueldo ', ps.mes_pago)
                        SET fc.id_referencia = ps.id
                        WHERE fc.id_referencia = ps.empleado_id
                          AND fc.id_referencia <> ps.id
                    ");
                    $stmt_fix_ref->execute();

                                        $stmt_fix_tipo = $pdo->prepare("
                                                UPDATE flujo_caja
                                                SET tipo = 'egreso'
                                                WHERE categoria = 'Pago de Sueldo'
                                                    AND observaciones = 'Importado de sueldos'
                                                    AND tipo = 'ingreso'
                                        ");
                                        $stmt_fix_tipo->execute();
                    
                    $stmt_clean_dups = $pdo->prepare("
                        DELETE fc_dup
                        FROM flujo_caja fc_dup
                        JOIN flujo_caja fc_keep
                          ON fc_dup.id > fc_keep.id
                         AND fc_dup.tipo = fc_keep.tipo
                         AND fc_dup.categoria = fc_keep.categoria
                         AND fc_dup.fecha = fc_keep.fecha
                         AND fc_dup.monto = fc_keep.monto
                         AND COALESCE(fc_dup.referencia, '') = COALESCE(fc_keep.referencia, '')
                         AND COALESCE(fc_dup.descripcion, '') = COALESCE(fc_keep.descripcion, '')
                         AND COALESCE(fc_dup.id_referencia, 0) = COALESCE(fc_keep.id_referencia, 0)
                        WHERE fc_dup.categoria = 'Pago de Sueldo'
                          AND fc_dup.observaciones = 'Importado de sueldos'
                          AND fc_keep.observaciones = 'Importado de sueldos'
                    ");
                    $stmt_clean_dups->execute();

                $stmt = $pdo->prepare("
                    SELECT ps.id, ps.empleado_id, ps.mes_pago, ps.monto_pagado, e.nombre
                    FROM pagos_sueldos ps
                    JOIN empleados e ON ps.empleado_id = e.id
                    WHERE ps.monto_pagado > 0
                    AND NOT EXISTS (
                        SELECT 1 FROM flujo_caja WHERE id_referencia = ps.id AND categoria = 'Pago de Sueldo'
                    )
                ");
                $stmt->execute();
                $sueldos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($sueldos as $sueldo) {
                    $stmt_insert = $pdo->prepare("
                        INSERT INTO flujo_caja 
                        (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                        VALUES (?, 'egreso', 'Pago de Sueldo', ?, ?, ?, ?, 'Importado de sueldos')
                    ");
                    $stmt_insert->execute([
                        date('Y-m-01', strtotime($sueldo['mes_pago'] . '-01')),
                        $sueldo['nombre'] . ' - ' . $sueldo['mes_pago'],
                        $sueldo['monto_pagado'],
                        'Sueldo ' . $sueldo['mes_pago'],
                        $sueldo['id'],
                        $_SESSION['user']['id']
                    ]);
                }

                $resumen['sueldos'] = count($sueldos);
            } catch (Exception $e) {
                $resumen['sueldos'] = 0;
            }
        }

        $pdo->commit();
        
        $exito = 'Importación completada exitosamente. ';
        foreach ($resumen as $tipo => $cantidad) {
            if ($cantidad > 0) {
                $exito .= "$cantidad $tipo importados. ";
            }
        }

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error durante la importación: ' . $e->getMessage();
    }
}

// Contar registros disponibles
$stats = [];

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM gastos WHERE estado_gasto_id = (SELECT id FROM estados_gastos WHERE nombre = 'Aprobado')");
    $stats['gastos'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['gastos'] = 0;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM compras WHERE estado = 'pagada'");
    $stats['compras'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['compras'] = 0;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_pedidos WHERE monto_pagado > 0");
    $stats['pedidos'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['pedidos'] = 0;
}

try {
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM pagos_sueldos WHERE monto_pagado > 0");
    $stats['sueldos'] = $stmt->fetch()['total'];
} catch (Exception $e) {
    $stats['sueldos'] = 0;
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Importar Transacciones</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>📥 Importar Transacciones</h1>
            <a href="flujo_caja.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
// Verificar autenticación
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Éxito:</strong> <?= htmlspecialchars($exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <strong>ℹ️ Información:</strong> Esta herramienta importa transacciones que ya existen en otros módulos 
        pero no están registradas en el flujo de caja. No duplicará registros.
    </div>

    <form method="POST" class="needs-validation">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📊 Ingresos</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="importar_pedidos" name="importar_pedidos" value="1">
                            <label class="form-check-label" for="importar_pedidos">
                                <strong>Pagos de Pedidos</strong>
                                <br>
                                <span class="badge bg-info"><?= $stats['pedidos'] ?> registros</span>
                                <br>
                                <small class="text-muted">Importa los pagos de pedidos de ecommerce</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card mb-3">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">📊 Egresos</h5>
                    </div>
                    <div class="card-body">
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="importar_gastos" name="importar_gastos" value="1">
                            <label class="form-check-label" for="importar_gastos">
                                <strong>Gastos Aprobados</strong>
                                <br>
                                <span class="badge bg-warning text-dark"><?= $stats['gastos'] ?> registros</span>
                                <br>
                                <small class="text-muted">Importa gastos con estado aprobado</small>
                            </label>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="importar_compras" name="importar_compras" value="1">
                            <label class="form-check-label" for="importar_compras">
                                <strong>Compras Pagadas</strong>
                                <br>
                                <span class="badge bg-warning text-dark"><?= $stats['compras'] ?> registros</span>
                                <br>
                                <small class="text-muted">Importa compras que ya fueron pagadas</small>
                            </label>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importar_sueldos" name="importar_sueldos" value="1">
                            <label class="form-check-label" for="importar_sueldos">
                                <strong>Pagos de Sueldos</strong>
                                <br>
                                <span class="badge bg-warning text-dark"><?= $stats['sueldos'] ?> registros</span>
                                <br>
                                <small class="text-muted">Importa pagos de sueldos registrados</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="alert alert-warning mt-4">
            <strong>⚠️ Advertencia:</strong>
            <ul class="mb-0 mt-2">
                <li>Solo se importarán registros que no existan en flujo de caja</li>
                <li>Los registros importados tendrán una nota de "Importado de..."</li>
                <li>No se eliminarán registros existentes</li>
                <li>Esta operación no se puede deshacer (pero sí eliminar registros manualmente)</li>
            </ul>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" name="importar" value="1" class="btn btn-primary btn-lg">
                <i class="bi bi-download"></i> Importar Seleccionados
            </button>
            <a href="flujo_caja.php" class="btn btn-secondary btn-lg">Cancelar</a>
        </div>
    </form>

    <div class="card mt-4">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">💡 Consejos</h5>
        </div>
        <div class="card-body">
            <p><strong>Primera vez que usas Flujo de Caja:</strong></p>
            <ol>
                <li>Selecciona todos los checkboxes</li>
                <li>Click en "Importar Seleccionados"</li>
                <li>El sistema importará todos los datos históricos</li>
                <li>De ahora en adelante, los registros se crearán automáticamente</li>
            </ol>

            <p><strong>Uso regular:</strong></p>
            <ul>
                <li>Los nuevos registros se crean automáticamente en flujo de caja</li>
                <li>Solo usa esta herramienta para sincronizar datos históricos</li>
                <li>Registra nuevas transacciones directamente en flujo de caja</li>
            </ul>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
</body>
</html>
