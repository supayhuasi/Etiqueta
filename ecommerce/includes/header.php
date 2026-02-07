<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($pdo)) {
  require __DIR__ . '/../config.php';
}

$script_path_public = $_SERVER['SCRIPT_NAME'] ?? '';
$public_base = '';
if ($script_path_public) {
  if (strpos($script_path_public, '/ecommerce/') !== false) {
    $public_base = preg_replace('#/ecommerce/.*$#', '/ecommerce', $script_path_public);
  } elseif (strpos($script_path_public, '/admin/') !== false) {
    $public_base = rtrim(preg_replace('#/admin/.*$#', '', $script_path_public), '/');
  } else {
    $public_base = rtrim(dirname($script_path_public), '/\\');
  }
}

$empresa_menu = null;
$ga_config = [
  'enabled' => false,
  'measurement_id' => ''
];
try {
  $stmt = $pdo->query("SELECT nombre, logo, redes_sociales, ga_enabled, ga_measurement_id FROM ecommerce_empresa LIMIT 1");
  $empresa_menu = $stmt->fetch(PDO::FETCH_ASSOC);
  $ga_config['enabled'] = !empty($empresa_menu['ga_enabled']);
  $ga_config['measurement_id'] = $empresa_menu['ga_measurement_id'] ?? '';
} catch (Exception $e) {
  $empresa_menu = null;
}

$logo_menu_src = null;
if (!empty($empresa_menu['logo'])) {
  $logo_filename = $empresa_menu['logo'];
  $logo_local_path = __DIR__ . '/../uploads/' . $logo_filename; // ecommerce/uploads
  $logo_root_path = __DIR__ . '/../../uploads/' . $logo_filename; // raiz /uploads

  if (file_exists($logo_local_path)) {
    $logo_menu_src = $public_base . '/uploads/' . $logo_filename;
  } elseif (file_exists($logo_root_path)) {
    $logo_menu_src = '/uploads/' . $logo_filename;
  }
}

$redes_menu = json_decode($empresa_menu['redes_sociales'] ?? '{}', true) ?? [];
$whatsapp_num = $redes_menu['whatsapp'] ?? '';
$whatsapp_msg = $redes_menu['whatsapp_mensaje'] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!empty($ga_config['enabled']) && !empty($ga_config['measurement_id'])): ?>
      <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($ga_config['measurement_id']) ?>"></script>
      <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', <?= json_encode($ga_config['measurement_id']) ?>);
      </script>
    <?php endif; ?>
    <title>Tucu Roller - Tienda Online</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <style>
      .whatsapp-float {
        position: fixed;
        right: 20px;
        bottom: 20px;
        background: #25D366;
        color: #fff;
        padding: 12px 18px;
        border-radius: 999px;
        font-weight: 600;
        text-decoration: none;
        box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        z-index: 9999;
        display: inline-flex;
        align-items: center;
        gap: 8px;
      }
      .whatsapp-float:hover {
        background: #1ebe5d;
        color: #fff;
      }
    </style>
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
        <li class="nav-item">
          <a class="nav-link" href="distribuidores.php">Distribuidores</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/" target="_blank" rel="noopener">Admin</a>
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
