<?php

if (!function_exists('cotizacion_siguiente_numero')) {
    function cotizacion_siguiente_numero(PDO $pdo): string
    {
        $anio = date('Y');
        $maxId = 0;
        try {
            $maxId = (int)$pdo->query('SELECT MAX(id) FROM ecommerce_cotizaciones')->fetchColumn();
        } catch (Throwable $e) {
            $maxId = 0;
        }
        return 'COT-' . $anio . '-' . str_pad((string)($maxId + 1), 5, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('cotizacion_duplicar')) {
    function cotizacion_duplicar(PDO $pdo, int $cotizacionId, ?int $usuarioId): array
    {
        if ($cotizacionId <= 0) {
            throw new Exception('Cotización inválida.');
        }

        $stmt = $pdo->prepare('SELECT * FROM ecommerce_cotizaciones WHERE id = ?');
        $stmt->execute([$cotizacionId]);
        $origen = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$origen) {
            throw new Exception('Cotización no encontrada.');
        }

        $columnas = $pdo->query('SHOW COLUMNS FROM ecommerce_cotizaciones')->fetchAll(PDO::FETCH_COLUMN, 0);
        $omitir = [
            'id',
            'numero_cotizacion',
            'estado',
            'fecha_creacion',
            'fecha_envio',
            'fecha_actualizacion',
            'creado_por',
            'pedido_id',
            'pedido_numero',
            'fecha_conversion',
        ];

        $numero = cotizacion_siguiente_numero($pdo);
        $cols = ['numero_cotizacion'];
        $vals = [$numero];

        foreach ($columnas as $col) {
            $col = (string)$col;
            if (in_array($col, $omitir, true)) {
                continue;
            }
            if (stripos($col, 'pedido') === 0 || stripos($col, 'afip') === 0 || $col === 'cae') {
                continue;
            }
            $cols[] = $col;
            $vals[] = $origen[$col] ?? null;
        }

        if (in_array('estado', $columnas, true)) {
            $cols[] = 'estado';
            $vals[] = 'pendiente';
        }
        if (in_array('creado_por', $columnas, true)) {
            $cols[] = 'creado_por';
            $vals[] = $usuarioId;
        }

        $placeholders = implode(', ', array_fill(0, count($cols), '?'));
        $quoted = implode(', ', array_map(static function ($c) {
            return '`' . str_replace('`', '', $c) . '`';
        }, $cols));
        $pdo->prepare('INSERT INTO ecommerce_cotizaciones (' . $quoted . ') VALUES (' . $placeholders . ')')->execute($vals);
        $nuevoId = (int)$pdo->lastInsertId();
        if ($nuevoId <= 0) {
            throw new Exception('No se pudo duplicar la cotización.');
        }

        try {
            if (function_exists('admin_table_exists') && admin_table_exists($pdo, 'ecommerce_cotizacion_items')) {
                $itemCols = $pdo->query('SHOW COLUMNS FROM ecommerce_cotizacion_items')->fetchAll(PDO::FETCH_COLUMN, 0);
                $stmtItems = $pdo->prepare('SELECT * FROM ecommerce_cotizacion_items WHERE cotizacion_id = ?');
                $stmtItems->execute([$cotizacionId]);
                $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($items as $item) {
                    $ic = [];
                    $iv = [];
                    foreach ($itemCols as $col) {
                        if ($col === 'id') {
                            continue;
                        }
                        $ic[] = '`' . str_replace('`', '', (string)$col) . '`';
                        $iv[] = $col === 'cotizacion_id' ? $nuevoId : ($item[$col] ?? null);
                    }
                    if (empty($ic)) {
                        continue;
                    }
                    $ph = implode(', ', array_fill(0, count($ic), '?'));
                    $pdo->prepare('INSERT INTO ecommerce_cotizacion_items (' . implode(', ', $ic) . ') VALUES (' . $ph . ')')->execute($iv);
                }
            }
        } catch (Throwable $e) {
            // la cotización ya se copió con el JSON de ítems
        }

        return [
            'id' => $nuevoId,
            'numero_cotizacion' => $numero,
        ];
    }
}
