<?php
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

// Obtener filtros
$mes = $_GET['mes'] ?? date('Y-m');
$tipo_filtro = $_GET['tipo'] ?? 'todos';

// Validar formato de mes
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

// Obtener transacciones del mes
$query = "SELECT * FROM flujo_caja WHERE DATE_FORMAT(fecha, '%Y-%m') = ? ";
$params = [$mes];

if ($tipo_filtro !== 'todos') {
    $query .= "AND tipo = ? ";
    $params[] = $tipo_filtro;
}

$query .= "ORDER BY fecha DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_ingresos = 0;
$total_egresos = 0;

foreach ($transacciones as $trans) {
    if ($trans['tipo'] === 'ingreso') {
        $total_ingresos += $trans['monto'];
    } else {
        $total_egresos += $trans['monto'];
    }
}

$saldo = $total_ingresos - $total_egresos;

// Obtener resumen por categoría
$stmt_categorias = $pdo->prepare("
    SELECT 
        tipo, 
        categoria, 
        COUNT(*) as cantidad, 
        SUM(monto) as total
    FROM flujo_caja 
    WHERE DATE_FORMAT(fecha, '%Y-%m') = ?
    GROUP BY tipo, categoria
    ORDER BY tipo DESC, total DESC
");
$stmt_categorias->execute([$mes]);
$categorias_resumen = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flujo de Caja</title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-ingreso { border-top: 4px solid #28A745; }
        .card-egreso { border-top: 4px solid #DC3545; }
        .badge-ingreso { background-color: #28A745; }
        .badge-egreso { background-color: #DC3545; }
        .categoria-ingreso { color: #28A745; font-weight: bold; }
        .categoria-egreso { color: #DC3545; font-weight: bold; }
        .resumen-box {
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .resumen-ingresos { background-color: #d4edda; border-left: 4px solid #28A745; }
        .resumen-egresos { background-color: #f8d7da; border-left: 4px solid #DC3545; }
        .resumen-saldo { background-color: #e7f3ff; border-left: 4px solid #0066cc; }
    </style>
</head>
<body>
<?php require 'includes/header.php'; ?>

<div class="container-fluid my-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1>💰 Flujo de Caja</h1>
        </div>
        <div class="col-md-6 text-end">
            <a href="flujo_caja_ingreso.php" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Nuevo Ingreso
            </a>
            <a href="flujo_caja_egreso.php" class="btn btn-danger">
                <i class="bi bi-plus-circle"></i> Nuevo Egreso
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="mes" class="form-label">Mes</label>
                    <input type="month" id="mes" name="mes" class="form-control" value="<?= $mes ?>" onchange="this.form.submit()">
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select" onchange="this.form.submit()">
                        <option value="todos">Todos</option>
                        <option value="ingreso" <?= $tipo_filtro === 'ingreso' ? 'selected' : '' ?>>Ingresos</option>
                        <option value="egreso" <?= $tipo_filtro === 'egreso' ? 'selected' : '' ?>>Egresos</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <!-- Resumen -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="resumen-box resumen-ingresos">
                <div class="h6 text-muted">INGRESOS</div>
                <div class="h4 categoria-ingreso">$<?= number_format($total_ingresos, 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="resumen-box resumen-egresos">
                <div class="h6 text-muted">EGRESOS</div>
                <div class="h4 categoria-egreso">$<?= number_format($total_egresos, 2) ?></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="resumen-box resumen-saldo">
                <div class="h6 text-muted">SALDO NETO</div>
                <div class="h4" style="color: <?= $saldo >= 0 ? '#28A745' : '#DC3545' ?>">
                    $<?= number_format($saldo, 2) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen por Categoría -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">Ingresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <?php
                    $hay_ingresos = false;
                    foreach ($categorias_resumen as $cat) {
                        if ($cat['tipo'] === 'ingreso') {
                            $hay_ingresos = true;
                            echo '<div class="d-flex justify-content-between mb-2">';
                            echo '<span>' . htmlspecialchars($cat['categoria']) . '</span>';
                            echo '<strong>$' . number_format($cat['total'], 2) . '</strong>';
                            echo '</div>';
                        }
                    }
                    if (!$hay_ingresos) {
                        echo '<p class="text-muted text-center">Sin ingresos registrados</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header bg-danger text-white">
                    <h5 class="mb-0">Egresos por Categoría</h5>
                </div>
                <div class="card-body">
                    <?php
                    $hay_egresos = false;
                    foreach ($categorias_resumen as $cat) {
                        if ($cat['tipo'] === 'egreso') {
                            $hay_egresos = true;
                            echo '<div class="d-flex justify-content-between mb-2">';
                            echo '<span>' . htmlspecialchars($cat['categoria']) . '</span>';
                            echo '<strong>$' . number_format($cat['total'], 2) . '</strong>';
                            echo '</div>';
                        }
                    }
                    if (!$hay_egresos) {
                        echo '<p class="text-muted text-center">Sin egresos registrados</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabla de Transacciones -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Transacciones de <?= date('F Y', strtotime($mes . '-01')); ?></h5>
        </div>
        <div class="card-body">
            <?php if (empty($transacciones)): ?>
                <p class="text-muted text-center">No hay transacciones registradas para este período</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Categoría</th>
                                <th>Descripción</th>
                                <th>Monto</th>
                                <th>Referencia</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transacciones as $trans): ?>
                                <tr class="<?= $trans['tipo'] === 'ingreso' ? 'table-success' : 'table-danger' ?>">
                                    <td><?= date('d/m/Y', strtotime($trans['fecha'])) ?></td>
                                    <td>
                                        <span class="badge <?= $trans['tipo'] === 'ingreso' ? 'badge-ingreso' : 'badge-egreso' ?>">
                                            <?= ucfirst($trans['tipo']) ?>
                                        </span>
                                    </td>
                                    <td><strong><?= htmlspecialchars($trans['categoria']) ?></strong></td>
                                    <td><?= htmlspecialchars($trans['descripcion'] ?? '-') ?></td>
                                    <td class="text-end">
                                        <strong class="<?= $trans['tipo'] === 'ingreso' ? 'text-success' : 'text-danger' ?>">
                                            <?= $trans['tipo'] === 'ingreso' ? '+' : '-' ?>$<?= number_format($trans['monto'], 2) ?>
                                        </strong>
                                    </td>
                                    <td>
                                        <?php
                                        if ($trans['referencia']) {
                                            echo '<small class="text-muted">' . htmlspecialchars($trans['referencia']) . '</small>';
                                        } else {
                                            echo '-';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="flujo_caja_editar.php?id=<?= $trans['id'] ?>" class="btn btn-sm btn-primary">Editar</a>
                                        <a href="flujo_caja_eliminar.php?id=<?= $trans['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('¿Está seguro?')">Eliminar</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
