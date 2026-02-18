<?php
/**
 * Security Helper - Medidas de seguridad para prevenir ataques
 */

// Prevenir acceso directo
if (!defined('SECURITY_CHECK')) {
    die('Acceso denegado');
}

// Configuración de seguridad de sesiones
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', 1); // Solo HTTPS
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// Regenerar ID de sesión periódicamente para prevenir session hijacking
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // cada 5 minutos
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

/**
 * Sanitizar entrada de usuario
 */
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validar token CSRF
 */
function validate_csrf_token($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Generar token CSRF
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Obtener token CSRF como campo hidden
 */
function csrf_field() {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Prevenir SQL Injection - Validar ID
 */
function validate_id($id) {
    return filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
}

/**
 * Validar email
 */
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validar URL
 */
function validate_url($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}

/**
 * Prevenir XSS en output
 */
function escape_html($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Limitar intentos de login (Rate Limiting)
 */
function check_rate_limit($identifier, $max_attempts = 5, $time_window = 300) {
    if (!isset($_SESSION['rate_limit'])) {
        $_SESSION['rate_limit'] = [];
    }
    
    $now = time();
    $key = md5($identifier);
    
    // Limpiar intentos antiguos
    if (isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = array_filter(
            $_SESSION['rate_limit'][$key],
            function($timestamp) use ($now, $time_window) {
                return ($now - $timestamp) < $time_window;
            }
        );
    }
    
    // Verificar límite
    if (!isset($_SESSION['rate_limit'][$key])) {
        $_SESSION['rate_limit'][$key] = [];
    }
    
    if (count($_SESSION['rate_limit'][$key]) >= $max_attempts) {
        return false;
    }
    
    $_SESSION['rate_limit'][$key][] = $now;
    return true;
}

/**
 * Validar archivo subido
 */
function validate_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], $max_size = 5242880) {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Parámetros inválidos'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Error al subir archivo'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'error' => 'Archivo demasiado grande (máx 5MB)'];
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime, $allowed_types)) {
        return ['success' => false, 'error' => 'Tipo de archivo no permitido'];
    }
    
    return ['success' => true];
}

/**
 * Sanitizar nombre de archivo
 */
function sanitize_filename($filename) {
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    $filename = preg_replace('/\.+/', '.', $filename);
    return $filename;
}

/**
 * Prevenir Path Traversal
 */
function validate_path($path, $base_dir) {
    $real_base = realpath($base_dir);
    $real_path = realpath($path);
    
    if ($real_path === false || strpos($real_path, $real_base) !== 0) {
        return false;
    }
    
    return true;
}

/**
 * Headers de seguridad HTTP
 */
function set_security_headers() {
    // Prevenir XSS
    header("X-XSS-Protection: 1; mode=block");
    
    // Prevenir clickjacking
    header("X-Frame-Options: SAMEORIGIN");
    
    // Prevenir MIME sniffing
    header("X-Content-Type-Options: nosniff");
    
    // Política de referrer
    header("Referrer-Policy: strict-origin-when-cross-origin");
    
    // Content Security Policy (básico)
    header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https: data:;");
    
    // Permissions Policy
    header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
}

/**
 * Log de actividad sospechosa
 */
function log_security_event($event, $details = []) {
    $log_file = __DIR__ . '/../../logs/security.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $log_entry = sprintf(
        "[%s] IP: %s | Event: %s | Details: %s | UA: %s\n",
        $timestamp,
        $ip,
        $event,
        json_encode($details),
        $user_agent
    );
    
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Detectar patrones de ataque comunes
 */
function detect_attack_patterns($input) {
    $patterns = [
        '/(\%27)|(\')|(\-\-)|(;)|(\%23)|(#)/i', // SQL Injection
        '/<script[^>]*>.*?<\/script>/is', // XSS
        '/(\.\.|\/\.\.|\.\.\/)/i', // Path Traversal
        '/(union.*select|insert.*into|delete.*from|drop.*table)/i', // SQL Keywords
        '/(<iframe|<object|<embed|<applet)/i', // Embedding
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $input)) {
            log_security_event('ATTACK_DETECTED', ['pattern' => $pattern, 'input' => substr($input, 0, 100)]);
            return true;
        }
    }
    
    return false;
}

/**
 * Protección contra fuerza bruta
 */
function prevent_brute_force($identifier) {
    if (!check_rate_limit($identifier, 5, 900)) { // 5 intentos en 15 minutos
        log_security_event('BRUTE_FORCE_ATTEMPT', ['identifier' => $identifier]);
        http_response_code(429);
        die('Demasiados intentos. Intenta de nuevo en 15 minutos.');
    }
}

// Aplicar headers de seguridad automáticamente
set_security_headers();

// Validar todas las entradas GET y POST
foreach ($_GET as $key => $value) {
    if (detect_attack_patterns($value)) {
        log_security_event('MALICIOUS_INPUT_GET', ['key' => $key]);
        http_response_code(400);
        die('Solicitud inválida');
    }
}

foreach ($_POST as $key => $value) {
    if (is_string($value) && detect_attack_patterns($value)) {
        log_security_event('MALICIOUS_INPUT_POST', ['key' => $key]);
        http_response_code(400);
        die('Solicitud inválida');
    }
}
