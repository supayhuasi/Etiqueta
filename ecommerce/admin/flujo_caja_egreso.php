<?php
require '../../config.php';

$error = '';;
$exito = '';
$tipo_egreso = $_GET['tipo'] ?? 'gasto';

// Obtener empleados para pagos de sueldo
$stmt = $pdo->prepare("
    SELECT id, nombre, sueldo_base 
    FROM empleados 
    WHERE activo = 1
    ORDER BY nombre
");
$stmt->execute();
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener gastos pendientes
$stmt = $pdo->prepare("
    SELECT id, numero_gasto, descripcion, monto, beneficiario
    FROM gastos 
    WHERE estado_gasto_id IN (SELECT id FROM estados_gastos WHERE nombre IN ('Pendiente', 'Aprobado'))
    ORDER BY fecha DESC
    LIMIT 20
");
$stmt->execute();
$gastos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener compras pendientes (si existe la tabla)
$compras = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, numero_compra, proveedor, total
        FROM compras 
        WHERE estado IN ('pendiente', 'aprobada')
        ORDER BY fecha DESC
        LIMIT 20
    ");
    $stmt->execute();
    $compras = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tabla de compras no existe
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $tipo_egreso_post = $_POST['tipo_egreso'] ?? 'gasto';
        $fecha = $_POST['fecha'] ?? date('Y-m-d');
        $monto = floatval($_POST['monto'] ?? 0);
        $observaciones = $_POST['observaciones'] ?? '';
        $usuario_id = $_SESSION['user']['id'] ?? null;

        if ($monto <= 0) {
            throw new Exception('El monto debe ser mayor a 0');
        }

        // Variables para diferentes tipos
        $categoria = '';
        $descripcion = '';
        $referencia = '';
        $id_referencia = null;

        if ($tipo_egreso_post === 'sueldo') {
            $empleado_id = intval($_POST['empleado_id'] ?? 0);
            $mes_pago = $_POST['mes_pago'] ?? date('Y-m');
            
            if ($empleado_id <= 0) {
                throw new Exception('Seleccione un empleado');
            }

            // Obtener datos del empleado
            $stmt = $pdo->prepare("SELECT nombre, sueldo_base FROM empleados WHERE id = ?");
            $stmt->execute([$empleado_id]);
            $empleado = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empleado) {
                throw new Exception('Empleado no encontrado');
            }

            // Registrar en tabla de pagos parciales
            $stmt = $pdo->prepare("
                INSERT INTO pagos_sueldos_parciales 
                (empleado_id, mes_pago, sueldo_total, sueldo_pendiente, monto_pagado, fecha_pago, usuario_registra, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            // Calcular sueldo pendiente
            $stmt_suma = $pdo->prepare("
                SELECT COALESCE(SUM(monto_pagado), 0) as total_pagado
                FROM pagos_sueldos_parciales
                WHERE empleado_id = ? AND mes_pago = ?
            ");
            $stmt_suma->execute([$empleado_id, $mes_pago]);
            $suma = $stmt_suma->fetch(PDO::FETCH_ASSOC);
            $total_pagado = $suma['total_pagado'] + $monto;
            $sueldo_pendiente = $empleado['sueldo_base'] - $total_pagado;

            if ($total_pagado > $empleado['sueldo_base']) {
                throw new Exception('El monto total de pagos supera el sueldo base');
            }

            $stmt->execute([
                $empleado_id,
                $mes_pago,
                $empleado['sueldo_base'],
                $sueldo_pendiente,
                $monto,
                $fecha,
                $usuario_id,
                $observaciones
            ]);

            $categoria = 'Pago de Sueldo';
            $descripcion = $empleado['nombre'] . ' - ' . $mes_pago . ' (Pago parcial)';
            $referencia = 'Sueldo ' . $mes_pago;
            $id_referencia = $empleado_id;

        } elseif ($tipo_egreso_post === 'gasto') {
            $gasto_id = intval($_POST['gasto_id'] ?? 0);
            $categoria = $_POST['categoria'] ?? '';

            if ($gasto_id > 0) {
                $stmt = $pdo->prepare("SELECT numero_gasto, descripcion FROM gastos WHERE id = ?");
                $stmt->execute([$gasto_id]);
                $gasto = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($gasto) {
                    $descripcion = $gasto['descripcion'];
                    $referencia = 'Gasto ' . $gasto['numero_gasto'];
                    $id_referencia = $gasto_id;
                }
            }

            if (!$categoria) {
                throw new Exception('Seleccione una categoría de gasto');
            }

            $descripcion = $descripcion ?: $_POST['descripcion'] ?? '';

        } elseif ($tipo_egreso_post === 'compra') {
            $compra_id = intval($_POST['compra_id'] ?? 0);
            $categoria = 'Compra';

            if ($compra_id > 0) {
                $stmt = $pdo->prepare("SELECT numero_compra, proveedor FROM compras WHERE id = ?");
                $stmt->execute([$compra_id]);
                $compra = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($compra) {
                    $descripcion = 'Compra a ' . $compra['proveedor'];
                    $referencia = 'Compra ' . $compra['numero_compra'];
                    $id_referencia = $compra_id;
                }
            }

            $descripcion = $descripcion ?: $_POST['descripcion'] ?? '';
        }

        // Registrar en flujo de caja
        $stmt = $pdo->prepare("
            INSERT INTO flujo_caja 
            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
            VALUES (?, 'egreso', ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $fecha,
            $categoria,
            $descripcion,
            $monto,
            $referencia,
            $id_referencia,
            $usuario_id,
            $observaciones
        ]);

        $exito = 'Egreso registrado correctamente';
        $_POST = [];

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$titulo = match($tipo_egreso) {
    'sueldo' => 'Nuevo Pago de Sueldo',
    'gasto' => 'Nuevo Gasto',
    'compra' => 'Nueva Compra',
    default => 'Nuevo Egreso'
};

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $titulo ?></title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1>➖ <?= $titulo ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="flujo_caja.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($exito): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>Éxito:</strong> <?= htmlspecialchars($exito) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Tabs para diferentes tipos de egreso -->
    <ul class="nav nav-tabs mb-4" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tipo_egreso === 'gasto' ? 'active' : '' ?>" href="?tipo=gasto">💵 Gastos</a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tipo_egreso === 'sueldo' ? 'active' : '' ?>" href="?tipo=sueldo">👨‍💼 Pago de Sueldos</a>
        </li>
        <?php if (!empty($compras)): ?>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= $tipo_egreso === 'compra' ? 'active' : '' ?>" href="?tipo=compra">📦 Compras</a>
        </li>
        <?php endif; ?>
    </ul>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation">
                <input type="hidden" name="tipo_egreso" value="<?= htmlspecialchars($tipo_egreso) ?>">

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fecha" class="form-label">Fecha *</label>
                            <input type="date" id="fecha" name="fecha" class="form-control" value="<?= $_POST['fecha'] ?? date('Y-m-d') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="monto" class="form-label">Monto *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="monto" name="monto" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($_POST['monto'] ?? '') ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Formulario para GASTOS -->
                <?php if ($tipo_egreso === 'gasto'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="gasto_id" class="form-label">Gasto Existente</label>
                                <select id="gasto_id" name="gasto_id" class="form-select">
                                    <option value="">Seleccionar o crear nuevo...</option>
                                    <?php foreach ($gastos as $gasto): ?>
                                        <option value="<?= $gasto['id'] ?>" <?= ($_POST['gasto_id'] ?? '') == $gasto['id'] ? 'selected' : '' ?>>
                                            <?= $gasto['numero_gasto'] ?> - <?= htmlspecialchars($gasto['descripcion']) ?> - $<?= number_format($gasto['monto'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="categoria" class="form-label">Categoría *</label>
                                <select id="categoria" name="categoria" class="form-select" required>
                                    <option value="">Seleccionar...</option>
                                    <option value="Servicios" <?= ($_POST['categoria'] ?? '') === 'Servicios' ? 'selected' : '' ?>>Servicios</option>
                                    <option value="Insumos" <?= ($_POST['categoria'] ?? '') === 'Insumos' ? 'selected' : '' ?>>Insumos</option>
                                    <option value="Transporte" <?= ($_POST['categoria'] ?? '') === 'Transporte' ? 'selected' : '' ?>>Transporte</option>
                                    <option value="Mantenimiento" <?= ($_POST['categoria'] ?? '') === 'Mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                                    <option value="Utilidades" <?= ($_POST['categoria'] ?? '') === 'Utilidades' ? 'selected' : '' ?>>Utilidades</option>
                                    <option value="Otro" <?= ($_POST['categoria'] ?? '') === 'Otro' ? 'selected' : '' ?>>Otro</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <input type="text" id="descripcion" name="descripcion" class="form-control" value="<?= htmlspecialchars($_POST['descripcion'] ?? '') ?>" placeholder="Detalles del gasto">
                    </div>

                <!-- Formulario para SUELDOS -->
                <?php elseif ($tipo_egreso === 'sueldo'): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="empleado_id" class="form-label">Empleado *</label>
                                <select id="empleado_id" name="empleado_id" class="form-select" required onchange="actualizarSueldo()">
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($empleados as $emp): ?>
                                        <option value="<?= $emp['id'] ?>" data-sueldo="<?= $emp['sueldo_base'] ?>" <?= ($_POST['empleado_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($emp['nombre']) ?> - $<?= number_format($emp['sueldo_base'], 2) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="mes_pago" class="form-label">Mes de Pago *</label>
                                <input type="month" id="mes_pago" name="mes_pago" class="form-control" value="<?= $_POST['mes_pago'] ?? date('Y-m') ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <strong>💡 Pago Parcial:</strong> Puede registrar pagos parciales del sueldo. Este sistema registrará cada pago con su fecha.
                        <div id="sueldo_info" style="margin-top: 10px;"></div>
                    </div>

                <!-- Formulario para COMPRAS -->
                <?php elseif ($tipo_egreso === 'compra' && !empty($compras)): ?>
                    <div class="mb-3">
                        <label for="compra_id" class="form-label">Compra Existente</label>
                        <select id="compra_id" name="compra_id" class="form-select">
                            <option value="">Seleccionar o crear nueva...</option>
                            <?php foreach ($compras as $compra): ?>
                                <option value="<?= $compra['id'] ?>" <?= ($_POST['compra_id'] ?? '') == $compra['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($compra['numero_compra']) ?> - <?= htmlspecialchars($compra['proveedor']) ?> - $<?= number_format($compra['total'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <input type="text" id="descripcion" name="descripcion" class="form-control" value="<?= htmlspecialchars($_POST['descripcion'] ?? '') ?>" placeholder="Detalles de la compra">
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="3"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-danger btn-lg">
                        <i class="bi bi-check-circle"></i> Registrar Egreso
                    </button>
                    <a href="flujo_caja.php" class="btn btn-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function actualizarSueldo() {
    const select = document.getElementById('empleado_id');
    const option = select.options[select.selectedIndex];
    const sueldo = parseFloat(option.dataset.sueldo) || 0;
    const nombre = option.text.split(' - ')[0];
    
    const info = document.getElementById('sueldo_info');
    if (sueldo > 0) {
        info.innerHTML = `<strong>${nombre}</strong><br>Sueldo Base: $${sueldo.toFixed(2)}`;
    }
}

// Actualizar al cargar si hay empleado seleccionado
document.addEventListener('DOMContentLoaded', actualizarSueldo);
</script>

<?php require 'includes/footer.php'; ?>
</body>
</html>
