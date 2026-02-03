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
        SELECT r.*, m.nombre as material_nombre
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
