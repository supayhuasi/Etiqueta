<?php
require_once 'config.php';

/*
 SEMÁFOROS:
 Estado 1 -> 2
   >15 días amarillo
   >20 días rojo

 Estado 2 -> 3
   >10 días amarillo
   >15 días rojo
*/

// ==========================
// PRODUCTOS + SEMÁFORO
// ==========================
$sql = "
SELECT
    p.id,
    p.codigo_barra,
    p.numero_orden,
    p.estado_id,
    DATEDIFF(NOW(), p.fecha_estado) AS dias_en_estado,

    CASE
        WHEN p.estado_id = 1 AND DATEDIFF(NOW(), p.fecha_estado) > 20 THEN 'rojo'
        WHEN p.estado_id = 1 AND DATEDIFF(NOW(), p.fecha_estado) > 15 THEN 'amarillo'

        WHEN p.estado_id = 2 AND DATEDIFF(NOW(), p.fecha_estado) > 15 THEN 'rojo'
        WHEN p.estado_id = 2 AND DATEDIFF(NOW(), p.fecha_estado) > 10 THEN 'amarillo'

        ELSE 'verde'
    END AS semaforo

FROM productos p
WHERE p.estado_id < 4
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
$kpis = ['verde' => 0, 'amarillo' => 0, 'rojo' => 0];

foreach ($productos as $p) {
    $kpis[$p['semaforo']]++;
}

function estadoNombre($id) {
    return match($id) {
        1 => 'Producción',
        2 => 'Cortado',
        3 => 'Armado',
        4 => 'Entregado',
        default => 'Desconocido'
    };
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

<div class="container-fluid p-4">

<h2 class="mb-4">📊 Dashboard General de Producción</h2>

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

<!-- Tabla -->
<div class="card shadow-sm">
<div class="card-body table-responsive">

<table class="table table-hover align-middle">
<thead class="table-dark">
<tr>
    <th>Orden</th>
    <th>Estado</th>
    <th>Días</th>
    <th>Semáforo</th>
</tr>
</thead>
<tbody>

<?php foreach ($productos as $p): 
    $badge = match($p['semaforo']) {
        'rojo' => 'danger',
        'amarillo' => 'warning',
        default => 'success'
    };
?>
<tr>
    <td><?= htmlspecialchars($p['numero_orden']) ?></td>
    <td><?= estadoNombre($p['estado_id']) ?></td>
    <td><?= $p['dias_en_estado'] ?></td>
    <td>
        <span class="badge bg-<?= $badge ?>">
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
