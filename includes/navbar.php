<?php
if (!isset($_SESSION)) {
    session_start();
}
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

        <li class="nav-item">
          <a class="nav-link" href="index.php">Inicio</a>
        </li>

        <li class="nav-item">
          <a class="nav-link" href="scan.php">Escaneo</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="dashboard.php">📊 Dashboard</a>
        </li>

        <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
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
          <a class="nav-link" href="sueldos.php">💰 Sueldos</a>
        </li>
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