<?php

function obtener_config_envio(PDO $pdo): array {
    $default = [
        'costo_base' => 500.00,
        'gratis_desde_importe' => null,
        'gratis_desde_cantidad' => null,
        'activo' => 1
    ];

    try {
        $stmt = $pdo->query("SELECT * FROM ecommerce_envio_config WHERE id = 1 LIMIT 1");
        $config = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($config) {
            return array_merge($default, [
                'costo_base' => isset($config['costo_base']) ? (float)$config['costo_base'] : $default['costo_base'],
                'gratis_desde_importe' => $config['gratis_desde_importe'] !== null ? (float)$config['gratis_desde_importe'] : null,
                'gratis_desde_cantidad' => $config['gratis_desde_cantidad'] !== null ? (int)$config['gratis_desde_cantidad'] : null,
                'activo' => isset($config['activo']) ? (int)$config['activo'] : $default['activo']
            ]);
        }
    } catch (Exception $e) {
    }

    return $default;
}

function calcular_envio(PDO $pdo, float $subtotal, int $cantidad_total): array {
    $config = obtener_config_envio($pdo);
    $costo_base = (float)($config['costo_base'] ?? 0);
    $gratis_importe = $config['gratis_desde_importe'];
    $gratis_cantidad = $config['gratis_desde_cantidad'];
    $activo = (int)($config['activo'] ?? 1);

    if ($subtotal <= 0 || $activo !== 1) {
        return [
            'costo' => 0.0,
            'mensaje' => $activo !== 1 ? 'Envío desactivado' : 'Envío sin cargo',
            'config' => $config
        ];
    }

    $aplica_gratis = false;
    $mensaje = '';

    if ($gratis_importe !== null && $gratis_importe > 0 && $subtotal >= $gratis_importe) {
        $aplica_gratis = true;
        $mensaje = 'Envío gratis por monto';
    }

    if ($gratis_cantidad !== null && $gratis_cantidad > 0 && $cantidad_total >= $gratis_cantidad) {
        $aplica_gratis = true;
        $mensaje = $mensaje !== '' ? $mensaje . ' y cantidad' : 'Envío gratis por cantidad';
    }

    if ($aplica_gratis) {
        return [
            'costo' => 0.0,
            'mensaje' => $mensaje,
            'config' => $config
        ];
    }

    return [
        'costo' => $costo_base,
        'mensaje' => 'Costo de envío',
        'config' => $config
    ];
}
