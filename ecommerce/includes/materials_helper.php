<?php
/** Helper para overrides de costo de materiales a nivel de pedido */
function materials_asegurar_tabla_overrides(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_pedido_material_overrides (
        id INT PRIMARY KEY AUTO_INCREMENT,
        pedido_id INT NOT NULL,
        material_producto_id INT NOT NULL,
        costo_unitario DECIMAL(12,4) NOT NULL,
        comentario VARCHAR(255) NULL,
        usuario_id INT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_pedido_material (pedido_id, material_producto_id),
        INDEX idx_pedido (pedido_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function materials_obtener_override(PDO $pdo, int $pedido_id, int $material_producto_id): ?float
{
    try {
        materials_asegurar_tabla_overrides($pdo);
        $stmt = $pdo->prepare("SELECT costo_unitario FROM ecommerce_pedido_material_overrides WHERE pedido_id = ? AND material_producto_id = ? LIMIT 1");
        $stmt->execute([$pedido_id, $material_producto_id]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (float)$v : null;
    } catch (Throwable $e) {
        return null;
    }
}

function materials_guardar_override(PDO $pdo, int $pedido_id, int $material_producto_id, float $costo_unitario, ?string $comentario = null, ?int $usuario_id = null): bool
{
    try {
        materials_asegurar_tabla_overrides($pdo);
        $stmt = $pdo->prepare("INSERT INTO ecommerce_pedido_material_overrides (pedido_id, material_producto_id, costo_unitario, comentario, usuario_id) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE costo_unitario = VALUES(costo_unitario), comentario = VALUES(comentario), usuario_id = VALUES(usuario_id), created_at = NOW()");
        $stmt->execute([$pedido_id, $material_producto_id, $costo_unitario, $comentario, $usuario_id]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

function materials_listar_overrides_por_pedido(PDO $pdo, int $pedido_id): array
{
    try {
        materials_asegurar_tabla_overrides($pdo);
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_material_overrides WHERE pedido_id = ? ORDER BY created_at DESC");
        $stmt->execute([$pedido_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
}

function materials_obtener_override_total(PDO $pdo, int $pedido_id): ?float
{
    try {
        $stmt = $pdo->prepare("SELECT costo_total_override FROM ecommerce_pedido_costo_override WHERE pedido_id = ? LIMIT 1");
        $stmt->execute([$pedido_id]);
        $v = $stmt->fetchColumn();
        return $v !== false ? (float)$v : null;
    } catch (Throwable $e) {
        return null;
    }
}

function materials_guardar_override_total(PDO $pdo, int $pedido_id, float $costo_total_override, ?int $usuario_id = null): bool
{
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_pedido_costo_override (
            pedido_id INT PRIMARY KEY,
            costo_total_override DECIMAL(12,2) NULL,
            usuario_id INT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $stmt = $pdo->prepare("INSERT INTO ecommerce_pedido_costo_override (pedido_id, costo_total_override, usuario_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE costo_total_override = VALUES(costo_total_override), usuario_id = VALUES(usuario_id), updated_at = NOW()");
        $stmt->execute([$pedido_id, $costo_total_override, $usuario_id]);
        return true;
    } catch (Throwable $e) {
        return false;
    }
}

?>
