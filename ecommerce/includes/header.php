<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($pdo)) {
  require __DIR__ . '/../config.php';
}

$empresa_menu = null;
try {
  $stmt = $pdo->query("SELECT nombre, logo FROM ecommerce_empresa LIMIT 1");
  $empresa_menu = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $empresa_menu = null;
}

$logo_menu_src = null;
if (!empty($empresa_menu['logo'])) {
  $logo_menu_path = __DIR__ . '/../uploads/' . $empresa_menu['logo'];
  if (file_exists($logo_menu_path)) {
    $logo_menu_src = 'uploads/' . $empresa_menu['logo'];
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tucu Roller - Tienda Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container-fluid">
    <button class="navbar-toggler order-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <a class="navbar-brand d-flex align-items-center gap-2 mx-auto order-1" href="index.php">
      <?php if ($logo_menu_src): ?>
        <img src="<?= htmlspecialchars($logo_menu_src) ?>" alt="<?= htmlspecialchars($empresa_menu['nombre'] ?? 'Logo') ?>" style="height: 64px; width: auto;">
      <?php endif; ?>
    </a>
    <div class="collapse navbar-collapse order-2 justify-content-end" id="navbarNav">
      <ul class="navbar-nav">
        <li class="nav-item">
          <a class="nav-link" href="index.php">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="tienda.php">Tienda</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="nosotros.php">Nosotros</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="contacto.php">Contacto</a>
        </li>
        <?php if (!empty($_SESSION['cliente_id'])): ?>
          <li class="nav-item">
            <a class="nav-link" href="mis_pedidos.php">Mis pedidos</a>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="cliente_logout.php">Salir<?= !empty($_SESSION['cliente_nombre']) ? ' (' . htmlspecialchars($_SESSION['cliente_nombre']) . ')' : '' ?></a>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="cliente_login.php">Ingresar</a>
          </li>
        <?php endif; ?>
        <li class="nav-item">
          <a class="nav-link position-relative" href="carrito.php">
             Carrito
            <?php if (!empty($_SESSION['carrito']) && count($_SESSION['carrito']) > 0): ?>
              <span class="badge bg-danger position-absolute top-0 start-100 translate-middle">
                <?= count($_SESSION['carrito']) ?>
              </span>
            <?php endif; ?>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<main>
