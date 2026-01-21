<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

require 'config.php';

/* =========================
   FILTROS
========================= */
$estado_id = $_GET['estado_id'] ?? '';
$orden     = $_GET['orden'] ?? '';

$where = [];
$params = [];

if ($estado_id !== '') {
    $where[] = 'p.estado_id = ?';
    $params[] = $estado_id;
}

if ($orden !== '') {
    $where[] = 'p.numero_orden LIKE ?';
    $params[] = "%$orden%";
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* =========================
   QUERY PRINCIPAL
========================= */
$sql = "
SELECT 
    p.id,
    p.codigo_barra,
    p.numero_orden,
    p.tipo,
    p.ancho_cm,
    p.alto_cm,
    e.nombre AS estado,
    p.estado_id
FROM productos p
JOIN estados e ON p.estado_id = e.id
$whereSQL
ORDER BY p.fecha_alta DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* =========================
   ESTADOS PARA FILTRO
========================= */
$estados = $pdo->query("SELECT id, nombre FROM estados ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<?php require_once __DIR__ . '/includes/navbar.php'; ?>

<div class="container-fluid mt-4">

    <h3 class="mb-4">📊 Dashboard General</h3>

    <!-- FILTROS -->
    <form class="row g-3 mb-4">

        <div class="col-md-3">
            <label class="form-label">Estado</label>
            <select name="estado_id" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($estados as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= ($estado_id == $e['id']) ? 'selected' : '' ?>>
                        <?= $e['nombre'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">Número de Orden</label>
            <input type="text" name="orden" class="form-control" value="<?= htmlspecialchars($orden) ?>">
        </div>

        <div class="col-md-2 align-self-end">
            <button class="btn btn-primary w-100">🔍 Filtrar</button>
        </div>

        <div class="col-md-2 align-self-end">
            <a href="dashboard.php" class="btn btn-secondary w-100">♻ Limpiar</a>
        </div>

    </form>

    <!-- TABLA -->
    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-dark text-center">
                <tr>
                    <th>Código</th>
                    <th>Cliente</th>
                    <th>Tipo</th>
                    <th>Medidas</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($productos as $p): ?>
                    <tr class="text-center">
                        <td><?= $p['codigo_barra'] ?></td>
                        <td><?= $p['numero_orden'] ?></td>
                        <td><?= $p['tipo'] ?></td>
                        <td><?= $p['ancho_cm'] ?> x <?= $p['alto_cm'] ?> cm</td>
                        <td>
                            <span class="badge bg-info">
                                <?= $p['estado'] ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>

                <?php if (count($productos) === 0): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">
                            No hay resultados
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
