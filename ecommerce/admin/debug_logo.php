<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require '../../config.php';

echo "<h1>Debug Logo</h1>";

try {
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

    if (!empty($empresa['logo'])) {
        echo "<h3>Intentando mostrar logo:</h3>";
        echo "<img src='/ecommerce/uploads/{$empresa['logo']}' style='max-width: 300px; border: 2px solid red;'><br>";
        echo "Ruta usada: /ecommerce/uploads/{$empresa['logo']}";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
