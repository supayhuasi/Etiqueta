<?php
/**
 * Helper para cargar el menú público dinámicamente desde la BD
 * Se incluye en includes/header.php para renderizar el menú del sitio web
 */

if (!function_exists('render_menu_publico_dinamico')) {
    function render_menu_publico_dinamico($pdo) {
        try {
            // Obtener todos los items del menú público activos (sin padre)
            $stmt = $pdo->query("
                SELECT * FROM ecommerce_menu_publico 
                WHERE activo = 1 AND padre_id IS NULL AND mostrar_en_navbar = 1
                ORDER BY orden
            ");
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                // Si no hay items configurados, retornar false para usar menú antiguo
                return false;
            }

            return $items;
        } catch (Exception $e) {
            // Si hay error, retornar false para usar menú antiguo
            return false;
        }
    }
}

// Variable global para indicar si usar menú dinámico
$USE_DYNAMIC_PUBLIC_MENU = false;

// Verificar si la tabla de menú público existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_menu_publico'");
    if ($stmt->rowCount() > 0) {
        $USE_DYNAMIC_PUBLIC_MENU = true;
    }
} catch (Exception $e) {
    $USE_DYNAMIC_PUBLIC_MENU = false;
}
?>
