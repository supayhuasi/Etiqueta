<?php
session_start();
require_once 'config.php';

// SOLO ADMIN
if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$msg = '';

// Obtener roles disponibles
$stmt = $pdo->query("SELECT * FROM roles ORDER BY nombre");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario']);
    $nombre  = trim($_POST['nombre']);
    $pass    = $_POST['password'];
    $rol_id  = intval($_POST['rol_id']);

    if ($usuario && $pass && $rol_id) {
        $hash = password_hash($pass, PASSWORD_BCRYPT);

        try {
            // Verificar si el usuario ya existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            if ($stmt->rowCount() > 0) {
                $msg = '<div class="alert alert-danger">El usuario ya existe</div>';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO usuarios (usuario, password, nombre, rol_id, activo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([$usuario, $hash, $nombre, $rol_id]);
                $msg = '<div class="alert alert-success">Usuario creado correctamente</div>';
            }
        } catch (PDOException $e) {
            $msg = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
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
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Rol</label>
          <select name="rol_id" class="form-select" required>
            <option value="">Seleccionar rol...</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
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