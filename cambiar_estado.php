<?php
require_once 'config.php';

$codigo = $_POST['codigo_barra'] ?? null;
$ok = false;

if ($codigo) {

    // Actualiza el estado según el estado actual
    $sql = "
        UPDATE productos
        SET estado_id = CASE
            WHEN estado_id = 1 THEN 2
            WHEN estado_id = 2 THEN 3
            WHEN estado_id = 3 THEN 4
            ELSE estado_id
        END
        WHERE codigo_barra = ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$codigo]);

    // Verificamos si encontró el producto
    if ($stmt->rowCount() > 0) {
        $ok = true;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cambio de Estado</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-5">

<?php if ($ok): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>✅ Éxito!</strong> El estado se actualizó correctamente.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php else: ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>❌ Error!</strong> Código no encontrado o inválido.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

    <div class="mt-4">
        <a href="index.php" class="btn btn-primary">⬅️ Volver</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
