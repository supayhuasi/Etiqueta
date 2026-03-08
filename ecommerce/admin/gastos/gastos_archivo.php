<?php
session_start();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    echo 'No autenticado';
    exit;
}

$rol = $_SESSION['rol'] ?? '';
if (!in_array($rol, ['admin', 'usuario'], true)) {
    http_response_code(403);
    echo 'Sin permisos';
    exit;
}

require_once dirname(__DIR__, 3) . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'ID inválido';
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT archivo FROM gastos WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['archivo'])) {
        http_response_code(404);
        echo 'Adjunto no encontrado';
        exit;
    }

    $archivo = basename((string)$row['archivo']);
    $path = __DIR__ . '/uploads/' . $archivo;

    if (!is_file($path)) {
        http_response_code(404);
        echo 'Archivo no encontrado en disco';
        exit;
    }

    $ext = strtolower(pathinfo($archivo, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';

    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = finfo_file($finfo, $path);
            if (is_string($detected) && $detected !== '') {
                $mime = $detected;
            }
            finfo_close($finfo);
        }
    }

    if ($mime === 'application/octet-stream') {
        $map = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        if (isset($map[$ext])) {
            $mime = $map[$ext];
        }
    }

    $inlineExt = ['pdf', 'jpg', 'jpeg', 'png'];
    $disposition = in_array($ext, $inlineExt, true) ? 'inline' : 'attachment';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . $disposition . '; filename="' . rawurlencode($archivo) . '"');
    header('X-Content-Type-Options: nosniff');

    readfile($path);
    exit;
} catch (Throwable $e) {
    error_log('gastos_archivo error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Error interno';
}
