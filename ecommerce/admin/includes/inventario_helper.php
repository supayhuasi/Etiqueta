<?php

if (!function_exists('inventario_entradas_pendientes')) {
    /**
     * Cantidades de compras no canceladas que todavía no se recibieron.
     *
     * @return array{productos: array<int,float>, colores: array<int,float>}
     */
    function inventario_entradas_pendientes(PDO $pdo): array
    {
        $vacio = ['productos' => [], 'colores' => []];

        if (!admin_table_exists($pdo, 'ecommerce_compras') || !admin_table_exists($pdo, 'ecommerce_compra_items')) {
            return $vacio;
        }
        if (!admin_column_exists($pdo, 'ecommerce_compra_items', 'producto_id')
            || !admin_column_exists($pdo, 'ecommerce_compra_items', 'cantidad')) {
            return $vacio;
        }

        $recibida = admin_column_exists($pdo, 'ecommerce_compra_items', 'cantidad_recibida')
            ? 'COALESCE(ci.cantidad_recibida, 0)'
            : '0';
        $colorSel = admin_column_exists($pdo, 'ecommerce_compra_items', 'color_opcion_id')
            ? 'ci.color_opcion_id'
            : 'NULL';

        $where = ['1=1'];
        if (admin_column_exists($pdo, 'ecommerce_compras', 'estado')) {
            $where[] = "COALESCE(c.estado, '') <> 'cancelada'";
        }
        if (admin_column_exists($pdo, 'ecommerce_compras', 'recepcion_estado')) {
            $where[] = "COALESCE(c.recepcion_estado, 'pendiente') IN ('pendiente', 'parcial')";
        }

        try {
            $sql = "SELECT ci.producto_id, {$colorSel} AS color_opcion_id,
                           SUM(GREATEST(ci.cantidad - {$recibida}, 0)) AS pendiente
                    FROM ecommerce_compra_items ci
                    INNER JOIN ecommerce_compras c ON c.id = ci.compra_id
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY ci.producto_id, {$colorSel}";
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('inventario_entradas_pendientes: ' . $e->getMessage());
            return $vacio;
        }

        $productos = [];
        $colores = [];
        foreach ($rows as $row) {
            $pendiente = (float)($row['pendiente'] ?? 0);
            if ($pendiente <= 0) {
                continue;
            }
            $productoId = (int)($row['producto_id'] ?? 0);
            $colorId = (int)($row['color_opcion_id'] ?? 0);
            if ($productoId > 0) {
                $productos[$productoId] = ($productos[$productoId] ?? 0) + $pendiente;
            }
            if ($colorId > 0) {
                $colores[$colorId] = ($colores[$colorId] ?? 0) + $pendiente;
            }
        }

        return ['productos' => $productos, 'colores' => $colores];
    }
}

if (!function_exists('inventario_alerta_stock')) {
    function inventario_alerta_stock(float $stock, float $stockMinimo): string
    {
        if ($stock < 0) {
            return 'negativo';
        }
        if ($stock == 0.0) {
            return 'sin_stock';
        }
        if ($stockMinimo > 0 && $stock <= $stockMinimo) {
            return 'bajo_minimo';
        }
        return 'normal';
    }
}
