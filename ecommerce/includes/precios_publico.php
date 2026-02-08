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

function calcular_precio_publico(int $productoId, ?int $categoriaId, float $precioBase, int $listaId, array $itemsMap, array $catMap): array {
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

    return [
        'precio' => $precio,
        'precio_original' => $precioBase,
        'descuento_pct' => $descuento
    ];
}
?>
