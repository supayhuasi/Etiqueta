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

$hoy = date('Y-m-d');

// Páginas estáticas indexables (se excluyen carrito/checkout/mis_pedidos: son páginas
// privadas o transaccionales sin valor de búsqueda propio).
$urls = [
  ['loc' => $base_url . '/index.php', 'lastmod' => $hoy, 'changefreq' => 'daily', 'priority' => '1.0'],
  ['loc' => $base_url . '/tienda.php', 'lastmod' => $hoy, 'changefreq' => 'daily', 'priority' => '0.9'],
  ['loc' => $base_url . '/nosotros.php', 'lastmod' => $hoy, 'changefreq' => 'monthly', 'priority' => '0.5'],
  ['loc' => $base_url . '/contacto.php', 'lastmod' => $hoy, 'changefreq' => 'monthly', 'priority' => '0.5'],
  ['loc' => $base_url . '/distribuidores.php', 'lastmod' => $hoy, 'changefreq' => 'monthly', 'priority' => '0.5'],
  ['loc' => $base_url . '/faq.php', 'lastmod' => $hoy, 'changefreq' => 'monthly', 'priority' => '0.5'],
  ['loc' => $base_url . '/blog.php', 'lastmod' => $hoy, 'changefreq' => 'weekly', 'priority' => '0.6'],
];

try {
  $stmt = $pdo->query("SELECT id, fecha_actualizacion, fecha_creacion FROM ecommerce_productos WHERE activo = 1 AND mostrar_ecommerce = 1");
  $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($productos as $p) {
    $fecha = $p['fecha_actualizacion'] ?: $p['fecha_creacion'];
    $urls[] = [
      'loc' => $base_url . '/producto.php?id=' . (int)$p['id'],
      'lastmod' => $fecha ? date('Y-m-d', strtotime($fecha)) : $hoy,
      'changefreq' => 'weekly',
      'priority' => '0.8',
    ];
  }
} catch (Exception $e) {
}

try {
  $stmt = $pdo->query("SELECT slug, updated_at, publicado_en FROM ecommerce_blog_articulos WHERE estado = 'publicado'");
  $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
  foreach ($articulos as $a) {
    $fecha = $a['updated_at'] ?: $a['publicado_en'];
    $urls[] = [
      'loc' => $base_url . '/blog_articulo.php?slug=' . urlencode($a['slug']),
      'lastmod' => $fecha ? date('Y-m-d', strtotime($fecha)) : $hoy,
      'changefreq' => 'monthly',
      'priority' => '0.6',
    ];
  }
} catch (Exception $e) {
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($urls as $url): ?>
  <url>
    <loc><?= htmlspecialchars($url['loc']) ?></loc>
    <lastmod><?= htmlspecialchars($url['lastmod']) ?></lastmod>
    <changefreq><?= htmlspecialchars($url['changefreq']) ?></changefreq>
    <priority><?= htmlspecialchars($url['priority']) ?></priority>
  </url>
<?php endforeach; ?>
</urlset>
