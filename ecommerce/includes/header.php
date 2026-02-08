<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!ob_get_level()) {
  ob_start();
}

if (!isset($pdo)) {
  require __DIR__ . '/../config.php';
}
require_once __DIR__ . '/cache.php';

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
$empresa_menu = cache_get('ecommerce_empresa_menu', 300);
if (!$empresa_menu) {
  try {
    $stmt = $pdo->query("SELECT nombre, logo, redes_sociales, ga_enabled, ga_measurement_id, descripcion, about_us, direccion, ciudad, provincia, telefono, email, favicon, seo_title, seo_description, seo_image FROM ecommerce_empresa LIMIT 1");
    $empresa_menu = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($empresa_menu) {
      cache_set('ecommerce_empresa_menu', $empresa_menu);
    }
  } catch (Exception $e) {
    $empresa_menu = null;
  }
}
if ($empresa_menu) {
  $ga_config['enabled'] = !empty($empresa_menu['ga_enabled']);
  $ga_config['measurement_id'] = $empresa_menu['ga_measurement_id'] ?? '';
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

$favicon_src = null;
if (!empty($empresa_menu['favicon'])) {
  $favicon_filename = $empresa_menu['favicon'];
  $favicon_local_path = __DIR__ . '/../uploads/' . $favicon_filename;
  $favicon_root_path = __DIR__ . '/../../uploads/' . $favicon_filename;

  if (file_exists($favicon_local_path)) {
    $favicon_src = $public_base . '/uploads/' . $favicon_filename;
  } elseif (file_exists($favicon_root_path)) {
    $favicon_src = '/uploads/' . $favicon_filename;
  }
}

$redes_menu = json_decode($empresa_menu['redes_sociales'] ?? '{}', true) ?? [];
$whatsapp_num = $redes_menu['whatsapp'] ?? '';
$whatsapp_msg = $redes_menu['whatsapp_mensaje'] ?? '';

$request_scheme = 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  $request_scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $request_scheme . '://' . $host . $public_base;
$current_url = $request_scheme . '://' . $host . ($_SERVER['REQUEST_URI'] ?? '/');

$page_key = basename($_SERVER['PHP_SELF']);
$page_titles = [
  'index.php' => 'Inicio',
  'tienda.php' => 'Tienda',
  'producto.php' => 'Producto',
  'carrito.php' => 'Carrito',
  'checkout.php' => 'Checkout',
  'contacto.php' => 'Contacto',
  'nosotros.php' => 'Nosotros',
  'distribuidores.php' => 'Distribuidores',
  'mis_pedidos.php' => 'Mis pedidos'
];

$site_name = $empresa_menu['nombre'] ?? 'Tucu Roller';
$default_title = ($page_titles[$page_key] ?? 'Tienda') . ' | ' . $site_name;

$raw_description = $empresa_menu['seo_description'] ?? $empresa_menu['descripcion'] ?? $empresa_menu['about_us'] ?? 'Tienda online de ' . $site_name;
$raw_description = trim(preg_replace('/\s+/', ' ', strip_tags($raw_description)));
if (strlen($raw_description) > 160) {
  $raw_description = substr($raw_description, 0, 157) . '...';
}

$seo_title = isset($seo_title) && $seo_title ? $seo_title : (!empty($empresa_menu['seo_title']) ? $empresa_menu['seo_title'] : $default_title);
$seo_description = isset($seo_description) && $seo_description ? $seo_description : $raw_description;
$seo_type = isset($seo_type) && $seo_type ? $seo_type : 'website';
$seo_image = isset($seo_image) && $seo_image ? $seo_image : (!empty($empresa_menu['seo_image']) ? $empresa_menu['seo_image'] : ($logo_menu_src ? $request_scheme . '://' . $host . $logo_menu_src : ''));
$seo_canonical = isset($seo_canonical) && $seo_canonical ? $seo_canonical : $current_url;
$seo_robots = isset($seo_robots) && $seo_robots ? $seo_robots : 'index,follow';
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
    <title><?= htmlspecialchars($seo_title) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo_description) ?>">
    <meta name="robots" content="<?= htmlspecialchars($seo_robots) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seo_canonical) ?>">
    <?php if (!empty($favicon_src)): ?>
      <link rel="icon" href="<?= htmlspecialchars($favicon_src) ?>">
    <?php endif; ?>
    <meta property="og:title" content="<?= htmlspecialchars($seo_title) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo_description) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($seo_type) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seo_canonical) ?>">
    <?php if (!empty($seo_image)): ?>
      <meta property="og:image" content="<?= htmlspecialchars($seo_image) ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= htmlspecialchars($site_name) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($seo_title) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($seo_description) ?>">
    <?php if (!empty($seo_image)): ?>
      <meta name="twitter:image" content="<?= htmlspecialchars($seo_image) ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
    <script type="application/ld+json">
      <?= json_encode([
        '@context' => 'https://schema.org',
        '@graph' => [
          [
            '@type' => 'Organization',
            'name' => $site_name,
            'url' => $base_url ?: ($request_scheme . '://' . $host),
            'logo' => $seo_image ?: null,
            'contactPoint' => !empty($empresa_menu['telefono']) ? [[
              '@type' => 'ContactPoint',
              'telephone' => $empresa_menu['telefono'],
              'contactType' => 'customer service',
              'areaServed' => 'AR'
            ]] : null,
            'address' => (!empty($empresa_menu['direccion']) || !empty($empresa_menu['ciudad']) || !empty($empresa_menu['provincia'])) ? [
              '@type' => 'PostalAddress',
              'streetAddress' => $empresa_menu['direccion'] ?? '',
              'addressLocality' => $empresa_menu['ciudad'] ?? '',
              'addressRegion' => $empresa_menu['provincia'] ?? '',
              'addressCountry' => 'AR'
            ] : null
          ],
          [
            '@type' => 'WebSite',
            'name' => $site_name,
            'url' => $base_url ?: ($request_scheme . '://' . $host)
          ]
        ]
      ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
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
          <a class="nav-link" href="faq.php">FAQ</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="distribuidores.php">Distribuidores</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/admin/" target="_blank" rel="noopener">Admin</a>
        </li>
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
        <?php if (!empty($_SESSION['cliente_id'])): ?>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="cuentaMenu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <?= htmlspecialchars($_SESSION['cliente_nombre'] ?? 'Mi cuenta') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="cuentaMenu">
              <li><a class="dropdown-item" href="mis_pedidos.php">Mis pedidos</a></li>
              <li><a class="dropdown-item" href="cliente_perfil.php">Editar datos</a></li>
              <li><a class="dropdown-item" href="cliente_cambiar_clave.php">Cambiar clave</a></li>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="cliente_logout.php">Salir</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item">
            <a class="nav-link" href="cliente_login.php">Ingresar</a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<main>
