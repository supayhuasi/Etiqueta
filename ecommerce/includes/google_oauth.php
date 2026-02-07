<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo)) {
    require __DIR__ . '/../config.php';
}

function google_oauth_request_scheme(): string {
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        return $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
    }
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return 'https';
    }
    return 'http';
}

function google_oauth_base_url(): string {
    $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
    $public_base = '';
    if ($script_path) {
        if (strpos($script_path, '/ecommerce/') !== false) {
            $public_base = preg_replace('#/ecommerce/.*$#', '/ecommerce', $script_path);
        } elseif (strpos($script_path, '/admin/') !== false) {
            $public_base = rtrim(preg_replace('#/admin/.*$#', '', $script_path), '/');
        } else {
            $public_base = rtrim(dirname($script_path), '/\\');
        }
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return google_oauth_request_scheme() . '://' . $host . $public_base;
}

function google_oauth_config(): array {
    $config = $GLOBALS['google_oauth'] ?? [
        'client_id' => '',
        'client_secret' => '',
        'redirect_uri' => ''
    ];

    if (empty($config['redirect_uri'])) {
        $config['redirect_uri'] = google_oauth_base_url() . '/cliente_google_callback.php';
    }

    return $config;
}

function google_oauth_enabled(): bool {
    $config = google_oauth_config();
    return !empty($config['client_id']) && !empty($config['client_secret']) && !empty($config['redirect_uri']);
}

function google_oauth_build_auth_url(string $state): string {
    $config = google_oauth_config();
    $params = [
        'client_id' => $config['client_id'],
        'redirect_uri' => $config['redirect_uri'],
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'access_type' => 'online',
        'include_granted_scopes' => 'true',
        'prompt' => 'select_account',
        'state' => $state
    ];

    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_oauth_http_post(string $url, array $data): ?array {
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data),
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function google_oauth_http_get(string $url, array $headers = []): ?array {
    $header_lines = "";
    foreach ($headers as $header) {
        $header_lines .= $header . "\r\n";
    }

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => $header_lines,
            'timeout' => 10
        ]
    ];

    $context = stream_context_create($options);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        return null;
    }

    $decoded = json_decode($response, true);
    return is_array($decoded) ? $decoded : null;
}

function google_oauth_exchange_code(string $code): ?array {
    $config = google_oauth_config();
    $payload = [
        'code' => $code,
        'client_id' => $config['client_id'],
        'client_secret' => $config['client_secret'],
        'redirect_uri' => $config['redirect_uri'],
        'grant_type' => 'authorization_code'
    ];

    return google_oauth_http_post('https://oauth2.googleapis.com/token', $payload);
}

function google_oauth_fetch_userinfo(string $access_token): ?array {
    return google_oauth_http_get('https://openidconnect.googleapis.com/v1/userinfo', [
        'Authorization: Bearer ' . $access_token
    ]);
}
?>
