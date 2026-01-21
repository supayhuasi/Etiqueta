<?php
session_start();
if (isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Login - Sistema</title>
<link href="../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center vh-100">

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-4">
      <div class="card shadow">
        <div class="card-body">
          <h4 class="text-center mb-3">Ingreso al sistema</h4>

          <form method="post" action="check.php">
            <div class="mb-3">
              <input type="text" name="usuario" class="form-control" placeholder="Usuario" required>
            </div>
            <div class="mb-3">
              <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
            </div>
            <button class="btn btn-primary w-100">Ingresar</button>
          </form>

        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
