<?php
require 'config.php';

$telas = $pdo->query("SELECT * FROM telas WHERE activo = 1 ORDER BY nombre")->fetchAll();
$colores = $pdo->query("SELECT * FROM colores WHERE activo = 1 ORDER BY nombre")->fetchAll();
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

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">

            <h2 class="mb-4">➕ Nuevo Producto</h2>

            <form action="guardar.php" method="post">

                <!-- TIPO -->
                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <select class="form-select" id="tipo" name="tipo" required>
                        <option value="">Seleccione tipo</option>
                        <option value="Roller">Roller</option>
                        <option value="Toldo">Toldo</option>
                        <option value="Banda Vertical">Banda Vertical</option>
                        <option value="Panel Oriental">Panel Oriental</option>
                        <option value="Mosquitero">Mosquitero</option>
                        <option value="Cortinas Clasicas">Cortina Clasicas</option>
                    </select>
                </div>

                <!-- ORDEN -->
                <div class="mb-3">
                    <label for="numero_orden" class="form-label">Cliente</label>
                    <input type="text" class="form-control" id="numero_orden" name="numero_orden" required>
                </div>

                <!-- MEDIDAS -->
                <div class="row">
                    <div class="col">
                        <label for="ancho_cm" class="form-label">Ancho (cm)</label>
                        <input type="number" class="form-control" id="ancho_cm" name="ancho_cm" required>
                    </div>
                    <div class="col">
                        <label for="alto_cm" class="form-label">Alto (cm)</label>
                        <input type="number" class="form-control" id="alto_cm" name="alto_cm" required>
                    </div>
                </div>

                <!-- TELA -->
                <div class="mb-3 mt-3">
                    <label for="tela_id" class="form-label">Tela</label>
                    <select class="form-select" id="tela_id" name="tela_id">
                        <option value="">-- Sin tela --</option>
                        <?php foreach ($telas as $t): ?>
                            <option value="<?= $t['id'] ?>">
                                <?= htmlspecialchars($t['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- COLOR -->
                <div class="mb-3">
                    <label for="color_id" class="form-label">Color</label>
                    <select class="form-select" id="color_id" name="color_id">
                        <option value="">-- Sin color --</option>
                        <?php foreach ($colores as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- BOTONES -->
                <div class="d-grid gap-2">
                    <button class="btn btn-primary" type="submit">💾 Guardar</button>
                    <a href="index.php" class="btn btn-secondary">⬅️ Volver</a>
                </div>

            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
