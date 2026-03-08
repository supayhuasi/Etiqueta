<?php
if (!isset($pdo)) {
    require __DIR__ . '/../config.php';
}

function obtener_lista_precio_publica(PDO $pdo): int {
    try {
        require_once __DIR__ . '/cache.php';
        $cached = cache_get('ecommerce_lista_precio_id', 300);
        if ($cached !== null) {
            return (int)$cached;
        }

        $stmt = $pdo->query("SELECT lista_precio_id FROM ecommerce_config LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $lista_id = !empty($row['lista_precio_id']) ? (int)$row['lista_precio_id'] : 0;
        cache_set('ecommerce_lista_precio_id', $lista_id);
        return $lista_id;
    } catch (Exception $e) {
        return 0;
    }
}

function cargar_mapas_lista_publica(PDO $pdo, int $listaId): array {
    if ($listaId <= 0) {
        return ['items' => [], 'categorias' => []];
    }

    require_once __DIR__ . '/cache.php';
    $cache_key = 'ecommerce_lista_publica_mapas_' . $listaId;
    $cached = cache_get($cache_key, 300);
    if (is_array($cached) && isset($cached['items'], $cached['categorias'])) {
        return $cached;
    }

    $stmt = $pdo->prepare("SELECT producto_id, precio_nuevo, descuento_porcentaje FROM ecommerce_lista_precio_items WHERE activo = 1 AND lista_precio_id = ?");
    $stmt->execute([$listaId]);
    $items_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items_map = [];
    foreach ($items_rows as $row) {
        $items_map[$row['producto_id']] = [
            'precio_nuevo' => (float)$row['precio_nuevo'],
            'descuento_porcentaje' => (float)$row['descuento_porcentaje']
        ];
    }

    $stmt = $pdo->prepare("SELECT categoria_id, descuento_porcentaje FROM ecommerce_lista_precio_categorias WHERE activo = 1 AND lista_precio_id = ?");
    $stmt->execute([$listaId]);
    $cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $cat_map = [];
    foreach ($cat_rows as $row) {
        $cat_map[$row['categoria_id']] = (float)$row['descuento_porcentaje'];
    }

    $result = ['items' => $items_map, 'categorias' => $cat_map];
    cache_set($cache_key, $result);
    return $result;
}

function obtener_ajuste_horario_publico(PDO $pdo): array {
    static $cached = null;

    if (is_array($cached)) {
        return $cached;
    }

    $cached = [
        'aplica' => false,
        'activo' => false,
        'tipo' => 'descuento',
        'porcentaje' => 0.0,
        'hora_inicio' => null,
        'hora_fin' => null,
        'factor' => 1.0,
        'categoria_ids' => [],
    ];

    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_precio_horario_config'");
        if (!$stmt || $stmt->rowCount() === 0) {
            return $cached;
        }

        $stmt = $pdo->query("SELECT activo, tipo_ajuste, porcentaje, hora_inicio, hora_fin FROM ecommerce_precio_horario_config ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || empty($row['activo'])) {
            return $cached;
        }

        $tipo = strtolower(trim((string)($row['tipo_ajuste'] ?? 'descuento')));
        if (!in_array($tipo, ['descuento', 'aumento'], true)) {
            $tipo = 'descuento';
        }

        $porcentaje = (float)($row['porcentaje'] ?? 0);
        if ($porcentaje <= 0) {
            return $cached;
        }

        $hora_inicio = (string)($row['hora_inicio'] ?? '00:00:00');
        $hora_fin = (string)($row['hora_fin'] ?? '00:00:00');
        $hora_actual = date('H:i:s');

        $aplica = false;
        if ($hora_inicio <= $hora_fin) {
            $aplica = ($hora_actual >= $hora_inicio && $hora_actual <= $hora_fin);
        } else {
            $aplica = ($hora_actual >= $hora_inicio || $hora_actual <= $hora_fin);
        }

        $factor = $tipo === 'descuento'
            ? max(0.0, 1.0 - ($porcentaje / 100))
            : (1.0 + ($porcentaje / 100));

        $categoria_ids = [];
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_precio_horario_categorias'");
        if ($stmt && $stmt->rowCount() > 0) {
            $stmt = $pdo->query("SELECT categoria_id FROM ecommerce_precio_horario_categorias");
            $categoria_ids = array_values(array_unique(array_filter(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)), static fn($id) => $id > 0)));
        }

        $cached = [
            'aplica' => $aplica,
            'activo' => true,
            'tipo' => $tipo,
            'porcentaje' => $porcentaje,
            'hora_inicio' => $hora_inicio,
            'hora_fin' => $hora_fin,
            'factor' => $factor,
            'categoria_ids' => $categoria_ids,
        ];
    } catch (Exception $e) {
        return $cached;
    }

    return $cached;
}

function calcular_precio_publico(int $productoId, ?int $categoriaId, float $precioBase, int $listaId, array $itemsMap, array $catMap): array {
    global $pdo;

    $precioBase = (float)$precioBase;
    $precio = $precioBase;
    $descuento = 0.0;

    if ($listaId > 0) {
        if (isset($itemsMap[$productoId])) {
            $item = $itemsMap[$productoId];
            $precioNuevo = (float)($item['precio_nuevo'] ?? 0);
            $descItem = (float)($item['descuento_porcentaje'] ?? 0);
            if ($precioNuevo > 0) {
                $precio = $precioNuevo;
                if ($precioBase > 0) {
                    $descuento = max(0, round((1 - ($precioNuevo / $precioBase)) * 100, 2));
                }
            } elseif ($descItem > 0) {
                $descuento = $descItem;
                $precio = $precioBase * (1 - $descuento / 100);
            }
        }

        if ($descuento <= 0 && $categoriaId) {
            $descCat = (float)($catMap[$categoriaId] ?? 0);
            if ($descCat > 0) {
                $descuento = $descCat;
                $precio = $precioBase * (1 - $descuento / 100);
            }
        }
    }

    $ajuste_horario = obtener_ajuste_horario_publico($pdo);
    $categorias_objetivo = $ajuste_horario['categoria_ids'] ?? [];
    $aplica_por_categoria = empty($categorias_objetivo)
        || ($categoriaId !== null && in_array((int)$categoriaId, $categorias_objetivo, true));

    if (!empty($ajuste_horario['aplica']) && $aplica_por_categoria) {
        $precio *= (float)($ajuste_horario['factor'] ?? 1);
    }

    if ($precioBase > 0) {
        $descuento = round((1 - ($precio / $precioBase)) * 100, 2);
    }

    return [
        'precio' => $precio,
        'precio_original' => $precioBase,
        'descuento_pct' => $descuento,
        'ajuste_horario' => array_merge($ajuste_horario, ['aplica_categoria' => $aplica_por_categoria])
    ];
}
?>
