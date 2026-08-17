<?php
require_once __DIR__ . '/../../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$usuario = trim((string)($input['usuario'] ?? $_POST['usuario'] ?? ''));
$password = (string)($input['password'] ?? $_POST['password'] ?? '');
$dispositivo = trim((string)($input['dispositivo'] ?? $_POST['dispositivo'] ?? ''));

if ($usuario === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Usuario y contraseña son requeridos']);
    exit;
}

$stmt = $pdo->prepare("SELECT u.*, r.nombre as rol FROM usuarios u LEFT JOIN roles r ON u.rol_id = r.id WHERE u.usuario = ? AND u.activo = 1");
$stmt->execute([$usuario]);
$u = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$u || !password_verify($password, $u['password'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos']);
    exit;
}

$token = bin2hex(random_bytes(32));
$insert = $pdo->prepare('INSERT INTO nube_api_tokens (usuario_id, token, dispositivo) VALUES (?, ?, ?)');
$insert->execute([$u['id'], $token, $dispositivo !== '' ? $dispositivo : null]);

echo json_encode([
    'success' => true,
    'token' => $token,
    'usuario' => [
        'id' => (int)$u['id'],
        'usuario' => $u['usuario'],
        'nombre' => $u['nombre'],
        'rol' => $u['rol'],
    ],
]);
