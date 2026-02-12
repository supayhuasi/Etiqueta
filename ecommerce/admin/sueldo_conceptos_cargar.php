<?php
session_start();
require '../../config.php';

// Obtener empleado
$empleado_id = $_GET['empleado_id'] ?? null;
if (!$empleado_id) {
    die("ID de empleado requerido");
}

$stmt = $pdo->prepare("SELECT id, nombre, sueldo_base FROM empleados WHERE id = ?");
$stmt->execute([$empleado_id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

$mes_actual = date('Y-m');

// Obtener todos los conceptos disponibles
$stmt = $pdo->query("SELECT id, nombre, tipo FROM conceptos ORDER BY tipo DESC, nombre ASC");
$conceptos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener conceptos ya cargados este mes
$stmt = $pdo->prepare("
    SELECT c.id, c.nombre, sc.monto, sc.formula, sc.es_porcentaje
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
");
$stmt->execute([$empleado_id, $mes_actual]);
$conceptos_cargados = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $concepto_id = $_POST['concepto_id'] ?? null;
    $monto = (float)($_POST['monto'] ?? 0);
    
    if ($concepto_id && $monto > 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO sueldo_conceptos (empleado_id, concepto_id, mes, monto)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$empleado_id, $concepto_id, $mes_actual, $monto]);
            
            $_SESSION['success_msg'] = "Concepto agregado correctamente";
            header("Location: ?empleado_id=$empleado_id");
            exit;
        } catch (Exception $e) {
            $_SESSION['error_msg'] = "Error al agregar concepto: " . $e->getMessage();
        }
    }
}

// Procesar DELETE
if ($_GET['action'] === 'delete' && $_GET['concepto_id']) {
    $stmt = $pdo->prepare("
        DELETE FROM sueldo_conceptos
        WHERE empleado_id = ? AND concepto_id = ? AND mes = ?
    ");
    $stmt->execute([$empleado_id, $_GET['concepto_id'], $mes_actual]);
    
    $_SESSION['success_msg'] = "Concepto eliminado";
    header("Location: ?empleado_id=$empleado_id");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
    <title>Cargar Conceptos - <?= htmlspecialchars($empleado['nombre']) ?></title>
    <style>
        body { padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; }
        h2 { color: #333; margin-bottom: 20px; }
        .alert { margin-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .tabla-conceptos { margin-top: 30px; }
        .tabla-conceptos table { width: 100%; margin-top: 10px; }
        .tabla-conceptos th, .tabla-conceptos td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .tabla-conceptos th { background: #f9f9f9; font-weight: bold; }
        .btn-delete { color: red; cursor: pointer; }
        .stats { background: #f0f8ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Cargar Conceptos de Sueldo</h2>
    <p><strong>Empleado:</strong> <?= htmlspecialchars($empleado['nombre']) ?></p>
    <p><strong>Sueldo Base:</strong> $<?= number_format($empleado['sueldo_base'], 2) ?></p>
    <p><strong>Mes:</strong> <?= $mes_actual ?></p>

    <?php if (isset($_SESSION['success_msg'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success_msg']) ?></div>
        <?php unset($_SESSION['success_msg']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_msg'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error_msg']) ?></div>
        <?php unset($_SESSION['error_msg']); ?>
    <?php endif; ?>

    <div class="stats">
        <h4>Resumen</h4>
        <?php
        $bonificaciones = array_sum(array_filter($conceptos_cargados, fn($c) => strpos($c, 'bonificacion') !== false));
        $descuentos = array_sum(array_filter($conceptos_cargados, fn($c) => strpos($c, 'descuento') !== false));
        $total_neto = $empleado['sueldo_base'] + $bonificaciones - $descuentos;
        ?>
        <p>Base: $<?= number_format($empleado['sueldo_base'], 2) ?></p>
        <p>Bonificaciones: $<?= number_format($bonificaciones, 2) ?></p>
        <p>Descuentos: $<?= number_format($descuentos, 2) ?></p>
        <p><strong>Total Neto: $<?= number_format($total_neto, 2) ?></strong></p>
    </div>

    <form method="POST">
        <div class="form-group">
            <label>Agregar Concepto:</label>
            <select name="concepto_id" class="form-control" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($conceptos_disponibles as $c): ?>
                    <?php if (!isset($conceptos_cargados[$c['id']])): ?>
                        <option value="<?= $c['id'] ?>">
                            [<?= strtoupper($c['tipo']) ?>] <?= htmlspecialchars($c['nombre']) ?>
                        </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Monto:</label>
            <input type="number" name="monto" class="form-control" step="0.01" placeholder="0.00" required>
        </div>

        <button type="submit" class="btn btn-success">Agregar Concepto</button>
    </form>

    <?php if (!empty($conceptos_cargados)): ?>
    <div class="tabla-conceptos">
        <h3>Conceptos Cargados para <?= $mes_actual ?></h3>
        <table>
            <tr>
                <th>Concepto</th>
                <th>Tipo</th>
                <th>Monto</th>
                <th>Acción</th>
            </tr>
            <?php
            // Re-fetch con más info
            $stmt = $pdo->prepare("
                SELECT sc.id, c.nombre, c.tipo, sc.monto, sc.formula, sc.es_porcentaje
                FROM sueldo_conceptos sc
                JOIN conceptos c ON sc.concepto_id = c.id
                WHERE sc.empleado_id = ? AND sc.mes = ?
                ORDER BY c.tipo DESC
            ");
            $stmt->execute([$empleado_id, $mes_actual]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items as $item):
            ?>
                <tr>
                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                    <td><?= strtoupper($item['tipo']) ?></td>
                    <td>$<?= number_format($item['monto'], 2) ?></td>
                    <td>
                        <a href="?empleado_id=<?= $empleado_id ?>&action=delete&concepto_id=<?= $item['id'] ?>" 
                           class="btn-delete" onclick="return confirm('¿Eliminar?')">Eliminar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    <br><br>
    <a href="debug_sueldo_ana.php" class="btn btn-secondary">Volver a Debug</a>
</div>
</body>
</html>
