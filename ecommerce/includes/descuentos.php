<?php

function normalizar_codigo_descuento(string $codigo): string {
    return strtoupper(trim($codigo));
}

function obtener_descuento_por_codigo(PDO $pdo, string $codigo): ?array {
    try {
        require_once __DIR__ . '/cache.php';
        $cache_key = 'ecommerce_descuento_' . $codigo;
        $cached = cache_get($cache_key, 60);
        if (is_array($cached)) {
            return $cached;
        }

        $stmt = $pdo->prepare("SELECT * FROM ecommerce_descuentos WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            cache_set($cache_key, $row);
        }
        return $row ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function validar_descuento(array $descuento, float $subtotal): array {
    $hoy = date('Y-m-d');
    if (empty($descuento['activo'])) {
        return ['valido' => false, 'mensaje' => 'El código no está activo'];
    }
    if (!empty($descuento['fecha_inicio']) && $descuento['fecha_inicio'] > $hoy) {
        return ['valido' => false, 'mensaje' => 'El código aún no está vigente'];
    }
    if (!empty($descuento['fecha_fin']) && $descuento['fecha_fin'] < $hoy) {
        return ['valido' => false, 'mensaje' => 'El código está vencido'];
    }
    if (!empty($descuento['minimo_subtotal']) && $subtotal < (float)$descuento['minimo_subtotal']) {
        return ['valido' => false, 'mensaje' => 'El subtotal no alcanza el mínimo requerido'];
    }
    if (!empty($descuento['usos_max']) && (int)$descuento['usos_usados'] >= (int)$descuento['usos_max']) {
        return ['valido' => false, 'mensaje' => 'El código alcanzó el máximo de usos'];
    }

    return ['valido' => true, 'mensaje' => 'Código aplicado'];
}

function calcular_monto_descuento(string $tipo, float $valor, float $subtotal): float {
    if ($subtotal <= 0) {
        return 0.0;
    }
    if ($tipo === 'porcentaje') {
        $pct = max(0.0, min(100.0, $valor));
        return round($subtotal * ($pct / 100), 2);
    }
    if ($tipo === 'monto') {
        return round(min($valor, $subtotal), 2);
    }
    return 0.0;
}

function aplicar_descuento_actual(PDO $pdo, float $subtotal): array {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $codigo = $_SESSION['descuento_codigo'] ?? '';
    $codigo = normalizar_codigo_descuento((string)$codigo);

    if ($codigo === '') {
        return ['valido' => false, 'monto' => 0.0, 'codigo' => '', 'mensaje' => ''];
    }

    $descuento = obtener_descuento_por_codigo($pdo, $codigo);
    if (!$descuento) {
        unset($_SESSION['descuento_codigo']);
        return ['valido' => false, 'monto' => 0.0, 'codigo' => '', 'mensaje' => 'El código no existe'];
    }

    $validacion = validar_descuento($descuento, $subtotal);
    if (!$validacion['valido']) {
        unset($_SESSION['descuento_codigo']);
        return ['valido' => false, 'monto' => 0.0, 'codigo' => '', 'mensaje' => $validacion['mensaje']];
    }

    $monto = calcular_monto_descuento($descuento['tipo'], (float)$descuento['valor'], $subtotal);
    return [
        'valido' => true,
        'monto' => $monto,
        'codigo' => $codigo,
        'mensaje' => $validacion['mensaje'],
        'descuento' => $descuento
    ];
}
