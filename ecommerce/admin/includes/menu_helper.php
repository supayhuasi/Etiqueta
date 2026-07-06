<?php
/**
 * Helper para cargar el menú dinámicamente desde la BD
 * Se incluye en header.php para renderizar el menú del admin
 */

if (!function_exists('render_menu_dinamico')) {
    function render_menu_dinamico($pdo, $can_access, $admin_url, $role, $relative_root, $public_base) {
        try {
            // Obtener todas las secciones activas
            $stmt = $pdo->query("
                SELECT * FROM ecommerce_menu_configuracion 
                WHERE activo = 1
                ORDER BY orden
            ");
            $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($secciones)) {
                echo "<!-- No hay secciones de menú activas -->";
                return;
            }

            foreach ($secciones as $seccion) {
                // Verificar permisos de sección
                $permisos = json_decode($seccion['permisos'], true) ?? [];
                $tiene_acceso = false;

                if (in_array('*', $permisos)) {
                    $tiene_acceso = true;
                } else {
                    foreach ($permisos as $permiso) {
                        if ($can_access($permiso)) {
                            $tiene_acceso = true;
                            break;
                        }
                    }
                }

                if (!$tiene_acceso) {
                    continue;
                }

                // Obtener items de la sección
                $stmt = $pdo->prepare("
                    SELECT * FROM ecommerce_menu_items 
                    WHERE seccion_id = ? AND activo = 1
                    ORDER BY orden
                ");
                $stmt->execute([$seccion['id']]);
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Renderizar sección
                ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menu<?= htmlspecialchars($seccion['seccion']) ?>" title="<?= htmlspecialchars($seccion['titulo'] ?? $seccion['label']) ?>">
                        <span>
                            <i class="<?= htmlspecialchars($seccion['icono'] ?? 'bi bi-box') ?>"></i>
                            <span class="menu-label"><?= htmlspecialchars($seccion['label']) ?></span>
                        </span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menu<?= htmlspecialchars($seccion['seccion']) ?>">
                        <?php foreach ($items as $item): ?>
                            <?php
                            // Verificar permisos del item
                            if ($item['permiso'] && !$can_access($item['permiso'])) {
                                continue;
                            }

                            // Preparar URL
                            $url = $item['url'];
                            if (strpos($url, 'sueldos/') !== false || strpos($url, 'cheques/') !== false || strpos($url, 'gastos/') !== false || strpos($url, 'asistencias/') !== false) {
                                // URLs especiales que pueden estar en subdirectorios
                                $url = $admin_url . $item['url'];
                            } elseif (strpos($url, '/') === 0) {
                                // URLs absolutas
                                $url = $item['url'];
                            } else {
                                // URLs relativas
                                $url = $admin_url . $item['url'];
                            }

                            // Determinar si está activo
                            $current_file = basename($_SERVER['PHP_SELF']);
                            $item_file = basename($item['url']);
                            $is_active = $current_file === $item_file || 
                                       strpos($_SERVER['REQUEST_URI'], rtrim($item['url'], '/')) !== false;
                            ?>
                            <a href="<?= htmlspecialchars($url) ?>" class="<?= $is_active ? 'active' : '' ?>">
                                <i class="<?= htmlspecialchars($item['icono'] ?? 'bi bi-box') ?>"></i>
                                <?= htmlspecialchars($item['titulo']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php
            }
        } catch (Exception $e) {
            // Si hay error, no mostrar nada (el menú hardcodeado será un fallback)
            // error_log("Error al cargar menú dinámico: " . $e->getMessage());
        }
    }
}

// Variable global para indicar si usar menú dinámico
$USE_DYNAMIC_MENU = true;

// Verificar si las tablas de menú existen
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_menu_configuracion'");
    if ($stmt->rowCount() === 0) {
        $USE_DYNAMIC_MENU = false;
    }
} catch (Exception $e) {
    $USE_DYNAMIC_MENU = false;
}
?>
