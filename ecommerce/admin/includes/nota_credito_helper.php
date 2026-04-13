<?php

/**
 * NOTA DE CRÉDITO - Helper para gestión de notas de crédito
 * - Crear y emitir notas de crédito vinculadas a pedidos/facturas
 * - Soportar comprobantes fiscales (AFIP tipo 03 - NC)
 * - Generar PDF de nota de crédito
 */

if (!function_exists('nota_credito_table_exists')) {
    function nota_credito_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ensureNotaCreditoSchema')) {
    function ensureNotaCreditoSchema(PDO $pdo): void
    {
        try {
            // Tabla principal de notas de crédito
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_notas_credito (
                id INT AUTO_INCREMENT PRIMARY KEY,
                pedido_id INT NOT NULL,
                numero_nc VARCHAR(50) NULL,
                tipo_nc VARCHAR(5) NULL COMMENT 'Tipo de comprobante: 03=NC A, 08=NC B, 13=NC C',
                monto_total DECIMAL(12,2) NOT NULL DEFAULT 0,
                impuestos_json LONGTEXT NULL,
                motivo VARCHAR(200) NOT NULL COMMENT 'Devolución, Ajuste de precio, etc.',
                descripcion TEXT NULL,
                estado ENUM('borrador','emitida','cancelada') NOT NULL DEFAULT 'borrador',
                comprobante_tipo VARCHAR(20) NOT NULL DEFAULT 'factura' COMMENT 'factura o recibo',
                cae VARCHAR(20) NULL,
                cae_vencimiento DATE NULL,
                afip_resultado VARCHAR(20) NULL COMMENT 'Estado de envío a AFIP',
                afip_observaciones TEXT NULL,
                nc_archivo VARCHAR(255) NULL,
                nc_nombre_original VARCHAR(255) NULL,
                fecha_emision DATETIME NULL,
                creado_por INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_pedido_id (pedido_id),
                KEY idx_numero_nc (numero_nc),
                KEY idx_estado (estado),
                KEY idx_tipo_nc (tipo_nc),
                KEY idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            // Tabla de items de nota de crédito (permite detallar qué items se están devolviendo)
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_notas_credito_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nota_credito_id INT NOT NULL,
                pedido_item_id INT NULL COMMENT 'Referencia al item original del pedido',
                descripcion VARCHAR(255) NOT NULL,
                cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
                precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
                subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
                KEY idx_nota_credito_id (nota_credito_id),
                KEY idx_pedido_item_id (pedido_item_id),
                FOREIGN KEY fk_nota_credito_id (nota_credito_id) REFERENCES ecommerce_notas_credito(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        } catch (Throwable $e) {
            error_log('ensureNotaCreditoSchema error: ' . $e->getMessage());
        }
    }
}

if (!function_exists('nota_credito_get_next_numero')) {
    function nota_credito_get_next_numero(PDO $pdo, string $tipo_nc = '03'): string
    {
        try {
            $stmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING(numero_nc, 6) AS UNSIGNED)), 0) + 1 as siguiente
                FROM ecommerce_notas_credito
                WHERE tipo_nc = ? AND numero_nc IS NOT NULL
            ");
            $stmt->execute([$tipo_nc]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $numero = (int)($result['siguiente'] ?? 1);
            
            // Obtener punto de venta de config AFIP
            $afipConfig = [];
            if (function_exists('contabilidad_get_afip_config')) {
                $afipConfig = contabilidad_get_afip_config($pdo);
            }
            $punto_venta = (int)($afipConfig['punto_venta'] ?? 1);
            
            return sprintf('%s-%04d-%08d', $tipo_nc, $punto_venta, $numero);
        } catch (Throwable $e) {
            return $tipo_nc . '-0001-00000001';
        }
    }
}

if (!function_exists('nota_credito_crear')) {
    function nota_credito_crear(PDO $pdo, int $pedido_id, array $data): int
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            // Validar que el pedido existe y tiene factura
            $stmt = $pdo->prepare("SELECT id, numero_factura, tipo_factura, comprobante_tipo, total FROM ecommerce_pedidos WHERE id = ?");
            $stmt->execute([$pedido_id]);
            $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$pedido) {
                throw new Exception('Pedido no encontrado');
            }
            
            // Determinar tipo de comprobante
            $comprobante_tipo = trim((string)($data['comprobante_tipo'] ?? $pedido['comprobante_tipo'] ?? 'factura'));
            if (!in_array($comprobante_tipo, ['factura', 'recibo'], true)) {
                $comprobante_tipo = 'factura';
            }
            
            // Determinar tipo de NC según factura
            $tipo_nc = trim((string)($data['tipo_nc'] ?? '03'));
            if (!in_array($tipo_nc, ['03', '08', '13'], true)) {
                $tipo_nc = '03';
            }
            
            // Obtener o generar número de NC
            $numero_nc = trim((string)($data['numero_nc'] ?? ''));
            if (empty($numero_nc)) {
                $numero_nc = nota_credito_get_next_numero($pdo, $tipo_nc);
            }
            
            // Monto total
            $monto_total = floatval($data['monto_total'] ?? 0);
            if ($monto_total <= 0) {
                throw new Exception('El monto de la nota de crédito debe ser mayor a 0');
            }
            
            // Motivo
            $motivo = trim((string)($data['motivo'] ?? 'Devolución'));
            if (empty($motivo)) {
                $motivo = 'Ajuste de factura';
            }
            
            $descripcion = trim((string)($data['descripcion'] ?? ''));
            $impuestos_json = $data['impuestos_json'] ?? null;
            $creado_por = (int)($data['creado_por'] ?? ($_SESSION['usuario_id'] ?? null));
            
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_notas_credito
                (pedido_id, numero_nc, tipo_nc, monto_total, impuestos_json, motivo, descripcion, comprobante_tipo, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $pedido_id,
                $numero_nc,
                $tipo_nc,
                $monto_total,
                $impuestos_json,
                $motivo,
                $descripcion,
                $comprobante_tipo,
                $creado_por
            ]);
            
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('nota_credito_crear error: ' . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('nota_credito_obtener')) {
    function nota_credito_obtener(PDO $pdo, int $nota_credito_id): ?array
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            $stmt = $pdo->prepare("
                SELECT nc.*, 
                       p.numero_factura as factura_original,
                       p.tipo_factura as tipo_factura_original,
                       p.total as pedido_total,
                       c.nombre as cliente_nombre,
                       c.email as cliente_email
                FROM ecommerce_notas_credito nc
                LEFT JOIN ecommerce_pedidos p ON nc.pedido_id = p.id
                LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
                WHERE nc.id = ?
            ");
            $stmt->execute([$nota_credito_id]);
            $nc = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$nc) {
                return null;
            }
            
            // Obtener items
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_notas_credito_items
                WHERE nota_credito_id = ?
                ORDER BY id ASC
            ");
            $stmt->execute([$nota_credito_id]);
            $nc['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
            return $nc;
        } catch (Throwable $e) {
            error_log('nota_credito_obtener error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('nota_credito_listar')) {
    function nota_credito_listar(PDO $pdo, array $filtros = []): array
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            $sql = "
                SELECT nc.id, nc.numero_nc, nc.monto_total, nc.motivo, nc.estado,
                       nc.cae, nc.fecha_emision,
                       c.nombre as cliente_nombre,
                       p.numero_factura as factura_original
                FROM ecommerce_notas_credito nc
                LEFT JOIN ecommerce_pedidos p ON nc.pedido_id = p.id
                LEFT JOIN ecommerce_clientes c ON p.cliente_id = c.id
                WHERE 1=1
            ";
            
            $params = [];
            
            if (!empty($filtros['pedido_id'])) {
                $sql .= " AND nc.pedido_id = ?";
                $params[] = (int)$filtros['pedido_id'];
            }
            
            if (!empty($filtros['estado'])) {
                $sql .= " AND nc.estado = ?";
                $params[] = $filtros['estado'];
            }
            
            if (!empty($filtros['numero_nc'])) {
                $sql .= " AND nc.numero_nc LIKE ?";
                $params[] = '%' . $filtros['numero_nc'] . '%';
            }
            
            if (!empty($filtros['cliente_nombre'])) {
                $sql .= " AND c.nombre LIKE ?";
                $params[] = '%' . $filtros['cliente_nombre'] . '%';
            }
            
            $sql .= " ORDER BY nc.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            error_log('nota_credito_listar error: ' . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('nota_credito_agregar_item')) {
    function nota_credito_agregar_item(PDO $pdo, int $nota_credito_id, array $item_data): int
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            $descripcion = trim((string)($item_data['descripcion'] ?? ''));
            $cantidad = floatval($item_data['cantidad'] ?? 1);
            $precio_unitario = floatval($item_data['precio_unitario'] ?? 0);
            $subtotal = $cantidad * $precio_unitario;
            $pedido_item_id = !empty($item_data['pedido_item_id']) ? (int)$item_data['pedido_item_id'] : null;
            
            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_notas_credito_items
                (nota_credito_id, pedido_item_id, descripcion, cantidad, precio_unitario, subtotal)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $nota_credito_id,
                $pedido_item_id,
                $descripcion,
                $cantidad,
                $precio_unitario,
                $subtotal
            ]);
            
            // Recalcular monto total de la NC
            $stmtRecalc = $pdo->prepare("UPDATE ecommerce_notas_credito SET monto_total = (SELECT COALESCE(SUM(subtotal), 0) FROM ecommerce_notas_credito_items WHERE nota_credito_id = ?) WHERE id = ?");
            $stmtRecalc->execute([$nota_credito_id, $nota_credito_id]);
            
            return (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('nota_credito_agregar_item error: ' . $e->getMessage());
            throw $e;
        }
    }
}

if (!function_exists('nota_credito_emitir')) {
    function nota_credito_emitir(PDO $pdo, int $nota_credito_id): array
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            $nc = nota_credito_obtener($pdo, $nota_credito_id);
            if (!$nc) {
                return ['ok' => false, 'error' => 'Nota de crédito no encontrada'];
            }
            
            if ($nc['estado'] !== 'borrador') {
                return ['ok' => false, 'error' => 'La NC debe estar en estado borrador para emitir'];
            }
            
            // Si es recibo, solo marcar como emitida
            if ($nc['comprobante_tipo'] === 'recibo') {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_notas_credito
                    SET estado = 'emitida', fecha_emision = NOW(), numero_nc = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nc['numero_nc'], $nota_credito_id]);
                
                return ['ok' => true, 'message' => 'Nota de crédito emitida como recibo interno'];
            }
            
            // Si es factura, intentar emitir con AFIP
            if ($nc['comprobante_tipo'] === 'factura') {
                // Por ahora, solo marcar como emitida (se puede integrar con AFIP luego)
                // La integración con AFIP para NC tipo 03 requiere:
                // - FERecuperatorXML para solicitar CAE
                // - Validación de factura de origen
                // - Generación de número secuencial
                
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_notas_credito
                    SET estado = 'emitida', fecha_emision = NOW(), afip_resultado = 'PENDIENTE', numero_nc = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nc['numero_nc'], $nota_credito_id]);
                
                return ['ok' => true, 'message' => 'Nota de crédito emitida. Pendiente solicitud CAE a AFIP'];
            }
            
            return ['ok' => false, 'error' => 'Tipo de comprobante no reconocido'];
        } catch (Throwable $e) {
            error_log('nota_credito_emitir error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

if (!function_exists('nota_credito_cancelar')) {
    function nota_credito_cancelar(PDO $pdo, int $nota_credito_id, string $motivo = ''): array
    {
        try {
            ensureNotaCreditoSchema($pdo);
            
            $stmt = $pdo->prepare("
                UPDATE ecommerce_notas_credito
                SET estado = 'cancelada'
                WHERE id = ? AND estado IN ('borrador', 'emitida')
            ");
            $stmt->execute([$nota_credito_id]);
            
            if ($stmt->rowCount() > 0) {
                return ['ok' => true, 'message' => 'Nota de crédito cancelada'];
            }
            
            return ['ok' => false, 'error' => 'No se pudo cancelar la nota de crédito'];
        } catch (Throwable $e) {
            error_log('nota_credito_cancelar error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
