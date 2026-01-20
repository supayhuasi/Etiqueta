<?php
require 'config.php';

$id = $_GET['id'];
$p = $pdo->prepare("SELECT * FROM productos WHERE id=?");
$p->execute([$id]);
$p = $p->fetch();

$telas = $pdo->query("SELECT * FROM telas")->fetchAll();
$colores = $pdo->query("SELECT * FROM colores")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6">
                <h2 class="mb-4">Editar Producto</h2>

                <form action="guardar.php" method="post">
                    <input type="hidden" name="id" value="<?= $p['id'] ?>">

                    <div class="mb-3">
                        <label for="numero_orden" class="form-label">Número Orden</label>
                        <input class="form-control" type="text" id="numero_orden" name="numero_orden" value="<?= $p['numero_orden'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="ancho_cm" class="form-label">Ancho (cm)</label>
                        <input class="form-control" type="number" id="ancho_cm" name="ancho_cm" value="<?= $p['ancho_cm'] ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="alto_cm" class="form-label">Alto (cm)</label>
                        <input class="form-control" type="number" id="alto_cm" name="alto_cm" value="<?= $p['alto_cm'] ?>" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" type="submit">💾 Guardar</button>
                    </div>
                </form>

                <a href="index.php" class="btn btn-secondary mt-3">⬅️ Volver</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
