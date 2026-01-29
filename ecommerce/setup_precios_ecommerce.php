<?php
require 'config.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_config'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE ecommerce_config (
                id INT PRIMARY KEY AUTO_INCREMENT,
                lista_precio_id INT,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        echo "✓ Tabla ecommerce_config creada<br>";
    }

    $stmt = $pdo->query("SELECT COUNT(*) as total FROM ecommerce_config");
    $count = (int)($stmt->fetch()['total'] ?? 0);
    if ($count === 0) {
        $pdo->exec("INSERT INTO ecommerce_config (id, lista_precio_id) VALUES (1, NULL)");
        echo "✓ Configuración inicial creada<br>";
    }

    echo "✓ Setup de precios para ecommerce completado";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
