<?php
require 'config.php';

$mensaje = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = trim($_POST['codigo_barra']);

    if ($codigo !== "") {
        // Buscar producto
        $stmt = $pdo->prepare("SELECT id, estado_id FROM productos WHERE codigo_barra = ?");
        $stmt->execute([$codigo]);
        $producto = $stmt->fetch();

        if ($producto) {
            // Cambiar a estado ENTREGADO (id = 4)
            $pdo->prepare(
                "UPDATE productos SET estado_id = 4 WHERE id = ?"
            )->execute([$producto['id']]);

            // Guardar historial
            $pdo->prepare(
                "INSERT INTO historial_estados (producto_id, estado_id) VALUES (?, 4)"
            )->execute([$producto['id']]);

            $mensaje = "✅ Producto ENTREGADO correctamente";
        } else {
            $mensaje = "❌ Código no encontrado";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Escaneo de entrega</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .scanner-container {
            width: 100%;
            max-width: 600px;
            padding: 30px;
            background: #222;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
        }
        input {
            font-size: 28px !important;
            padding: 20px !important;
        }
        .ok { color: #00ff88; }
        .error { color: #ff5555; }
    </style>
</head>

<body>
    <div class="scanner-container text-center">
        <h1 class="mb-5">📦 Escanear cortina / toldo</h1>

        <form method="post">
            <div class="mb-3">
                <input
                    type="text"
                    name="codigo_barra"
                    class="form-control form-control-lg"
                    autofocus
                    placeholder="Escanee el código de barras"
                    autocomplete="off"
                >
            </div>
        </form>

        <?php if ($mensaje): ?>
            <div class="alert <?= str_contains($mensaje,'✅') ? 'alert-success' : 'alert-danger' ?> alert-dismissible fade show mt-4" role="alert">
                <h4 class="alert-heading">
                    <?= str_contains($mensaje,'✅') ? '✅ Éxito' : '❌ Error' ?>
                </h4>
                <p class="mb-0"><?= $mensaje ?></p>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="mt-4">
            <a href="index.php" class="btn btn-secondary">⬅️ Volver</a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
