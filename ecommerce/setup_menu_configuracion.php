<?php
require 'config.php';

$messages = [];

try {
    // Verificar si la tabla ya existe
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_menu_configuracion'");
    $table_exists = $stmt->rowCount() > 0;

    if (!$table_exists) {
        // Crear tabla de configuración del menú
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_menu_configuracion (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seccion VARCHAR(50) NOT NULL UNIQUE,
                icono VARCHAR(100),
                label VARCHAR(100) NOT NULL,
                titulo VARCHAR(100),
                permisos JSON,
                orden INT DEFAULT 0,
                activo BOOLEAN DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_orden (orden),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $messages[] = "✓ Tabla 'ecommerce_menu_configuracion' creada correctamente.";
    } else {
        $messages[] = "ℹ Tabla 'ecommerce_menu_configuracion' ya existe.";
    }

    // Crear tabla de items de menú
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_menu_items'");
    $items_table_exists = $stmt->rowCount() > 0;

    if (!$items_table_exists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_menu_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seccion_id INT,
                titulo VARCHAR(100) NOT NULL,
                icono VARCHAR(100),
                url VARCHAR(255),
                permiso VARCHAR(100),
                orden INT DEFAULT 0,
                activo BOOLEAN DEFAULT 1,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (seccion_id) REFERENCES ecommerce_menu_configuracion(id) ON DELETE CASCADE,
                INDEX idx_orden (orden),
                INDEX idx_activo (activo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $messages[] = "✓ Tabla 'ecommerce_menu_items' creada correctamente.";
    } else {
        $messages[] = "ℹ Tabla 'ecommerce_menu_items' ya existe.";
    }

    // Crear tabla de menú público
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_menu_publico'");
    $publico_table_exists = $stmt->rowCount() > 0;

    if (!$publico_table_exists) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_menu_publico (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(100) NOT NULL,
                url VARCHAR(255),
                icono VARCHAR(100),
                orden INT DEFAULT 0,
                activo BOOLEAN DEFAULT 1,
                mostrar_en_navbar BOOLEAN DEFAULT 1,
                es_dropdown BOOLEAN DEFAULT 0,
                padre_id INT,
                fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (padre_id) REFERENCES ecommerce_menu_publico(id) ON DELETE CASCADE,
                INDEX idx_orden (orden),
                INDEX idx_activo (activo),
                INDEX idx_padre (padre_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        $messages[] = "✓ Tabla 'ecommerce_menu_publico' creada correctamente.";
    } else {
        $messages[] = "ℹ Tabla 'ecommerce_menu_publico' ya existe.";
    }

    // Insertar configuraciones por defecto si no existen
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ecommerce_menu_configuracion");
    $result = $stmt->fetch();
    
    if ($result['count'] == 0) {
        $default_config = [
            ['dashboard', 'bi bi-house-door', 'Inicio', 'Inicio', json_encode(['*']), 0],
            ['catalogo', 'bi bi-box-seam', 'Catálogo', 'Catálogo', json_encode(['categorias', 'productos', 'matriz_precios', 'listas_precios', 'precios_ecommerce']), 1],
            ['empresa', 'bi bi-building', 'Empresa', 'Empresa', json_encode(['empresa', 'trabajos', 'slideshow', 'mp_config', 'precios_ecommerce', 'google_analytics', 'email_config', 'envio_config', 'metodos_pago', 'faq', 'blog', 'suscriptores', 'admin_mensajes']), 2],
            ['ventas', 'bi bi-cart-check', 'Ventas', 'Ventas', json_encode(['pedidos', 'ordenes_produccion', 'instalaciones', 'recordatorios', 'crm', 'facturacion_clientes', 'clientes_web', 'cotizaciones', 'cotizacion_clientes', 'descuentos', 'encuestas', 'calidad', 'ventas_reportes', 'kpis']), 3],
            ['compras', 'bi bi-bag', 'Compras e Inventario', 'Compras e Inventario', json_encode(['inventario', 'proveedores', 'compras', 'inventario_ajustes']), 4],
            ['rrhh', 'bi bi-person-badge', 'Recursos Humanos', 'Recursos Humanos', json_encode(['sueldos', 'plantillas', 'asistencias']), 5],
            ['finanzas', 'bi bi-cash-stack', 'Finanzas', 'Finanzas', json_encode(['finanzas', 'flujo_caja', 'cheques', 'gastos']), 6],
            ['sistema', 'bi bi-gear-fill', 'Sistema', 'Sistema', json_encode(['inicio_principal', 'scan', 'dashboard_principal', 'usuarios', 'roles']), 7]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO ecommerce_menu_configuracion 
            (seccion, icono, label, titulo, permisos, orden, activo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($default_config as $config) {
            $stmt->execute($config);
        }

        $messages[] = "✓ Configuraciones de menú por defecto insertadas.";
    } else {
        $messages[] = "ℹ Las configuraciones de menú ya existen.";
    }

    // Verificar y crear items por defecto
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ecommerce_menu_items");
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        $default_items = [
            // Dashboard
            ['dashboard', 'Inicio', 'bi bi-house-door', '/ecommerce/admin/index.php', 'dashboard', 0],
            
            // Catálogo
            ['catalogo', 'Categorías', 'bi bi-folder', '/ecommerce/admin/categorias.php', 'categorias', 0],
            ['catalogo', 'Productos', 'bi bi-box', '/ecommerce/admin/productos.php', 'productos', 1],
            ['catalogo', 'Matriz de Precios', 'bi bi-table', '/ecommerce/admin/matriz_precios.php', 'matriz_precios', 2],
            ['catalogo', 'Listas de Precios', 'bi bi-currency-dollar', '/ecommerce/admin/listas_precios.php', 'listas_precios', 3],
            ['catalogo', 'Precios Ecommerce', 'bi bi-cart', '/ecommerce/admin/precios_ecommerce.php', 'precios_ecommerce', 4],
            
            // Empresa
            ['empresa', 'Información', 'bi bi-info-circle', '/ecommerce/admin/empresa.php', 'empresa', 0],
            ['empresa', 'Trabajos Realizados', 'bi bi-images', '/ecommerce/admin/trabajos.php', 'trabajos', 1],
            ['empresa', 'Slider Principal', 'bi bi-card-image', '/ecommerce/admin/slideshow.php', 'slideshow', 2],
            ['empresa', 'Mercado Pago', 'bi bi-credit-card', '/ecommerce/admin/mp_config.php', 'mp_config', 3],
            ['empresa', 'Link de Pago', 'bi bi-link-45deg', '/ecommerce/admin/mp_link_pago.php', 'mp_link_pago', 4],
            ['empresa', 'Preguntas Frecuentes', 'bi bi-question-circle', '/ecommerce/admin/faq.php', 'faq', 5],
            ['empresa', 'Blog', 'bi bi-journal-text', '/ecommerce/admin/blog.php', 'blog', 6],
            ['empresa', 'Envío', 'bi bi-truck', '/ecommerce/admin/envio_config.php', 'envio_config', 7],
            
            // Ventas
            ['ventas', 'Pedidos', 'bi bi-receipt', '/ecommerce/admin/pedidos.php', 'pedidos', 0],
            ['ventas', 'Órdenes de Producción', 'bi bi-gear', '/ecommerce/admin/ordenes_produccion.php', 'ordenes_produccion', 1],
            ['ventas', 'Instalaciones', 'bi bi-tools', '/ecommerce/admin/instalaciones.php', 'instalaciones', 2],
            ['ventas', 'CRM Seguimiento', 'bi bi-person-lines-fill', '/ecommerce/admin/crm.php', 'crm', 3],
            ['ventas', 'Clientes', 'bi bi-people', '/ecommerce/admin/clientes_unificado.php', 'clientes_web', 4],
            ['ventas', 'Cotizaciones', 'bi bi-file-earmark-richtext', '/ecommerce/admin/cotizaciones.php', 'cotizaciones', 5],
            
            // Compras
            ['compras', 'Inventario', 'bi bi-boxes', '/ecommerce/admin/inventario.php', 'inventario', 0],
            ['compras', 'Proveedores', 'bi bi-truck', '/ecommerce/admin/proveedores.php', 'proveedores', 1],
            ['compras', 'Compras', 'bi bi-basket', '/ecommerce/admin/compras.php', 'compras', 2],
            
            // RR.HH.
            ['rrhh', 'Sueldos', 'bi bi-cash-coin', '/ecommerce/admin/sueldos/sueldos.php', 'sueldos', 0],
            ['rrhh', 'Empleados', 'bi bi-people', '/ecommerce/admin/empleados.php', 'empleados', 1],
            
            // Finanzas
            ['finanzas', 'Estado Financiero', 'bi bi-speedometer2', '/ecommerce/admin/finanzas.php', 'finanzas', 0],
            ['finanzas', 'Flujo de Caja', 'bi bi-cash', '/ecommerce/admin/flujo_caja.php', 'flujo_caja', 1],
            ['finanzas', 'Cheques', 'bi bi-credit-card-2-front', '/ecommerce/admin/cheques/cheques.php', 'cheques', 2],
            ['finanzas', 'Gastos', 'bi bi-wallet2', '/ecommerce/admin/gastos/gastos.php', 'gastos', 3],
            
            // Sistema
            ['sistema', 'Usuarios', 'bi bi-people', '/ecommerce/admin/usuarios_lista.php', 'usuarios', 0],
            ['sistema', 'Roles', 'bi bi-shield-check', '/ecommerce/admin/roles_usuarios.php', 'roles', 1]
        ];

        // Primero obtener los IDs de las secciones
        $secciones = [];
        $stmt = $pdo->query("SELECT id, seccion FROM ecommerce_menu_configuracion");
        foreach ($stmt->fetchAll() as $row) {
            $secciones[$row['seccion']] = $row['id'];
        }

        $stmt = $pdo->prepare("
            INSERT INTO ecommerce_menu_items 
            (seccion_id, titulo, icono, url, permiso, orden, activo) 
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");

        foreach ($default_items as $item) {
            $seccion_id = $secciones[$item[0]] ?? null;
            if ($seccion_id) {
                $stmt->execute([$seccion_id, $item[1], $item[2], $item[3], $item[4], $item[5]]);
            }
        }

        $messages[] = "✓ Items de menú por defecto insertados.";
    } else {
        $messages[] = "ℹ Los items de menú ya existen.";
    }

    // Insertar menú público por defecto
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ecommerce_menu_publico");
    $result = $stmt->fetch();

    if ($result['count'] == 0) {
        $default_public_menu = [
            ['Inicio', 'index.php', 'bi bi-house-door', 0, 1],
            ['Tienda', 'tienda.php', 'bi bi-shop', 1, 1],
            ['Nosotros', 'nosotros.php', 'bi bi-info-circle', 2, 1],
            ['Contacto', 'contacto.php', 'bi bi-envelope', 3, 1],
            ['FAQ', 'faq.php', 'bi bi-question-circle', 4, 1],
            ['Distribuidores', 'distribuidores.php', 'bi bi-truck', 5, 1],
        ];

        $stmt = $pdo->prepare("
            INSERT INTO ecommerce_menu_publico 
            (titulo, url, icono, orden, activo, mostrar_en_navbar, es_dropdown, padre_id) 
            VALUES (?, ?, ?, ?, ?, 1, 0, NULL)
        ");

        foreach ($default_public_menu as $item) {
            $stmt->execute($item);
        }

        $messages[] = "✓ Items del menú público por defecto insertados.";
    } else {
        $messages[] = "ℹ Los items del menú público ya existen.";
    }

} catch (PDOException $e) {
    $messages[] = "✗ Error: " . $e->getMessage();
}

// HTML de respuesta
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup - Configuración de Menú</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">⚙️ Setup - Configuración del Menú del Ecommerce</h5>
            </div>
            <div class="card-body">
                <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-info"><?= $msg ?></div>
                <?php endforeach; ?>
                
                <hr>
                <a href="admin/menu_configuracion.php" class="btn btn-primary">Ir a Configuración del Menú</a>
                <a href="admin/index.php" class="btn btn-secondary">Volver al Admin</a>
            </div>
        </div>
    </div>
</body>
</html>
