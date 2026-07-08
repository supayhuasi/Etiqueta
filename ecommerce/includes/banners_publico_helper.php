<?php

if (!function_exists('banners_asegurar_tabla')) {
    function banners_asegurar_tabla(PDO $pdo): void
    {
        static $listo = false;
        if ($listo) {
            return;
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_banners (
            id INT PRIMARY KEY AUTO_INCREMENT,
            titulo VARCHAR(255) NOT NULL,
            imagen VARCHAR(255) NOT NULL,
            enlace VARCHAR(500) NULL,
            ubicacion VARCHAR(50) NOT NULL DEFAULT 'blog_sidebar',
            orden INT DEFAULT 0,
            activo TINYINT DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $listo = true;
    }
}

if (!function_exists('banners_zonas_disponibles')) {
    function banners_zonas_disponibles(): array
    {
        return [
            'blog_sidebar' => 'Blog (lateral)',
            'tienda_sidebar' => 'Tienda (lateral)',
            'producto_detalle' => 'Producto (debajo del detalle)',
            'inicio' => 'Inicio (sección de banners)',
        ];
    }
}

if (!function_exists('obtener_banners_zona')) {
    function obtener_banners_zona(PDO $pdo, string $zona): array
    {
        banners_asegurar_tabla($pdo);
        try {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_banners WHERE activo = 1 AND ubicacion = ? ORDER BY orden ASC, id DESC");
            $stmt->execute([$zona]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}
