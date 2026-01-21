<?php
session_start();
require_once 'config.php';

// SOLO ADMIN
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $pass    = $_POST['password'];
    $rol     = $_POST['rol'];

    if ($usuario && $pass) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (usuario, password, rol)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$usuario, $hash, $rol]);
            $msg = '<div class="alert alert-success">Usuario creado correctamente</div>';
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger">El usuario ya existe</div>';
        }
    } else {
        $msg = '<div class="alert alert-warning">Completá todos los campos</div>';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Crear Usuario</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="assets/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container d-flex justify-content-center align-items-center vh-100">
  <div class="card shadow w-100" style="max-width: 400px;">
    <div class="card-body">
      <h4 class="text-center mb-3">Crear Usuario</h4>

      <?= $msg ?>

      <form method="post">
        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <input type="text" name="usuario" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Rol</label>
          <select name="rol" class="form-select">
            <option value="operario">Operario</option>
            <option value="admin">Administrador</option>
          </select>
        </div>

        <button class="btn btn-primary w-100">Crear usuario</button>
      </form>

      <a href="index.php" class="btn btn-link w-100 mt-2">Volver</a>
    </div>
  </div>
</div>

</body>
</html>