<?php require 'config.php';

$telas = $pdo->query("SELECT * FROM telas WHERE activo=1")->fetchAll();
$colores = $pdo->query("SELECT * FROM colores WHERE activo=1")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    <div class="container mt-5">
        <div class="row">
            <div class="col-md-6">
                <h2 class="mb-4">Nuevo Producto</h2>

                <form action="guardar.php" method="post">
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="roller">Roller</option>
                            <option value="toldo">Toldo</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="numero_orden" class="form-label">Número Orden</label>
                        <input class="form-control" id="numero_orden" name="numero_orden" required>
                    </div>

                    <div class="mb-3">
                        <label for="ancho_cm" class="form-label">Ancho (cm)</label>
                        <input class="form-control" type="number" id="ancho_cm" name="ancho_cm" required>
                    </div>

                    <div class="mb-3">
                        <label for="alto_cm" class="form-label">Alto (cm)</label>
                        <input class="form-control" type="number" id="alto_cm" name="alto_cm" required>
                    </div>

                    <div class="mb-3">
                        <label for="tela_id" class="form-label">Tela</label>
                        <select class="form-select" id="tela_id" name="tela_id">
                            <option value="">--</option>
                            <?php foreach ($telas as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= $t['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="color_id" class="form-label">Color</label>
                        <select class="form-select" id="color_id" name="color_id">
                            <option value="">--</option>
                            <?php foreach ($colores as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= $c['nombre'] ?></option>
                            <?php endforeach; ?>
                        </select>
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
