<?php
session_start();
if (isset($_SESSION['user'])) {
  header("Location: ../index.php");
  exit;
}

require '../config.php';

$empresa = null;
try {
  $stmt = $pdo->query("SELECT nombre, logo FROM ecommerce_empresa LIMIT 1");
  $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $empresa = null;
}

$logo_src = null;
if (!empty($empresa['logo'])) {
  $logo_path = __DIR__ . '/../ecommerce/uploads/' . $empresa['logo'];
  if (file_exists($logo_path)) {
    $logo_src = '../ecommerce/uploads/' . $empresa['logo'];
  }
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
          <?php if ($logo_src): ?>
            <div class="text-center mb-3">
              <img src="<?= htmlspecialchars($logo_src) ?>" alt="<?= htmlspecialchars($empresa['nombre'] ?? 'Logo') ?>" style="max-height: 90px; max-width: 100%;">
            </div>
          <?php endif; ?>
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
