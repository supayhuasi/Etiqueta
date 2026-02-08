<?php
session_start();
if (!isset($_SESSION['user'])) {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Tucu Roller</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-5">
            <div class="text-center">
                <h1 class="mb-3">Tucu Roller</h1>
                <p class="text-muted mb-4">Cortinas, toldos y persianas a medida.</p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="/ecommerce/" class="btn btn-primary">Ir a la tienda</a>
                    <a href="/ecommerce/admin/auth/login.php" class="btn btn-outline-secondary">Ingreso admin</a>
                </div>
                <div class="mt-4">
                    <a href="/ecommerce/politica_privacidad.php" class="text-muted">Política de privacidad</a>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
    </html>
    <?php
    exit;
}

require 'config.php';

// ==========================
// ESTADOS (para el filtro)
// ==========================
$estados = $pdo
    ->query("SELECT id, nombre FROM estados ORDER BY id")
    ->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// FILTRO
// ==========================
$estadoFiltro = $_GET['estado_id'] ?? '';

// ==========================
// CONSULTA PRODUCTOS
// ==========================
$sql = "
SELECT p.*, e.nombre AS estado
FROM productos p
JOIN estados e ON p.estado_id = e.id
";

$params = [];

if ($estadoFiltro !== '') {
    $sql .= " WHERE p.estado_id = ?";
    $params[] = $estadoFiltro;
}

$sql .= " ORDER BY p.fecha_alta DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Productos</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container mt-5">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>📦 Productos</h2>
        <a href="crear.php" class="btn btn-success">➕ Nuevo</a>
    </div>

    <!-- FILTRO POR ESTADO -->
    <form method="get" class="row g-3 mb-3 align-items-center">
        <div class="col-md-4">
            <select name="estado_id" class="form-select" onchange="this.form.submit()">
                <option value="">🔎 Todos los estados</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id'] ?>"
                        <?= ($estadoFiltro == $e['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($estadoFiltro !== ''): ?>
        <div class="col-md-2">
            <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
        </div>
        <?php endif; ?>
    </form>

    <!-- TABLA -->
    <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
            <thead class="table-dark">
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Medidas</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>

            <?php if (count($productos) === 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">
                        No hay productos para mostrar
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($productos as $p): ?>

                <?php
                // Color del estado
                if ($p['estado_id'] == 1) $color = 'secondary';
                elseif ($p['estado_id'] == 2) $color = 'primary';
                elseif ($p['estado_id'] == 3) $color = 'warning';
                elseif ($p['estado_id'] == 4) $color = 'success';
                else $color = 'info';
                ?>

                <tr>
                    <td><?= htmlspecialchars($p['codigo_barra']) ?></td>
                    <td><?= htmlspecialchars($p['numero_orden']) ?></td>
                    <td><?= htmlspecialchars($p['tipo']) ?></td>
                    <td><?= $p['ancho_cm'] ?> x <?= $p['alto_cm'] ?></td>
                    <td>
                        <span class="badge bg-<?= $color ?>">
                            <?= htmlspecialchars($p['estado']) ?>
                        </span>
                    </td>
                   <td>
    <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">✏️</a>

    <a href="eliminar.php?id=<?= $p['id'] ?>"
       class="btn btn-sm btn-danger"
       onclick="return confirm('¿Eliminar producto?')">🗑️</a>

    <a href="imprimir.php?id=<?= $p['id'] ?>"
       target="_blank"
       class="btn btn-sm btn-secondary">🖨️</a>

    <?php if ($p['estado_id'] != 5): ?>
        <a href="mandar_stock.php?id=<?= $p['id'] ?>"
           class="btn btn-sm btn-success"
           onclick="return confirm('¿Mandar este producto a stock?')">
           📦 Stock
        </a>
    <?php endif; ?>
</td>
                </tr>

            <?php endforeach; ?>

            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
