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
    <style>
        body {
            background-color: #f8f9fa;
        }
        .sidebar {
            background: linear-gradient(135deg, #2c3e50 0%, #1a4d7a 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .sidebar a {
            color: white;
            text-decoration: none;
            padding: 10px 15px;
            display: block;
            border-radius: 5px;
            margin-bottom: 5px;
            transition: all 0.3s ease;
        }
        .sidebar a:hover, .sidebar a.active {
            background-color: #f39c12;
            color: white;
        }
        .main-content {
            padding: 30px;
        }
        .top-navbar {
            background: linear-gradient(135deg, #2c3e50 0%, #1a4d7a 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    </style>
</head>
<body>

<div class="top-navbar">
    <h3 style="margin: 0;">🏢 Admin - Tucu Roller Ecommerce</h3>
    <div class="dropdown">
        <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            👤 <?= htmlspecialchars($_SESSION['user']['usuario']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= $admin_url ?>cambiar_clave.php">🔑 Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= $admin_url ?>auth/logout.php">🚪 Salir</a></li>
        </ul>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <h5 class="text-white mb-4">📊 Menú</h5>
            <a href="<?= $admin_url ?>index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">📈 Inicio</a>
            <a href="<?= $admin_url ?>dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">📊 Tablero de Control</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">CATÁLOGO</p>
            <a href="<?= $admin_url ?>categorias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">📁 Categorías</a>
            <a href="<?= $admin_url ?>productos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : '' ?>">📦 Productos</a>
            <a href="<?= $admin_url ?>matriz_precios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'matriz_precios.php' ? 'active' : '' ?>">📏 Matriz de Precios</a>
            <a href="<?= $admin_url ?>listas_precios.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['listas_precios.php', 'listas_precios_crear.php', 'listas_precios_editar.php', 'listas_precios_items.php', 'listas_precios_items_agregar.php', 'listas_precios_categorias.php']) ? 'active' : '' ?>">💰 Listas de Precios</a>
            <a href="<?= $admin_url ?>precios_ecommerce.php" class="<?= basename($_SERVER['PHP_SELF']) === 'precios_ecommerce.php' ? 'active' : '' ?>">🛍️ Precios Ecommerce</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">EMPRESA</p>
            <a href="<?= $admin_url ?>empresa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'empresa.php' ? 'active' : '' ?>">🏪 Información</a>
            <a href="<?= $admin_url ?>trabajos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'trabajos.php' ? 'active' : '' ?>">🖼️ Trabajos Realizados</a>
            <a href="<?= $admin_url ?>mp_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mp_config.php' ? 'active' : '' ?>">💳 Mercado Pago</a>
            <a href="<?= $admin_url ?>inventario.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['inventario.php', 'inventario_reporte_reponer.php', 'inventario_reporte_productos.php', 'inventario_movimientos.php']) ? 'active' : '' ?>">📦 Inventario</a>
            <a href="<?= $admin_url ?>pedidos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">📋 Pedidos</a>
            <a href="<?= $admin_url ?>ordenes_produccion.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ordenes_produccion.php' ? 'active' : '' ?>">🏭 Órdenes de Producción</a>
            <a href="<?= $admin_url ?>facturacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'facturacion_clientes.php' ? 'active' : '' ?>">💳 Facturación</a>
            <a href="<?= $admin_url ?>cotizaciones.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizaciones.php', 'cotizacion_crear.php', 'cotizacion_detalle.php', 'cotizacion_editar.php']) ? 'active' : '' ?>">💼 Cotizaciones</a>
            <a href="<?= $admin_url ?>cotizacion_clientes.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizacion_clientes.php', 'cotizacion_clientes_crear.php']) ? 'active' : '' ?>">👥 Clientes Cotización</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">COMPRAS</p>
            <a href="<?= $admin_url ?>proveedores.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['proveedores.php', 'proveedores_crear.php']) ? 'active' : '' ?>">🏭 Proveedores</a>
            <a href="<?= $admin_url ?>compras.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['compras.php', 'compras_crear.php', 'compras_detalle.php']) ? 'active' : '' ?>">🧾 Compras</a>
            <a href="<?= $admin_url ?>inventario_ajustes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventario_ajustes.php' ? 'active' : '' ?>">⚙️ Ajustes de Inventario</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">RECURSOS HUMANOS</p>
            <a href="<?= $admin_url ?>sueldos/sueldos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['sueldos.php', 'pagar_sueldo.php', 'sueldo_editar.php', 'sueldo_conceptos.php', 'sueldo_recibo.php']) ? 'active' : '' ?>">💰 Sueldos</a>
            <a href="<?= $admin_url ?>sueldos/plantillas.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['plantillas.php', 'plantillas_crear.php', 'plantillas_editar.php', 'plantillas_items.php']) ? 'active' : '' ?>">📋 Plantillas</a>
            <a href="<?= $admin_url ?>asistencias/asistencias.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['asistencias.php', 'asistencias_crear.php', 'asistencias_editar.php', 'asistencias_reporte.php', 'asistencias_horarios.php']) ? 'active' : '' ?>">📌 Asistencias</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">FINANZAS</p>
            <a href="<?= $admin_url ?>flujo_caja.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['flujo_caja.php', 'flujo_caja_ingreso.php', 'flujo_caja_egreso.php', 'flujo_caja_editar.php', 'flujo_caja_eliminar.php', 'flujo_caja_reportes.php', 'flujo_caja_importar.php', 'pagos_sueldos_parciales.php']) ? 'active' : '' ?>">💰 Flujo de Caja</a>
            <a href="<?= $admin_url ?>cheques/cheques.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cheques.php', 'cheques_crear.php', 'cheques_editar.php', 'cheques_pagar.php']) ? 'active' : '' ?>">🏦 Cheques</a>
            <a href="<?= $admin_url ?>gastos/gastos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['gastos.php', 'gastos_crear.php', 'gastos_editar.php', 'tipos_gastos.php']) ? 'active' : '' ?>">💸 Gastos</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">SISTEMA</p>
            <div class="ms-2">
                <a href="<?= $relative_root ?>index.php">🏠 Inicio Principal</a>
                <a href="<?= $relative_root ?>scan.php">🔍 Escaneo</a>
                <a href="<?= $relative_root ?>dashboard.php">📊 Dashboard</a>
                <a href="<?= $relative_root ?>usuarios_lista.php">👥 Usuarios - Listar</a>
                <a href="<?= $relative_root ?>usuarios_crear.php">➕ Usuarios - Crear</a>
                <a href="<?= $relative_root ?>roles_usuarios.php">🛡️ Usuarios - Roles</a>
            </div>
            <hr class="bg-white">
            <a href="<?= $relative_root ?>ecommerce/index.php" class="mt-3">🔗 Ir a Tienda</a>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
