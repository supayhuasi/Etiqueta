<?php
if (!headers_sent()) {
    ob_start();
}

if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
    header("Content-Security-Policy: frame-ancestors 'self'");
}

// Usar el auth del sistema principal
if (session_status() === PHP_SESSION_NONE) {
    $is_https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $is_https,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        ini_set('session.cookie_secure', $is_https ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/');
    }

    session_start();

    if (empty($_SESSION['__session_initialized'])) {
        session_regenerate_id(true);
        $_SESSION['__session_initialized'] = time();
    }
}

if (!function_exists('admin_h')) {
    function admin_h($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_csrf_token')) {
    function admin_csrf_token()
    {
        if (empty($_SESSION['admin_csrf_token'])) {
            $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['admin_csrf_token'];
    }
}

if (!function_exists('admin_validate_csrf')) {
    function admin_validate_csrf($token)
    {
        if (empty($_SESSION['admin_csrf_token']) || $token === null) {
            return false;
        }

        if (function_exists('hash_equals')) {
            return hash_equals($_SESSION['admin_csrf_token'], $token);
        }

        return $_SESSION['admin_csrf_token'] === $token;
    }
}

if (!function_exists('admin_require_csrf_post')) {
    function admin_require_csrf_post()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        if (!admin_validate_csrf($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            die('Solicitud inválida (CSRF).');
        }
    }
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
        'pedidos', 'ordenes_produccion', 'instalaciones', 'visitas',
        'clientes_web',
        'inventario',
        'finanzas',
        'flujo_caja',
        'cheques',
        'gastos',
        'encuestas',
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
        'ordenes_produccion',
        'instalaciones',
        'visitas',
        'cotizaciones',
        'cotizacion_clientes',
        'clientes_web',
        'encuestas',
        'ventas_reportes',
        'inicio_principal', 'scan', 'dashboard_principal', 'tienda'
    ]
];

// Agregar FAQ a roles con acceso estándar
if (isset($role_permissions['usuario']) && !in_array('faq', $role_permissions['usuario'], true)) {
    $role_permissions['usuario'][] = 'faq';
}
if (isset($role_permissions['ventas']) && !in_array('faq', $role_permissions['ventas'], true)) {
    $role_permissions['ventas'][] = 'faq';
}

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
    'faq.php' => 'faq',
    'envio_config.php' => 'envio_config',
    'trabajos.php' => 'trabajos',
    'mp_config.php' => 'mp_config',
    'mp_link_pago.php' => 'mp_config',
    'precios_horarios.php' => 'precios_ecommerce',
    'metodos_pago.php' => 'metodos_pago',
    'pedidos.php' => 'pedidos',
    'ordenes_produccion.php' => 'ordenes_produccion',
    'instalaciones.php' => 'instalaciones',
    'instalaciones_reporte_direcciones.php' => 'instalaciones',
    'instalaciones_reporte_productos.php' => 'instalaciones',
    'visitas.php' => 'visitas',
    'facturacion_clientes.php' => 'facturacion_clientes',
    'clientes_web.php' => 'clientes_web',
    'cotizaciones.php' => 'cotizaciones',
    'cotizacion_crear.php' => 'cotizaciones',
    'cotizacion_detalle.php' => 'cotizaciones',
    'descuentos.php' => 'descuentos',
    'cotizacion_clientes.php' => 'cotizacion_clientes',
    'encuestas.php' => 'encuestas',
    'encuestas_crear.php' => 'encuestas',
    'encuestas_editar.php' => 'encuestas',
    'ventas_reportes.php' => 'ventas_reportes',
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
    'finanzas.php' => 'finanzas',
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

if (!function_exists('admin_table_exists')) {
    function admin_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('admin_column_exists')) {
    function admin_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

$notificaciones_atrasos = [];
$notificaciones_atrasos_total = 0;

if ($role === 'admin') {
    try {
        if (
            admin_table_exists($pdo, 'ecommerce_ordenes_produccion')
            && admin_table_exists($pdo, 'ecommerce_pedidos')
            && admin_column_exists($pdo, 'ecommerce_ordenes_produccion', 'pedido_id')
            && admin_column_exists($pdo, 'ecommerce_ordenes_produccion', 'fecha_entrega')
            && admin_column_exists($pdo, 'ecommerce_ordenes_produccion', 'estado')
        ) {
            $sql_count = "
                SELECT COUNT(*)
                FROM ecommerce_ordenes_produccion op
                JOIN ecommerce_pedidos p ON p.id = op.pedido_id
                WHERE op.fecha_entrega IS NOT NULL
                  AND op.fecha_entrega < CURDATE()
                  AND LOWER(COALESCE(op.estado, '')) NOT IN ('terminado', 'entregado', 'cancelado')
                  AND LOWER(COALESCE(p.estado, '')) <> 'cancelado'
            ";
            $notificaciones_atrasos_total = (int)$pdo->query($sql_count)->fetchColumn();

            if ($notificaciones_atrasos_total > 0) {
                $sql_lista = "
                    SELECT
                        p.id AS pedido_id,
                        p.numero_pedido,
                        c.nombre AS cliente_nombre,
                        op.fecha_entrega,
                        op.estado,
                        DATEDIFF(CURDATE(), op.fecha_entrega) AS dias_atraso
                    FROM ecommerce_ordenes_produccion op
                    JOIN ecommerce_pedidos p ON p.id = op.pedido_id
                    LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id
                    WHERE op.fecha_entrega IS NOT NULL
                      AND op.fecha_entrega < CURDATE()
                      AND LOWER(COALESCE(op.estado, '')) NOT IN ('terminado', 'entregado', 'cancelado')
                      AND LOWER(COALESCE(p.estado, '')) <> 'cancelado'
                    ORDER BY op.fecha_entrega ASC
                    LIMIT 8
                ";
                $notificaciones_atrasos = $pdo->query($sql_lista)->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }
    } catch (Throwable $e) {
        $notificaciones_atrasos = [];
        $notificaciones_atrasos_total = 0;
    }
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
        :root {
            --admin-bg: #f3f6fb;
            --admin-surface: #ffffff;
            --admin-border: #e6ebf2;
            --admin-text: #1f2a37;
            --admin-muted: #6b7280;
            --admin-primary: #2563eb;
            --admin-primary-soft: #eff6ff;
            --admin-shadow: 0 10px 28px rgba(15, 23, 42, 0.08);
            --admin-radius: 14px;
        }
        body {
            background: radial-gradient(circle at top right, #eef4ff 0%, var(--admin-bg) 45%, #f8fafc 100%);
            color: var(--admin-text);
        }
        .sidebar {
            background: linear-gradient(180deg, #f8fbff 0%, #eef2f7 100%);
            min-height: 100vh;
            padding: 0;
            border-right: 1px solid var(--admin-border);
            box-shadow: inset -1px 0 0 rgba(255, 255, 255, 0.7);
            position: sticky;
            top: 0;
            max-height: 100vh;
            overflow-y: auto;
        }
        .sidebar .logo-section {
            padding: 20px;
            text-align: center;
            background: var(--admin-surface);
            border-bottom: 1px solid var(--admin-border);
        }
        .sidebar .logo-section img {
            max-width: 150px;
            height: auto;
        }
        .sidebar-menu {
            padding: 15px 10px;
            padding-bottom: 90px;
        }
        .menu-section {
            margin-bottom: 10px;
        }
        .menu-header {
            background: var(--admin-surface);
            border: 1px solid var(--admin-border);
            border-radius: 10px;
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            font-weight: 600;
            color: var(--admin-text);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .menu-header span i {
            margin-right: 8px;
            opacity: 0.9;
        }
        .menu-header .bi-chevron-down {
            transition: transform 0.2s ease;
            opacity: 0.75;
        }
        .menu-header:not(.collapsed) .bi-chevron-down {
            transform: rotate(180deg);
        }
        .menu-header:hover {
            background: var(--admin-primary-soft);
            color: var(--admin-primary);
            border-color: #cfe0ff;
            transform: translateX(3px);
        }
        .menu-header.collapsed {
            background: var(--admin-surface);
            color: var(--admin-text);
        }
        .menu-items {
            padding: 8px 6px 4px;
            margin-top: 4px;
            border-left: 2px solid #e4ecfb;
            margin-left: 8px;
        }
        .menu-items a {
            color: #4b5563;
            text-decoration: none;
            padding: 8px 15px 8px 35px;
            display: block;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.2s ease;
            font-size: 14px;
            position: relative;
        }
        .menu-items a::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #a3b7da;
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
        }
        .menu-items a:hover {
            background-color: #edf2ff;
            color: var(--admin-primary);
            padding-left: 40px;
        }
        .menu-items a:hover::before {
            background: var(--admin-primary);
        }
        .menu-items a.active {
            background-color: var(--admin-primary);
            color: #fff;
            font-weight: 600;
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.28);
        }
        .menu-items a.active::before {
            background: #fff;
        }
        .menu-section.has-active > .menu-header {
            border-color: #b7cdfc;
            background: #eff5ff;
            color: #1e40af;
        }
        .main-content {
            padding: 30px;
        }
        .top-navbar {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(6px);
            color: var(--admin-text);
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--admin-border);
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.06);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .top-navbar h5 {
            font-weight: 700;
            letter-spacing: .02em;
        }
        .top-navbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .notif-btn {
            position: relative;
            border-radius: 999px;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0;
        }
        .notif-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            font-size: .66rem;
            min-width: 18px;
            height: 18px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            background: #dc3545;
            color: #fff;
            border: 2px solid #fff;
            font-weight: 700;
        }
        .notif-dropdown {
            width: min(420px, 92vw);
            padding: 0;
            border: 1px solid var(--admin-border);
            box-shadow: var(--admin-shadow);
            border-radius: 12px;
            overflow: hidden;
        }
        .notif-header {
            padding: 10px 14px;
            font-weight: 700;
            border-bottom: 1px solid var(--admin-border);
            background: #f8fbff;
        }
        .notif-item {
            display: block;
            padding: 10px 14px;
            border-bottom: 1px solid #eef2f7;
            color: inherit;
            text-decoration: none;
        }
        .notif-item:hover {
            background: #f8fbff;
            color: inherit;
        }
        .notif-item:last-child {
            border-bottom: 0;
        }
        .notif-empty {
            padding: 14px;
            color: var(--admin-muted);
            font-size: .92rem;
        }
        .notif-alert-strip {
            background: #fff3cd;
            border-bottom: 1px solid #ffe69c;
            color: #664d03;
            padding: 8px 16px;
            font-size: .92rem;
        }
        .card {
            border: 1px solid var(--admin-border);
            border-radius: var(--admin-radius);
            box-shadow: var(--admin-shadow);
            overflow: hidden;
        }
        .card-header {
            border-bottom: 1px solid var(--admin-border);
            font-weight: 600;
            background: #f8fbff;
        }
        .btn {
            border-radius: 10px;
            font-weight: 600;
        }
        .btn-primary {
            background: linear-gradient(135deg, #2f6ff3, #1e55cf);
            border-color: #1f55cf;
            box-shadow: 0 10px 18px rgba(37, 99, 235, 0.22);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #2a64dd, #1b4dbd);
            border-color: #1b4dbd;
        }
        .table {
            --bs-table-bg: transparent;
        }
        .table thead th {
            border-bottom-width: 1px;
            color: #334155;
            font-size: .82rem;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .table tbody tr {
            border-color: #edf1f7;
        }
        .table-hover tbody tr:hover {
            background: #f7faff;
        }
        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #d9e1ec;
            box-shadow: none;
        }
        .form-control:focus,
        .form-select:focus {
            border-color: #8ab0ff;
            box-shadow: 0 0 0 .2rem rgba(37, 99, 235, 0.14);
        }
        .badge {
            border-radius: 999px;
            padding: .4em .7em;
            font-weight: 600;
        }
        @media (max-width: 992px) {
            .top-navbar {
                padding: 12px 16px;
            }
            .main-content {
                padding: 16px;
            }
            .sidebar {
                min-height: auto;
            }
            .sidebar .logo-section {
                padding: 14px;
            }
            .menu-header {
                padding: 10px 12px;
            }
            .menu-items a {
                padding: 8px 10px 8px 28px;
            }
            .btn {
                padding: .45rem .7rem;
            }
        }
    </style>
</head>
<body>

<div class="top-navbar">
    <h5 style="margin: 0; color: #007bff;"><i class="bi bi-speedometer2"></i> Panel de Administración</h5>
    <div class="top-navbar-right">
        <?php if ($role === 'admin'): ?>
            <div class="dropdown">
                <button class="btn btn-outline-danger btn-sm notif-btn" type="button" data-bs-toggle="dropdown" aria-label="Notificaciones">
                    <i class="bi bi-bell"></i>
                    <?php if ($notificaciones_atrasos_total > 0): ?>
                        <span class="notif-badge"><?= $notificaciones_atrasos_total > 99 ? '99+' : (int)$notificaciones_atrasos_total ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu dropdown-menu-end notif-dropdown">
                    <div class="notif-header">Pedidos atrasados</div>
                    <?php if (empty($notificaciones_atrasos)): ?>
                        <div class="notif-empty">No hay pedidos atrasados en este momento.</div>
                    <?php else: ?>
                        <?php foreach ($notificaciones_atrasos as $notif): ?>
                            <a class="notif-item" href="<?= $admin_url ?>orden_produccion_detalle.php?pedido_id=<?= (int)($notif['pedido_id'] ?? 0) ?>">
                                <div class="fw-semibold"><?= htmlspecialchars($notif['numero_pedido'] ?? ('Pedido #' . (int)($notif['pedido_id'] ?? 0))) ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($notif['cliente_nombre'] ?: 'Cliente sin nombre') ?></div>
                                <div class="small text-danger">
                                    Venció el <?= !empty($notif['fecha_entrega']) ? htmlspecialchars(date('d/m/Y', strtotime((string)$notif['fecha_entrega']))) : '-' ?>
                                    · <?= max(1, (int)($notif['dias_atraso'] ?? 0)) ?> día(s) de atraso
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <a class="notif-item text-primary fw-semibold" href="<?= $admin_url ?>pedidos.php">Ver todos los pedidos</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

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
</div>

<?php if ($role === 'admin' && $notificaciones_atrasos_total > 0): ?>
    <div class="notif-alert-strip">
        <i class="bi bi-exclamation-triangle-fill me-1"></i>
        Tenés <?= (int)$notificaciones_atrasos_total ?> pedido(s) atrasado(s) con fecha de entrega vencida.
    </div>
<?php endif; ?>

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
                <?php if ($can_access_any(['empresa', 'trabajos', 'mp_config', 'precios_ecommerce', 'google_analytics', 'email_config', 'envio_config', 'metodos_pago', 'faq', 'suscriptores'])): ?>
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
                        <a href="<?= $admin_url ?>mp_link_pago.php" class="<?= basename($_SERVER['PHP_SELF']) === 'mp_link_pago.php' ? 'active' : '' ?>"><i class="bi bi-link-45deg"></i> Link de Pago</a>
                        <?php endif; ?>
                        <?php if ($can_access('precios_ecommerce')): ?>
                        <a href="<?= $admin_url ?>precios_horarios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'precios_horarios.php' ? 'active' : '' ?>"><i class="bi bi-clock-history"></i> Precios por Horario</a>
                        <?php endif; ?>
                        <?php if ($can_access('metodos_pago')): ?>
                        <a href="<?= $admin_url ?>metodos_pago.php" class="<?= basename($_SERVER['PHP_SELF']) === 'metodos_pago.php' ? 'active' : '' ?>"><i class="bi bi-wallet2"></i> Métodos de Pago</a>
                        <?php endif; ?>
                        <?php if ($can_access('google_analytics')): ?>
                        <a href="<?= $admin_url ?>google_analytics.php" class="<?= basename($_SERVER['PHP_SELF']) === 'google_analytics.php' ? 'active' : '' ?>"><i class="bi bi-graph-up"></i> Google Analytics</a>
                        <?php endif; ?>
                        <?php if ($can_access('google_analytics') || $can_access('empresa')): ?>
                        <a href="<?= $admin_url ?>typebot_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'typebot_config.php' ? 'active' : '' ?>"><i class="bi bi-chat-dots"></i> Typebot</a>
                        <?php endif; ?>
                        <?php if ($can_access('email_config')): ?>
                        <a href="<?= $admin_url ?>email_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'email_config.php' ? 'active' : '' ?>"><i class="bi bi-envelope"></i> Email (SMTP)</a>
                        <?php endif; ?>
                        <?php if ($can_access('faq')): ?>
                        <a href="<?= $admin_url ?>faq.php" class="<?= basename($_SERVER['PHP_SELF']) === 'faq.php' ? 'active' : '' ?>"><i class="bi bi-question-circle"></i> Preguntas Frecuentes</a>
                        <?php endif; ?>
                        <?php if ($can_access('envio_config')): ?>
                        <a href="<?= $admin_url ?>envio_config.php" class="<?= basename($_SERVER['PHP_SELF']) === 'envio_config.php' ? 'active' : '' ?>"><i class="bi bi-truck"></i> Envío</a>
                        <?php endif; ?>
                        <?php if ($can_access('suscriptores')): ?>
                        <a href="<?= $admin_url ?>suscriptores.php" class="<?= basename($_SERVER['PHP_SELF']) === 'suscriptores.php' ? 'active' : '' ?>"><i class="bi bi-envelope-at"></i> Suscriptores</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ventas -->
                <?php if ($can_access_any(['pedidos', 'ordenes_produccion', 'instalaciones', 'visitas', 'facturacion_clientes', 'clientes_web', 'cotizaciones', 'cotizacion_clientes', 'descuentos', 'encuestas', 'ventas_reportes'])): ?>
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
                        <a href="<?= $admin_url ?>produccion_tareas_usuarios.php" class="<?= basename($_SERVER['PHP_SELF']) === 'produccion_tareas_usuarios.php' ? 'active' : '' ?>"><i class="bi bi-person-workspace"></i> Tareas por Usuario</a>
                        <?php endif; ?>
                        <?php if ($can_access('instalaciones')): ?>
                        <a href="<?= $admin_url ?>instalaciones.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['instalaciones.php', 'instalaciones_reporte_direcciones.php', 'instalaciones_reporte_productos.php']) ? 'active' : '' ?>"><i class="bi bi-tools"></i> Instalaciones</a>
                        <?php endif; ?>
                        <?php if ($can_access('visitas')): ?>
                        <a href="<?= $admin_url ?>visitas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'visitas.php' ? 'active' : '' ?>"><i class="bi bi-list-task"></i> Visitas</a>
                        <?php endif; ?>
                        <?php if ($can_access('facturacion_clientes')): ?>
                        <a href="<?= $admin_url ?>facturacion_clientes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'facturacion_clientes.php' ? 'active' : '' ?>"><i class="bi bi-file-earmark-text"></i> Facturación</a>
                        <?php endif; ?>
                        <?php if ($can_access('clientes_web')): ?>
                        <a href="<?= $admin_url ?>clientes_web.php" class="<?= basename($_SERVER['PHP_SELF']) === 'clientes_web.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> Clientes Web</a>
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
                        <?php if ($can_access('encuestas')): ?>
                        <a href="<?= $admin_url ?>encuestas.php" class="<?= in_array(basename($_SERVER['PHP_SELF']), ['encuestas.php', 'encuestas_crear.php', 'encuestas_editar.php']) ? 'active' : '' ?>"><i class="bi bi-clipboard-check"></i> Encuestas</a>
                        <?php endif; ?>
                        <?php if ($can_access('ventas_reportes')): ?>
                        <a href="<?= $admin_url ?>ventas_reportes.php" class="<?= basename($_SERVER['PHP_SELF']) === 'ventas_reportes.php' ? 'active' : '' ?>"><i class="bi bi-graph-up-arrow"></i> Reporte de Ventas</a>
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
                        <?php if ($role === 'admin'): ?>
                        <a href="<?= $admin_url ?>empleados.php" class="<?= basename($_SERVER['PHP_SELF']) === 'empleados.php' ? 'active' : '' ?>"><i class="bi bi-people"></i> Empleados</a>
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
                <?php if ($can_access_any(['finanzas', 'flujo_caja', 'cheques', 'gastos'])): ?>
                <div class="menu-section">
                    <div class="menu-header collapsed" data-bs-toggle="collapse" data-bs-target="#menuFinanzas">
                        <span><i class="bi bi-cash-stack"></i> Finanzas</span>
                        <i class="bi bi-chevron-down"></i>
                    </div>
                    <div class="collapse menu-items" id="menuFinanzas">
                        <?php if ($can_access('finanzas')): ?>
                        <a href="<?= $admin_url ?>finanzas.php" class="<?= basename($_SERVER['PHP_SELF']) === 'finanzas.php' ? 'active' : '' ?>"><i class="bi bi-speedometer2"></i> Estado Financiero</a>
                        <?php endif; ?>
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
                        <a href="<?= $public_base ?>/scan.php"><i class="bi bi-upc-scan"></i> Escaneo</a>
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
                    <a href="/index.php" target="_blank" class="btn btn-success w-100">
                        <i class="bi bi-shop"></i> Ir a la Tienda
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 main-content">

<script>
document.addEventListener('DOMContentLoaded', function () {
    const menuSections = document.querySelectorAll('.menu-section');

    menuSections.forEach(function (section) {
        const activeItem = section.querySelector('.menu-items a.active');
        if (activeItem) {
            section.classList.add('has-active');
            const collapseEl = section.querySelector('.collapse.menu-items');
            if (collapseEl) {
                collapseEl.classList.add('show');
            }
            const headerEl = section.querySelector('.menu-header[data-bs-toggle="collapse"]');
            if (headerEl) {
                headerEl.classList.remove('collapsed');
                headerEl.setAttribute('aria-expanded', 'true');
            }
        }
    });
});
</script>
