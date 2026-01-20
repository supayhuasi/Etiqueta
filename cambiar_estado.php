<?php
require 'config.php';

$codigo = $_POST['codigo_barra'];

$sql = "UPDATE productos SET estado_id = 4 WHERE codigo_barra = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$codigo]);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambiar Estado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ Éxito!</strong> Estado actualizado correctamente.
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

        <div class="mt-4">
            <a href="index.php" class="btn btn-primary">⬅️ Volver a Productos</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
