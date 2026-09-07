<?php
function auditoria_asegurar_tabla(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_auditorias (
        id INT PRIMARY KEY AUTO_INCREMENT,
        tabla VARCHAR(100) NOT NULL,
        registro_id INT NULL,
        accion VARCHAR(100) NOT NULL,
        datos_json JSON NULL,
        usuario_id INT NULL,
        ip VARCHAR(45) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_tabla_registro (tabla, registro_id),
        INDEX idx_usuario (usuario_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function auditoria_registrar(PDO $pdo, string $tabla, $registro_id = null, string $accion = 'accion', array $datos = [], $usuario_id = null): void
{
    try {
        auditoria_asegurar_tabla($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("INSERT INTO ecommerce_auditorias (tabla, registro_id, accion, datos_json, usuario_id, ip) VALUES (?, ?, ?, ?, ?, ?)");
        $json = null;
        if (!empty($datos)) {
            $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        }
        $stmt->execute([
            $tabla,
            $registro_id !== null ? (int)$registro_id : null,
            $accion,
            $json,
            $usuario_id !== null ? (int)$usuario_id : (!empty($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null),
            $ip
        ]);
    } catch (Throwable $e) {
        // No interrumpir la ejecución si falla la auditoría
    }
}

function auditoria_listar(PDO $pdo, string $tabla, $registro_id = null, int $limit = 100): array
{
    try {
        auditoria_asegurar_tabla($pdo);
        if ($registro_id === null) {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_auditorias WHERE tabla = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$tabla, $limit]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_auditorias WHERE tabla = ? AND registro_id = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$tabla, (int)$registro_id, $limit]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

?>
