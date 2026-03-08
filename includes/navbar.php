<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$base_path = '';
if ($script_path !== '') {
  $base_path = rtrim(str_replace('\\', '/', dirname($script_path)), '/');
  if ($base_path === '/' || $base_path === '.') {
    $base_path = '';
  }
}

$scan_url = ($base_path !== '' ? $base_path : '') . '/scan.php';
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
  <div class="container-fluid">

    <a class="navbar-brand" href="index.php">
      Tucu Roller
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">

      <ul class="navbar-nav me-auto">

        <?php if (isset($_SESSION['rol'])): ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= htmlspecialchars($scan_url) ?>">Escaneo</a>
        </li>
        <?php if (!in_array($_SESSION['rol'], ['ventas','operario'])): ?>
        <li class="nav-item">
          <a class="nav-link" href="index.php">Inicio</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="dashboard.php">📊 Dashboard</a>
        </li>
        <?php endif; ?>

        <?php if ($_SESSION['rol'] === 'admin'): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
            Usuarios
          </a>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="usuarios_lista.php">Listar</a></li>
            <li><a class="dropdown-item" href="usuarios_crear.php">Crear</a></li>
            <li><a class="dropdown-item" href="roles_usuarios.php">Roles</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="ecommerce/admin/sueldos/sueldos.php">💰 Sueldos</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="ecommerce/admin/cheques/cheques.php">🏦 Cheques</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="ecommerce/admin/gastos/gastos.php">💸 Gastos</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="ecommerce/admin/asistencias/asistencias.php">📋 Asistencias</a>
        </li>
        <?php endif; ?>
        <?php endif; ?>

      </ul>

      <div class="d-flex align-items-center gap-3">
        <span class="navbar-text text-white">
          <?= $_SESSION['user']['usuario'] ?>
        </span>

        <div class="dropdown">
          <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            ⚙️ Opciones
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="cambiar_clave.php">🔑 Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="auth/logout.php">🚪 Salir</a></li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</nav>