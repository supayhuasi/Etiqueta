<?php

if (!function_exists('ensureGastosBudgetSchema')) {
    function ensureGastosBudgetSchema(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS tipos_gastos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            nombre VARCHAR(100) NOT NULL UNIQUE,
            descripcion TEXT,
            color VARCHAR(20),
            activo BOOLEAN DEFAULT 1,
            fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        $cols = $pdo->query("SHOW COLUMNS FROM tipos_gastos")->fetchAll(PDO::FETCH_COLUMN, 0);

        if (!in_array('presupuesto_mensual', $cols, true)) {
            $pdo->exec("ALTER TABLE tipos_gastos ADD COLUMN presupuesto_mensual DECIMAL(12,2) NULL DEFAULT NULL AFTER color");
        }
        if (!in_array('porcentaje_alerta', $cols, true)) {
            $pdo->exec("ALTER TABLE tipos_gastos ADD COLUMN porcentaje_alerta DECIMAL(5,2) NOT NULL DEFAULT 80 AFTER presupuesto_mensual");
        }
        if (!in_array('bloquear_exceso', $cols, true)) {
            $pdo->exec("ALTER TABLE tipos_gastos ADD COLUMN bloquear_exceso TINYINT(1) NOT NULL DEFAULT 1 AFTER porcentaje_alerta");
        }

        $tiposDefault = [
            ['Servicios', 'Servicios profesionales', '#007BFF'],
            ['Insumos', 'Materiales e insumos', '#28A745'],
            ['Transporte', 'Gastos de transporte', '#FFC107'],
            ['Mantenimiento', 'Mantenimiento y reparaciones', '#DC3545'],
            ['Utilidades', 'Servicios de utilidades', '#6F42C1'],
            ['Publicidad', 'Publicidad, marketing y difusión', '#E83E8C'],
            ['Impuestos', 'Impuestos, tasas y percepciones', '#FD7E14'],
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO tipos_gastos (nombre, descripcion, color) VALUES (?, ?, ?)");
        foreach ($tiposDefault as $tipo) {
            $stmt->execute($tipo);
        }
    }
}

if (!function_exists('obtenerResumenPresupuestoGasto')) {
    function obtenerResumenPresupuestoGasto(PDO $pdo, int $tipoGastoId, string $fecha, float $montoNuevo = 0, ?int $excludeGastoId = null): array
    {
        $base = [
            'tipo_id' => $tipoGastoId,
            'tipo_nombre' => '',
            'color' => '#6c757d',
            'presupuesto_mensual' => 0.0,
            'porcentaje_alerta' => 80.0,
            'bloquear_exceso' => true,
            'mes' => date('Y-m'),
            'gastado_actual' => 0.0,
            'monto_nuevo' => (float)$montoNuevo,
            'monto_proyectado' => (float)$montoNuevo,
            'umbral_alerta_monto' => 0.0,
            'porcentaje_usado' => 0.0,
            'estado' => 'sin_presupuesto',
            'mensaje' => 'Sin presupuesto configurado para este tipo.'
        ];

        if ($tipoGastoId <= 0) {
            return $base;
        }

        $stmtTipo = $pdo->prepare("SELECT id, nombre, color, presupuesto_mensual, porcentaje_alerta, bloquear_exceso FROM tipos_gastos WHERE id = ? LIMIT 1");
        $stmtTipo->execute([$tipoGastoId]);
        $tipo = $stmtTipo->fetch(PDO::FETCH_ASSOC);

        if (!$tipo) {
            return $base;
        }

        $mes = preg_match('/^\d{4}-\d{2}/', (string)$fecha) ? substr((string)$fecha, 0, 7) : date('Y-m');
        $presupuesto = (float)($tipo['presupuesto_mensual'] ?? 0);
        $porcentajeAlerta = (float)($tipo['porcentaje_alerta'] ?? 80);
        if ($porcentajeAlerta <= 0) {
            $porcentajeAlerta = 80;
        }
        $bloquearExceso = !empty($tipo['bloquear_exceso']);

        $sql = "SELECT COALESCE(SUM(monto), 0) FROM gastos WHERE tipo_gasto_id = ? AND DATE_FORMAT(fecha, '%Y-%m') = ?";
        $params = [$tipoGastoId, $mes];
        if (!empty($excludeGastoId)) {
            $sql .= " AND id <> ?";
            $params[] = (int)$excludeGastoId;
        }
        $stmtGastos = $pdo->prepare($sql);
        $stmtGastos->execute($params);
        $gastadoActual = (float)$stmtGastos->fetchColumn();

        $montoProyectado = $gastadoActual + max(0, (float)$montoNuevo);
        $umbralAlertaMonto = $presupuesto > 0 ? ($presupuesto * ($porcentajeAlerta / 100)) : 0;
        $porcentajeUsado = $presupuesto > 0 ? (($montoProyectado / $presupuesto) * 100) : 0;

        $estado = 'sin_presupuesto';
        $mensaje = 'Sin presupuesto configurado para este tipo.';

        if ($presupuesto > 0) {
            if ($montoProyectado > $presupuesto) {
                $estado = 'excedido';
                $mensaje = sprintf(
                    '⚠️ %s superaría su presupuesto mensual: gastado $%s, nuevo total $%s sobre $%s.',
                    $tipo['nombre'],
                    number_format($gastadoActual, 2, ',', '.'),
                    number_format($montoProyectado, 2, ',', '.'),
                    number_format($presupuesto, 2, ',', '.')
                );
            } elseif ($montoProyectado >= $umbralAlertaMonto) {
                $estado = 'alerta';
                $mensaje = sprintf(
                    '⚠️ %s alcanzaría el %.0f%% del presupuesto: $%s de $%s.',
                    $tipo['nombre'],
                    $porcentajeAlerta,
                    number_format($montoProyectado, 2, ',', '.'),
                    number_format($presupuesto, 2, ',', '.')
                );
            } else {
                $estado = 'ok';
                $mensaje = sprintf(
                    'Presupuesto disponible para %s: $%s restantes.',
                    $tipo['nombre'],
                    number_format(max(0, $presupuesto - $montoProyectado), 2, ',', '.')
                );
            }
        }

        return [
            'tipo_id' => (int)$tipo['id'],
            'tipo_nombre' => (string)$tipo['nombre'],
            'color' => (string)($tipo['color'] ?? '#6c757d'),
            'presupuesto_mensual' => $presupuesto,
            'porcentaje_alerta' => $porcentajeAlerta,
            'bloquear_exceso' => $bloquearExceso,
            'mes' => $mes,
            'gastado_actual' => $gastadoActual,
            'monto_nuevo' => (float)$montoNuevo,
            'monto_proyectado' => $montoProyectado,
            'umbral_alerta_monto' => $umbralAlertaMonto,
            'porcentaje_usado' => $porcentajeUsado,
            'estado' => $estado,
            'mensaje' => $mensaje,
        ];
    }
}

if (!function_exists('obtenerAlertasPresupuestoMes')) {
    function obtenerAlertasPresupuestoMes(PDO $pdo, string $mes): array
    {
        $stmt = $pdo->prepare("
            SELECT 
                t.id,
                t.nombre,
                t.color,
                t.presupuesto_mensual,
                t.porcentaje_alerta,
                t.bloquear_exceso,
                COALESCE(SUM(g.monto), 0) AS gastado_actual
            FROM tipos_gastos t
            LEFT JOIN gastos g 
                ON g.tipo_gasto_id = t.id
               AND DATE_FORMAT(g.fecha, '%Y-%m') = ?
            WHERE t.activo = 1
              AND COALESCE(t.presupuesto_mensual, 0) > 0
            GROUP BY t.id, t.nombre, t.color, t.presupuesto_mensual, t.porcentaje_alerta, t.bloquear_exceso
            ORDER BY gastado_actual DESC, t.nombre ASC
        ");
        $stmt->execute([$mes]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $alertas = [];
        foreach ($rows as $row) {
            $presupuesto = (float)($row['presupuesto_mensual'] ?? 0);
            $gastado = (float)($row['gastado_actual'] ?? 0);
            $porcentajeAlerta = (float)($row['porcentaje_alerta'] ?? 80);
            $umbral = $presupuesto * ($porcentajeAlerta / 100);
            $porcentajeUsado = $presupuesto > 0 ? (($gastado / $presupuesto) * 100) : 0;

            if ($gastado > $presupuesto) {
                $row['estado'] = 'excedido';
            } elseif ($gastado >= $umbral) {
                $row['estado'] = 'alerta';
            } else {
                $row['estado'] = 'ok';
            }

            $row['porcentaje_usado'] = $porcentajeUsado;
            $row['disponible'] = $presupuesto - $gastado;

            if ($row['estado'] !== 'ok') {
                $alertas[] = $row;
            }
        }

        return $alertas;
    }
}
