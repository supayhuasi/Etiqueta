<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}
require 'config.php';

$sql = "
SELECT p.*, e.nombre AS estado
FROM productos p
JOIN estados e ON p.estado_id = e.id
ORDER BY p.fecha_alta DESC
";
$productos = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Productos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Productos</h2>
            <a href="crear.php" class="btn btn-success">➕ Nuevo</a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Código</th>
                        <th>Orden</th>
                        <th>Tipo</th>
                        <th>Medidas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($productos as $p): ?>
                    <tr>
                        <td><?= $p['codigo_barra'] ?></td>
                        <td><?= $p['numero_orden'] ?></td>
                        <td><?= $p['tipo'] ?></td>
                        <td><?= $p['ancho_cm'] ?> x <?= $p['alto_cm'] ?></td>
                        <td><span class="badge bg-info"><?= $p['estado'] ?></span></td>
                        <td>
                            <a href="editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-primary">✏️</a>
                            <a href="eliminar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar?')">🗑️</a>
                            <a href="imprimir.php?id=<?= $p['id'] ?>" target="_blank" class="btn btn-sm btn-secondary">🖨️</a>
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
