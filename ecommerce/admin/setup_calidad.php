<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/includes/calidad_helper.php';

header('Content-Type: text/plain; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    exit("Error: no se pudo inicializar la conexión a la base de datos.\n");
}

try {
    ensureCalidadSchema($pdo);
    echo "✓ Módulo de calidad inicializado correctamente\n";
    echo "✓ Tabla ecommerce_calidad_eventos verificada\n";
    echo "✓ Ya podés usar /admin/calidad.php\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Error al configurar calidad: " . $e->getMessage() . "\n";
}
