<?php
require __DIR__ . '/config.php';

header('Content-Type: application/xml; charset=UTF-8');

$script_path_public = $_SERVER['SCRIPT_NAME'] ?? '';
$public_base = '';
if ($script_path_public) {
  if (strpos($script_path_public, '/ecommerce/') !== false) {
    $public_base = preg_replace('#/ecommerce/.*$#', '/ecommerce', $script_path_public);
  } else {
    $public_base = rtrim(dirname($script_path_public), '/\\');
  }
}

$request_scheme = 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
} elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
  $request_scheme = 'https';
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$base_url = $request_scheme . '://' . $host . $public_base;

$urls = [
  $base_url . '/index.php',
  $base_url . '/tienda.php',
  $base_url . '/nosotros.php',
  $base_url . '/contacto.php',
  $base_url . '/distribuidores.php',
  $base_url . '/carrito.php',
  $base_url . '/checkout.php'
];

try {
  $stmt = $pdo->query("SELECT id FROM ecommerce_productos WHERE activo = 1 AND mostrar_ecommerce = 1");
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($productos as $p) {
    $urls[] = $base_url . '/producto.php?id=' . (int)$p['id'];
  }
} catch (Exception $e) {
}

$lastmod = date('Y-m-d');

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url) ?></loc>
    <lastmod><?= $lastmod ?></lastmod>
  </url>
<?php endforeach; ?>
</urlset>
