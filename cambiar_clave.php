<?php
session_start();
require 'config.php';

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $_SESSION['user']['id'];
        $pass_actual = $_POST['pass_actual'];
        $pass_nueva = $_POST['pass_nueva'];
        $pass_confirma = $_POST['pass_confirma'];

        // Obtener contraseña actual
        $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        // Validar contraseña actual
        if (!password_verify($pass_actual, $user['password'])) {
            $error = 'La contraseña actual es incorrecta';
        } elseif (strlen($pass_nueva) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres';
        } elseif ($pass_nueva !== $pass_confirma) {
            $error = 'Las contraseñas no coinciden';
        } else {
            // Actualizar contraseña
            $hash = password_hash($pass_nueva, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $id]);
            $msg = 'Contraseña cambiad correctamente';
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambiar Contraseña</title>
    <link href="assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">Tucu Roller</a>
        <a href="index.php" class="btn btn-outline-light btn-sm">← Volver</a>
    </div>
</nav>

<div class="container mt-5">
    <div class="row">
        <div class="col-md-6 offset-md-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Cambiar Contraseña</h5>
                </div>
                <div class="card-body">
                    <?php if ($msg): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($msg) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" class="form-control" name="pass_actual" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" class="form-control" name="pass_nueva" required>
                            <small class="form-text text-muted">Mínimo 6 caracteres</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" name="pass_confirma" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Cambiar Contraseña</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/bootstrap.min.js"></script>
</body>
</html>
