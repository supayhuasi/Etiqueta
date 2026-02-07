<?php
if (!headers_sent()) {
    ob_start();
}
// Usar el auth del sistema principal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Resolver rutas correctamente desde cualquier ubicación
// __FILE__ = /path/to/ecommerce/admin/includes/header.php
// Necesitamos llegar a /path/to/config.php (4 niveles arriba)
$base_path = dirname(dirname(dirname(dirname(__FILE__))));
require $base_path . '/config.php';

// Crear variables para las rutas relativas en HTML
// $relative_root: cantidad de ../ para llegar a la raíz del proyecto
// $admin_url: URL base del admin para usar en enlaces (absoluta desde la raíz del servidor)

$current_dir = substr(str_replace(realpath($_SERVER['DOCUMENT_ROOT']), '', realpath(dirname($_SERVER['PHP_SELF']))), 1);
$depth = substr_count($current_dir, '/');
$relative_root = str_repeat('../', $depth);

// Usar URL absoluta desde el servidor para evitar problemas con rutas relativas
// Detectar automáticamente la base del admin (funciona con /ecommerce/admin o /admin)
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';
$admin_url = '/ecommerce/admin/'; // fallback
$public_base = '/ecommerce'; // fallback
if ($script_path) {
    if (strpos($script_path, '/admin/') !== false) {
        $admin_url = preg_replace('#/admin/.*$#', '/admin/', $script_path);
        $public_base = rtrim(preg_replace('#/admin/.*$#', '', $script_path), '/');
    } else {
        $admin_url = rtrim(dirname($script_path), '/\\') . '/';
        $public_base = rtrim(dirname($script_path), '/\\');
    }
}
if ($public_base === '') {
    $public_base = '';
}

// Verificar que esté logueado
if (!isset($_SESSION['user'])) {
    // Redirigir al login del admin usando la base detectada
    header("Location: {$admin_url}auth/login.php");
    exit;
}

// Verificar que tenga rol válido
if (!isset($_SESSION['rol'])) {
    die("Acceso denegado. Rol no válido.");
}

// Permisos de menú por rol
// Ajustar según tus necesidades
$role = $_SESSION['rol'] ?? 'usuario';
$role_permissions = [
    'admin' => ['*'],
    'usuario' => [
        'dashboard',
        'productos', 'categorias', 'matriz_precios', 'listas_precios', 'precios_ecommerce',
        'pedidos', 'ordenes_produccion',
        'inventario',
        'flujo_caja',
        'cheques',
        'gastos',
        'inicio_principal', 'scan', 'dashboard_principal', 'tienda'
    ],
    'operario' => [
        'dashboard',
        'ordenes_produccion',
        'inventario',
        'inicio_principal', 'scan', 'dashboard_principal', 'tienda'
    ],
    'ventas' => [
        'dashboard',
        'pedidos',
        'cotizaciones',
        'cotizacion_clientes',
        'inicio_principal', 'scan', 'dashboard_principal', 'tienda'
    ]
];

$can_access = function (string $key) use ($role_permissions, $role): bool {
    if (!isset($role_permissions[$role])) {
        return $key === 'dashboard' || $key === 'tienda' || $key === 'inicio_principal' || $key === 'dashboard_principal';
    }
    $allowed = $role_permissions[$role];
    return in_array('*', $allowed, true) || in_array($key, $allowed, true);
};

$can_access_any = function (array $keys) use ($can_access): bool {
    foreach ($keys as $key) {
        if ($can_access($key)) {
            return true;
        }
    }
    return false;
};

// Control de acceso por página
$page_permissions = [
    'index.php' => 'dashboard',
    'categorias.php' => 'categorias',
    'categorias_crear.php' => 'categorias',
    'categorias_editar.php' => 'categorias',
    'productos.php' => 'productos',
    'productos_crear.php' => 'productos',
    'productos_editar.php' => 'productos',
    'matriz_precios.php' => 'matriz_precios',
    'listas_precios.php' => 'listas_precios',
    'listas_precios_crear.php' => 'listas_precios',
    'listas_precios_editar.php' => 'listas_precios',
    'precios_ecommerce.php' => 'precios_ecommerce',
    'empresa.php' => 'empresa',
    'email_config.php' => 'email_config',
    'envio_config.php' => 'envio_config',
    'trabajos.php' => 'trabajos',
    'mp_config.php' => 'mp_config',
    'metodos_pago.php' => 'metodos_pago',
    'pedidos.php' => 'pedidos',
    'ordenes_produccion.php' => 'ordenes_produccion',
    'facturacion_clientes.php' => 'facturacion_clientes',
    'cotizaciones.php' => 'cotizaciones',
    'cotizacion_crear.php' => 'cotizaciones',
    'cotizacion_detalle.php' => 'cotizaciones',
    'descuentos.php' => 'descuentos',
    'cotizacion_clientes.php' => 'cotizacion_clientes',
    'google_analytics.php' => 'google_analytics',
    'inventario.php' => 'inventario',
    'inventario_movimientos.php' => 'inventario',
    'inventario_reporte_productos.php' => 'inventario',
    'inventario_reporte_reponer.php' => 'inventario',
    'proveedores.php' => 'proveedores',
    'compras.php' => 'compras',
    'compras_crear.php' => 'compras',
    'compras_detalle.php' => 'compras',
    'inventario_ajustes.php' => 'inventario_ajustes',
    'sueldos.php' => 'sueldos',
    'pagar_sueldo.php' => 'sueldos',
    'plantillas.php' => 'plantillas',
    'asistencias.php' => 'asistencias',
    'flujo_caja.php' => 'flujo_caja',
    'flujo_caja_ingreso.php' => 'flujo_caja',
    'flujo_caja_egreso.php' => 'flujo_caja',
    'flujo_caja_reportes.php' => 'flujo_caja',
    'pagos_sueldos_parciales.php' => 'flujo_caja',
    'cheques.php' => 'cheques',
    'cheques_crear.php' => 'cheques',
    'cheques_editar.php' => 'cheques',
    'cheques_cambiar_estado.php' => 'cheques',
    'gastos.php' => 'gastos',
    'gastos_crear.php' => 'gastos',
    'gastos_editar.php' => 'gastos',
    'gastos_cambiar_estado.php' => 'gastos',
    'usuarios_lista.php' => 'usuarios',
    'roles_usuarios.php' => 'roles'
];

$current_page = basename($_SERVER['PHP_SELF']);
if (isset($page_permissions[$current_page]) && !$can_access($page_permissions[$current_page])) {
    die("Acceso denegado. No tenés permisos para esta sección.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Tucu Roller</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(180deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            padding: 0;
            border-right: 2px solid #dee2e6;
        }
        .sidebar .logo-section {
            padding: 20px;
            text-align: center;
            background: white;
            border-bottom: 2px solid #dee2e6;
        }
        .sidebar .logo-section img {
            max-width: 150px;
            height: auto;
        }
        .sidebar-menu {
            padding: 15px 10px;
        }
        .menu-section {
            margin-bottom: 10px;
        }
        .menu-header {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
            color: #495057;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-header:hover {
            background: #007bff;
            color: white;
            transform: translateX(5px);
        }
        .menu-header.collapsed {
            background: white;
            color: #495057;
        }
        .menu-items {
            padding: 5px 0;
        }
        .menu-items a {
            color: #495057;
            text-decoration: none;
            padding: 8px 15px 8px 35px;
            display: block;
            border-radius: 5px;
            margin: 2px 0;
            transition: all 0.3s ease;
            font-size: 14px;
        }
        .menu-items a:hover {
            background-color: #e9ecef;
            color: #007bff;
            padding-left: 40px;
        }
        .menu-items a.active {
            background-color: #007bff;
            color: white;
            font-weight: 600;
        }
        .main-content {
            padding: 30px;
        }
        .top-navbar {
            background: white;
            color: #495057;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #dee2e6;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>

<div class="top-navbar">
    <h5 style="margin: 0; color: #007bff;"><i class="bi bi-speedometer2"></i> Panel de Administración</h5>
    <div class="dropdown">
        <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['user']['usuario']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $admin_url ?>cambiar_clave.php"><i class="bi bi-key"></i> Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= $admin_url ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Salir</a></li>
        </ul>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <div class="logo-section">
                <?php
                // Obtener logo de la empresa
                try {
                    $stmt_logo = $pdo->query("SELECT logo FROM ecommerce_empresa WHERE id = 1");
                    $empresa_logo = $stmt_logo->fetch(PDO::FETCH_ASSOC);
                    if (!empty($empresa_logo['logo'])):
                        $logo_filename = $empresa_logo['logo'];
                        $logo_local_path = $base_path . '/ecommerce/uploads/' . $logo_filename;
                        $logo_root_path = $base_path . '/uploads/' . $logo_filename;
                        $logo_src = null;
                        if (file_exists($logo_local_path)) {
                            $logo_src = $public_base . '/uploads/' . $logo_filename;
                        } elseif (file_exists($logo_root_path)) {
                            $logo_src = '/uploads/' . $logo_filename;
                        }
                        if ($logo_src):
                ?>
                    <img src="<?= htmlspecialchars($logo_src) ?>" alt="Logo" class="img-fluid">
                <?php 
                        else:
                ?>
                    <h4 class="text-primary mb-0">Tucu Roller</h4>
                <?php 
                        endif;
                    else:
                ?>
                    <h4 class="text-primary mb-0">Tucu Roller</h4>
                <?php 
                    endif;
                } catch (Exception $e) {
                ?>
                    <h4 class="text-primary mb-0">Tucu Roller</h4>
                <?php } ?>
            </div>
            
            <div class="sidebar-menu">
                <!-- Inicio -->
                <?php if ($can_access('dashboard')): ?>
                <div class="menu-section">
                    <a href="<?= $admin_url ?>index.php" class="menu-header <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? '' : 'collapsed' ?>" style="cursor: default;">
                        <span><i class="bi bi-house-door"></i> Inicio</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Catálogo -->
                <?php if ($can_access_any(['categorias', 'productos', 'matriz_precios', 'listas_precios', 'precios_ecommerce'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuCatalogo">
                        <span><i class="bi bi-box-seam"></i> Catálogo</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuCatalogo">
                        <?php if ($can_access('categorias')): ?>
                        <a href="<?= $admin_url ?>categorias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>"><i class="bi bi-folder"></i> Categorías</a>
                        <?php endif; ?>
                        <?php if ($can_access('productos')): ?>
                        <a href="<?= $admin_url ?>productos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : '' ?>"><i class="bi bi-box"></i> Productos</a>
                        <?php endif; ?>
                        <?php if ($can_access('matriz_precios')): ?>
                        <a href="<?= $admin_url ?>matriz_precios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'matriz_precios.php' ? 'active' : '' ?>"><i class="bi bi-table"></i> Matriz de Precios</a>
                        <?php endif; ?>
                        <?php if ($can_access('listas_precios')): ?>
                        <a href="<?= $admin_url ?>listas_precios.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['listas_precios.php', 'listas_precios_crear.php', 'listas_precios_editar.php']) ? 'active' : '' ?>"><i class="bi bi-currency-dollar"></i> Listas de Precios</a>
                        <?php endif; ?>
                        <?php if ($can_access('precios_ecommerce')): ?>
                        <a href="<?= $admin_url ?>precios_ecommerce.php" class="<?= basename($_SERVER['PHP_SELF']) === 'precios_ecommerce.php' ? 'active' : '' ?>"><i class="bi bi-cart"></i> Precios Ecommerce</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Empresa -->
                <?php if ($can_access_any(['empresa', 'trabajos', 'mp_config', 'google_analytics', 'email_config', 'envio_config', 'metodos_pago'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuEmpresa">
                        <span><i class="bi bi-building"></i> Empresa</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuEmpresa">
                        <?php if ($can_access('empresa')): ?>
                        <a href="<?= $admin_url ?>empresa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'empresa.php' ? 'active' : '' ?>"><i class="bi bi-info-circle"></i> Información</a>
                        <?php endif; ?>
                        <?php if ($can_access('trabajos')): ?>
                        <a href="<?= $admin_url ?>trabajos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'trabajos.php' ? 'active' : '' ?>"><i class="bi bi-images"></i> Trabajos Realizados</a>
                        <?php endif; ?>
                        <?php if ($can_access('mp_config')): ?>
                        <a href="<?= $admin_url ?>mp_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mp_config.php' ? 'active' : '' ?>"><i class="bi bi-credit-card"></i> Mercado Pago</a>
                        <?php endif; ?>
                        <?php if ($can_access('metodos_pago')): ?>
                        <a href="<?= $admin_url ?>metodos_pago.php" class="<?= basename($_SERVER['PHP_SELF']) === 'metodos_pago.php' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Métodos de Pago</a>
                        <?php endif; ?>
                        <?php if ($can_access('google_analytics')): ?>
                        <a href="<?= $admin_url ?>google_analytics.php" class="<?= basename($_SERVER['PHP_SELF']) === 'google_analytics.php' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Google Analytics</a>
                        <?php endif; ?>
                        <?php if ($can_access('email_config')): ?>
                        <a href="<?= $admin_url ?>email_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'email_config.php' ? 'active' : '' ?>"><i class="bi bi-envelope"></i> Email (SMTP)</a>
                        <?php endif; ?>
                        <?php if ($can_access('envio_config')): ?>
                        <a href="<?= $admin_url ?>envio_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'envio_config.php' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Envío</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ventas -->
                <?php if ($can_access_any(['pedidos', 'ordenes_produccion', 'facturacion_clientes', 'cotizaciones', 'cotizacion_clientes', 'descuentos'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuVentas">
                        <span><i class="bi bi-cart-check"></i> Ventas</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuVentas">
                        <?php if ($can_access('pedidos')): ?>
                        <a href="<?= $admin_url ?>pedidos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Pedidos</a>
                        <?php endif; ?>
                        <?php if ($can_access('ordenes_produccion')): ?>
                        <a href="<?= $admin_url ?>ordenes_produccion.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ordenes_produccion.php' ? 'active' : '' ?>"><i class="bi bi-gear"></i> Órdenes de Producción</a>
                        <?php endif; ?>
                        <?php if ($can_access('facturacion_clientes')): ?>
                        <a href="<?= $admin_url ?>facturacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'facturacion_clientes.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Facturación</a>
                        <?php endif; ?>
                        <?php if ($can_access('cotizaciones')): ?>
                        <a href="<?= $admin_url ?>cotizaciones.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizaciones.php', 'cotizacion_crear.php', 'cotizacion_detalle.php']) ? 'active' : '' ?>"><i class="bi bi-file-earmark-richtext"></i> Cotizaciones</a>
                        <?php endif; ?>
                        <?php if ($can_access('cotizacion_clientes')): ?>
                        <a href="<?= $admin_url ?>cotizacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cotizacion_clientes.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> Clientes Cotización</a>
                        <?php endif; ?>
                        <?php if ($can_access('descuentos')): ?>
                        <a href="<?= $admin_url ?>descuentos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'descuentos.php' ? 'active' : '' ?>"><i class="bi bi-percent"></i> Descuentos</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Compras e Inventario -->
                <?php if ($can_access_any(['inventario', 'proveedores', 'compras', 'inventario_ajustes'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuCompras">
                        <span><i class="bi bi-bag"></i> Compras e Inventario</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuCompras">
                        <?php if ($can_access('inventario')): ?>
                        <a href="<?= $admin_url ?>inventario.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['inventario.php', 'inventario_movimientos.php']) ? 'active' : '' ?>"><i class="bi bi-boxes"></i> Inventario</a>
                        <?php endif; ?>
                        <?php if ($can_access('proveedores')): ?>
                        <a href="<?= $admin_url ?>proveedores.php" class="<?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Proveedores</a>
                        <?php endif; ?>
                        <?php if ($can_access('compras')): ?>
                        <a href="<?= $admin_url ?>compras.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['compras.php', 'compras_crear.php', 'compras_detalle.php']) ? 'active' : '' ?>"><i class="bi bi-basket"></i> Compras</a>
                        <?php endif; ?>
                        <?php if ($can_access('inventario_ajustes')): ?>
                        <a href="<?= $admin_url ?>inventario_ajustes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventario_ajustes.php' ? 'active' : '' ?>"><i class="bi bi-sliders"></i> Ajustes de Inventario</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recursos Humanos -->
                <?php if ($can_access_any(['sueldos', 'plantillas', 'asistencias'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuRRHH">
                        <span><i class="bi bi-person-badge"></i> Recursos Humanos</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuRRHH">
                        <?php if ($can_access('sueldos')): ?>
                        <a href="<?= $admin_url ?>sueldos/sueldos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['sueldos.php', 'pagar_sueldo.php']) ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> Sueldos</a>
                        <?php endif; ?>
                        <?php if ($can_access('plantillas')): ?>
                        <a href="<?= $admin_url ?>sueldos/plantillas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'plantillas.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark"></i> Plantillas</a>
                        <?php endif; ?>
                        <?php if ($can_access('asistencias')): ?>
                        <a href="<?= $admin_url ?>asistencias/asistencias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'asistencias.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i> Asistencias</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Finanzas -->
                <?php if ($can_access_any(['flujo_caja', 'cheques', 'gastos'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuFinanzas">
                        <span><i class="bi bi-cash-stack"></i> Finanzas</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuFinanzas">
                        <?php if ($can_access('flujo_caja')): ?>
                        <a href="<?= $admin_url ?>flujo_caja.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['flujo_caja.php', 'flujo_caja_ingreso.php', 'flujo_caja_egreso.php', 'flujo_caja_reportes.php', 'pagos_sueldos_parciales.php']) ? 'active' : '' ?>"><i class="bi bi-cash"></i> Flujo de Caja</a>
                        <?php endif; ?>
                        <?php if ($can_access('cheques')): ?>
                        <a href="<?= $admin_url ?>cheques/cheques.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cheques.php' ? 'active' : '' ?>"><i class="bi bi-credit-card-2-front"></i> Cheques</a>
                        <?php endif; ?>
                        <?php if ($can_access('gastos')): ?>
                        <a href="<?= $admin_url ?>gastos/gastos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'gastos.php' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Gastos</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Sistema -->
                <?php if ($can_access_any(['inicio_principal', 'scan', 'dashboard_principal', 'usuarios', 'roles'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuSistema">
                        <span><i class="bi bi-gear-fill"></i> Sistema</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuSistema">
                        <?php if ($can_access('inicio_principal')): ?>
                        <a href="<?= $relative_root ?>index.php"><i class="bi bi-house"></i> Inicio Principal</a>
                        <?php endif; ?>
                        <?php if ($can_access('scan')): ?>
                        <a href="<?= $relative_root ?>scan.php"><i class="bi bi-upc-scan"></i> Escaneo</a>
                        <?php endif; ?>
                        <?php if ($can_access('dashboard_principal')): ?>
                        <a href="<?= $relative_root ?>dashboard.php"><i class="bi bi-speedometer"></i> Dashboard</a>
                        <?php endif; ?>
                        <?php if ($can_access('usuarios')): ?>
                        <a href="<?= $admin_url ?>usuarios_lista.php"><i class="bi bi-people"></i> Usuarios</a>
                        <?php endif; ?>
                        <?php if ($can_access('roles')): ?>
                        <a href="<?= $admin_url ?>roles_usuarios.php"><i class="bi bi-shield-check"></i> Roles</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Botón Ir a Tienda -->
                <?php if ($can_access('tienda')): ?>
                <div class="menu-section mt-3">
                    <a href="/ecommerce/index.php" target="_blank" class="btn btn-success w-100">
                        <i class="bi bi-shop"></i> Ir a la Tienda
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
