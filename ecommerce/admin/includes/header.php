<?php
// Usar el auth del sistema principal
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require '../../config.php';

// Verificar que esté logueado
if (!isset($_SESSION['user'])) {
    header("Location: ../../auth/login.php");
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
            <li><a class="dropdown-item" href="../../cambiar_clave.php">🔑 Cambiar Contraseña</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="../../auth/logout.php">🚪 Salir</a></li>
        </ul>
    </div>
</div>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar">
            <h5 class="text-white mb-4">📊 Menú</h5>
            <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>">📈 Inicio</a>
            <a href="dashboard.php" class="<?= basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : '' ?>">📊 Tablero de Control</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">CATÁLOGO</p>
            <a href="categorias.php" class="<?= basename($_SERVER['PHP_SELF']) === 'categorias.php' ? 'active' : '' ?>">📁 Categorías</a>
            <a href="productos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'productos.php' ? 'active' : '' ?>">📦 Productos</a>
            <a href="matriz_precios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'matriz_precios.php' ? 'active' : '' ?>">📏 Matriz de Precios</a>
            <a href="listas_precios.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['listas_precios.php', 'listas_precios_crear.php', 'listas_precios_editar.php', 'listas_precios_items.php', 'listas_precios_items_agregar.php', 'listas_precios_categorias.php']) ? 'active' : '' ?>">💰 Listas de Precios</a>
            <a href="precios_ecommerce.php" class="<?= basename($_SERVER['PHP_SELF']) === 'precios_ecommerce.php' ? 'active' : '' ?>">🛍️ Precios Ecommerce</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">EMPRESA</p>
            <a href="empresa.php" class="<?= basename($_SERVER['PHP_SELF']) === 'empresa.php' ? 'active' : '' ?>">🏪 Información</a>
            <a href="mp_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mp_config.php' ? 'active' : '' ?>">💳 Mercado Pago</a>
            <a href="inventario.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['inventario.php', 'inventario_reporte_reponer.php', 'inventario_movimientos.php']) ? 'active' : '' ?>">📦 Inventario</a>
            <a href="pedidos.php" class="<?= basename($_SERVER['PHP_SELF']) === 'pedidos.php' ? 'active' : '' ?>">📋 Pedidos</a>
            <a href="ordenes_produccion.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ordenes_produccion.php' ? 'active' : '' ?>">🏭 Órdenes de Producción</a>
            <a href="facturacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'facturacion_clientes.php' ? 'active' : '' ?>">💳 Facturación</a>
            <a href="cotizaciones.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizaciones.php', 'cotizacion_crear.php', 'cotizacion_detalle.php', 'cotizacion_editar.php']) ? 'active' : '' ?>">💼 Cotizaciones</a>
            <a href="cotizacion_clientes.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cotizacion_clientes.php', 'cotizacion_clientes_crear.php']) ? 'active' : '' ?>">👥 Clientes Cotización</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">COMPRAS</p>
            <a href="proveedores.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['proveedores.php', 'proveedores_crear.php']) ? 'active' : '' ?>">🏭 Proveedores</a>
            <a href="compras.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['compras.php', 'compras_crear.php', 'compras_detalle.php']) ? 'active' : '' ?>">🧾 Compras</a>
            <a href="inventario_ajustes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'inventario_ajustes.php' ? 'active' : '' ?>">⚙️ Ajustes de Inventario</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">RECURSOS HUMANOS</p>
            <a href="sueldos/sueldos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['sueldos.php', 'pagar_sueldo.php', 'sueldo_editar.php', 'sueldo_conceptos.php', 'sueldo_recibo.php']) ? 'active' : '' ?>">💰 Sueldos</a>
            <a href="sueldos/plantillas.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['plantillas.php', 'plantillas_crear.php', 'plantillas_editar.php', 'plantillas_items.php']) ? 'active' : '' ?>">📋 Plantillas</a>
            <a href="asistencias/asistencias.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['asistencias.php', 'asistencias_crear.php', 'asistencias_editar.php', 'asistencias_reporte.php', 'asistencias_horarios.php']) ? 'active' : '' ?>">📌 Asistencias</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">FINANZAS</p>
            <a href="cheques/cheques.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['cheques.php', 'cheques_crear.php', 'cheques_editar.php', 'cheques_pagar.php']) ? 'active' : '' ?>">🏦 Cheques</a>
            <a href="gastos/gastos.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['gastos.php', 'gastos_crear.php', 'gastos_editar.php', 'tipos_gastos.php']) ? 'active' : '' ?>">💸 Gastos</a>
            <hr class="bg-white">
            <p class="text-white-50 small mb-3">SISTEMA</p>
            <div class="ms-2">
                <a href="../../index.php">🏠 Inicio Principal</a>
                <a href="../../scan.php">🔍 Escaneo</a>
                <a href="../../dashboard.php">📊 Dashboard</a>
                <a href="../../usuarios_lista.php">👥 Usuarios - Listar</a>
                <a href="../../usuarios_crear.php">➕ Usuarios - Crear</a>
                <a href="../../roles_usuarios.php">🛡️ Usuarios - Roles</a>
            </div>
            <hr class="bg-white">
            <a href="../index.php" class="mt-3">🔗 Ir a Tienda</a>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">
