<?php

if (!function_exists('ensureComprasWorkflowSchema')) {
    function ensureComprasWorkflowSchema(PDO $pdo): void
    {
        try {
            $cols = $pdo->query("SHOW COLUMNS FROM ecommerce_compras")->fetchAll(PDO::FETCH_COLUMN, 0);
            $creadas = [];

            if (!in_array('estado', $cols, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compras ADD COLUMN estado VARCHAR(30) NOT NULL DEFAULT 'orden_pendiente' AFTER total");
                $creadas[] = 'estado';
            }
            if (!in_array('recepcion_estado', $cols, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compras ADD COLUMN recepcion_estado VARCHAR(30) NOT NULL DEFAULT 'pendiente' AFTER estado");
                $creadas[] = 'recepcion_estado';
            }
            if (!in_array('fecha_aprobacion', $cols, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compras ADD COLUMN fecha_aprobacion DATETIME NULL AFTER recepcion_estado");
                $creadas[] = 'fecha_aprobacion';
            }
            if (!in_array('fecha_recepcion', $cols, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compras ADD COLUMN fecha_recepcion DATETIME NULL AFTER fecha_aprobacion");
                $creadas[] = 'fecha_recepcion';
            }
            if (!in_array('stock_actualizado', $cols, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compras ADD COLUMN stock_actualizado TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_recepcion");
                $creadas[] = 'stock_actualizado';
            }

            if (!empty($creadas)) {
                $pdo->exec("UPDATE ecommerce_compras SET estado = 'aprobada' WHERE estado IS NULL OR estado = '' OR estado = 'orden_pendiente'");
                $pdo->exec("UPDATE ecommerce_compras SET recepcion_estado = 'total' WHERE recepcion_estado IS NULL OR recepcion_estado = '' OR recepcion_estado = 'pendiente'");
                $pdo->exec("UPDATE ecommerce_compras SET fecha_aprobacion = COALESCE(fecha_aprobacion, fecha_creacion)");
                $pdo->exec("UPDATE ecommerce_compras SET fecha_recepcion = COALESCE(fecha_recepcion, fecha_creacion) WHERE recepcion_estado = 'total'");
                $pdo->exec("UPDATE ecommerce_compras SET stock_actualizado = 1 WHERE stock_actualizado = 0");
            }
        } catch (Throwable $e) {
            // noop
        }

        try {
            $colsItems = $pdo->query("SHOW COLUMNS FROM ecommerce_compra_items")->fetchAll(PDO::FETCH_COLUMN, 0);
            $creadasItems = [];

            if (!in_array('cantidad_recibida', $colsItems, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compra_items ADD COLUMN cantidad_recibida INT NOT NULL DEFAULT 0 AFTER cantidad");
                $creadasItems[] = 'cantidad_recibida';
            }
            if (!in_array('color_opcion_id', $colsItems, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compra_items ADD COLUMN color_opcion_id INT NULL AFTER producto_id");
                $creadasItems[] = 'color_opcion_id';
            }
            if (!in_array('atributos_json', $colsItems, true)) {
                $pdo->exec("ALTER TABLE ecommerce_compra_items ADD COLUMN atributos_json TEXT NULL AFTER subtotal");
                $creadasItems[] = 'atributos_json';
            }

            if (!empty($creadasItems)) {
                $pdo->exec("UPDATE ecommerce_compra_items SET cantidad_recibida = cantidad WHERE cantidad_recibida = 0");
            }
        } catch (Throwable $e) {
            // noop
        }
    }
}

if (!function_exists('compraEstadoMeta')) {
    function compraEstadoMeta(?string $estado): array
    {
        $map = [
            'orden_pendiente' => ['label' => 'Orden pendiente', 'class' => 'warning text-dark'],
            'aprobada' => ['label' => 'Compra aprobada', 'class' => 'success'],
            'cancelada' => ['label' => 'Cancelada', 'class' => 'danger'],
        ];

        return $map[$estado ?? ''] ?? ['label' => ucfirst((string)$estado), 'class' => 'secondary'];
    }
}

if (!function_exists('compraRecepcionMeta')) {
    function compraRecepcionMeta(?string $estado): array
    {
        $map = [
            'pendiente' => ['label' => 'Pendiente', 'class' => 'secondary'],
            'parcial' => ['label' => 'Recepción parcial', 'class' => 'info'],
            'total' => ['label' => 'Recepción total', 'class' => 'primary'],
        ];

        return $map[$estado ?? ''] ?? ['label' => ucfirst((string)$estado), 'class' => 'secondary'];
    }
}

if (!function_exists('aplicarStockCompraDelta')) {
    function aplicarStockCompraDelta(PDO $pdo, array $item, int $delta, string $referencia): void
    {
        $delta = (int)$delta;
        if ($delta === 0) {
            return;
        }

        $productoId = (int)($item['producto_id'] ?? 0);
        if ($productoId <= 0) {
            return;
        }

        $alto = !empty($item['alto_cm']) ? (int)$item['alto_cm'] : null;
        $ancho = !empty($item['ancho_cm']) ? (int)$item['ancho_cm'] : null;
        $tipoPrecio = (string)($item['tipo_precio'] ?? 'fijo');
        $colorOpcionId = (int)($item['color_opcion_id'] ?? 0);
        $tieneMatriz = $pdo->query("SHOW TABLES LIKE 'ecommerce_matriz_precios'")->rowCount() > 0;
        $tieneOpciones = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'")->rowCount() > 0;

        if ($tipoPrecio === 'variable' && $alto && $ancho && $tieneMatriz) {
            $stmtCheck = $pdo->prepare("SELECT id FROM ecommerce_matriz_precios WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ? LIMIT 1");
            $stmtCheck->execute([$productoId, $alto, $ancho]);
            $matriz = $stmtCheck->fetch(PDO::FETCH_ASSOC);

            if ($matriz) {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_matriz_precios SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
                $stmtUpd->execute([$delta, $matriz['id']]);
            } elseif ($delta > 0) {
                $stmtIns = $pdo->prepare("INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock) VALUES (?, ?, ?, 0, ?)");
                $stmtIns->execute([$productoId, $alto, $ancho, $delta]);
            }
        } elseif ($colorOpcionId > 0 && $tieneOpciones) {
            $stmtUpd = $pdo->prepare("UPDATE ecommerce_atributo_opciones SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
            $stmtUpd->execute([$delta, $colorOpcionId]);
        } else {
            $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = GREATEST(stock + ?, 0) WHERE id = ?");
            $stmtUpd->execute([$delta, $productoId]);
        }

        $stmtMov = $pdo->prepare("INSERT INTO ecommerce_inventario_movimientos (producto_id, tipo, cantidad, alto_cm, ancho_cm, referencia) VALUES (?, 'compra', ?, ?, ?, ?)");
        $stmtMov->execute([$productoId, $delta, $alto, $ancho, substr($referencia, 0, 100)]);
    }
}
