<?php
require '../../config.php';

echo "<h1>Debug Logo</h1>";

// Ver qué hay en la base de datos
$stmt = $pdo->query("SELECT * FROM empresa WHERE id = 1");
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Datos de empresa:</h3>";
echo "<pre>";
print_r($empresa);
echo "</pre>";

echo "<h3>Logo en BD:</h3>";
echo "Valor: '" . ($empresa['logo'] ?? 'NULL') . "'<br>";
echo "Vacío: " . (empty($empresa['logo']) ? 'SI' : 'NO') . "<br>";

echo "<h3>Rutas a verificar:</h3>";
$rutas = [
    '/ecommerce/uploads/' . ($empresa['logo'] ?? ''),
    '../uploads/' . ($empresa['logo'] ?? ''),
    '../../uploads/' . ($empresa['logo'] ?? ''),
];

foreach ($rutas as $ruta) {
    echo "Ruta: $ruta<br>";
    $file_path = $_SERVER['DOCUMENT_ROOT'] . $ruta;
    echo "Path completo: $file_path<br>";
    echo "Existe: " . (file_exists($file_path) ? 'SI' : 'NO') . "<br>";
    if (file_exists($file_path)) {
        echo "<img src='$ruta' style='max-width: 200px;'><br>";
    }
    echo "<hr>";
}
?>
