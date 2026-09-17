<?php

if (!defined('CRM_LEADS_DESDE')) {
    define('CRM_LEADS_DESDE', '2026-09-14');
}

function crm_config_defaults(): array
{
    return [
        'dias_vencido' => 1,
        'notificar_campana' => 1,
        'notificar_email' => 1,
    ];
}

function crm_config_ensure_schema(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }

    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_crm_config (
            id INT PRIMARY KEY,
            dias_vencido INT NOT NULL DEFAULT 1,
            notificar_campana TINYINT(1) NOT NULL DEFAULT 1,
            notificar_email TINYINT(1) NOT NULL DEFAULT 1,
            fecha_actualizacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $pdo->exec("INSERT IGNORE INTO ecommerce_crm_config (id, dias_vencido, notificar_campana, notificar_email)
            VALUES (1, 1, 1, 1)");
    } catch (Throwable $e) {
        error_log('crm_config_schema: ' . $e->getMessage());
    }

    $ready = true;
}

function crm_config_load(PDO $pdo): array
{
    $config = crm_config_defaults();
    crm_config_ensure_schema($pdo);

    try {
        $stmt = $pdo->query("SELECT dias_vencido, notificar_campana, notificar_email FROM ecommerce_crm_config WHERE id = 1 LIMIT 1");
        $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
        if (is_array($row)) {
            $config['dias_vencido'] = max(1, min(365, (int)($row['dias_vencido'] ?? 1)));
            $config['notificar_campana'] = !empty($row['notificar_campana']) ? 1 : 0;
            $config['notificar_email'] = !empty($row['notificar_email']) ? 1 : 0;
        }
    } catch (Throwable $e) {
        error_log('crm_config_load: ' . $e->getMessage());
    }

    return $config;
}

function crm_config_save(PDO $pdo, array $data): array
{
    crm_config_ensure_schema($pdo);

    $config = [
        'dias_vencido' => max(1, min(365, (int)($data['dias_vencido'] ?? 1))),
        'notificar_campana' => !empty($data['notificar_campana']) ? 1 : 0,
        'notificar_email' => !empty($data['notificar_email']) ? 1 : 0,
    ];

    $stmt = $pdo->prepare("INSERT INTO ecommerce_crm_config (id, dias_vencido, notificar_campana, notificar_email)
        VALUES (1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            dias_vencido = VALUES(dias_vencido),
            notificar_campana = VALUES(notificar_campana),
            notificar_email = VALUES(notificar_email)");
    $stmt->execute([
        $config['dias_vencido'],
        $config['notificar_campana'],
        $config['notificar_email'],
    ]);

    return $config;
}

function crm_config_alias(string $alias): string
{
    $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $alias);
    return $safe !== '' ? $safe : 'c';
}

function crm_config_fecha_base_sql(string $alias = 'c'): string
{
    $a = crm_config_alias($alias);
    return "DATE(COALESCE({$a}.proximo_contacto, {$a}.ultima_gestion, {$a}.fecha_creacion))";
}

function crm_config_sql_vencido_expr(string $alias, int $dias): string
{
    $dias = max(1, min(365, $dias));
    $a = crm_config_alias($alias);
    $base = crm_config_fecha_base_sql($a);
    return "{$a}.estado NOT IN ('ganado','perdido') AND {$base} IS NOT NULL AND DATE_ADD({$base}, INTERVAL {$dias} DAY) <= CURDATE()";
}

function crm_lead_esta_vencido(array $lead, int $dias): bool
{
    if (in_array((string)($lead['estado'] ?? ''), ['ganado', 'perdido'], true)) {
        return false;
    }

    $dias = max(1, min(365, $dias));
    $ref = trim((string)($lead['proximo_contacto'] ?? ''));
    if ($ref === '') {
        $ref = substr((string)($lead['ultima_gestion'] ?? ''), 0, 10);
    }
    if ($ref === '') {
        $ref = substr((string)($lead['fecha_creacion'] ?? ''), 0, 10);
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $ref)) {
        return false;
    }

    $ref = substr($ref, 0, 10);
    $vence = date('Y-m-d', strtotime($ref . ' +' . $dias . ' days'));
    return $vence !== false && $vence <= date('Y-m-d');
}

function crm_contactos_vencidos(PDO $pdo, int $soloUsuarioId = 0, int $limit = 50): array
{
    if (!crm_config_table_exists($pdo, 'ecommerce_crm_visitas') || !crm_config_table_exists($pdo, 'ecommerce_visitas')) {
        return [];
    }

    $config = crm_config_load($pdo);
    $vencidoSql = crm_config_sql_vencido_expr('c', (int)$config['dias_vencido']);
    $base = crm_config_fecha_base_sql('c');
    $limit = max(1, min(300, $limit));

    $sql = "SELECT
            c.id,
            c.estado,
            c.prioridad,
            c.proximo_contacto,
            c.asignado_a,
            c.ultima_gestion,
            c.fecha_creacion,
            v.titulo,
            v.cliente_nombre,
            v.telefono,
            COALESCE(NULLIF(TRIM(u.nombre), ''), u.usuario, 'Sin asignar') AS asignado_nombre,
            u.email AS asignado_email,
            DATEDIFF(CURDATE(), DATE_ADD({$base}, INTERVAL " . (int)$config['dias_vencido'] . " DAY)) AS dias_atraso
        FROM ecommerce_crm_visitas c
        INNER JOIN ecommerce_visitas v ON v.id = c.visita_id
        LEFT JOIN usuarios u ON u.id = c.asignado_a
        WHERE COALESCE(v.fecha_visita, DATE(c.fecha_creacion)) >= ?
          AND {$vencidoSql}";
    $params = [CRM_LEADS_DESDE];

    if ($soloUsuarioId > 0) {
        $sql .= " AND c.asignado_a = ?";
        $params[] = $soloUsuarioId;
    }

    $sql .= " ORDER BY {$base} ASC, c.id ASC LIMIT {$limit}";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('crm_contactos_vencidos: ' . $e->getMessage());
        return [];
    }
}

function crm_config_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function crm_panel_url(string $query = 'vencidos=1'): string
{
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'tucuroller.com.ar');
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $isLocal = $host === 'localhost' || strpos($host, '127.0.0.1') === 0;
    $scheme = ($https || !$isLocal) ? 'https' : 'http';
    $url = $scheme . '://' . $host . '/ecommerce/admin/crm.php';
    if ($query !== '') {
        $url .= '?' . ltrim($query, '?');
    }
    return $url;
}

function crm_avisos_ya_enviados_hoy(PDO $pdo): bool
{
    try {
        crm_config_ensure_diario($pdo);
        $stmt = $pdo->prepare("SELECT id FROM ecommerce_notificaciones_diarias WHERE tipo = 'crm_contactos_vencidos' AND fecha_notificacion = ? LIMIT 1");
        $stmt->execute([date('Y-m-d')]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function crm_config_ensure_diario(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_notificaciones_diarias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tipo VARCHAR(80) NOT NULL,
        fecha_notificacion DATE NOT NULL,
        total INT NOT NULL DEFAULT 0,
        payload_json LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_tipo_fecha (tipo, fecha_notificacion),
        INDEX idx_fecha (fecha_notificacion)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function crm_marcar_avisos_enviados_hoy(PDO $pdo, int $total, array $payload = []): void
{
    try {
        crm_config_ensure_diario($pdo);
        $stmt = $pdo->prepare("INSERT INTO ecommerce_notificaciones_diarias (tipo, fecha_notificacion, total, payload_json)
            VALUES ('crm_contactos_vencidos', ?, ?, ?)
            ON DUPLICATE KEY UPDATE total = VALUES(total), payload_json = VALUES(payload_json)");
        $stmt->execute([
            date('Y-m-d'),
            $total,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
    } catch (Throwable $e) {
        error_log('crm_marcar_avisos: ' . $e->getMessage());
    }
}

function crm_enviar_avisos_vencidos(PDO $pdo, bool $forzar = false): array
{
    $resultado = [
        'enviados' => 0,
        'omitidos' => 0,
        'destinatarios' => 0,
        'error' => '',
    ];

    $config = crm_config_load($pdo);
    if (empty($config['notificar_email'])) {
        $resultado['error'] = 'Las notificaciones por email están desactivadas.';
        return $resultado;
    }

    if (!$forzar && crm_avisos_ya_enviados_hoy($pdo)) {
        return $resultado;
    }

    $vencidos = crm_contactos_vencidos($pdo, 0, 300);
    if (empty($vencidos)) {
        crm_marcar_avisos_enviados_hoy($pdo, 0, ['mensaje' => 'Sin contactos vencidos']);
        return $resultado;
    }

    $porVendedor = [];
    foreach ($vencidos as $lead) {
        $uid = (int)($lead['asignado_a'] ?? 0);
        if ($uid <= 0) {
            $resultado['omitidos']++;
            continue;
        }
        $porVendedor[$uid][] = $lead;
    }

    $mailer = dirname(__DIR__, 2) . '/includes/mailer.php';
    if (!function_exists('enviar_email') && is_file($mailer)) {
        require_once $mailer;
    }
    if (!function_exists('enviar_email')) {
        $resultado['error'] = 'No se pudo cargar el envío de email.';
        return $resultado;
    }

    $crmUrl = crm_panel_url('vencidos=1');
    $dias = (int)$config['dias_vencido'];

    foreach ($porVendedor as $leadsVendedor) {
        $primero = $leadsVendedor[0];
        $email = trim((string)($primero['asignado_email'] ?? ''));
        $nombre = trim((string)($primero['asignado_nombre'] ?? 'Vendedor'));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $resultado['omitidos'] += count($leadsVendedor);
            continue;
        }

        $filas = '';
        foreach ($leadsVendedor as $lead) {
            $cliente = trim((string)($lead['cliente_nombre'] ?? '')) !== ''
                ? (string)$lead['cliente_nombre']
                : (string)($lead['titulo'] ?? 'Contacto');
            $proximo = !empty($lead['proximo_contacto'])
                ? date('d/m/Y', strtotime((string)$lead['proximo_contacto']))
                : 'Sin fecha';
            $atraso = max(0, (int)($lead['dias_atraso'] ?? 0));
            $filas .= '<tr>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($cliente) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars((string)($lead['telefono'] ?? '')) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;">' . htmlspecialchars($proximo) . '</td>'
                . '<td style="padding:8px;border-bottom:1px solid #eee;color:#b42318;">' . $atraso . ' día(s)</td>'
                . '</tr>';
        }

        $html = '<html><body style="font-family:Arial,sans-serif;color:#333;">'
            . '<div style="max-width:640px;margin:0 auto;padding:20px;">'
            . '<h2 style="margin-top:0;">Contactos CRM vencidos</h2>'
            . '<p>Hola <strong>' . htmlspecialchars($nombre) . '</strong>, tenés '
            . count($leadsVendedor) . ' contacto(s) vencido(s) (regla: ' . $dias . ' día(s) desde el próximo contacto).</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">'
            . '<tr style="background:#f8f9fa;text-align:left;">'
            . '<th style="padding:8px;">Cliente</th><th style="padding:8px;">Teléfono</th>'
            . '<th style="padding:8px;">Próximo</th><th style="padding:8px;">Atraso</th></tr>'
            . $filas
            . '</table>'
            . '<p style="margin-top:20px;"><a href="' . htmlspecialchars($crmUrl) . '" style="background:#dc3545;color:#fff;padding:10px 16px;text-decoration:none;border-radius:4px;">Ver vencidos en el CRM</a></p>'
            . '</div></body></html>';

        try {
            if (enviar_email($email, 'CRM: contactos vencidos (' . count($leadsVendedor) . ')', $html)) {
                $resultado['enviados']++;
                $resultado['destinatarios']++;
            } else {
                $resultado['omitidos'] += count($leadsVendedor);
            }
        } catch (Throwable $e) {
            error_log('crm_email_vencidos: ' . $e->getMessage());
            $resultado['omitidos'] += count($leadsVendedor);
        }
    }

    crm_marcar_avisos_enviados_hoy($pdo, $resultado['enviados'], [
        'vendedores' => $resultado['destinatarios'],
        'omitidos' => $resultado['omitidos'],
        'total_leads' => count($vencidos),
    ]);

    return $resultado;
}
