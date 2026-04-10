<?php
if (!headers_sent()) {
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
}

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        ini_set('session.cookie_secure', $isHttps ? '1' : '0');
        ini_set('session.cookie_samesite', 'Lax');
        session_set_cookie_params(0, '/');
    }
    session_start();
}

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'msg' => 'Sesión expirada.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$basePath = dirname(__DIR__, 2);
require $basePath . '/config.php';

$q = trim((string)($_GET['q'] ?? ''));
$length = function_exists('mb_strlen') ? mb_strlen($q, 'UTF-8') : strlen($q);

if ($q === '' || ($length < 2 && !preg_match('/\d+/', $q))) {
    echo json_encode(['ok' => true, 'results' => []], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $idCandidate = preg_match('/\d+/', $q, $match) ? (int)($match[0] ?? 0) : 0;
    $like = '%' . preg_replace('/\s+/', '%', $q) . '%';

    $stmt = $pdo->prepare("SELECT
            p.id,
            COALESCE(NULLIF(TRIM(c.nombre), ''), NULLIF(TRIM(p.envio_nombre), ''), 'Cliente sin nombre') AS cliente_nombre,
            COALESCE(NULLIF(TRIM(c.email), ''), '') AS cliente_email
        FROM ecommerce_pedidos p
        LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id
        WHERE (? > 0 AND p.id = ?)
           OR COALESCE(c.nombre, '') LIKE ?
           OR COALESCE(c.email, '') LIKE ?
           OR COALESCE(p.envio_nombre, '') LIKE ?
        ORDER BY CASE WHEN p.id = ? THEN 0 ELSE 1 END, p.id DESC
        LIMIT 15");
    $stmt->execute([$idCandidate, $idCandidate, $like, $like, $like, $idCandidate]);

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $pedidoId = (int)($row['id'] ?? 0);
        if ($pedidoId <= 0) {
            continue;
        }

        $clienteNombre = trim((string)($row['cliente_nombre'] ?? '')) ?: 'Cliente sin nombre';
        $clienteEmail = trim((string)($row['cliente_email'] ?? ''));
        $label = 'Pedido #' . $pedidoId . ' · ' . $clienteNombre . ($clienteEmail !== '' ? ' · ' . $clienteEmail : '');

        $results[] = [
            'id' => $pedidoId,
            'cliente_nombre' => $clienteNombre,
            'cliente_email' => $clienteEmail,
            'label' => $label,
        ];
    }

    echo json_encode(['ok' => true, 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'msg' => 'No se pudo buscar pedidos: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
