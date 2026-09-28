<?php

if (!function_exists('utilidad_fecha_columna_pedidos')) {
    function utilidad_fecha_columna_pedidos(PDO $pdo): string
    {
        foreach (['fecha_pedido', 'fecha_creacion', 'fecha', 'created_at'] as $col) {
            if (function_exists('admin_column_exists') && admin_column_exists($pdo, 'ecommerce_pedidos', $col)) {
                return $col;
            }
        }
        return 'fecha_creacion';
    }
}

if (!function_exists('utilidad_costo_compra_unitario')) {
    function utilidad_costo_compra_unitario(PDO $pdo, int $productoId): float
    {
        static $cache = [];
        if ($productoId <= 0) {
            return 0.0;
        }
        if (array_key_exists($productoId, $cache)) {
            return $cache[$productoId];
        }

        $cache[$productoId] = 0.0;
        if (!function_exists('admin_table_exists')
            || !admin_table_exists($pdo, 'ecommerce_compra_items')
            || !admin_table_exists($pdo, 'ecommerce_compras')) {
            return 0.0;
        }

        try {
            $fechaCompra = function_exists('admin_column_exists') && admin_column_exists($pdo, 'ecommerce_compras', 'fecha_compra')
                ? 'c.fecha_compra'
                : 'c.id';
            $stmt = $pdo->prepare("
                SELECT ci.costo_unitario
                FROM ecommerce_compra_items ci
                INNER JOIN ecommerce_compras c ON c.id = ci.compra_id
                WHERE ci.producto_id = ?
                  AND ci.costo_unitario > 0
                ORDER BY {$fechaCompra} DESC, c.id DESC, ci.id DESC
                LIMIT 1
            ");
            $stmt->execute([$productoId]);
            $cache[$productoId] = max(0.0, (float)$stmt->fetchColumn());
        } catch (Throwable $e) {
            $cache[$productoId] = 0.0;
        }

        return $cache[$productoId];
    }
}

if (!function_exists('utilidad_costo_item')) {
    function utilidad_costo_item(PDO $pdo, array $item): array
    {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $productoId = (int)($item['producto_id'] ?? 0);
        if ($cantidad <= 0 || $productoId <= 0) {
            return ['costo' => 0.0, 'origen' => 'sin_dato'];
        }

        $usaReceta = !empty($item['usa_receta']);
        if ($usaReceta) {
            $recetasFile = dirname(__DIR__, 2) . '/includes/funciones_recetas.php';
            if (is_file($recetasFile)) {
                require_once $recetasFile;
            }
            if (function_exists('calcular_costo_material_pedido')) {
                $costoReceta = (float)calcular_costo_material_pedido($pdo, [$item], null);
                if ($costoReceta > 0) {
                    return ['costo' => round($costoReceta, 2), 'origen' => 'receta'];
                }
            }
        }

        $unitario = utilidad_costo_compra_unitario($pdo, $productoId);
        if ($unitario > 0) {
            return ['costo' => round($unitario * $cantidad, 2), 'origen' => 'compra'];
        }

        return ['costo' => 0.0, 'origen' => 'sin_costo'];
    }
}

if (!function_exists('utilidad_venta_item')) {
    function utilidad_venta_item(array $item): float
    {
        $cantidad = (float)($item['cantidad'] ?? 0);
        $precio = (float)($item['precio_unitario'] ?? 0);
        $bruto = (float)($item['subtotal'] ?? 0);
        if ($bruto <= 0) {
            $bruto = $precio * $cantidad;
        }

        $pedidoSubtotal = (float)($item['pedido_subtotal'] ?? 0);
        $descuento = (float)($item['pedido_descuento'] ?? 0);
        if ($pedidoSubtotal > 0 && $descuento > 0) {
            $factor = max(0.0, ($pedidoSubtotal - $descuento) / $pedidoSubtotal);
            $bruto *= $factor;
        }

        return round(max(0.0, $bruto), 2);
    }
}

if (!function_exists('utilidad_reporte_rango')) {
    /**
     * @return array{
     *   categorias: array<int, array<string, mixed>>,
     *   totales: array<string, float|int>,
     *   sin_costo: int
     * }
     */
    function utilidad_reporte_rango(PDO $pdo, string $desde, string $hasta): array
    {
        $vacio = [
            'categorias' => [],
            'totales' => [
                'cantidad' => 0.0,
                'venta' => 0.0,
                'costo' => 0.0,
                'utilidad' => 0.0,
                'margen_pct' => 0.0,
                'pedidos' => 0,
                'productos' => 0,
            ],
            'sin_costo' => 0,
        ];

        if (!function_exists('admin_table_exists')
            || !admin_table_exists($pdo, 'ecommerce_pedidos')
            || !admin_table_exists($pdo, 'ecommerce_pedido_items')) {
            return $vacio;
        }

        $fechaCol = utilidad_fecha_columna_pedidos($pdo);
        $tieneProductos = admin_table_exists($pdo, 'ecommerce_productos');
        $tieneCategorias = admin_table_exists($pdo, 'ecommerce_categorias');
        $tieneAtributos = admin_column_exists($pdo, 'ecommerce_pedido_items', 'atributos');
        $tieneUsaReceta = $tieneProductos && admin_column_exists($pdo, 'ecommerce_productos', 'usa_receta');
        $tieneDescPedido = admin_column_exists($pdo, 'ecommerce_pedidos', 'descuento_monto');
        $tieneSubtotalPedido = admin_column_exists($pdo, 'ecommerce_pedidos', 'subtotal');
        $tieneCupon = admin_column_exists($pdo, 'ecommerce_pedidos', 'cupon_descuento');
        $tieneAlto = admin_column_exists($pdo, 'ecommerce_pedido_items', 'alto_cm');
        $tieneAncho = admin_column_exists($pdo, 'ecommerce_pedido_items', 'ancho_cm');
        $tieneSubtotalItem = admin_column_exists($pdo, 'ecommerce_pedido_items', 'subtotal');

        $select = [
            'pi.pedido_id',
            'pi.producto_id',
            'pi.cantidad',
            'pi.precio_unitario',
            $tieneSubtotalItem ? 'pi.subtotal' : 'NULL AS subtotal',
            $tieneAlto ? 'pi.alto_cm' : '0 AS alto_cm',
            $tieneAncho ? 'pi.ancho_cm' : '0 AS ancho_cm',
            $tieneAtributos ? 'pi.atributos' : 'NULL AS atributos',
            $tieneSubtotalPedido ? 'p.subtotal AS pedido_subtotal' : '0 AS pedido_subtotal',
            $tieneDescPedido ? 'COALESCE(p.descuento_monto, 0) AS pedido_descuento_base' : '0 AS pedido_descuento_base',
            $tieneCupon ? 'COALESCE(p.cupon_descuento, 0) AS pedido_cupon' : '0 AS pedido_cupon',
        ];

        $joins = ['INNER JOIN ecommerce_pedidos p ON p.id = pi.pedido_id'];
        if ($tieneProductos) {
            $select[] = 'pr.nombre AS producto_nombre';
            $select[] = 'pr.categoria_id';
            $select[] = $tieneUsaReceta ? 'COALESCE(pr.usa_receta, 0) AS usa_receta' : '0 AS usa_receta';
            $joins[] = 'LEFT JOIN ecommerce_productos pr ON pr.id = pi.producto_id';
        } else {
            $select[] = 'NULL AS producto_nombre';
            $select[] = 'NULL AS categoria_id';
            $select[] = '0 AS usa_receta';
        }
        if ($tieneCategorias && $tieneProductos) {
            $select[] = 'cat.nombre AS categoria_nombre';
            $joins[] = 'LEFT JOIN ecommerce_categorias cat ON cat.id = pr.categoria_id';
        } else {
            $select[] = 'NULL AS categoria_nombre';
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
            FROM ecommerce_pedido_items pi
            ' . implode("\n            ", $joins) . '
            WHERE p.`' . $fechaCol . '` BETWEEN ? AND ?
              AND COALESCE(p.estado, \'\') NOT IN (\'cancelado\', \'pago_rechazado\', \'pago_reembolsado\')
        ';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$desde, $hasta]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            error_log('utilidad_reporte_rango: ' . $e->getMessage());
            return $vacio;
        }

        $categorias = [];
        $pedidos = [];
        $sinCosto = 0;

        foreach ($rows as $row) {
            $row['pedido_descuento'] = (float)($row['pedido_descuento_base'] ?? 0) + (float)($row['pedido_cupon'] ?? 0);
            $venta = utilidad_venta_item($row);
            $costoInfo = utilidad_costo_item($pdo, $row);
            $costo = (float)$costoInfo['costo'];
            $utilidad = round($venta - $costo, 2);
            $cantidad = (float)($row['cantidad'] ?? 0);
            $productoId = (int)($row['producto_id'] ?? 0);
            $categoriaId = (int)($row['categoria_id'] ?? 0);
            $categoriaNombre = trim((string)($row['categoria_nombre'] ?? ''));
            if ($categoriaNombre === '') {
                $categoriaId = 0;
                $categoriaNombre = 'Sin categoría';
            }
            $productoNombre = trim((string)($row['producto_nombre'] ?? ''));
            if ($productoNombre === '') {
                $productoNombre = $productoId > 0 ? ('Producto #' . $productoId) : 'Ítem sin ficha';
            }

            if ($costoInfo['origen'] === 'sin_costo') {
                $sinCosto++;
            }

            if (!isset($categorias[$categoriaId])) {
                $categorias[$categoriaId] = [
                    'categoria_id' => $categoriaId,
                    'categoria' => $categoriaNombre,
                    'cantidad' => 0.0,
                    'venta' => 0.0,
                    'costo' => 0.0,
                    'utilidad' => 0.0,
                    'margen_pct' => 0.0,
                    'pedidos' => [],
                    'productos' => [],
                ];
            }

            $categorias[$categoriaId]['cantidad'] += $cantidad;
            $categorias[$categoriaId]['venta'] += $venta;
            $categorias[$categoriaId]['costo'] += $costo;
            $categorias[$categoriaId]['utilidad'] += $utilidad;
            $categorias[$categoriaId]['pedidos'][(int)$row['pedido_id']] = true;

            $prodKey = $productoId > 0 ? (string)$productoId : ('x-' . md5($productoNombre));
            if (!isset($categorias[$categoriaId]['productos'][$prodKey])) {
                $categorias[$categoriaId]['productos'][$prodKey] = [
                    'producto_id' => $productoId,
                    'producto' => $productoNombre,
                    'cantidad' => 0.0,
                    'venta' => 0.0,
                    'costo' => 0.0,
                    'utilidad' => 0.0,
                    'margen_pct' => 0.0,
                    'origen_costo' => $costoInfo['origen'],
                    'pedidos' => [],
                ];
            }
            $categorias[$categoriaId]['productos'][$prodKey]['cantidad'] += $cantidad;
            $categorias[$categoriaId]['productos'][$prodKey]['venta'] += $venta;
            $categorias[$categoriaId]['productos'][$prodKey]['costo'] += $costo;
            $categorias[$categoriaId]['productos'][$prodKey]['utilidad'] += $utilidad;
            $categorias[$categoriaId]['productos'][$prodKey]['pedidos'][(int)$row['pedido_id']] = true;
            if ($costoInfo['origen'] === 'sin_costo') {
                $categorias[$categoriaId]['productos'][$prodKey]['origen_costo'] = 'sin_costo';
            }

            $pedidos[(int)$row['pedido_id']] = true;
        }

        $totales = [
            'cantidad' => 0.0,
            'venta' => 0.0,
            'costo' => 0.0,
            'utilidad' => 0.0,
            'margen_pct' => 0.0,
            'pedidos' => count($pedidos),
            'productos' => 0,
        ];

        foreach ($categorias as &$cat) {
            $cat['venta'] = round((float)$cat['venta'], 2);
            $cat['costo'] = round((float)$cat['costo'], 2);
            $cat['utilidad'] = round((float)$cat['utilidad'], 2);
            $cat['margen_pct'] = $cat['venta'] > 0 ? round(($cat['utilidad'] / $cat['venta']) * 100, 1) : 0.0;
            $cat['pedidos'] = count($cat['pedidos']);

            foreach ($cat['productos'] as &$prod) {
                $prod['venta'] = round((float)$prod['venta'], 2);
                $prod['costo'] = round((float)$prod['costo'], 2);
                $prod['utilidad'] = round((float)$prod['utilidad'], 2);
                $prod['margen_pct'] = $prod['venta'] > 0 ? round(($prod['utilidad'] / $prod['venta']) * 100, 1) : 0.0;
                $prod['pedidos'] = count($prod['pedidos']);
            }
            unset($prod);

            uasort($cat['productos'], static function ($a, $b) {
                return ($b['utilidad'] <=> $a['utilidad']);
            });
            $cat['productos'] = array_values($cat['productos']);
            $totales['productos'] += count($cat['productos']);
            $totales['cantidad'] += (float)$cat['cantidad'];
            $totales['venta'] += (float)$cat['venta'];
            $totales['costo'] += (float)$cat['costo'];
            $totales['utilidad'] += (float)$cat['utilidad'];
        }
        unset($cat);

        uasort($categorias, static function ($a, $b) {
            return ($b['utilidad'] <=> $a['utilidad']);
        });

        $totales['venta'] = round((float)$totales['venta'], 2);
        $totales['costo'] = round((float)$totales['costo'], 2);
        $totales['utilidad'] = round((float)$totales['utilidad'], 2);
        $totales['margen_pct'] = $totales['venta'] > 0 ? round(($totales['utilidad'] / $totales['venta']) * 100, 1) : 0.0;

        return [
            'categorias' => array_values($categorias),
            'totales' => $totales,
            'sin_costo' => $sinCosto,
        ];
    }
}

if (!function_exists('utilidad_fmt_money')) {
    function utilidad_fmt_money(float $valor): string
    {
        return '$' . number_format($valor, 2, ',', '.');
    }
}

if (!function_exists('utilidad_resolver_periodo')) {
    /**
     * @param array<string, mixed> $get
     * @return array{desde:string,hasta:string,categoria_id:int,label:string}
     */
    function utilidad_resolver_periodo(array $get): array
    {
        $hoy = new DateTime('today');
        $desde = trim((string)($get['desde'] ?? ''));
        $hasta = trim((string)($get['hasta'] ?? ''));
        $preset = trim((string)($get['preset'] ?? ''));
        $categoriaId = (int)($get['categoria_id'] ?? 0);

        if ($preset === 'mes') {
            $desde = (clone $hoy)->modify('first day of this month')->format('Y-m-d');
            $hasta = $hoy->format('Y-m-d');
        } elseif ($preset === 'mes_ant') {
            $desde = (clone $hoy)->modify('first day of last month')->format('Y-m-d');
            $hasta = (clone $hoy)->modify('last day of last month')->format('Y-m-d');
        } elseif ($preset === '30') {
            $desde = (clone $hoy)->modify('-29 days')->format('Y-m-d');
            $hasta = $hoy->format('Y-m-d');
        } elseif ($preset === '90') {
            $desde = (clone $hoy)->modify('-89 days')->format('Y-m-d');
            $hasta = $hoy->format('Y-m-d');
        } elseif ($preset === 'anio') {
            $desde = $hoy->format('Y-01-01');
            $hasta = $hoy->format('Y-m-d');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = (clone $hoy)->modify('first day of this month')->format('Y-m-d');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $hoy->format('Y-m-d');
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'desde' => $desde,
            'hasta' => $hasta,
            'categoria_id' => max(0, $categoriaId),
            'label' => date('d/m/Y', strtotime($desde)) . ' — ' . date('d/m/Y', strtotime($hasta)),
        ];
    }
}

if (!function_exists('utilidad_aplicar_filtro_categoria')) {
    /**
     * @param array{categorias: array<int, array<string, mixed>>, totales: array<string, float|int>, sin_costo: int} $reporte
     * @return array{categorias: array<int, array<string, mixed>>, totales: array<string, float|int>, sin_costo: int}
     */
    function utilidad_aplicar_filtro_categoria(array $reporte, int $categoriaId): array
    {
        if ($categoriaId <= 0) {
            return $reporte;
        }

        $categorias = array_values(array_filter($reporte['categorias'], static function ($cat) use ($categoriaId) {
            return (int)$cat['categoria_id'] === $categoriaId;
        }));

        $totales = [
            'cantidad' => 0.0,
            'venta' => 0.0,
            'costo' => 0.0,
            'utilidad' => 0.0,
            'margen_pct' => 0.0,
            'pedidos' => 0,
            'productos' => 0,
        ];
        foreach ($categorias as $cat) {
            $totales['cantidad'] += (float)$cat['cantidad'];
            $totales['venta'] += (float)$cat['venta'];
            $totales['costo'] += (float)$cat['costo'];
            $totales['utilidad'] += (float)$cat['utilidad'];
            $totales['productos'] += count($cat['productos']);
            $totales['pedidos'] += (int)$cat['pedidos'];
        }
        $totales['venta'] = round((float)$totales['venta'], 2);
        $totales['costo'] = round((float)$totales['costo'], 2);
        $totales['utilidad'] = round((float)$totales['utilidad'], 2);
        $totales['margen_pct'] = $totales['venta'] > 0 ? round(($totales['utilidad'] / $totales['venta']) * 100, 1) : 0.0;

        $reporte['categorias'] = $categorias;
        $reporte['totales'] = $totales;
        return $reporte;
    }
}

if (!function_exists('utilidad_empresa_membrete')) {
    /**
     * @return array{nombre:string,logo:?string}
     */
    function utilidad_empresa_membrete(PDO $pdo): array
    {
        $nombre = 'Tucu Roller';
        $logo = null;
        try {
            $row = $pdo->query("SELECT nombre, logo FROM ecommerce_empresa LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($row['nombre'])) {
                $nombre = (string)$row['nombre'];
            }
            $archivo = trim((string)($row['logo'] ?? ''));
            if ($archivo !== '') {
                $ecommerce = dirname(__DIR__, 2);
                $raiz = dirname($ecommerce);
                foreach ([
                    $ecommerce . '/uploads/' . $archivo,
                    $raiz . '/uploads/' . $archivo,
                ] as $path) {
                    if (is_file($path)) {
                        $logo = '/uploads/' . $archivo;
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            // seguir con el nombre por defecto
        }

        return ['nombre' => $nombre, 'logo' => $logo];
    }
}

if (!function_exists('utilidad_contexto_periodo')) {
    /**
     * Gastos y compras del rango, solo como contexto. No se restan de la utilidad por producto.
     *
     * @return array{
     *   gastos: array<string, mixed>,
     *   compras: array<string, mixed>,
     *   decision: array<string, float>
     * }
     */
    function utilidad_contexto_periodo(PDO $pdo, string $desde, string $hasta, float $utilidadProductos): array
    {
        $gastos = [
            'total' => 0.0,
            'pagado' => 0.0,
            'pendiente' => 0.0,
            'cantidad' => 0,
            'tipos' => [],
        ];
        $compras = [
            'total' => 0.0,
            'cantidad' => 0,
            'estados' => [],
            'proveedores' => [],
        ];

        if (function_exists('admin_table_exists') && admin_table_exists($pdo, 'gastos')) {
            $fechaCol = 'fecha';
            if (function_exists('admin_column_exists')) {
                if (admin_column_exists($pdo, 'gastos', 'fecha')) {
                    $fechaCol = 'fecha';
                } elseif (admin_column_exists($pdo, 'gastos', 'fecha_gasto')) {
                    $fechaCol = 'fecha_gasto';
                } elseif (admin_column_exists($pdo, 'gastos', 'created_at')) {
                    $fechaCol = 'created_at';
                }
            }

            $joinEstado = function_exists('admin_table_exists') && admin_table_exists($pdo, 'estados_gastos')
                && function_exists('admin_column_exists') && admin_column_exists($pdo, 'gastos', 'estado_gasto_id');
            $joinTipo = function_exists('admin_table_exists') && admin_table_exists($pdo, 'tipos_gastos')
                && function_exists('admin_column_exists') && admin_column_exists($pdo, 'gastos', 'tipo_gasto_id');

            try {
                $select = [
                    'COALESCE(SUM(g.monto), 0) AS total',
                    'COUNT(*) AS cantidad',
                ];
                if ($joinEstado) {
                    $select[] = "COALESCE(SUM(CASE WHEN LOWER(COALESCE(e.nombre, '')) = 'pagado' THEN g.monto ELSE 0 END), 0) AS pagado";
                    $select[] = "COALESCE(SUM(CASE WHEN LOWER(COALESCE(e.nombre, '')) <> 'pagado' THEN g.monto ELSE 0 END), 0) AS pendiente";
                } else {
                    $select[] = '0 AS pagado';
                    $select[] = 'COALESCE(SUM(g.monto), 0) AS pendiente';
                }

                $sql = 'SELECT ' . implode(', ', $select) . ' FROM gastos g';
                if ($joinEstado) {
                    $sql .= ' LEFT JOIN estados_gastos e ON e.id = g.estado_gasto_id';
                }
                $sql .= ' WHERE g.`' . $fechaCol . '` BETWEEN ? AND ?';
                if ($joinEstado) {
                    $sql .= " AND LOWER(COALESCE(e.nombre, '')) NOT IN ('cancelado', 'anulado')";
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute([$desde, $hasta]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $gastos['total'] = round((float)($row['total'] ?? 0), 2);
                $gastos['pagado'] = round((float)($row['pagado'] ?? 0), 2);
                $gastos['pendiente'] = round((float)($row['pendiente'] ?? 0), 2);
                $gastos['cantidad'] = (int)($row['cantidad'] ?? 0);
            } catch (Throwable $e) {
                error_log('utilidad_contexto gastos: ' . $e->getMessage());
            }

            if ($joinTipo) {
                try {
                    $sqlTipos = '
                        SELECT COALESCE(t.nombre, \'Sin tipo\') AS nombre, COUNT(*) AS cantidad, COALESCE(SUM(g.monto), 0) AS total
                        FROM gastos g
                        LEFT JOIN tipos_gastos t ON t.id = g.tipo_gasto_id
                    ';
                    if ($joinEstado) {
                        $sqlTipos .= ' LEFT JOIN estados_gastos e ON e.id = g.estado_gasto_id';
                    }
                    $sqlTipos .= ' WHERE g.`' . $fechaCol . '` BETWEEN ? AND ?';
                    if ($joinEstado) {
                        $sqlTipos .= " AND LOWER(COALESCE(e.nombre, '')) NOT IN ('cancelado', 'anulado')";
                    }
                    $sqlTipos .= ' GROUP BY t.id, t.nombre ORDER BY total DESC';
                    $stmt = $pdo->prepare($sqlTipos);
                    $stmt->execute([$desde, $hasta]);
                    $gastos['tipos'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $gastos['tipos'] = [];
                }
            }
        }

        if (function_exists('admin_table_exists') && admin_table_exists($pdo, 'ecommerce_compras')) {
            $fechaCol = function_exists('admin_column_exists') && admin_column_exists($pdo, 'ecommerce_compras', 'fecha_compra')
                ? 'fecha_compra'
                : 'fecha_creacion';
            $tieneEstado = function_exists('admin_column_exists') && admin_column_exists($pdo, 'ecommerce_compras', 'estado');
            $tieneProveedor = function_exists('admin_table_exists') && admin_table_exists($pdo, 'ecommerce_proveedores')
                && function_exists('admin_column_exists') && admin_column_exists($pdo, 'ecommerce_compras', 'proveedor_id');

            $where = ['c.`' . $fechaCol . '` BETWEEN ? AND ?'];
            if ($tieneEstado) {
                $where[] = "LOWER(COALESCE(c.estado, '')) <> 'cancelada'";
            }

            try {
                $stmt = $pdo->prepare('
                    SELECT COALESCE(SUM(c.total), 0) AS total, COUNT(*) AS cantidad
                    FROM ecommerce_compras c
                    WHERE ' . implode(' AND ', $where) . '
                ');
                $stmt->execute([$desde, $hasta]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $compras['total'] = round((float)($row['total'] ?? 0), 2);
                $compras['cantidad'] = (int)($row['cantidad'] ?? 0);
            } catch (Throwable $e) {
                error_log('utilidad_contexto compras: ' . $e->getMessage());
            }

            if ($tieneEstado) {
                try {
                    $stmt = $pdo->prepare('
                        SELECT COALESCE(c.estado, \'sin estado\') AS nombre, COUNT(*) AS cantidad, COALESCE(SUM(c.total), 0) AS total
                        FROM ecommerce_compras c
                        WHERE ' . implode(' AND ', $where) . '
                        GROUP BY c.estado
                        ORDER BY total DESC
                    ');
                    $stmt->execute([$desde, $hasta]);
                    $compras['estados'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $compras['estados'] = [];
                }
            }

            if ($tieneProveedor) {
                try {
                    $stmt = $pdo->prepare('
                        SELECT COALESCE(p.nombre, \'Sin proveedor\') AS nombre, COUNT(*) AS cantidad, COALESCE(SUM(c.total), 0) AS total
                        FROM ecommerce_compras c
                        LEFT JOIN ecommerce_proveedores p ON p.id = c.proveedor_id
                        WHERE ' . implode(' AND ', $where) . '
                        GROUP BY c.proveedor_id, p.nombre
                        ORDER BY total DESC
                        LIMIT 6
                    ');
                    $stmt->execute([$desde, $hasta]);
                    $compras['proveedores'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                } catch (Throwable $e) {
                    $compras['proveedores'] = [];
                }
            }
        }

        $gastosTotal = (float)$gastos['total'];
        $comprasTotal = (float)$compras['total'];
        $decision = [
            'utilidad_productos' => round($utilidadProductos, 2),
            'gastos' => $gastosTotal,
            'compras' => $comprasTotal,
            'despues_gastos' => round($utilidadProductos - $gastosTotal, 2),
            'salida_caja' => round($gastosTotal + $comprasTotal, 2),
        ];

        return [
            'gastos' => $gastos,
            'compras' => $compras,
            'decision' => $decision,
        ];
    }
}
