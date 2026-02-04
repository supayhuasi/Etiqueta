<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

require_once 'config.php';

if (!isset($pdo)) {
    die('Error: Conexión a base de datos no disponible');
}

// ==========================
// CONSULTA DASHBOARD
// ==========================
$sql = "
SELECT
    p.id,
    p.codigo_barra,
    p.numero_orden,
    h.estado_id,
    DATEDIFF(NOW(), h.fecha) AS dias_en_estado,

    CASE
        WHEN h.estado_id = 1 AND DATEDIFF(NOW(), h.fecha) > 20 THEN 'rojo'
        WHEN h.estado_id = 1 AND DATEDIFF(NOW(), h.fecha) > 15 THEN 'amarillo'
        WHEN h.estado_id = 2 AND DATEDIFF(NOW(), h.fecha) > 15 THEN 'rojo'
        WHEN h.estado_id = 2 AND DATEDIFF(NOW(), h.fecha) > 10 THEN 'amarillo'
        ELSE 'verde'
    END AS semaforo

FROM productos p
JOIN historial_estados h
  ON h.producto_id = p.id
JOIN (
    SELECT producto_id, MAX(fecha) AS ultima_fecha
    FROM historial_estados
    GROUP BY producto_id
) ult
  ON ult.producto_id = h.producto_id
 AND ult.ultima_fecha = h.fecha

WHERE h.estado_id < 4
ORDER BY
    semaforo = 'rojo' DESC,
    semaforo = 'amarillo' DESC,
    dias_en_estado DESC
";

$stmt = $pdo->query($sql);
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================
// KPIs
// ==========================
$kpis = ['verde'=>0, 'amarillo'=>0, 'rojo'=>0];
foreach ($productos as $p) {
    $kpis[$p['semaforo']]++;
}

// ==========================
// FUNCIONES
// ==========================
function estadoNombre($id) {
    if ($id == 1) return 'PENDIENTE';
    if ($id == 2) return 'EN PRODUCCIÓN';
    if ($id == 3) return 'LISTO';
    if ($id == 4) return 'ENTREGADO';
    return 'Desconocido';
}

function badgeColor($s) {
    if ($s === 'rojo') return 'danger';
    if ($s === 'amarillo') return 'warning';
    return 'success';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Dashboard Producción</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<?php require_once 'includes/navbar.php'; ?>

<div class="container-fluid p-4">

<h2 class="mb-4">📊 Dashboard General</h2>

<!-- KPIs -->
<div class="row text-center mb-4">

<div class="col-md-4 mb-2">
    <div class="card border-success">
        <div class="card-body">
            <h6>🟢 En tiempo</h6>
            <h2><?= $kpis['verde'] ?></h2>
        </div>
    </div>
</div>

<div class="col-md-4 mb-2">
    <div class="card border-warning">
        <div class="card-body">
            <h6>🟡 En riesgo</h6>
            <h2><?= $kpis['amarillo'] ?></h2>
        </div>
    </div>
</div>

<div class="col-md-4 mb-2">
    <div class="card border-danger">
        <div class="card-body">
            <h6>🔴 Atrasados</h6>
            <h2><?= $kpis['rojo'] ?></h2>
        </div>
    </div>
</div>

</div>

<!-- TABLA -->
<div class="card shadow-sm">
<div class="card-body table-responsive">

<table class="table table-hover align-middle">
<thead class="table-dark">
<tr>
    <th>Cliente</th>
    <th>Estado</th>
    <th>Días</th>
    <th>Semáforo</th>
</tr>
</thead>
<tbody>

<?php foreach ($productos as $p): ?>
<tr>
    <td><?= htmlspecialchars($p['numero_orden']) ?></td>
    <td><?= estadoNombre($p['estado_id']) ?></td>
    <td><?= $p['dias_en_estado'] ?></td>
    <td>
        <span class="badge bg-<?= badgeColor($p['semaforo']) ?>">
            <?= strtoupper($p['semaforo']) ?>
        </span>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>
</div>

</div>

</body>
</html>
