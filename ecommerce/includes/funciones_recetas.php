<?php
/**
 * Funciones para evaluar condiciones en recetas
 * Permite usar fórmulas condicionales en materiales de recetas
 */

/**
 * Evalúa si una condición se cumple
 * 
 * @param string $condicion_tipo Tipo de condición: 'ancho', 'alto', 'area', 'atributo'
 * @param string $condicion_operador Operador: 'igual', 'mayor', 'mayor_igual', 'menor', 'menor_igual', 'diferente'
 * @param string $condicion_valor Valor a comparar
 * @param float $valor_actual Valor actual (ancho, alto, área)
 * @return bool True si la condición se cumple
 */
function evaluar_condicion($condicion_tipo, $condicion_operador, $condicion_valor, $valor_actual) {
    if (empty($condicion_tipo) || empty($condicion_operador) || empty($condicion_valor)) {
        return true; // Si no hay condición, siempre se incluye
    }
    
    $valor_actual = floatval($valor_actual);
    $valor_condicion = floatval($condicion_valor);
    
    switch ($condicion_operador) {
        case 'igual':
            return abs($valor_actual - $valor_condicion) < 0.01;
        case 'mayor':
            return $valor_actual > $valor_condicion;
        case 'mayor_igual':
            return $valor_actual >= $valor_condicion;
        case 'menor':
            return $valor_actual < $valor_condicion;
        case 'menor_igual':
            return $valor_actual <= $valor_condicion;
        case 'diferente':
            return abs($valor_actual - $valor_condicion) >= 0.01;
        default:
            return true;
    }
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
 * @return float|string Valor a comparar
 */
function obtener_valor_condicion($pdo, $producto_id, $condicion_tipo, $condicion_atributo_id, $ancho, $alto, $atributos_seleccionados = []) {
    switch ($condicion_tipo) {
        case 'ancho':
            return $ancho;
            
        case 'alto':
            return $alto;
            
        case 'area':
            // Convertir cm a metros y calcular área
            return ($ancho / 100) * ($alto / 100);
            
        case 'atributo':
            // Buscar el valor del atributo seleccionado
            if (is_array($atributos_seleccionados) && !empty($atributos_seleccionados)) {
                foreach ($atributos_seleccionados as $attr) {
                    if ($attr['id'] == $condicion_atributo_id) {
                        return floatval($attr['valor'] ?? 0);
                    }
                }
            }
            return 0;
            
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
    $stmt = $pdo->prepare("
        SELECT r.*, m.nombre as material_nombre,
               CASE 
                   WHEN EXISTS(SELECT 1 FROM ecommerce_materiales WHERE id = r.material_producto_id) THEN 'material'
                   ELSE 'producto'
               END as tipo_item
        FROM ecommerce_producto_recetas_productos r
        JOIN ecommerce_productos m ON r.material_producto_id = m.id
        WHERE r.producto_id = ?
        ORDER BY m.nombre
    ");
    $stmt->execute([$producto_id]);
    $materiales_receta = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $materiales_filtrados = [];
    
    foreach ($materiales_receta as $material) {
        // Evaluar condición si existe
        if ($material['con_condicion']) {
            $valor_actual = obtener_valor_condicion(
                $pdo,
                $producto_id,
                $material['condicion_tipo'],
                $material['condicion_atributo_id'],
                $ancho,
                $alto,
                $atributos_seleccionados
            );
            
            $cumple_condicion = evaluar_condicion(
                $material['condicion_tipo'],
                $material['condicion_operador'],
                $material['condicion_valor'],
                $valor_actual
            );
            
            if (!$cumple_condicion) {
                continue; // Saltar este material si no cumple la condición
            }
        }
        
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

function calcular_costo_material_pedido(PDO $pdo, array $items): float {
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
            $costo_unitario = obtener_costo_unitario_material($pdo, $material_id);
            if ($costo_unitario <= 0) {
                continue;
            }

            $costo_total += $cantidad_total * $costo_unitario;
        }
    }

    return round($costo_total, 2);
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
    
    return "Si {$tipo_texto} {$operador_texto} {$condicion_valor}";
}
?>
