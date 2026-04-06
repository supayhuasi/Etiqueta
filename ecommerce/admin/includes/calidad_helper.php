<?php

if (!function_exists('calidad_table_exists')) {
    function calidad_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
        }

        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('calidad_columns')) {
    function calidad_columns(PDO $pdo, string $table): array
    {
        try {
            return $pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN, 0) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('calidad_pick_date_column')) {
    function calidad_pick_date_column(PDO $pdo, string $table, array $candidates): ?string
    {
        $columns = calidad_columns($pdo, $table);
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }
}

if (!function_exists('calidad_scalar')) {
    function calidad_scalar(PDO $pdo, string $sql, array $params = [], $default = 0)
    {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $value = $stmt->fetchColumn();
            return ($value !== false && $value !== null) ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('ensureCalidadSchema')) {
    function ensureCalidadSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_calidad_eventos (
                id INT PRIMARY KEY AUTO_INCREMENT,
                tipo ENUM('reclamo','rehecho','demora','satisfaccion') NOT NULL,
                titulo VARCHAR(150) NOT NULL,
                descripcion TEXT NULL,
                pedido_id INT NULL,
                instalacion_tipo VARCHAR(20) NULL,
                instalacion_id INT NULL,
                cliente_nombre VARCHAR(150) NULL,
                cantidad INT NOT NULL DEFAULT 1,
                dias_demora INT NOT NULL DEFAULT 0,
                puntaje_satisfaccion DECIMAL(5,2) NULL,
                fecha_evento DATE NOT NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'abierto',
                creado_por INT NULL,
                fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_tipo_fecha (tipo, fecha_evento),
                INDEX idx_pedido (pedido_id),
                INDEX idx_instalacion (instalacion_tipo, instalacion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $columns = calidad_columns($pdo, 'ecommerce_calidad_eventos');
            if (!in_array('cliente_nombre', $columns, true)) {
                $pdo->exec("ALTER TABLE ecommerce_calidad_eventos ADD COLUMN cliente_nombre VARCHAR(150) NULL AFTER instalacion_id");
            }
            if (!in_array('cantidad', $columns, true)) {
                $pdo->exec("ALTER TABLE ecommerce_calidad_eventos ADD COLUMN cantidad INT NOT NULL DEFAULT 1 AFTER cliente_nombre");
            }
            if (!in_array('dias_demora', $columns, true)) {
                $pdo->exec("ALTER TABLE ecommerce_calidad_eventos ADD COLUMN dias_demora INT NOT NULL DEFAULT 0 AFTER cantidad");
            }
            if (!in_array('puntaje_satisfaccion', $columns, true)) {
                $pdo->exec("ALTER TABLE ecommerce_calidad_eventos ADD COLUMN puntaje_satisfaccion DECIMAL(5,2) NULL AFTER dias_demora");
            }
            if (!in_array('estado', $columns, true)) {
                $pdo->exec("ALTER TABLE ecommerce_calidad_eventos ADD COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'abierto' AFTER fecha_evento");
            }
        } catch (Throwable $e) {
            error_log('ensureCalidadSchema: ' . $e->getMessage());
        }
    }
}

if (!function_exists('obtenerMetricasCalidad')) {
    function obtenerMetricasCalidad(PDO $pdo, string $desde, string $hasta): array
    {
        ensureCalidadSchema($pdo);

        $metricas = [
            'pedidos_entregados' => 0,
            'reclamos' => 0,
            'reclamos_resueltos' => 0,
            'productos_rehechos' => 0,
            'demoras_entrega' => 0,
            'demoras_promedio_dias' => 0.0,
            'instalaciones_totales' => 0,
            'instalaciones_con_reclamo' => 0,
            'porcentaje_instalaciones_sin_reclamo' => 0.0,
            'satisfaccion_media' => 0.0,
            'satisfaccion_porcentaje' => 0.0,
            'satisfaccion_respuestas' => 0,
            'satisfaccion_escala' => 5.0,
        ];

        $pedidosDate = calidad_pick_date_column($pdo, 'ecommerce_pedidos', ['fecha_entrega', 'fecha_pedido', 'fecha_creacion']);
        if ($pedidosDate !== null) {
            $metricas['pedidos_entregados'] = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_pedidos WHERE LOWER(COALESCE(estado, '')) = 'entregado' AND DATE({$pedidosDate}) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
        }

        if (calidad_table_exists($pdo, 'ecommerce_calidad_eventos')) {
            $metricas['reclamos'] = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_calidad_eventos WHERE tipo = 'reclamo' AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
            $metricas['reclamos_resueltos'] = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_calidad_eventos WHERE tipo = 'reclamo' AND estado IN ('resuelto','cerrado') AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
            $metricas['productos_rehechos'] = (int)calidad_scalar(
                $pdo,
                "SELECT COALESCE(SUM(cantidad), 0) FROM ecommerce_calidad_eventos WHERE tipo = 'rehecho' AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );

            $demorasManual = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_calidad_eventos WHERE tipo = 'demora' AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
            $demorasManualProm = (float)calidad_scalar(
                $pdo,
                "SELECT COALESCE(AVG(NULLIF(dias_demora, 0)), 0) FROM ecommerce_calidad_eventos WHERE tipo = 'demora' AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );

            if ($demorasManual > 0) {
                $metricas['demoras_entrega'] = $demorasManual;
                $metricas['demoras_promedio_dias'] = $demorasManualProm;
            }

            $metricas['instalaciones_con_reclamo'] = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(DISTINCT CONCAT(COALESCE(instalacion_tipo, ''), '-', COALESCE(instalacion_id, 0)))
                 FROM ecommerce_calidad_eventos
                 WHERE tipo = 'reclamo'
                   AND instalacion_id IS NOT NULL
                   AND instalacion_id > 0
                   AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );

            $satManualCount = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_calidad_eventos WHERE tipo = 'satisfaccion' AND puntaje_satisfaccion IS NOT NULL AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
            $satManualAvg = (float)calidad_scalar(
                $pdo,
                "SELECT COALESCE(AVG(puntaje_satisfaccion), 0) FROM ecommerce_calidad_eventos WHERE tipo = 'satisfaccion' AND puntaje_satisfaccion IS NOT NULL AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
            $satManualMax = (float)calidad_scalar(
                $pdo,
                "SELECT COALESCE(MAX(puntaje_satisfaccion), 5) FROM ecommerce_calidad_eventos WHERE tipo = 'satisfaccion' AND puntaje_satisfaccion IS NOT NULL AND DATE(fecha_evento) BETWEEN ? AND ?",
                [$desde, $hasta],
                5
            );

            if ($satManualCount > 0) {
                $metricas['satisfaccion_media'] = $satManualAvg;
                $metricas['satisfaccion_respuestas'] = $satManualCount;
                $metricas['satisfaccion_escala'] = max(5.0, $satManualMax);
                $metricas['satisfaccion_porcentaje'] = min(100, ($satManualAvg / max(1, $metricas['satisfaccion_escala'])) * 100);
            }
        }

        $ordCols = calidad_columns($pdo, 'ecommerce_ordenes_produccion');
        if (!empty($ordCols) && in_array('fecha_entrega', $ordCols, true) && in_array('estado', $ordCols, true)) {
            $demorasAuto = (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*)
                 FROM ecommerce_ordenes_produccion
                 WHERE fecha_entrega IS NOT NULL
                   AND DATE(fecha_entrega) BETWEEN ? AND ?
                   AND DATE(fecha_entrega) < CURDATE()
                   AND LOWER(COALESCE(estado, '')) NOT IN ('terminado','entregado','cancelado')",
                [$desde, $hasta],
                0
            );
            $demorasAutoProm = (float)calidad_scalar(
                $pdo,
                "SELECT COALESCE(AVG(GREATEST(DATEDIFF(CURDATE(), DATE(fecha_entrega)), 0)), 0)
                 FROM ecommerce_ordenes_produccion
                 WHERE fecha_entrega IS NOT NULL
                   AND DATE(fecha_entrega) BETWEEN ? AND ?
                   AND DATE(fecha_entrega) < CURDATE()
                   AND LOWER(COALESCE(estado, '')) NOT IN ('terminado','entregado','cancelado')",
                [$desde, $hasta],
                0
            );

            if ($metricas['demoras_entrega'] === 0) {
                $metricas['demoras_entrega'] = $demorasAuto;
                $metricas['demoras_promedio_dias'] = $demorasAutoProm;
            }
        }

        if (!empty($ordCols) && in_array('fecha_instalacion', $ordCols, true)) {
            $metricas['instalaciones_totales'] += (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*)
                 FROM ecommerce_ordenes_produccion
                 WHERE fecha_instalacion IS NOT NULL
                   AND DATE(fecha_instalacion) BETWEEN ? AND ?
                   AND LOWER(COALESCE(estado, '')) NOT IN ('cancelado')",
                [$desde, $hasta],
                0
            );
        }

        $manualCols = calidad_columns($pdo, 'ecommerce_instalaciones_manuales');
        if (!empty($manualCols) && in_array('fecha_instalacion', $manualCols, true)) {
            $metricas['instalaciones_totales'] += (int)calidad_scalar(
                $pdo,
                "SELECT COUNT(*) FROM ecommerce_instalaciones_manuales WHERE fecha_instalacion IS NOT NULL AND DATE(fecha_instalacion) BETWEEN ? AND ?",
                [$desde, $hasta],
                0
            );
        }

        if ($metricas['instalaciones_totales'] > 0) {
            $instalacionesSinReclamo = max(0, $metricas['instalaciones_totales'] - $metricas['instalaciones_con_reclamo']);
            $metricas['porcentaje_instalaciones_sin_reclamo'] = ($instalacionesSinReclamo / $metricas['instalaciones_totales']) * 100;
        }

        if (
            calidad_table_exists($pdo, 'ecommerce_encuesta_respuestas')
            && calidad_table_exists($pdo, 'ecommerce_encuesta_preguntas')
        ) {
            try {
                $stmtSat = $pdo->prepare("
                    SELECT
                        COUNT(*) AS total,
                        AVG(CAST(r.respuesta AS DECIMAL(10,2))) AS promedio,
                        MAX(CAST(r.respuesta AS DECIMAL(10,2))) AS maximo
                    FROM ecommerce_encuesta_respuestas r
                    INNER JOIN ecommerce_encuesta_preguntas p ON p.id = r.pregunta_id
                    WHERE p.tipo = 'escala'
                      AND r.respuesta REGEXP '^[0-9]+(\\.[0-9]+)?$'
                      AND DATE(r.fecha_creacion) BETWEEN ? AND ?
                ");
                $stmtSat->execute([$desde, $hasta]);
                $sat = $stmtSat->fetch(PDO::FETCH_ASSOC) ?: [];

                $totalSat = (int)($sat['total'] ?? 0);
                if ($totalSat > 0) {
                    $promedio = (float)($sat['promedio'] ?? 0);
                    $escala = max(5.0, (float)($sat['maximo'] ?? 5));
                    $metricas['satisfaccion_media'] = $promedio;
                    $metricas['satisfaccion_respuestas'] = $totalSat;
                    $metricas['satisfaccion_escala'] = $escala;
                    $metricas['satisfaccion_porcentaje'] = min(100, ($promedio / max(1, $escala)) * 100);
                }
            } catch (Throwable $e) {
                // noop
            }
        }

        return $metricas;
    }
}

if (!function_exists('obtenerEventosCalidad')) {
    function obtenerEventosCalidad(PDO $pdo, string $desde, string $hasta, int $limit = 50): array
    {
        ensureCalidadSchema($pdo);

        $limit = max(1, min(200, $limit));
        $joinPedido = calidad_table_exists($pdo, 'ecommerce_pedidos') ? 'LEFT JOIN ecommerce_pedidos p ON p.id = c.pedido_id' : '';
        $pedidoField = calidad_table_exists($pdo, 'ecommerce_pedidos') ? 'p.numero_pedido' : 'NULL';

        try {
            $sql = "
                SELECT c.*, {$pedidoField} AS numero_pedido
                FROM ecommerce_calidad_eventos c
                {$joinPedido}
                WHERE DATE(c.fecha_evento) BETWEEN ? AND ?
                ORDER BY c.fecha_evento DESC, c.id DESC
                LIMIT {$limit}
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$desde, $hasta]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('obtenerSerieCalidadMensual')) {
    function obtenerSerieCalidadMensual(PDO $pdo, int $meses = 6): array
    {
        ensureCalidadSchema($pdo);

        $meses = max(3, min(12, $meses));
        $labels = [];
        $series = [
            'pedidos_entregados' => [],
            'reclamos' => [],
            'rehechos' => [],
            'demoras' => [],
            'instalaciones_sin_reclamo_pct' => [],
            'satisfaccion_pct' => [],
        ];
        $mesesTexto = [
            '01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr',
            '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago',
            '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'
        ];

        $base = new DateTime(date('Y-m-01'));
        for ($i = $meses - 1; $i >= 0; $i--) {
            $inicioMes = (clone $base)->modify("-{$i} months");
            $desde = $inicioMes->format('Y-m-01');
            $hasta = $inicioMes->format('Y-m-t');
            $metricas = obtenerMetricasCalidad($pdo, $desde, $hasta);

            $mesClave = $inicioMes->format('m');
            $labels[] = ($mesesTexto[$mesClave] ?? $mesClave) . ' ' . $inicioMes->format('Y');
            $series['pedidos_entregados'][] = (int)($metricas['pedidos_entregados'] ?? 0);
            $series['reclamos'][] = (int)($metricas['reclamos'] ?? 0);
            $series['rehechos'][] = (int)($metricas['productos_rehechos'] ?? 0);
            $series['demoras'][] = (int)($metricas['demoras_entrega'] ?? 0);
            $series['instalaciones_sin_reclamo_pct'][] = round((float)($metricas['porcentaje_instalaciones_sin_reclamo'] ?? 0), 1);
            $series['satisfaccion_pct'][] = round((float)($metricas['satisfaccion_porcentaje'] ?? 0), 1);
        }

        return [
            'labels' => $labels,
            'series' => $series,
        ];
    }
}
