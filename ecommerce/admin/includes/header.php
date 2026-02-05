<?php
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
// Esto siempre funciona correctamente desde cualquier ubicación
$admin_url = '/ecommerce/admin/';  // URL absoluta desde raíz del servidor

// Verificar que esté logueado
if (!isset($_SESSION['user'])) {
    // Redirigir al login del admin (en /ecommerce/admin/auth/login.php)
    header("Location: /ecommerce/admin/auth/login.php");
    exit;
}

// Verificar que sea admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado. Solo administradores pueden acceder.");
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
                    $stmt_logo = $pdo->query("SELECT logo FROM empresa WHERE id = 1");
                    $empresa = $stmt_logo->fetch(PDO::FETCH_ASSOC);
                    if (!empty($empresa['logo'])):
                ?>
                    <img src="/ecommerce/uploads/empresa/<?= htmlspecialchars($empresa['logo']) ?>" alt="Logo" class="img-fluid">
                <?php 
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
                <div class="menu-section">
                    <a href="<?= $admin_url ?>index.php" class="menu-header <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? '' : 'collapsed' ?>" style="cursor: default;">
                        <span><i class="bi bi-house-door"></i> Inicio</span>
                    </a>
                </div>

                <!-- Catálogo -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuCatalogo">
                        <span><i class="bi bi-box-seam"></i> Catálogo</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuCatalogo">
                        <a href="<?= $admin_url ?>categorias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>"><i class="bi bi-folder"></i> Categorías</a>
                        <a href="<?= $admin_url ?>productos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : '' ?>"><i class="bi bi-box"></i> Productos</a>
                        <a href="<?= $admin_url ?>matriz_precios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'matriz_precios.php' ? 'active' : '' ?>"><i class="bi bi-table"></i> Matriz de Precios</a>
                        <a href="<?= $admin_url ?>listas_precios.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['listas_precios.php', 'listas_precios_crear.php', 'listas_precios_editar.php']) ? 'active' : '' ?>"><i class="bi bi-currency-dollar"></i> Listas de Precios</a>
                        <a href="<?= $admin_url ?>precios_ecommerce.php" class="<?= basename($_SERVER['PHP_SELF']) === 'precios_ecommerce.php' ? 'active' : '' ?>"><i class="bi bi-cart"></i> Precios Ecommerce</a>
                    </div>
                </div>

                <!-- Empresa -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuEmpresa">
                        <span><i class="bi bi-building"></i> Empresa</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuEmpresa">
                        <a href="<?= $admin_url ?>empresa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'empresa.php' ? 'active' : '' ?>"><i class="bi bi-info-circle"></i> Información</a>
                        <a href="<?= $admin_url ?>trabajos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'trabajos.php' ? 'active' : '' ?>"><i class="bi bi-images"></i> Trabajos Realizados</a>
                        <a href="<?= $admin_url ?>mp_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mp_config.php' ? 'active' : '' ?>"><i class="bi bi-credit-card"></i> Mercado Pago</a>
                    </div>
                </div>

                <!-- Ventas -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuVentas">
                        <span><i class="bi bi-cart-check"></i> Ventas</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuVentas">
                        <a href="<?= $admin_url ?>pedidos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>"><i class="bi bi-receipt"></i> Pedidos</a>
                        <a href="<?= $admin_url ?>ordenes_produccion.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ordenes_produccion.php' ? 'active' : '' ?>"><i class="bi bi-gear"></i> Órdenes de Producción</a>
                        <a href="<?= $admin_url ?>facturacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'facturacion_clientes.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Facturación</a>
                        <a href="<?= $admin_url ?>cotizaciones.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizaciones.php', 'cotizacion_crear.php', 'cotizacion_detalle.php']) ? 'active' : '' ?>"><i class="bi bi-file-earmark-richtext"></i> Cotizaciones</a>
                        <a href="<?= $admin_url ?>cotizacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cotizacion_clientes.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> Clientes Cotización</a>
                    </div>
                </div>

                <!-- Compras e Inventario -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuCompras">
                        <span><i class="bi bi-bag"></i> Compras e Inventario</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuCompras">
                        <a href="<?= $admin_url ?>inventario.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['inventario.php', 'inventario_movimientos.php']) ? 'active' : '' ?>"><i class="bi bi-boxes"></i> Inventario</a>
                        <a href="<?= $admin_url ?>proveedores.php" class="<?= basename($_SERVER['PHP_SELF']) === 'proveedores.php' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Proveedores</a>
                        <a href="<?= $admin_url ?>compras.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['compras.php', 'compras_crear.php', 'compras_detalle.php']) ? 'active' : '' ?>"><i class="bi bi-basket"></i> Compras</a>
                        <a href="<?= $admin_url ?>inventario_ajustes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventario_ajustes.php' ? 'active' : '' ?>"><i class="bi bi-sliders"></i> Ajustes de Inventario</a>
                    </div>
                </div>

                <!-- Recursos Humanos -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuRRHH">
                        <span><i class="bi bi-person-badge"></i> Recursos Humanos</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuRRHH">
                        <a href="<?= $admin_url ?>sueldos/sueldos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['sueldos.php', 'pagar_sueldo.php']) ? 'active' : '' ?>"><i class="bi bi-cash-coin"></i> Sueldos</a>
                        <a href="<?= $admin_url ?>sueldos/plantillas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'plantillas.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark"></i> Plantillas</a>
                        <a href="<?= $admin_url ?>asistencias/asistencias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'asistencias.php' ? 'active' : '' ?>"><i class="bi bi-calendar-check"></i> Asistencias</a>
                    </div>
                </div>

                <!-- Finanzas -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuFinanzas">
                        <span><i class="bi bi-cash-stack"></i> Finanzas</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuFinanzas">
                        <a href="<?= $admin_url ?>flujo_caja.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['flujo_caja.php', 'flujo_caja_ingreso.php', 'flujo_caja_egreso.php', 'flujo_caja_reportes.php', 'pagos_sueldos_parciales.php']) ? 'active' : '' ?>"><i class="bi bi-cash"></i> Flujo de Caja</a>
                        <a href="<?= $admin_url ?>cheques/cheques.php" class="<?= basename($_SERVER['PHP_SELF']) === 'cheques.php' ? 'active' : '' ?>"><i class="bi bi-credit-card-2-front"></i> Cheques</a>
                        <a href="<?= $admin_url ?>gastos/gastos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'gastos.php' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Gastos</a>
                    </div>
                </div>

                <!-- Sistema -->
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuSistema">
                        <span><i class="bi bi-gear-fill"></i> Sistema</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuSistema">
                        <a href="<?= $relative_root ?>index.php"><i class="bi bi-house"></i> Inicio Principal</a>
                        <a href="<?= $relative_root ?>scan.php"><i class="bi bi-upc-scan"></i> Escaneo</a>
                        <a href="<?= $relative_root ?>dashboard.php"><i class="bi bi-speedometer"></i> Dashboard</a>
                        <a href="<?= $relative_root ?>usuarios_lista.php"><i class="bi bi-people"></i> Usuarios</a>
                        <a href="<?= $relative_root ?>roles_usuarios.php"><i class="bi bi-shield-check"></i> Roles</a>
                    </div>
                </div>

                <!-- Botón Ir a Tienda -->
                <div class="menu-section mt-3">
                    <a href="/ecommerce/index.php" target="_blank" class="btn btn-success w-100">
                        <i class="bi bi-shop"></i> Ir a la Tienda
                    </a>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
