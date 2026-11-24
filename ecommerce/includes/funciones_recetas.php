<?php
/**
 * Funciones para evaluar condiciones en recetas
 * Permite usar fórmulas condicionales en materiales de recetas
 */

/**
 * Separa valores de condición por coma (ej: "Blanco, Negro")
 *
 * @param string $condicion_valor
 * @return array
 */
function parsear_valores_condicion($condicion_valor) {
    $partes = explode(',', (string)$condicion_valor);
    $valores = [];
    foreach ($partes as $parte) {
        $parte = trim($parte);
        if ($parte !== '') {
            $valores[] = $parte;
        }
    }
    return $valores;
}

/**
 * Evalúa si una condición se cumple
 * 
 * @param string $condicion_tipo Tipo de condición: 'ancho', 'alto', 'area', 'atributo'
 * @param string $condicion_operador Operador: 'igual', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'diferente'
 * @param string $condicion_valor Valor a comparar (admite varios separados por coma)
 * @param float|string $valor_actual Valor actual (ancho, alto, área o texto de atributo)
 * @return bool True si la condición se cumple
 */
function evaluar_condicion($condicion_tipo, $condicion_operador, $condicion_valor, $valor_actual) {
    if (empty($condicion_tipo) || empty($condicion_operador) || $condicion_valor === '' || $condicion_valor === null) {
        return true; // Si no hay condición, siempre se incluye
    }

    $valores = parsear_valores_condicion($condicion_valor);
    if (empty($valores)) {
        return true;
    }

    // Atributos (color, etc.) y valores no numéricos: comparación de texto
    $es_texto = ($condicion_tipo === 'atributo')
        || !is_numeric($valor_actual)
        || !is_numeric($valores[0]);

    if ($es_texto) {
        $actual = mb_strtolower(trim((string)$valor_actual));
        $lista = array_map(static function ($v) {
            return mb_strtolower(trim((string)$v));
        }, $valores);
        $esta_en_lista = in_array($actual, $lista, true);

        switch ($condicion_operador) {
            case 'igual':
                return $esta_en_lista;
            case 'diferente':
                return !$esta_en_lista;
            case 'mayor':
                return $actual > $lista[0];
            case 'mayor_igual':
                return $actual >= $lista[0];
            case 'menor':
                return $actual < $lista[0];
            case 'menor_igual':
                return $actual <= $lista[0];
            default:
                return true;
        }
    }

    $actual = floatval($valor_actual);

    // Para = y ≠ con varios números: cumple si coincide con alguno
    if ($condicion_operador === 'igual' || $condicion_operador === 'diferente') {
        $coincide = false;
        foreach ($valores as $v) {
            if (abs($actual - floatval($v)) < 0.01) {
                $coincide = true;
                break;
            }
        }
        return $condicion_operador === 'igual' ? $coincide : !$coincide;
    }

    $valor_condicion = floatval($valores[0]);
    switch ($condicion_operador) {
        case 'mayor':
            return $actual > $valor_condicion;
        case 'mayor_igual':
            return $actual >= $valor_condicion;
        case 'menor':
            return $actual < $valor_condicion;
        case 'menor_igual':
            return $actual <= $valor_condicion;
        default:
            return true;
    }
}

function recetas_asegurar_condiciones(PDO $pdo): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_producto_receta_condiciones (
                id INT PRIMARY KEY AUTO_INCREMENT,
                receta_id INT NOT NULL,
                tipo ENUM('ancho','alto','area','atributo') NOT NULL,
                operador ENUM('igual','mayor','mayor_igual','menor','menor_igual','diferente') NOT NULL DEFAULT 'igual',
                valor VARCHAR(255) NULL,
                atributo_id INT NULL,
                orden INT NOT NULL DEFAULT 0,
                INDEX idx_receta_cond_receta (receta_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {
        error_log('recetas_asegurar_condiciones create: ' . $e->getMessage());
        return;
    }

    try {
        $pdo->exec("
            INSERT INTO ecommerce_producto_receta_condiciones (receta_id, tipo, operador, valor, atributo_id, orden)
            SELECT r.id, r.condicion_tipo, r.condicion_operador, r.condicion_valor, r.condicion_atributo_id, 0
            FROM ecommerce_producto_recetas_productos r
            LEFT JOIN ecommerce_producto_receta_condiciones c ON c.receta_id = r.id
            WHERE r.con_condicion = 1
              AND r.condicion_tipo IS NOT NULL
              AND r.condicion_tipo <> ''
              AND r.condicion_operador IS NOT NULL
              AND r.condicion_operador <> ''
              AND c.id IS NULL
        ");
    } catch (Throwable $e) {
        error_log('recetas_asegurar_condiciones migrate: ' . $e->getMessage());
    }
}

function recetas_condiciones_legacy(array $material): array
{
    if (empty($material['con_condicion']) || empty($material['condicion_tipo'])) {
        return [];
    }

    return [[
        'tipo' => (string)$material['condicion_tipo'],
        'operador' => (string)($material['condicion_operador'] ?? 'igual'),
        'valor' => (string)($material['condicion_valor'] ?? ''),
        'atributo_id' => (int)($material['condicion_atributo_id'] ?? 0),
    ]];
}

function recetas_cargar_condiciones_por_receta(PDO $pdo, array $receta_ids): array
{
    recetas_asegurar_condiciones($pdo);
    $mapa = [];
    $ids = array_values(array_unique(array_filter(array_map('intval', $receta_ids))));
    if (empty($ids)) {
        return $mapa;
    }

    try {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT receta_id, tipo, operador, valor, atributo_id
            FROM ecommerce_producto_receta_condiciones
            WHERE receta_id IN ($placeholders)
            ORDER BY orden ASC, id ASC
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $mapa[(int)$row['receta_id']][] = [
                'tipo' => (string)$row['tipo'],
                'operador' => (string)$row['operador'],
                'valor' => (string)($row['valor'] ?? ''),
                'atributo_id' => (int)($row['atributo_id'] ?? 0),
            ];
        }
    } catch (Throwable $e) {
        error_log('recetas_cargar_condiciones_por_receta: ' . $e->getMessage());
    }

    return $mapa;
}

function recetas_guardar_condiciones(PDO $pdo, int $receta_id, array $condiciones): void
{
    recetas_asegurar_condiciones($pdo);
    $pdo->prepare('DELETE FROM ecommerce_producto_receta_condiciones WHERE receta_id = ?')->execute([$receta_id]);

    if ($receta_id <= 0) {
        return;
    }

    $stmt = $pdo->prepare("
        INSERT INTO ecommerce_producto_receta_condiciones (receta_id, tipo, operador, valor, atributo_id, orden)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $orden = 0;
    foreach ($condiciones as $cond) {
        $tipo = (string)($cond['tipo'] ?? '');
        $operador = (string)($cond['operador'] ?? '');
        $valor = trim((string)($cond['valor'] ?? ''));
        $atributo_id = (int)($cond['atributo_id'] ?? 0);
        if ($tipo === '' || $operador === '' || $valor === '') {
            continue;
        }
        $stmt->execute([
            $receta_id,
            $tipo,
            $operador,
            $valor,
            $atributo_id > 0 ? $atributo_id : null,
            $orden,
        ]);
        $orden++;
    }
}

function recetas_normalizar_condicion_post($tipo_raw, $operador, $valor): ?array
{
    $tipo_raw = trim((string)$tipo_raw);
    $operador = trim((string)$operador);
    $valor = trim((string)$valor);
    if ($tipo_raw === '' || $operador === '' || $valor === '') {
        return null;
    }

    $atributo_id = 0;
    $tipo = $tipo_raw;
    if (strpos($tipo_raw, 'atributo_') === 0) {
        $atributo_id = (int)substr($tipo_raw, strlen('atributo_'));
        $tipo = 'atributo';
    }

    $tipos_ok = ['ancho', 'alto', 'area', 'atributo'];
    $ops_ok = ['igual', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'diferente'];
    if (!in_array($tipo, $tipos_ok, true) || !in_array($operador, $ops_ok, true)) {
        return null;
    }
    if ($tipo === 'atributo' && $atributo_id <= 0) {
        return null;
    }

    return [
        'tipo' => $tipo,
        'operador' => $operador,
        'valor' => $valor,
        'atributo_id' => $atributo_id,
    ];
}

/**
 * Obtiene el valor a evaluar según el tipo de condición
 *
 * @param PDO $pdo Conexión a base de datos
 * @param int $producto_id ID del producto
 * @param string $condicion_tipo Tipo de condición
 * @param int $condicion_atributo_id ID del atributo (si aplica)
 * @param float $ancho Ancho en cm
 * @param float $alto Alto en cm
 * @param array $atributos_seleccionados Atributos del pedido
 * @param array $nombres_atributos id => nombre, para matchear cotizaciones sin id
 * @return float|string Valor a comparar
 */
function obtener_valor_condicion($pdo, $producto_id, $condicion_tipo, $condicion_atributo_id, $ancho, $alto, $atributos_seleccionados = [], array $nombres_atributos = []) {
    switch ($condicion_tipo) {
        case 'ancho':
            return $ancho;

        case 'alto':
            return $alto;

        case 'area':
            return ($ancho / 100) * ($alto / 100);

        case 'atributo':
            if (!is_array($atributos_seleccionados) || empty($atributos_seleccionados)) {
                return '';
            }

            $attr_id = (int)$condicion_atributo_id;
            foreach ($atributos_seleccionados as $attr) {
                if ($attr_id > 0 && (int)($attr['id'] ?? 0) === $attr_id) {
                    return trim((string)($attr['valor'] ?? ''));
                }
            }

            $nombre = trim((string)($nombres_atributos[$attr_id] ?? ''));
            if ($nombre === '' && $attr_id > 0) {
                try {
                    $stmt = $pdo->prepare('SELECT nombre FROM ecommerce_producto_atributos WHERE id = ? LIMIT 1');
                    $stmt->execute([$attr_id]);
                    $nombre = trim((string)($stmt->fetchColumn() ?: ''));
                } catch (Throwable $e) {
                    $nombre = '';
                }
            }
            if ($nombre === '') {
                return '';
            }

            $needle = mb_strtolower($nombre);
            foreach ($atributos_seleccionados as $attr) {
                if (mb_strtolower(trim((string)($attr['nombre'] ?? ''))) === $needle) {
                    return trim((string)($attr['valor'] ?? ''));
                }
            }
            return '';

        default:
            return 0;
    }
}

/**
 * Obtiene la receta de un producto con evaluación de condiciones
 * 
 * @param PDO $pdo Conexión a base de datos
 * @param int $producto_id ID del producto
 * @param float $ancho Ancho del producto en cm
 * @param float $alto Alto del producto en cm
 * @param array $atributos_seleccionados Atributos seleccionados del producto
 * @return array Array con materiales que cumplen las condiciones
 */
function obtener_receta_con_condiciones($pdo, $producto_id, $ancho, $alto, $atributos_seleccionados = []) {
    recetas_asegurar_condiciones($pdo);

    $stmt = $pdo->prepare("
        SELECT r.*, m.nombre as material_nombre,
               CASE WHEN m.es_material = 1 THEN 'material' ELSE 'producto' END as tipo_item
        FROM ecommerce_producto_recetas_productos r
        JOIN ecommerce_productos m ON r.material_producto_id = m.id
        WHERE r.producto_id = ?
        ORDER BY m.nombre
    ");
    $stmt->execute([$producto_id]);
    $materiales_receta = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $condiciones_por_receta = recetas_cargar_condiciones_por_receta(
        $pdo,
        array_column($materiales_receta, 'id')
    );

    $attr_ids = [];
    foreach ($condiciones_por_receta as $lista) {
        foreach ($lista as $cond) {
            if ((int)$cond['atributo_id'] > 0) {
                $attr_ids[] = (int)$cond['atributo_id'];
            }
        }
    }
    foreach ($materiales_receta as $material) {
        if ((int)($material['condicion_atributo_id'] ?? 0) > 0) {
            $attr_ids[] = (int)$material['condicion_atributo_id'];
        }
    }

    $nombres_atributos = [];
    $attr_ids = array_values(array_unique($attr_ids));
    if (!empty($attr_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($attr_ids), '?'));
            $stmt_nombres = $pdo->prepare("SELECT id, nombre FROM ecommerce_producto_atributos WHERE id IN ($placeholders)");
            $stmt_nombres->execute($attr_ids);
            foreach ($stmt_nombres->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $nombres_atributos[(int)$row['id']] = (string)$row['nombre'];
            }
        } catch (Throwable $e) {
            $nombres_atributos = [];
        }
    }

    $materiales_filtrados = [];

    foreach ($materiales_receta as $material) {
        $lista = $condiciones_por_receta[(int)($material['id'] ?? 0)] ?? [];
        if (empty($lista)) {
            $lista = recetas_condiciones_legacy($material);
        }

        $cumple = true;
        foreach ($lista as $cond) {
            $valor_actual = obtener_valor_condicion(
                $pdo,
                $producto_id,
                $cond['tipo'],
                $cond['atributo_id'],
                $ancho,
                $alto,
                $atributos_seleccionados,
                $nombres_atributos
            );
            if (!evaluar_condicion($cond['tipo'], $cond['operador'], $cond['valor'], $valor_actual)) {
                $cumple = false;
                break;
            }
        }

        if (!$cumple) {
            continue;
        }

        $material['condiciones'] = $lista;
        $materiales_filtrados[] = $material;
    }

    return $materiales_filtrados;
}

function obtener_costo_unitario_material(PDO $pdo, int $material_producto_id): float {
    if ($material_producto_id <= 0) {
        return 0.0;
    }

    try {
        $stmt = $pdo->prepare("SELECT ci.costo_unitario
            FROM ecommerce_compra_items ci
            INNER JOIN ecommerce_compras c ON c.id = ci.compra_id
            WHERE ci.producto_id = ?
            ORDER BY c.fecha_compra DESC, c.id DESC, ci.id DESC
            LIMIT 1");
        $stmt->execute([$material_producto_id]);
        $valor = (float)($stmt->fetchColumn() ?? 0);
        if ($valor > 0) {
            return $valor;
        }
    } catch (Throwable $e) {
        // Si aún no hay compras registradas o la estructura no coincide, seguimos con el fallback.
    }

    $stmt = $pdo->prepare("SELECT precio_base FROM ecommerce_productos WHERE id = ? LIMIT 1");
    $stmt->execute([$material_producto_id]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($material) {
        $valor = (float)($material['precio_base'] ?? 0);
        if ($valor > 0) {
            return $valor;
        }
    }

    try {
        $stmt = $pdo->prepare("SELECT costo FROM ecommerce_materiales WHERE id = ? LIMIT 1");
        $stmt->execute([$material_producto_id]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($material) {
            $valor = (float)($material['costo'] ?? 0);
            if ($valor > 0) {
                return $valor;
            }
        }
    } catch (Throwable $e) {
        // La tabla de materiales no existe o no tiene esa columna; lo dejamos en 0.
    }

    return 0.0;
}

function calcular_costo_material_pedido(PDO $pdo, array $items, ?int $pedido_id = null): float {
    $costo_total = 0.0;

    foreach ($items as $item) {
        $usa_receta = !empty($item['usa_receta']) || !empty($item['usa_receta_producto']);

        if (!$usa_receta && !empty($item['producto_id'])) {
            $stmt_prod = $pdo->prepare("SELECT usa_receta FROM ecommerce_productos WHERE id = ? LIMIT 1");
            $stmt_prod->execute([(int)($item['producto_id'] ?? 0)]);
            $usa_receta = !empty($stmt_prod->fetchColumn());
        }

        if (!$usa_receta) {
            continue;
        }

        $producto_id = (int)($item['producto_id'] ?? 0);
        $cantidad = (float)($item['cantidad'] ?? 0);
        $ancho_cm = (float)($item['ancho_cm'] ?? 0);
        $alto_cm = (float)($item['alto_cm'] ?? 0);

        if ($producto_id <= 0 || $cantidad <= 0) {
            continue;
        }

        $atributos = [];
        if (!empty($item['atributos'])) {
            $decoded = json_decode((string)$item['atributos'], true);
            if (is_array($decoded)) {
                $atributos = $decoded;
            }
        }

        $recetas = obtener_receta_con_condiciones($pdo, $producto_id, $ancho_cm, $alto_cm, $atributos);
        if (empty($recetas)) {
            continue;
        }

        $alto_m = $alto_cm / 100;
        $ancho_m = $ancho_cm / 100;
        $area_m2 = $alto_m * $ancho_m;

        foreach ($recetas as $receta) {
            $factor = (float)($receta['factor'] ?? 0);
            $merma = (float)($receta['merma_pct'] ?? 0);
            $tipo = $receta['tipo_calculo'] ?? 'fijo';
            $cantidad_base = 0.0;

            if ($tipo === 'fijo') {
                $cantidad_base = $factor;
            } elseif ($tipo === 'por_area') {
                $cantidad_base = $area_m2 * $factor;
            } elseif ($tipo === 'por_ancho') {
                $cantidad_base = $ancho_m * $factor;
            } elseif ($tipo === 'por_alto') {
                $cantidad_base = $alto_m * $factor;
            }

            $cantidad_total = $cantidad_base * (1 + ($merma / 100)) * $cantidad;
            if ($cantidad_total <= 0) {
                continue;
            }

            $material_id = (int)($receta['material_producto_id'] ?? 0);
            // Permitir override a nivel de pedido
            $costo_unitario = null;
            if ($pedido_id !== null) {
                try {
                    require_once __DIR__ . '/materials_helper.php';
                } catch (Throwable $e) {
                    // ignore
                }
                if (function_exists('materials_obtener_override')) {
                    $ov = materials_obtener_override($pdo, $pedido_id, $material_id);
                    if ($ov !== null && $ov > 0) {
                        $costo_unitario = $ov;
                    }
                }
            }

            if ($costo_unitario === null) {
                $costo_unitario = obtener_costo_unitario_material($pdo, $material_id);
            }
            if ($costo_unitario <= 0) {
                continue;
            }

            $costo_total += $cantidad_total * $costo_unitario;
        }
    }

    $costo_total = round($costo_total, 2);

    // Si existe override total para el pedido, devolverlo
    if ($pedido_id !== null) {
        try {
            require_once __DIR__ . '/materials_helper.php';
            if (function_exists('materials_obtener_override_total')) {
                $ov_total = materials_obtener_override_total($pdo, $pedido_id);
                if ($ov_total !== null && $ov_total >= 0) {
                    return (float)$ov_total;
                }
            }
        } catch (Throwable $e) {
            // ignore
        }
    }

    return $costo_total;
}

/**
 * Obtiene la descripción de una condición en texto legible
 * 
 * @param string $condicion_tipo Tipo de condición
 * @param string $condicion_operador Operador
 * @param string $condicion_valor Valor
 * @param string $nombre_atributo Nombre del atributo (si aplica)
 * @return string Descripción legible de la condición
 */
function describir_condicion($condicion_tipo, $condicion_operador, $condicion_valor, $nombre_atributo = '') {
    $tipos = [
        'ancho' => 'Ancho',
        'alto' => 'Alto',
        'area' => 'Área',
        'atributo' => $nombre_atributo ?: 'Atributo'
    ];
    
    $operadores = [
        'igual' => '=',
        'mayor' => '>',
        'mayor_igual' => '≥',
        'menor' => '<',
        'menor_igual' => '≤',
        'diferente' => '≠'
    ];
    
    $tipo_texto = $tipos[$condicion_tipo] ?? $condicion_tipo;
    $operador_texto = $operadores[$condicion_operador] ?? $condicion_operador;
    $valores = parsear_valores_condicion($condicion_valor);
    $valor_texto = count($valores) > 1 ? '(' . implode(', ', $valores) . ')' : ($valores[0] ?? $condicion_valor);

    return "Si {$tipo_texto} {$operador_texto} {$valor_texto}";
}
?>
