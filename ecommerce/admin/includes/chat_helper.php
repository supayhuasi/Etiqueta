<?php

function chat_asegurar_tablas(PDO $pdo): void
{
    static $verificado = false;
    if ($verificado) {
        return;
    }
    $verificado = true;

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chat_mensajes (
            id INT PRIMARY KEY AUTO_INCREMENT,
            usuario_id INT NOT NULL,
            mensaje VARCHAR(1000) NOT NULL,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_chat_mensajes_usuario (usuario_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    if (function_exists('admin_column_exists') && !admin_column_exists($pdo, 'usuarios', 'ultima_actividad')) {
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN ultima_actividad DATETIME NULL");
    }
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

function chat_obtener_mensajes(PDO $pdo, int $desde_id = 0, int $limite = 50): array
{
    $limite = max(1, min(200, $limite));

    if ($desde_id > 0) {
        $stmt = $pdo->prepare("
            SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
                   COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
            FROM chat_mensajes cm
            LEFT JOIN usuarios u ON u.id = cm.usuario_id
            WHERE cm.id > ?
            ORDER BY cm.id ASC
            LIMIT $limite
        ");
        $stmt->execute([$desde_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Primera carga: traer los últimos $limite mensajes, en orden cronológico
    $stmt = $pdo->prepare("
        SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
               COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON u.id = cm.usuario_id
        ORDER BY cm.id DESC
        LIMIT $limite
    ");
    $stmt->execute();
    return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));
}

function chat_enviar_mensaje(PDO $pdo, int $usuario_id, string $mensaje): array
{
    $mensaje = trim($mensaje);
    if ($mensaje === '') {
        throw new InvalidArgumentException('El mensaje no puede estar vacío');
    }
    $mensaje = function_exists('mb_substr') ? mb_substr($mensaje, 0, 1000) : substr($mensaje, 0, 1000);

    $stmt = $pdo->prepare("INSERT INTO chat_mensajes (usuario_id, mensaje) VALUES (?, ?)");
    $stmt->execute([$usuario_id, $mensaje]);
    $id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT cm.id, cm.usuario_id, cm.mensaje, cm.fecha_creacion,
               COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario') AS autor_nombre
        FROM chat_mensajes cm
        LEFT JOIN usuarios u ON u.id = cm.usuario_id
        WHERE cm.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}
