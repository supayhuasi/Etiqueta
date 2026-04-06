<?php
require 'includes/header.php';

function crm_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function crm_column_exists(PDO $pdo, string $table, string $column): bool
{
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function crm_ensure_schema(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_visitas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(180) NOT NULL,
        descripcion TEXT NULL,
        cliente_nombre VARCHAR(150) NULL,
        telefono VARCHAR(60) NULL,
        direccion VARCHAR(255) NULL,
        fecha_visita DATE NOT NULL,
        hora_visita TIME NULL,
        estado ENUM('pendiente','en_proceso','completada','cancelada') NOT NULL DEFAULT 'pendiente',
        orden_visual INT NOT NULL DEFAULT 0,
        creado_por INT NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_fecha_visita (fecha_visita),
        INDEX idx_estado (estado)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_crm_visitas (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visita_id INT NOT NULL,
        estado ENUM('nuevo','contactado','propuesta','negociacion','ganado','perdido') NOT NULL DEFAULT 'nuevo',
        prioridad ENUM('baja','media','alta','urgente') NOT NULL DEFAULT 'media',
        origen VARCHAR(80) NOT NULL DEFAULT 'visita',
        proximo_contacto DATE NULL,
        asignado_a INT NULL,
        monto_estimado DECIMAL(12,2) NOT NULL DEFAULT 0,
        notas_internas TEXT NULL,
        ultima_cotizacion_id INT NULL,
        ultima_cotizacion_numero VARCHAR(50) NULL,
        fecha_ultima_cotizacion DATETIME NULL,
        ultima_gestion DATETIME NULL,
        fecha_cierre DATE NULL,
        fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_visita_id (visita_id),
        INDEX idx_estado (estado),
        INDEX idx_prioridad (prioridad),
        INDEX idx_proximo_contacto (proximo_contacto),
        INDEX idx_asignado_a (asignado_a)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    if (!crm_column_exists($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_id')) {
        $pdo->exec("ALTER TABLE ecommerce_crm_visitas ADD COLUMN ultima_cotizacion_id INT NULL AFTER notas_internas");
    }
    if (!crm_column_exists($pdo, 'ecommerce_crm_visitas', 'ultima_cotizacion_numero')) {
        $pdo->exec("ALTER TABLE ecommerce_crm_visitas ADD COLUMN ultima_cotizacion_numero VARCHAR(50) NULL AFTER ultima_cotizacion_id");
    }
    if (!crm_column_exists($pdo, 'ecommerce_crm_visitas', 'fecha_ultima_cotizacion')) {
        $pdo->exec("ALTER TABLE ecommerce_crm_visitas ADD COLUMN fecha_ultima_cotizacion DATETIME NULL AFTER ultima_cotizacion_numero");
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_crm_seguimientos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        crm_id INT NOT NULL,
        visita_id INT NOT NULL,
        usuario_id INT NULL,
        canal ENUM('llamada','whatsapp','email','visita','cotizacion','otro') NOT NULL DEFAULT 'otro',
        resultado ENUM('pendiente','sin_respuesta','interesado','cotizado','cerrado','descartado') NOT NULL DEFAULT 'pendiente',
        comentario TEXT NOT NULL,
        proximo_contacto DATE NULL,
        fecha_contacto DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_crm_id (crm_id),
        INDEX idx_visita_id (visita_id),
        INDEX idx_resultado (resultado),
        INDEX idx_fecha_contacto (fecha_contacto),
        INDEX idx_proximo_contacto (proximo_contacto)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $initialized = true;
}

function crm_sync_from_visits(PDO $pdo): void
{
    if (!crm_table_exists($pdo, 'ecommerce_visitas') || !crm_table_exists($pdo, 'ecommerce_crm_visitas')) {
        return;
    }

    try {
        $pdo->exec("INSERT IGNORE INTO ecommerce_crm_visitas (visita_id, estado, prioridad, origen, proximo_contacto, asignado_a, fecha_creacion, fecha_actualizacion)
            SELECT
                v.id,
                CASE
                    WHEN v.estado = 'cancelada' THEN 'perdido'
                    WHEN v.estado = 'completada' THEN 'contactado'
                    ELSE 'nuevo'
                END,
                'media',
                'visita',
                v.fecha_visita,
                v.creado_por,
                COALESCE(v.fecha_creacion, NOW()),
                NOW()
            FROM ecommerce_visitas v");
    } catch (Throwable $e) {
        error_log('crm_sync_from_visits: ' . $e->getMessage());
    }
}

function crm_redirect_with_flash(string $type, string $message): void
{
    $query = $_GET;
    unset($query['ok'], $query['error']);
    $query[$type] = $message;

    $target = 'crm.php';
    $qs = http_build_query($query);
    if ($qs !== '') {
        $target .= '?' . $qs;
    }

    header('Location: ' . $target, true, 303);
    exit;
}

function crm_estado_options(): array
{
    return [
        'nuevo' => 'Nuevo lead',
        'contactado' => 'Contactado',
        'propuesta' => 'Cotizado / propuesta',
        'negociacion' => 'Negociación',
        'ganado' => 'Ganado',
        'perdido' => 'Perdido',
    ];
}

function crm_prioridad_options(): array
{
    return [
        'baja' => 'Baja',
        'media' => 'Media',
        'alta' => 'Alta',
        'urgente' => 'Urgente',
    ];
}

function crm_canal_options(): array
{
    return [
        'llamada' => 'Llamada',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'visita' => 'Visita',
        'cotizacion' => 'Cotización',
        'otro' => 'Otro',
    ];
}

function crm_resultado_options(): array
{
    return [
        'pendiente' => 'Pendiente',
        'sin_respuesta' => 'Sin respuesta',
        'interesado' => 'Interesado',
        'cotizado' => 'Cotizado',
        'cerrado' => 'Cerrado / ganado',
        'descartado' => 'Descartado',
    ];
}

function crm_estado_badge(string $estado): string
{
    $map = [
        'nuevo' => 'bg-secondary',
        'contactado' => 'bg-info text-dark',
        'propuesta' => 'bg-primary',
        'negociacion' => 'bg-warning text-dark',
        'ganado' => 'bg-success',
        'perdido' => 'bg-danger',
    ];

    return $map[$estado] ?? 'bg-secondary';
}

function crm_prioridad_badge(string $prioridad): string
{
    $map = [
        'baja' => 'bg-light text-dark',
        'media' => 'bg-secondary',
        'alta' => 'bg-warning text-dark',
        'urgente' => 'bg-danger',
    ];

    return $map[$prioridad] ?? 'bg-secondary';
}

function crm_resultado_badge(string $resultado): string
{
    $map = [
        'pendiente' => 'bg-secondary',
        'sin_respuesta' => 'bg-dark',
        'interesado' => 'bg-info text-dark',
        'cotizado' => 'bg-primary',
        'cerrado' => 'bg-success',
        'descartado' => 'bg-danger',
    ];

    return $map[$resultado] ?? 'bg-secondary';
}

function crm_format_money($value): string
{
    return '$' . number_format((float)$value, 2, ',', '.');
}

function crm_format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $ts = strtotime($date);
    if (!$ts) {
        return '—';
    }

    return date('d/m/Y', $ts);
}

function crm_format_datetime(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $ts = strtotime($date);
    if (!$ts) {
        return '—';
    }

    return date('d/m/Y H:i', $ts);
}

function crm_whatsapp_link(?string $telefono): string
{
    $digits = preg_replace('/\D+/', '', (string)$telefono);
    if ($digits === '') {
        return '';
    }
    if (strpos($digits, '54') !== 0) {
        $digits = '54' . $digits;
    }
    return 'https://wa.me/' . $digits;
}

crm_ensure_schema($pdo);
crm_sync_from_visits($pdo);

$usuario_actual_id = (int)($_SESSION['user']['id'] ?? 0);
$is_admin = (($role ?? '') === 'admin');
$estado_options = crm_estado_options();
$prioridad_options = crm_prioridad_options();
$canal_options = crm_canal_options();
$resultado_options = crm_resultado_options();

$mensaje = trim((string)($_GET['ok'] ?? ''));
$error = trim((string)($_GET['error'] ?? ''));

$usuarios = [];
try {
    $stmt = $pdo->query("SELECT id, COALESCE(NULLIF(TRIM(nombre), ''), usuario) AS nombre FROM usuarios WHERE COALESCE(activo,1) = 1 ORDER BY nombre ASC");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $usuarios = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    $accion = trim((string)($_POST['accion'] ?? ''));

    try {
        if ($accion === 'guardar_oportunidad') {
            $crm_id = (int)($_POST['crm_id'] ?? 0);
            $estado = trim((string)($_POST['estado'] ?? 'nuevo'));
            $prioridad = trim((string)($_POST['prioridad'] ?? 'media'));
            $origen = trim((string)($_POST['origen'] ?? 'visita'));
            $proximo_contacto = trim((string)($_POST['proximo_contacto'] ?? ''));
            $asignado_a = (int)($_POST['asignado_a'] ?? 0);
            $monto_estimado_raw = str_replace(',', '.', trim((string)($_POST['monto_estimado'] ?? '0')));
            $notas_internas = trim((string)($_POST['notas_internas'] ?? ''));

            if ($crm_id <= 0) {
                throw new Exception('No se encontró la oportunidad CRM.');
            }
            if (!isset($estado_options[$estado])) {
                throw new Exception('El estado CRM no es válido.');
            }
            if (!isset($prioridad_options[$prioridad])) {
                throw new Exception('La prioridad no es válida.');
            }
            if ($proximo_contacto !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximo_contacto)) {
                throw new Exception('La fecha de próximo contacto no es válida.');
            }
            if ($monto_estimado_raw === '' || !is_numeric($monto_estimado_raw)) {
                $monto_estimado_raw = '0';
            }

            if (!$is_admin) {
                $asignado_a = $usuario_actual_id > 0 ? $usuario_actual_id : 0;
            }

            $fecha_cierre = in_array($estado, ['ganado', 'perdido'], true) ? date('Y-m-d') : null;

            $stmt = $pdo->prepare("UPDATE ecommerce_crm_visitas
                SET estado = ?, prioridad = ?, origen = ?, proximo_contacto = ?, asignado_a = ?, monto_estimado = ?, notas_internas = ?, fecha_cierre = ?
                WHERE id = ?");
            $stmt->execute([
                $estado,
                $prioridad,
                $origen !== '' ? $origen : 'visita',
                $proximo_contacto !== '' ? $proximo_contacto : null,
                $asignado_a > 0 ? $asignado_a : null,
                (float)$monto_estimado_raw,
                $notas_internas !== '' ? $notas_internas : null,
                $fecha_cierre,
                $crm_id,
            ]);

            crm_redirect_with_flash('ok', 'Ficha CRM actualizada correctamente.');
        }

        if ($accion === 'agregar_seguimiento') {
            $crm_id = (int)($_POST['crm_id'] ?? 0);
            $canal = trim((string)($_POST['canal'] ?? 'otro'));
            $resultado = trim((string)($_POST['resultado'] ?? 'pendiente'));
            $comentario = trim((string)($_POST['comentario'] ?? ''));
            $proximo_contacto = trim((string)($_POST['proximo_contacto'] ?? ''));
            $nuevo_estado = trim((string)($_POST['nuevo_estado'] ?? ''));

            if ($crm_id <= 0) {
                throw new Exception('No se encontró la ficha CRM para cargar el seguimiento.');
            }
            if (!isset($canal_options[$canal])) {
                throw new Exception('El canal seleccionado no es válido.');
            }
            if (!isset($resultado_options[$resultado])) {
                throw new Exception('El resultado seleccionado no es válido.');
            }
            if ($comentario === '') {
                throw new Exception('Ingresá un comentario para registrar el seguimiento.');
            }
            if ($proximo_contacto !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximo_contacto)) {
                throw new Exception('La fecha de próximo contacto no es válida.');
            }

            $stmt = $pdo->prepare('SELECT id, visita_id, estado FROM ecommerce_crm_visitas WHERE id = ? LIMIT 1');
            $stmt->execute([$crm_id]);
            $crm_actual = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$crm_actual) {
                throw new Exception('La oportunidad CRM ya no existe.');
            }

            $stmt = $pdo->prepare("INSERT INTO ecommerce_crm_seguimientos (crm_id, visita_id, usuario_id, canal, resultado, comentario, proximo_contacto)
                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $crm_id,
                (int)$crm_actual['visita_id'],
                $usuario_actual_id > 0 ? $usuario_actual_id : null,
                $canal,
                $resultado,
                $comentario,
                $proximo_contacto !== '' ? $proximo_contacto : null,
            ]);

            if ($nuevo_estado === '') {
                $map_resultado_estado = [
                    'interesado' => 'contactado',
                    'cotizado' => 'propuesta',
                    'cerrado' => 'ganado',
                    'descartado' => 'perdido',
                ];
                $nuevo_estado = $map_resultado_estado[$resultado] ?? (string)$crm_actual['estado'];
            }

            if (!isset($estado_options[$nuevo_estado])) {
                $nuevo_estado = (string)$crm_actual['estado'];
            }

            $fecha_cierre = in_array($nuevo_estado, ['ganado', 'perdido'], true) ? date('Y-m-d') : null;

            $stmt = $pdo->prepare("UPDATE ecommerce_crm_visitas
                SET estado = ?, proximo_contacto = ?, ultima_gestion = NOW(), fecha_cierre = ?
                WHERE id = ?");
            $stmt->execute([
                $nuevo_estado,
                $proximo_contacto !== '' ? $proximo_contacto : null,
                $fecha_cierre,
                $crm_id,
            ]);

            crm_redirect_with_flash('ok', 'Seguimiento registrado correctamente.');
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$busqueda = trim((string)($_GET['q'] ?? ''));
$estado_filtro = trim((string)($_GET['estado'] ?? ''));
$prioridad_filtro = trim((string)($_GET['prioridad'] ?? ''));
$usuario_filtro = (int)($_GET['usuario_id'] ?? 0);
$solo_vencidos = isset($_GET['vencidos']) && (string)$_GET['vencidos'] !== '0';
$lead_id = (int)($_GET['lead'] ?? 0);

$where = ['1=1'];
$params = [];
if ($busqueda !== '') {
    $where[] = '(v.titulo LIKE ? OR COALESCE(v.cliente_nombre, "") LIKE ? OR COALESCE(v.telefono, "") LIKE ? OR COALESCE(v.direccion, "") LIKE ?)';
    $like = '%' . $busqueda . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($estado_filtro !== '' && isset($estado_options[$estado_filtro])) {
    $where[] = 'c.estado = ?';
    $params[] = $estado_filtro;
}
if ($prioridad_filtro !== '' && isset($prioridad_options[$prioridad_filtro])) {
    $where[] = 'c.prioridad = ?';
    $params[] = $prioridad_filtro;
}
if ($usuario_filtro > 0) {
    $where[] = 'c.asignado_a = ?';
    $params[] = $usuario_filtro;
}
if ($solo_vencidos) {
    $where[] = "c.proximo_contacto IS NOT NULL AND c.proximo_contacto < CURDATE() AND c.estado NOT IN ('ganado','perdido')";
}

$kpis = [
    'total' => 0,
    'activos' => 0,
    'vencidos' => 0,
    'hoy' => 0,
    'ganados' => 0,
    'potencial' => 0,
];
try {
    $stmt = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN estado NOT IN ('ganado','perdido') THEN 1 ELSE 0 END) AS activos,
        SUM(CASE WHEN proximo_contacto = CURDATE() AND estado NOT IN ('ganado','perdido') THEN 1 ELSE 0 END) AS hoy,
        SUM(CASE WHEN proximo_contacto IS NOT NULL AND proximo_contacto < CURDATE() AND estado NOT IN ('ganado','perdido') THEN 1 ELSE 0 END) AS vencidos,
        SUM(CASE WHEN estado = 'ganado' THEN 1 ELSE 0 END) AS ganados,
        SUM(CASE WHEN estado != 'perdido' THEN monto_estimado ELSE 0 END) AS potencial
    FROM ecommerce_crm_visitas");
    $kpis = $stmt->fetch(PDO::FETCH_ASSOC) ?: $kpis;
} catch (Throwable $e) {
    $kpis = [
        'total' => 0,
        'activos' => 0,
        'vencidos' => 0,
        'hoy' => 0,
        'ganados' => 0,
        'potencial' => 0,
    ];
}

$leads = [];
try {
    $sql = "SELECT
        c.*,
        v.titulo,
        v.descripcion AS visita_descripcion,
        v.cliente_nombre,
        v.telefono,
        v.direccion,
        v.fecha_visita,
        v.hora_visita,
        v.estado AS visita_estado,
        COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario, 'Sin asignar') AS asignado_nombre,
        (SELECT COUNT(*) FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id) AS total_seguimientos,
        (SELECT s.fecha_contacto FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultima_fecha_contacto,
        (SELECT s.resultado FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_resultado,
        (SELECT s.canal FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_canal,
        (SELECT s.comentario FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_comentario
    FROM ecommerce_crm_visitas c
    INNER JOIN ecommerce_visitas v ON v.id = c.visita_id
    LEFT JOIN usuarios u ON u.id = c.asignado_a
    WHERE " . implode(' AND ', $where) . "
    ORDER BY
        CASE WHEN c.estado IN ('ganado','perdido') THEN 1 ELSE 0 END ASC,
        CASE WHEN c.proximo_contacto IS NULL THEN 1 ELSE 0 END ASC,
        c.proximo_contacto ASC,
        FIELD(c.prioridad, 'urgente','alta','media','baja'),
        v.fecha_visita DESC,
        c.id DESC
    LIMIT 200";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $error = $error !== '' ? $error : 'No se pudo cargar el listado CRM.';
    $leads = [];
}

$lead_actual = null;
if ($lead_id <= 0 && !empty($leads)) {
    $lead_id = (int)$leads[0]['id'];
}

foreach ($leads as $lead_item) {
    if ((int)$lead_item['id'] === $lead_id) {
        $lead_actual = $lead_item;
        break;
    }
}

if (!$lead_actual && $lead_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT
            c.*,
            v.titulo,
            v.descripcion AS visita_descripcion,
            v.cliente_nombre,
            v.telefono,
            v.direccion,
            v.fecha_visita,
            v.hora_visita,
            v.estado AS visita_estado,
            COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario, 'Sin asignar') AS asignado_nombre,
            (SELECT COUNT(*) FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id) AS total_seguimientos,
            (SELECT s.fecha_contacto FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultima_fecha_contacto,
            (SELECT s.resultado FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_resultado,
            (SELECT s.canal FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_canal,
            (SELECT s.comentario FROM ecommerce_crm_seguimientos s WHERE s.crm_id = c.id ORDER BY s.fecha_contacto DESC, s.id DESC LIMIT 1) AS ultimo_comentario
        FROM ecommerce_crm_visitas c
        INNER JOIN ecommerce_visitas v ON v.id = c.visita_id
        LEFT JOIN usuarios u ON u.id = c.asignado_a
        WHERE c.id = ?
        LIMIT 1");
        $stmt->execute([$lead_id]);
        $lead_actual = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $lead_actual = null;
    }
}

$seguimientos = [];
if ($lead_actual) {
    try {
        $stmt = $pdo->prepare("SELECT s.*, COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario, 'Sistema') AS usuario_nombre
            FROM ecommerce_crm_seguimientos s
            LEFT JOIN usuarios u ON u.id = s.usuario_id
            WHERE s.crm_id = ?
            ORDER BY s.fecha_contacto DESC, s.id DESC
            LIMIT 100");
        $stmt->execute([(int)$lead_actual['id']]);
        $seguimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $seguimientos = [];
    }
}
?>

<style>
    .crm-hero {
        background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 60%, #3b82f6 100%);
        color: #fff;
        border-radius: 18px;
        padding: 1.35rem 1.4rem;
        box-shadow: 0 16px 32px rgba(29, 78, 216, 0.22);
        margin-bottom: 1.25rem;
    }
    .crm-hero .badge {
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }
    .crm-kpi {
        border: 1px solid #e6ecf5;
        border-radius: 15px;
        box-shadow: 0 8px 22px rgba(15, 23, 42, 0.05);
        height: 100%;
    }
    .crm-kpi .icon {
        width: 42px;
        height: 42px;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }
    .crm-table tbody tr.crm-row-overdue {
        background: #fff8e1;
    }
    .crm-detail-card,
    .crm-panel {
        border: 1px solid #e7edf6;
        border-radius: 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
    }
    .crm-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
        gap: .75rem;
    }
    .crm-meta-item {
        background: #f8fafc;
        border-radius: 12px;
        padding: .75rem .85rem;
        border: 1px solid #e8eef6;
    }
    .crm-meta-item small {
        display: block;
        color: #64748b;
        margin-bottom: .2rem;
    }
    .crm-timeline-item {
        position: relative;
        border-left: 3px solid #dbeafe;
        padding-left: .95rem;
        margin-left: .35rem;
        margin-bottom: 1rem;
    }
    .crm-timeline-item::before {
        content: '';
        position: absolute;
        left: -8px;
        top: 4px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #2563eb;
    }
    .crm-note {
        white-space: pre-wrap;
        word-break: break-word;
    }
    .crm-sticky {
        position: sticky;
        top: 1rem;
    }
    @media (max-width: 991px) {
        .crm-sticky {
            position: static;
        }
    }
</style>

<div class="crm-hero d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
    <div>
        <div class="d-flex flex-wrap gap-2 mb-2">
            <span class="badge rounded-pill">CRM desde visitas</span>
            <span class="badge rounded-pill">Seguimiento comercial</span>
        </div>
        <h1 class="h3 mb-1">📞 CRM de visitas</h1>
        <p class="mb-0 opacity-75">Cada visita queda convertida en una oportunidad para hacer seguimiento, cotizar y cerrar.</p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="instalaciones.php" class="btn btn-light"><i class="bi bi-calendar-check"></i> Ver visitas</a>
        <a href="cotizaciones.php" class="btn btn-outline-light"><i class="bi bi-file-earmark-richtext"></i> Cotizaciones</a>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6 col-xl-3">
        <div class="card crm-kpi">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Leads totales</div>
                    <div class="fs-4 fw-bold"><?= (int)($kpis['total'] ?? 0) ?></div>
                </div>
                <div class="icon bg-primary-subtle text-primary"><i class="bi bi-people"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card crm-kpi">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Seguimiento hoy</div>
                    <div class="fs-4 fw-bold"><?= (int)($kpis['hoy'] ?? 0) ?></div>
                </div>
                <div class="icon bg-info-subtle text-info"><i class="bi bi-calendar-day"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card crm-kpi">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Vencidos</div>
                    <div class="fs-4 fw-bold text-danger"><?= (int)($kpis['vencidos'] ?? 0) ?></div>
                </div>
                <div class="icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-xl-3">
        <div class="card crm-kpi">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small">Valor potencial</div>
                    <div class="fs-5 fw-bold"><?= htmlspecialchars(crm_format_money($kpis['potencial'] ?? 0)) ?></div>
                </div>
                <div class="icon bg-success-subtle text-success"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card crm-panel mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Filtros</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Cliente, teléfono, dirección o visita">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado CRM</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($estado_options as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $estado_filtro === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Prioridad</label>
                <select name="prioridad" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($prioridad_options as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $prioridad_filtro === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Responsable</label>
                <select name="usuario_id" class="form-select">
                    <option value="0">Todos</option>
                    <?php foreach ($usuarios as $usuario): ?>
                        <option value="<?= (int)$usuario['id'] ?>" <?= $usuario_filtro === (int)$usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill">Aplicar</button>
                <a href="crm.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
            <div class="col-12">
                <div class="form-check mt-1">
                    <input class="form-check-input" type="checkbox" value="1" id="vencidos" name="vencidos" <?= $solo_vencidos ? 'checked' : '' ?>>
                    <label class="form-check-label" for="vencidos">Mostrar solo seguimientos vencidos</label>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card crm-panel h-100">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Oportunidades generadas desde visitas</h5>
                <span class="badge bg-dark"><?= count($leads) ?> registros</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($leads)): ?>
                    <div class="p-4 text-center text-muted">
                        No hay visitas cargadas para convertir en seguimiento. Podés empezar desde <a href="instalaciones.php">Instalaciones y visitas</a>.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 crm-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Cliente / visita</th>
                                    <th>Estado</th>
                                    <th>Próximo</th>
                                    <th>Responsable</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leads as $lead): ?>
                                    <?php
                                    $esta_vencido = !empty($lead['proximo_contacto']) && $lead['proximo_contacto'] < date('Y-m-d') && !in_array($lead['estado'], ['ganado', 'perdido'], true);
                                    $es_activo = ((int)$lead['id'] === (int)$lead_id);
                                    ?>
                                    <tr class="<?= $esta_vencido ? 'crm-row-overdue' : '' ?> <?= $es_activo ? 'table-primary' : '' ?>">
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars(trim((string)($lead['cliente_nombre'] ?? '')) !== '' ? $lead['cliente_nombre'] : $lead['titulo']) ?></div>
                                            <div class="small text-muted"><?= htmlspecialchars($lead['titulo']) ?></div>
                                            <?php if (!empty($lead['telefono'])): ?>
                                                <div class="small text-muted"><i class="bi bi-telephone"></i> <?= htmlspecialchars($lead['telefono']) ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted">Visita: <?= htmlspecialchars(crm_format_date($lead['fecha_visita'] ?? null)) ?></div>
                                        </td>
                                        <td>
                                            <div class="mb-1"><span class="badge <?= crm_estado_badge((string)$lead['estado']) ?>"><?= htmlspecialchars($estado_options[$lead['estado']] ?? ucfirst((string)$lead['estado'])) ?></span></div>
                                            <div><span class="badge <?= crm_prioridad_badge((string)$lead['prioridad']) ?>"><?= htmlspecialchars($prioridad_options[$lead['prioridad']] ?? ucfirst((string)$lead['prioridad'])) ?></span></div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold <?= $esta_vencido ? 'text-danger' : '' ?>"><?= htmlspecialchars(crm_format_date($lead['proximo_contacto'] ?? null)) ?></div>
                                            <div class="small text-muted">Seg.: <?= (int)($lead['total_seguimientos'] ?? 0) ?></div>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars($lead['asignado_nombre'] ?? 'Sin asignar') ?></div>
                                            <?php if (!empty($lead['ultimo_resultado'])): ?>
                                                <div class="small"><span class="badge <?= crm_resultado_badge((string)$lead['ultimo_resultado']) ?>"><?= htmlspecialchars($resultado_options[$lead['ultimo_resultado']] ?? $lead['ultimo_resultado']) ?></span></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <a href="crm.php?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['lead' => (int)$lead['id']])), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-outline-primary">Abrir</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="crm-sticky">
            <div class="card crm-detail-card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Ficha del lead</h5>
                    <?php if ($lead_actual): ?>
                        <span class="badge <?= crm_estado_badge((string)$lead_actual['estado']) ?>"><?= htmlspecialchars($estado_options[$lead_actual['estado']] ?? $lead_actual['estado']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (!$lead_actual): ?>
                        <p class="text-muted mb-0">Seleccioná una oportunidad del listado para ver el detalle y cargar seguimientos.</p>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <span class="badge <?= crm_prioridad_badge((string)$lead_actual['prioridad']) ?>"><?= htmlspecialchars($prioridad_options[$lead_actual['prioridad']] ?? $lead_actual['prioridad']) ?></span>
                            <span class="badge bg-light text-dark">Visita <?= htmlspecialchars((string)$lead_actual['visita_estado']) ?></span>
                            <span class="badge bg-dark">$ <?= number_format((float)($lead_actual['monto_estimado'] ?? 0), 0, ',', '.') ?></span>
                        </div>

                        <h4 class="mb-1"><?= htmlspecialchars(trim((string)($lead_actual['cliente_nombre'] ?? '')) !== '' ? $lead_actual['cliente_nombre'] : $lead_actual['titulo']) ?></h4>
                        <p class="text-muted mb-3"><?= htmlspecialchars($lead_actual['titulo']) ?></p>

                        <div class="crm-meta mb-3">
                            <div class="crm-meta-item">
                                <small>Teléfono</small>
                                <div><?= htmlspecialchars($lead_actual['telefono'] ?? '—') ?></div>
                            </div>
                            <div class="crm-meta-item">
                                <small>Próximo contacto</small>
                                <div><?= htmlspecialchars(crm_format_date($lead_actual['proximo_contacto'] ?? null)) ?></div>
                            </div>
                            <div class="crm-meta-item">
                                <small>Última gestión</small>
                                <div><?= htmlspecialchars(crm_format_datetime($lead_actual['ultima_fecha_contacto'] ?? null)) ?></div>
                            </div>
                            <div class="crm-meta-item">
                                <small>Responsable</small>
                                <div><?= htmlspecialchars($lead_actual['asignado_nombre'] ?? 'Sin asignar') ?></div>
                            </div>
                        </div>

                        <?php if (!empty($lead_actual['direccion'])): ?>
                            <div class="mb-3"><strong>Dirección:</strong> <?= htmlspecialchars($lead_actual['direccion']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($lead_actual['visita_descripcion'])): ?>
                            <div class="mb-3"><strong>Detalle de visita:</strong><div class="crm-note text-muted"><?= htmlspecialchars($lead_actual['visita_descripcion']) ?></div></div>
                        <?php endif; ?>
                        <?php if (!empty($lead_actual['notas_internas'])): ?>
                            <div class="alert alert-light border mb-3">
                                <strong>Notas internas:</strong>
                                <div class="crm-note"><?= htmlspecialchars($lead_actual['notas_internas']) ?></div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($lead_actual['ultima_cotizacion_numero'])): ?>
                            <div class="alert alert-primary py-2 small mb-3">
                                <strong>Última cotización vinculada:</strong>
                                <?= htmlspecialchars($lead_actual['ultima_cotizacion_numero']) ?>
                                <?php if (!empty($lead_actual['fecha_ultima_cotizacion'])): ?>
                                    <span class="text-muted">· <?= htmlspecialchars(crm_format_datetime($lead_actual['fecha_ultima_cotizacion'])) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-wrap gap-2">
                            <?php $wa_link = crm_whatsapp_link($lead_actual['telefono'] ?? ''); ?>
                            <?php if ($wa_link !== ''): ?>
                                <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener" class="btn btn-success btn-sm"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                            <?php endif; ?>
                            <a href="instalaciones.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-calendar-check"></i> Ver visitas</a>
                            <a href="cotizacion_crear.php?crm_id=<?= (int)$lead_actual['id'] ?>" class="btn btn-primary btn-sm"><i class="bi bi-file-earmark-plus"></i> Crear cotización</a>
                            <?php if (!empty($lead_actual['ultima_cotizacion_id'])): ?>
                                <a href="cotizacion_detalle.php?id=<?= (int)$lead_actual['ultima_cotizacion_id'] ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-box-arrow-up-right"></i> Ver cotización</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($lead_actual): ?>
                <div class="card crm-detail-card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Actualizar oportunidad</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                            <input type="hidden" name="accion" value="guardar_oportunidad">
                            <input type="hidden" name="crm_id" value="<?= (int)$lead_actual['id'] ?>">

                            <div class="col-md-6">
                                <label class="form-label">Estado CRM</label>
                                <select name="estado" class="form-select">
                                    <?php foreach ($estado_options as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= (string)$lead_actual['estado'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Prioridad</label>
                                <select name="prioridad" class="form-select">
                                    <?php foreach ($prioridad_options as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= (string)$lead_actual['prioridad'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Próximo contacto</label>
                                <input type="date" name="proximo_contacto" class="form-control" value="<?= htmlspecialchars((string)($lead_actual['proximo_contacto'] ?? '')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Monto estimado</label>
                                <input type="number" step="0.01" min="0" name="monto_estimado" class="form-control" value="<?= htmlspecialchars((string)($lead_actual['monto_estimado'] ?? '0')) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Responsable</label>
                                <select name="asignado_a" class="form-select" <?= !$is_admin ? 'disabled' : '' ?>>
                                    <option value="0">Sin asignar</option>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <option value="<?= (int)$usuario['id'] ?>" <?= (int)($lead_actual['asignado_a'] ?? 0) === (int)$usuario['id'] ? 'selected' : '' ?>><?= htmlspecialchars($usuario['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$is_admin): ?>
                                    <input type="hidden" name="asignado_a" value="<?= (int)($lead_actual['asignado_a'] ?? $usuario_actual_id) ?>">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Origen</label>
                                <input type="text" name="origen" class="form-control" value="<?= htmlspecialchars((string)($lead_actual['origen'] ?? 'visita')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notas internas</label>
                                <textarea name="notas_internas" class="form-control" rows="3" placeholder="Información comercial, objeciones, presupuesto, etc."><?= htmlspecialchars((string)($lead_actual['notas_internas'] ?? '')) ?></textarea>
                            </div>
                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-primary">Guardar ficha CRM</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card crm-detail-card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">Registrar seguimiento</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                            <input type="hidden" name="accion" value="agregar_seguimiento">
                            <input type="hidden" name="crm_id" value="<?= (int)$lead_actual['id'] ?>">

                            <div class="col-md-6">
                                <label class="form-label">Canal</label>
                                <select name="canal" class="form-select">
                                    <?php foreach ($canal_options as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Resultado</label>
                                <select name="resultado" class="form-select">
                                    <?php foreach ($resultado_options as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cambiar estado CRM</label>
                                <select name="nuevo_estado" class="form-select">
                                    <option value="">Automático según resultado</option>
                                    <?php foreach ($estado_options as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>"><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Próximo contacto</label>
                                <input type="date" name="proximo_contacto" class="form-control" value="<?= htmlspecialchars((string)($lead_actual['proximo_contacto'] ?? '')) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Comentario</label>
                                <textarea name="comentario" class="form-control" rows="3" placeholder="Ej: se habló por WhatsApp, pidió una cotización para la semana próxima." required></textarea>
                            </div>
                            <div class="col-12 d-grid">
                                <button type="submit" class="btn btn-dark">Guardar seguimiento</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card crm-detail-card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Historial</h5>
                        <span class="badge bg-dark"><?= count($seguimientos) ?></span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($seguimientos)): ?>
                            <p class="text-muted mb-0">Todavía no hay seguimientos cargados para este lead.</p>
                        <?php else: ?>
                            <?php foreach ($seguimientos as $seg): ?>
                                <div class="crm-timeline-item">
                                    <div class="d-flex flex-wrap justify-content-between gap-2 mb-1">
                                        <strong><?= htmlspecialchars($canal_options[$seg['canal']] ?? ucfirst((string)$seg['canal'])) ?></strong>
                                        <span class="small text-muted"><?= htmlspecialchars(crm_format_datetime($seg['fecha_contacto'] ?? null)) ?></span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="badge <?= crm_resultado_badge((string)$seg['resultado']) ?>"><?= htmlspecialchars($resultado_options[$seg['resultado']] ?? $seg['resultado']) ?></span>
                                        <span class="small text-muted ms-1">por <?= htmlspecialchars($seg['usuario_nombre'] ?? 'Sistema') ?></span>
                                    </div>
                                    <div class="crm-note mb-2"><?= htmlspecialchars($seg['comentario']) ?></div>
                                    <?php if (!empty($seg['proximo_contacto'])): ?>
                                        <div class="small text-muted">Próximo contacto: <?= htmlspecialchars(crm_format_date($seg['proximo_contacto'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
