<?php

if (!function_exists('contabilidad_table_exists')) {
    function contabilidad_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return (int)$stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('ensureContabilidadSchema')) {
    function ensureContabilidadSchema(PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_contabilidad_config (
                id TINYINT PRIMARY KEY,
                moneda VARCHAR(10) NOT NULL DEFAULT 'ARS',
                condicion_fiscal VARCHAR(120) NULL,
                redondear_totales TINYINT(1) NOT NULL DEFAULT 1,
                notas_fiscales TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_contabilidad_impuestos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre VARCHAR(120) NOT NULL,
                codigo VARCHAR(40) NULL,
                descripcion VARCHAR(255) NULL,
                tipo_calculo ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
                valor DECIMAL(12,4) NOT NULL DEFAULT 0,
                aplica_a ENUM('pedido','cotizacion','ambos') NOT NULL DEFAULT 'ambos',
                base_calculo ENUM('subtotal','total') NOT NULL DEFAULT 'subtotal',
                incluido_en_precio TINYINT(1) NOT NULL DEFAULT 0,
                activo TINYINT(1) NOT NULL DEFAULT 1,
                orden_visual INT NOT NULL DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_activo_ambito (activo, aplica_a),
                INDEX idx_orden (orden_visual, id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $stmt = $pdo->query("SELECT COUNT(*) FROM ecommerce_contabilidad_config");
            $countConfig = (int)($stmt ? $stmt->fetchColumn() : 0);
            if ($countConfig <= 0) {
                $condicionFiscal = null;
                if (contabilidad_table_exists($pdo, 'ecommerce_empresa')) {
                    try {
                        $empresa = $pdo->query("SELECT regimen_iva FROM ecommerce_empresa LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
                        $condicionFiscal = trim((string)($empresa['regimen_iva'] ?? '')) ?: null;
                    } catch (Throwable $e) {
                        $condicionFiscal = null;
                    }
                }

                $stmtIns = $pdo->prepare("INSERT INTO ecommerce_contabilidad_config (id, moneda, condicion_fiscal, redondear_totales, notas_fiscales) VALUES (1, 'ARS', ?, 1, ?)");
                $stmtIns->execute([
                    $condicionFiscal,
                    'Configuración contable base para impuestos y referencia fiscal del sistema.'
                ]);
            }

            $stmt = $pdo->query("SELECT COUNT(*) FROM ecommerce_contabilidad_impuestos");
            $countTaxes = (int)($stmt ? $stmt->fetchColumn() : 0);
            if ($countTaxes <= 0) {
                $defaults = [
                    ['IVA 21%', 'IVA21', 'Impuesto al valor agregado general', 'porcentaje', 21.0, 'ambos', 'subtotal', 1, 1, 10],
                    ['Ingresos Brutos', 'IIBB', 'Percepción provincial sobre ventas', 'porcentaje', 3.5, 'ambos', 'subtotal', 0, 0, 20],
                    ['Percepción Ganancias', 'PERC-GAN', 'Percepción adicional para determinados clientes', 'porcentaje', 2.0, 'ambos', 'subtotal', 0, 0, 30],
                ];
                $stmtIns = $pdo->prepare("INSERT INTO ecommerce_contabilidad_impuestos (nombre, codigo, descripcion, tipo_calculo, valor, aplica_a, base_calculo, incluido_en_precio, activo, orden_visual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($defaults as $row) {
                    $stmtIns->execute($row);
                }
            }

            foreach (['ecommerce_cotizaciones', 'ecommerce_pedidos'] as $tablaDocumento) {
                if (!contabilidad_table_exists($pdo, $tablaDocumento)) {
                    continue;
                }

                $columnasDocumento = $pdo->query("SHOW COLUMNS FROM {$tablaDocumento}")->fetchAll(PDO::FETCH_COLUMN, 0);
                if (!in_array('impuestos_json', $columnasDocumento, true)) {
                    $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN impuestos_json LONGTEXT NULL AFTER total");
                }
                if (!in_array('impuestos_incluidos', $columnasDocumento, true)) {
                    $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN impuestos_incluidos DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER impuestos_json");
                }
                if (!in_array('impuestos_adicionales', $columnasDocumento, true)) {
                    $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN impuestos_adicionales DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER impuestos_incluidos");
                }
            }
        } catch (Throwable $e) {
            error_log('ensureContabilidadSchema: ' . $e->getMessage());
        }
    }
}

if (!function_exists('contabilidad_get_config')) {
    function contabilidad_get_config(PDO $pdo): array
    {
        ensureContabilidadSchema($pdo);
        try {
            $stmt = $pdo->query("SELECT * FROM ecommerce_contabilidad_config WHERE id = 1 LIMIT 1");
            return $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('contabilidad_save_config')) {
    function contabilidad_save_config(PDO $pdo, array $data): void
    {
        ensureContabilidadSchema($pdo);
        $stmt = $pdo->prepare("INSERT INTO ecommerce_contabilidad_config (id, moneda, condicion_fiscal, redondear_totales, notas_fiscales) VALUES (1, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE moneda = VALUES(moneda), condicion_fiscal = VALUES(condicion_fiscal), redondear_totales = VALUES(redondear_totales), notas_fiscales = VALUES(notas_fiscales)");
        $stmt->execute([
            $data['moneda'] ?? 'ARS',
            $data['condicion_fiscal'] ?? null,
            !empty($data['redondear_totales']) ? 1 : 0,
            $data['notas_fiscales'] ?? null,
        ]);
    }
}

if (!function_exists('contabilidad_get_impuestos')) {
    function contabilidad_get_impuestos(PDO $pdo, bool $soloActivos = false): array
    {
        ensureContabilidadSchema($pdo);
        try {
            $sql = "SELECT * FROM ecommerce_contabilidad_impuestos";
            if ($soloActivos) {
                $sql .= " WHERE activo = 1";
            }
            $sql .= " ORDER BY orden_visual ASC, id ASC";
            return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('contabilidad_get_impuesto')) {
    function contabilidad_get_impuesto(PDO $pdo, int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        ensureContabilidadSchema($pdo);
        try {
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_contabilidad_impuestos WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('contabilidad_calcular_impuestos')) {
    function contabilidad_calcular_impuestos(array $impuestos, float $subtotal, ?float $total = null, string $ambito = 'pedido'): array
    {
        $totalBase = $total !== null ? $total : $subtotal;
        $detalle = [];
        $totalIncluidos = 0.0;
        $totalAdicionales = 0.0;

        foreach ($impuestos as $imp) {
            $activo = !empty($imp['activo']);
            if (!$activo) {
                continue;
            }

            $aplicaA = (string)($imp['aplica_a'] ?? 'ambos');
            if ($aplicaA !== 'ambos' && $aplicaA !== $ambito) {
                continue;
            }

            $base = ((string)($imp['base_calculo'] ?? 'subtotal') === 'total') ? $totalBase : $subtotal;
            $valor = (float)($imp['valor'] ?? 0);
            $monto = 0.0;
            $incluido = !empty($imp['incluido_en_precio']);

            if ((string)($imp['tipo_calculo'] ?? 'porcentaje') === 'fijo') {
                $monto = $valor;
            } else {
                if ($incluido) {
                    $monto = $base - ($base / (1 + ($valor / 100)));
                } else {
                    $monto = $base * ($valor / 100);
                }
            }

            $detalle[] = [
                'nombre' => (string)($imp['nombre'] ?? 'Impuesto'),
                'codigo' => (string)($imp['codigo'] ?? ''),
                'monto' => $monto,
                'base' => $base,
                'incluido_en_precio' => $incluido,
                'tipo_calculo' => (string)($imp['tipo_calculo'] ?? 'porcentaje'),
                'valor' => $valor,
            ];

            if ($incluido) {
                $totalIncluidos += $monto;
            } else {
                $totalAdicionales += $monto;
            }
        }

        return [
            'detalle' => $detalle,
            'total_incluidos' => $totalIncluidos,
            'total_adicionales' => $totalAdicionales,
            'total_con_impuestos' => $totalBase + $totalAdicionales,
        ];
    }
}
