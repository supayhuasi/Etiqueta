<?php

function chat_asegurar_tablas(PDO $pdo): void
{
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_conversaciones (
            id INT PRIMARY KEY AUTO_INCREMENT,
            tipo ENUM('general','directo','grupo') NOT NULL DEFAULT 'grupo',
            nombre VARCHAR(255) NULL,
            creado_por INT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_participantes (
            conversacion_id INT NOT NULL,
            usuario_id INT NOT NULL,
            ultima_lectura_mensaje_id INT NOT NULL DEFAULT 0,
            fecha_union DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (conversacion_id, usuario_id),
            INDEX idx_chat_participantes_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_mensajes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            conversacion_id INT NOT NULL,
            usuario_id INT NOT NULL,
            mensaje VARCHAR(1000) NOT NULL,
            adjunto_archivo VARCHAR(255) NULL,
            adjunto_nombre VARCHAR(255) NULL,
            adjunto_tipo VARCHAR(100) NULL,
            adjunto_tamano INT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_mensajes_conversacion (conversacion_id, id),
            INDEX idx_chat_mensajes_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Migración desde la versión anterior del chat (un único canal, sin conversaciones)
    if (function_exists('admin_column_exists') && !admin_column_exists($pdo, 'chat_mensajes', 'conversacion_id')) {
        $pdo->exec("ALTER TABLE chat_mensajes ADD COLUMN conversacion_id INT NULL AFTER id");
        $general_id = chat_obtener_conversacion_general($pdo);
        $pdo->exec("UPDATE chat_mensajes SET conversacion_id = $general_id WHERE conversacion_id IS NULL");
        $pdo->exec("ALTER TABLE chat_mensajes MODIFY COLUMN conversacion_id INT NOT NULL");
        $pdo->exec("CREATE INDEX idx_chat_mensajes_conversacion ON chat_mensajes (conversacion_id, id)");
    }

    // Migración: soporte de archivos adjuntos en mensajes existentes
    $columnas_adjunto = [
        'adjunto_archivo' => 'VARCHAR(255) NULL',
        'adjunto_nombre' => 'VARCHAR(255) NULL',
        'adjunto_tipo' => 'VARCHAR(100) NULL',
        'adjunto_tamano' => 'INT NULL',
    ];
    foreach ($columnas_adjunto as $columna => $definicion) {
        if (function_exists('admin_column_exists') && !admin_column_exists($pdo, 'chat_mensajes', $columna)) {
            $pdo->exec("ALTER TABLE chat_mensajes ADD COLUMN $columna $definicion");
        }
    }

    if (function_exists('admin_column_exists') && !admin_column_exists($pdo, 'usuarios', 'ultima_actividad')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultima_actividad DATETIME NULL");
    }
}

function chat_obtener_conversacion_general(PDO $pdo): int
{
    static $general_id = null;
    if ($general_id !== null) {
        return $general_id;
    }

    $id = (int)$pdo->query("SELECT id FROM chat_conversaciones WHERE tipo = 'general' LIMIT 1")->fetchColumn();
    if ($id > 0) {
        $general_id = $id;
        return $general_id;
    }

    $pdo->prepare("INSERT INTO chat_conversaciones (tipo, nombre) VALUES ('general', 'General')")->execute();
    $general_id = (int)$pdo->lastInsertId();
    return $general_id;
}

function chat_asegurar_membresia(PDO $pdo, int $conversacion_id, int $usuario_id): void
{
    $stmt = $pdo->prepare("INSERT IGNORE INTO chat_participantes (conversacion_id, usuario_id) VALUES (?, ?)");
    $stmt->execute([$conversacion_id, $usuario_id]);
}

function chat_usuario_tiene_acceso(PDO $pdo, int $conversacion_id, int $usuario_id): bool
{
    $stmt = $pdo->prepare("SELECT tipo FROM chat_conversaciones WHERE id = ?");
    $stmt->execute([$conversacion_id]);
    $tipo = $stmt->fetchColumn();
    if ($tipo === false) {
        return false;
    }
    if ($tipo === 'general') {
        chat_asegurar_membresia($pdo, $conversacion_id, $usuario_id);
        return true;
    }

    $stmt = $pdo->prepare("SELECT 1 FROM chat_participantes WHERE conversacion_id = ? AND usuario_id = ?");
    $stmt->execute([$conversacion_id, $usuario_id]);
    return (bool)$stmt->fetchColumn();
}

function chat_obtener_o_crear_directo(PDO $pdo, int $usuario_a, int $usuario_b): int
{
    if ($usuario_a === $usuario_b) {
        throw new InvalidArgumentException('No podés iniciar una conversación con vos mismo');
    }

    $stmt = $pdo->prepare("
        SELECT cp1.conversacion_id
        FROM chat_participantes cp1
        JOIN chat_participantes cp2 ON cp2.conversacion_id = cp1.conversacion_id AND cp2.usuario_id = ?
        JOIN chat_conversaciones c ON c.id = cp1.conversacion_id
        WHERE cp1.usuario_id = ? AND c.tipo = 'directo'
        LIMIT 1
    ");
    $stmt->execute([$usuario_b, $usuario_a]);
    $existente = $stmt->fetchColumn();
    if ($existente) {
        return (int)$existente;
    }

    $pdo->prepare("INSERT INTO chat_conversaciones (tipo, creado_por) VALUES ('directo', ?)")->execute([$usuario_a]);
    $conv_id = (int)$pdo->lastInsertId();
    chat_asegurar_membresia($pdo, $conv_id, $usuario_a);
    chat_asegurar_membresia($pdo, $conv_id, $usuario_b);

    return $conv_id;
}

function chat_crear_grupo(PDO $pdo, int $creador_id, string $nombre, array $miembros_ids): int
{
    $nombre = trim($nombre);
    if ($nombre === '') {
        throw new InvalidArgumentException('El grupo necesita un nombre');
    }
    $nombre = function_exists('mb_substr') ? mb_substr($nombre, 0, 255) : substr($nombre, 0, 255);

    $miembros_ids = array_values(array_unique(array_map('intval', $miembros_ids)));
    $miembros_ids = array_values(array_filter($miembros_ids, function ($id) {
        return $id > 0;
    }));
    if (count($miembros_ids) < 1) {
        throw new InvalidArgumentException('Elegí al menos un integrante para el grupo');
    }

    $pdo->prepare("INSERT INTO chat_conversaciones (tipo, nombre, creado_por) VALUES ('grupo', ?, ?)")
        ->execute([$nombre, $creador_id]);
    $conv_id = (int)$pdo->lastInsertId();

    chat_asegurar_membresia($pdo, $conv_id, $creador_id);
    foreach ($miembros_ids as $mid) {
        chat_asegurar_membresia($pdo, $conv_id, $mid);
    }

    return $conv_id;
}

function chat_listar_conversaciones(PDO $pdo, int $usuario_id): array
{
    chat_asegurar_membresia($pdo, chat_obtener_conversacion_general($pdo), $usuario_id);

    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.tipo,
            c.nombre,
            cp.ultima_lectura_mensaje_id,
            (SELECT mensaje FROM chat_mensajes WHERE conversacion_id = c.id ORDER BY id DESC LIMIT 1) AS ultimo_mensaje,
            (SELECT adjunto_nombre FROM chat_mensajes WHERE conversacion_id = c.id ORDER BY id DESC LIMIT 1) AS ultimo_mensaje_adjunto,
            (SELECT fecha_creacion FROM chat_mensajes WHERE conversacion_id = c.id ORDER BY id DESC LIMIT 1) AS ultimo_mensaje_fecha,
            (SELECT MAX(id) FROM chat_mensajes WHERE conversacion_id = c.id) AS ultimo_mensaje_id,
            (SELECT COUNT(*) FROM chat_mensajes WHERE conversacion_id = c.id AND id > cp.ultima_lectura_mensaje_id AND usuario_id != ?) AS no_leidos
        FROM chat_participantes cp
        JOIN chat_conversaciones c ON c.id = cp.conversacion_id
        WHERE cp.usuario_id = ?
        ORDER BY (c.tipo = 'general') DESC, ultimo_mensaje_id IS NULL, ultimo_mensaje_id DESC
    ");
    $stmt->execute([$usuario_id, $usuario_id]);
    $conversaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($conversaciones as &$conv) {
        if ($conv['tipo'] === 'general') {
            $conv['nombre'] = 'General';
            continue;
        }
        if ($conv['tipo'] === 'directo') {
            $stmt2 = $pdo->prepare("
                SELECT u.id, COALESCE(NULLIF(u.nombre, ''), u.usuario) AS nombre_mostrar
                FROM chat_participantes cp
                JOIN usuarios u ON u.id = cp.usuario_id
                WHERE cp.conversacion_id = ? AND cp.usuario_id != ?
                LIMIT 1
            ");
            $stmt2->execute([$conv['id'], $usuario_id]);
            $otro = $stmt2->fetch(PDO::FETCH_ASSOC);
            $conv['nombre'] = $otro ? $otro['nombre_mostrar'] : 'Usuario';
            $conv['otro_usuario_id'] = $otro ? (int)$otro['id'] : 0;
        }
    }
    unset($conv);

    return $conversaciones;
}

function chat_marcar_leido(PDO $pdo, int $conversacion_id, int $usuario_id, int $hasta_mensaje_id): void
{
    if ($hasta_mensaje_id <= 0) {
        return;
    }
    $stmt = $pdo->prepare("
        UPDATE chat_participantes
        SET ultima_lectura_mensaje_id = GREATEST(ultima_lectura_mensaje_id, ?)
        WHERE conversacion_id = ? AND usuario_id = ?
    ");
    $stmt->execute([$hasta_mensaje_id, $conversacion_id, $usuario_id]);
}

function chat_actualizar_actividad(PDO $pdo, int $usuario_id): void
{
    if ($usuario_id <= 0) {
        return;
    }
    $stmt = $pdo->prepare("UPDATE usuarios SET ultima_actividad = NOW() WHERE id = ?");
    $stmt->execute([$usuario_id]);
}

function chat_usuarios_conectados(PDO $pdo, int $excluir_id, int $minutos = 3): array
{
    $minutos = max(1, $minutos);
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(NULLIF(nombre, ''), usuario) AS nombre_mostrar
        FROM usuarios
        WHERE COALESCE(activo, 1) = 1
          AND id != ?
          AND ultima_actividad IS NOT NULL
          AND ultima_actividad >= (NOW() - INTERVAL ? MINUTE)
        ORDER BY nombre_mostrar ASC
    ");
    $stmt->bindValue(1, $excluir_id, PDO::PARAM_INT);
    $stmt->bindValue(2, $minutos, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function chat_usuarios_activos(PDO $pdo, int $excluir_id): array
{
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(NULLIF(nombre, ''), usuario) AS nombre_mostrar
        FROM usuarios
        WHERE COALESCE(activo, 1) = 1 AND id != ?
        ORDER BY nombre_mostrar ASC
    ");
    $stmt->execute([$excluir_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function chat_obtener_mensajes(PDO $pdo, int $conversacion_id, int $desde_id = 0, int $limite = 50): array
{
    $limite = max(1, min(200, $limite));

    if ($desde_id > 0) {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
                   cm.adjunto_archivo, cm.adjunto_nombre, cm.adjunto_tipo, cm.adjunto_tamano,
                   COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
            FROM chat_mensajes cm
            LEFT JOIN usuarios u ON u.id = cm.usuario_id
            WHERE cm.conversacion_id = ? AND cm.id > ?
            ORDER BY cm.id ASC
            LIMIT $limite
        ");
        $stmt->execute([$conversacion_id, $desde_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Primera carga: traer los últimos $limite mensajes, en orden cronológico
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
               cm.adjunto_archivo, cm.adjunto_nombre, cm.adjunto_tipo, cm.adjunto_tamano,
               COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON u.id = cm.usuario_id
        WHERE cm.conversacion_id = ?
        ORDER BY cm.id DESC
        LIMIT $limite
    ");
    $stmt->execute([$conversacion_id]);
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function chat_obtener_mensaje_por_id(PDO $pdo, int $id): array
{
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
               cm.adjunto_archivo, cm.adjunto_nombre, cm.adjunto_tipo, cm.adjunto_tamano,
               COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON u.id = cm.usuario_id
        WHERE cm.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function chat_enviar_mensaje(PDO $pdo, int $conversacion_id, int $usuario_id, string $mensaje, ?array $adjunto = null): array
{
    $mensaje = trim($mensaje);
    if ($mensaje === '' && $adjunto === null) {
        throw new InvalidArgumentException('El mensaje no puede estar vacío');
    }
    $mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 1000) : substr($mensaje, 0, 1000);

    $stmt = $pdo->prepare("
        INSERT INTO chat_mensajes (conversacion_id, usuario_id, mensaje, adjunto_archivo, adjunto_nombre, adjunto_tipo, adjunto_tamano)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $conversacion_id,
        $usuario_id,
        $mensaje,
        $adjunto['archivo'] ?? null,
        $adjunto['nombre'] ?? null,
        $adjunto['tipo'] ?? null,
        $adjunto['tamano'] ?? null,
    ]);
    $id = (int)$pdo->lastInsertId();

    chat_marcar_leido($pdo, $conversacion_id, $usuario_id, $id);

    return chat_obtener_mensaje_por_id($pdo, $id);
}

function chat_obtener_lecturas(PDO $pdo, int $conversacion_id): array
{
    $stmt = $pdo->prepare("SELECT usuario_id, ultima_lectura_mensaje_id FROM chat_participantes WHERE conversacion_id = ?");
    $stmt->execute([$conversacion_id]);
    $lecturas = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $lecturas[(int)$row['usuario_id']] = (int)$row['ultima_lectura_mensaje_id'];
    }
    return $lecturas;
}

/**
 * Marca cada mensaje como "leído" cuando TODOS los demás participantes de la
 * conversación (todos menos el remitente) ya alcanzaron ese id en su puntero
 * de lectura. En una conversación directa equivale a "leído por el otro"; en
 * un grupo, a "leído por todos".
 */
function chat_marcar_estado_lectura(array $mensajes, array $lecturas): array
{
    foreach ($mensajes as &$m) {
        $remitente = (int)$m['usuario_id'];
        $min_otros = null;
        foreach ($lecturas as $uid => $ultima) {
            if ($uid === $remitente) {
                continue;
            }
            if ($min_otros === null || $ultima < $min_otros) {
                $min_otros = $ultima;
            }
        }
        $m['leido'] = ($min_otros !== null) && ($min_otros >= (int)$m['id']);
    }
    unset($m);
    return $mensajes;
}

function chat_agregar_url_adjuntos(array $mensajes, string $admin_url): array
{
    foreach ($mensajes as &$m) {
        if (!empty($m['adjunto_archivo'])) {
            $m['adjunto_url'] = $admin_url . 'uploads/chat/' . rawurlencode($m['adjunto_archivo']);
        } else {
            $m['adjunto_url'] = null;
        }
    }
    unset($m);
    return $mensajes;
}

function chat_validar_adjunto(array $file): void
{
    $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'rar', 'txt', 'csv'];
    $max_bytes = 10 * 1024 * 1024;

    $error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($error === UPLOAD_ERR_NO_FILE) {
        throw new InvalidArgumentException('No se seleccionó ningún archivo');
    }
    if ($error !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Error al subir el archivo (código ' . $error . ')');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Archivo temporal inválido');
    }
    if ((int)$file['size'] > $max_bytes) {
        throw new InvalidArgumentException('El archivo supera el máximo de 10MB');
    }

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $tipos_permitidos, true)) {
        throw new InvalidArgumentException('Tipo de archivo no permitido');
    }
}

function chat_guardar_adjunto(array $file): array
{
    chat_validar_adjunto($file);

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    $nombre_archivo = 'chat_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    $upload_dir = dirname(__DIR__) . '/uploads/chat/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0775, true);
    }
    if (!is_writable($upload_dir)) {
        throw new RuntimeException('La carpeta de adjuntos no tiene permisos de escritura');
    }
    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $nombre_archivo)) {
        throw new RuntimeException('No se pudo guardar el archivo adjunto');
    }

    $mime = 'application/octet-stream';
    if (function_exists('mime_content_type')) {
        $detectado = @mime_content_type($upload_dir . $nombre_archivo);
        if ($detectado) {
            $mime = $detectado;
        }
    }

    $nombre_original = (string)$file['name'];
    $nombre_original = function_exists('mb_substr') ? mb_substr($nombre_original, 0, 255) : substr($nombre_original, 0, 255);

    return [
        'archivo' => $nombre_archivo,
        'nombre' => $nombre_original,
        'tipo' => $mime,
        'tamano' => (int)$file['size'],
    ];
}
