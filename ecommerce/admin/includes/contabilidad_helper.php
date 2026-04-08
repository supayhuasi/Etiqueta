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

            $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_contabilidad_afip_config (
                id TINYINT PRIMARY KEY,
                ambiente ENUM('homologacion','produccion') NOT NULL DEFAULT 'homologacion',
                punto_venta INT NOT NULL DEFAULT 1,
                cuit_representada VARCHAR(20) NULL,
                razon_social VARCHAR(150) NULL,
                alias_certificado VARCHAR(150) NULL,
                email_contacto VARCHAR(150) NULL,
                provincia VARCHAR(120) NULL,
                localidad VARCHAR(120) NULL,
                private_key_pem LONGTEXT NULL,
                csr_pem LONGTEXT NULL,
                certificado_pem LONGTEXT NULL,
                certificado_vencimiento DATE NULL,
                wsaa_token LONGTEXT NULL,
                wsaa_sign LONGTEXT NULL,
                wsaa_expira_at DATETIME NULL,
                ultimo_error TEXT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

            $columnasAfipConfig = $pdo->query("SHOW COLUMNS FROM ecommerce_contabilidad_afip_config")->fetchAll(PDO::FETCH_COLUMN, 0);
            if (!in_array('wsaa_token', $columnasAfipConfig, true)) {
                $pdo->exec("ALTER TABLE ecommerce_contabilidad_afip_config ADD COLUMN wsaa_token LONGTEXT NULL AFTER certificado_vencimiento");
            }
            if (!in_array('wsaa_sign', $columnasAfipConfig, true)) {
                $pdo->exec("ALTER TABLE ecommerce_contabilidad_afip_config ADD COLUMN wsaa_sign LONGTEXT NULL AFTER wsaa_token");
            }
            if (!in_array('wsaa_expira_at', $columnasAfipConfig, true)) {
                $pdo->exec("ALTER TABLE ecommerce_contabilidad_afip_config ADD COLUMN wsaa_expira_at DATETIME NULL AFTER wsaa_sign");
            }
            if (!in_array('ultimo_error', $columnasAfipConfig, true)) {
                $pdo->exec("ALTER TABLE ecommerce_contabilidad_afip_config ADD COLUMN ultimo_error TEXT NULL AFTER wsaa_expira_at");
            }

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

            $stmt = $pdo->query("SELECT COUNT(*) FROM ecommerce_contabilidad_afip_config");
            $countAfip = (int)($stmt ? $stmt->fetchColumn() : 0);
            if ($countAfip <= 0) {
                $empresaAfip = [];
                if (contabilidad_table_exists($pdo, 'ecommerce_empresa')) {
                    try {
                        $empresaAfip = $pdo->query("SELECT nombre, cuit, email, provincia, ciudad FROM ecommerce_empresa LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
                    } catch (Throwable $e) {
                        $empresaAfip = [];
                    }
                }

                $stmtAfip = $pdo->prepare("INSERT INTO ecommerce_contabilidad_afip_config (id, ambiente, punto_venta, cuit_representada, razon_social, alias_certificado, email_contacto, provincia, localidad) VALUES (1, 'homologacion', 1, ?, ?, ?, ?, ?, ?)");
                $stmtAfip->execute([
                    preg_replace('/\D+/', '', (string)($empresaAfip['cuit'] ?? '')) ?: null,
                    trim((string)($empresaAfip['nombre'] ?? '')) ?: null,
                    trim((string)($empresaAfip['nombre'] ?? '')) !== '' ? 'AFIP ' . trim((string)$empresaAfip['nombre']) : 'Certificado AFIP',
                    trim((string)($empresaAfip['email'] ?? '')) ?: null,
                    trim((string)($empresaAfip['provincia'] ?? '')) ?: null,
                    trim((string)($empresaAfip['ciudad'] ?? '')) ?: null,
                ]);
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
                if (!in_array('comprobante_tipo', $columnasDocumento, true)) {
                    $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN comprobante_tipo VARCHAR(20) NOT NULL DEFAULT 'factura' AFTER impuestos_adicionales");
                }

                if ($tablaDocumento === 'ecommerce_pedidos') {
                    if (!in_array('tipo_factura', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN tipo_factura VARCHAR(5) NULL AFTER impuestos_adicionales");
                    }
                    if (!in_array('comprobante_tipo', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN comprobante_tipo VARCHAR(20) NOT NULL DEFAULT 'factura' AFTER tipo_factura");
                    }
                    if (!in_array('numero_factura', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN numero_factura VARCHAR(30) NULL AFTER comprobante_tipo");
                    }
                    if (!in_array('fecha_facturacion', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN fecha_facturacion DATETIME NULL AFTER numero_factura");
                    }
                    if (!in_array('cae', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN cae VARCHAR(20) NULL AFTER fecha_facturacion");
                    }
                    if (!in_array('cae_vencimiento', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN cae_vencimiento DATE NULL AFTER cae");
                    }
                    if (!in_array('afip_resultado', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN afip_resultado VARCHAR(20) NULL AFTER cae_vencimiento");
                    }
                    if (!in_array('afip_observaciones', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN afip_observaciones TEXT NULL AFTER afip_resultado");
                    }
                    if (!in_array('factura_archivo', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN factura_archivo VARCHAR(255) NULL AFTER afip_observaciones");
                    }
                    if (!in_array('factura_nombre_original', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN factura_nombre_original VARCHAR(255) NULL AFTER factura_archivo");
                    }
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

if (!function_exists('contabilidad_get_afip_config')) {
    function contabilidad_get_afip_config(PDO $pdo): array
    {
        ensureContabilidadSchema($pdo);
        try {
            $stmt = $pdo->query("SELECT * FROM ecommerce_contabilidad_afip_config WHERE id = 1 LIMIT 1");
            return $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('contabilidad_save_afip_config')) {
    function contabilidad_save_afip_config(PDO $pdo, array $data): void
    {
        ensureContabilidadSchema($pdo);

        $certVencimiento = null;
        $certPem = trim((string)($data['certificado_pem'] ?? ''));
        if ($certPem !== '' && function_exists('openssl_x509_parse')) {
            try {
                $parsed = openssl_x509_parse($certPem);
                if (!empty($parsed['validTo_time_t'])) {
                    $certVencimiento = date('Y-m-d', (int)$parsed['validTo_time_t']);
                }
            } catch (Throwable $e) {
                $certVencimiento = null;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO ecommerce_contabilidad_afip_config (id, ambiente, punto_venta, cuit_representada, razon_social, alias_certificado, email_contacto, provincia, localidad, private_key_pem, csr_pem, certificado_pem, certificado_vencimiento, wsaa_token, wsaa_sign, wsaa_expira_at, ultimo_error)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                ambiente = VALUES(ambiente),
                punto_venta = VALUES(punto_venta),
                cuit_representada = VALUES(cuit_representada),
                razon_social = VALUES(razon_social),
                alias_certificado = VALUES(alias_certificado),
                email_contacto = VALUES(email_contacto),
                provincia = VALUES(provincia),
                localidad = VALUES(localidad),
                private_key_pem = VALUES(private_key_pem),
                csr_pem = VALUES(csr_pem),
                certificado_pem = VALUES(certificado_pem),
                certificado_vencimiento = VALUES(certificado_vencimiento),
                wsaa_token = VALUES(wsaa_token),
                wsaa_sign = VALUES(wsaa_sign),
                wsaa_expira_at = VALUES(wsaa_expira_at),
                ultimo_error = VALUES(ultimo_error)");
        $stmt->execute([
            in_array(($data['ambiente'] ?? 'homologacion'), ['homologacion', 'produccion'], true) ? $data['ambiente'] : 'homologacion',
            max(1, (int)($data['punto_venta'] ?? 1)),
            preg_replace('/\D+/', '', (string)($data['cuit_representada'] ?? '')) ?: null,
            trim((string)($data['razon_social'] ?? '')) ?: null,
            trim((string)($data['alias_certificado'] ?? '')) ?: null,
            trim((string)($data['email_contacto'] ?? '')) ?: null,
            trim((string)($data['provincia'] ?? '')) ?: null,
            trim((string)($data['localidad'] ?? '')) ?: null,
            $data['private_key_pem'] ?? null,
            $data['csr_pem'] ?? null,
            $certPem !== '' ? $certPem : null,
            $certVencimiento,
            $data['wsaa_token'] ?? null,
            $data['wsaa_sign'] ?? null,
            !empty($data['wsaa_expira_at']) ? $data['wsaa_expira_at'] : null,
            trim((string)($data['ultimo_error'] ?? '')) ?: null,
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

if (!function_exists('contabilidad_normalizar_condicion_fiscal')) {
    function contabilidad_normalizar_condicion_fiscal(?string $valor): string
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return 'consumidor_final';
        }

        $normalizado = function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
        $normalizado = str_replace(['á', 'é', 'í', 'ó', 'ú'], ['a', 'e', 'i', 'o', 'u'], $normalizado);

        if (strpos($normalizado, 'responsable inscripto') !== false || strpos($normalizado, 'iva responsable inscripto') !== false) {
            return 'responsable_inscripto';
        }
        if (strpos($normalizado, 'monotrib') !== false) {
            return 'monotributista';
        }
        if (strpos($normalizado, 'exento') !== false) {
            return 'exento';
        }
        if (strpos($normalizado, 'no responsable') !== false) {
            return 'no_responsable';
        }
        if (strpos($normalizado, 'sujeto no categorizado') !== false) {
            return 'sujeto_no_categorizado';
        }
        if (strpos($normalizado, 'consumidor final') !== false) {
            return 'consumidor_final';
        }

        return preg_replace('/[^a-z0-9]+/', '_', $normalizado) ?: 'consumidor_final';
    }
}

if (!function_exists('contabilidad_determinar_tipo_factura')) {
    function contabilidad_determinar_tipo_factura(?string $condicionEmisor, ?string $condicionCliente, bool $solicitaFacturaA = false): array
    {
        $emisor = contabilidad_normalizar_condicion_fiscal($condicionEmisor);
        $cliente = contabilidad_normalizar_condicion_fiscal($condicionCliente);

        $tipo = 'B';
        $codigo = '06';
        $descripcion = 'Factura B';

        if (in_array($emisor, ['monotributista', 'exento', 'no_alcanzado'], true)) {
            $tipo = 'C';
            $codigo = '11';
            $descripcion = 'Factura C';
        } elseif ($emisor === 'responsable_inscripto') {
            if ($cliente === 'responsable_inscripto' || $solicitaFacturaA) {
                $tipo = 'A';
                $codigo = '01';
                $descripcion = 'Factura A';
            } else {
                $tipo = 'B';
                $codigo = '06';
                $descripcion = 'Factura B';
            }
        }

        return [
            'tipo' => $tipo,
            'codigo' => $codigo,
            'descripcion' => $descripcion,
            'condicion_emisor' => $emisor,
            'condicion_cliente' => $cliente,
        ];
    }
}

if (!function_exists('contabilidad_normalizar_comprobante_tipo')) {
    function contabilidad_normalizar_comprobante_tipo(?string $valor): string
    {
        $valor = strtolower(trim((string)$valor));
        if ($valor === 'recibo' || $valor === 'rec' || $valor === 'receipt') {
            return 'recibo';
        }

        return 'factura';
    }
}

if (!function_exists('contabilidad_generar_numero_factura')) {
    function contabilidad_generar_numero_factura(PDO $pdo, string $tipoFactura, int $puntoVenta = 1): string
    {
        ensureContabilidadSchema($pdo);

        $secuencia = 1;
        try {
            $stmt = $pdo->prepare("SELECT numero_factura FROM ecommerce_pedidos WHERE tipo_factura = ? AND numero_factura IS NOT NULL AND numero_factura != '' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$tipoFactura]);
            $ultimo = (string)($stmt->fetchColumn() ?: '');
            if ($ultimo !== '' && preg_match('/^(\d{4})-(\d{8})$/', $ultimo, $m)) {
                $puntoVenta = (int)$m[1];
                $secuencia = ((int)$m[2]) + 1;
            }
        } catch (Throwable $e) {
            $secuencia = 1;
        }

        return sprintf('%04d-%08d', max(1, $puntoVenta), max(1, $secuencia));
    }
}

if (!function_exists('contabilidad_afip_openssl_available')) {
    function contabilidad_afip_openssl_available(): bool
    {
        return extension_loaded('openssl')
            && function_exists('openssl_pkey_new')
            && function_exists('openssl_pkey_export')
            && function_exists('openssl_pkcs7_sign')
            && function_exists('openssl_csr_new')
            && function_exists('openssl_csr_export');
    }
}

if (!function_exists('contabilidad_afip_detect_openssl_config')) {
    function contabilidad_afip_detect_openssl_config(): ?string
    {
        $candidatos = array_filter([
            getenv('OPENSSL_CONF') ?: null,
            'C:\\xampp\\apache\\conf\\openssl.cnf',
            'C:\\xampp\\php\\extras\\openssl\\openssl.cnf',
            'C:\\xampp\\php\\extras\\ssl\\openssl.cnf',
            'C:\\xampp\\php\\windowsXamppPhp\\extras\\ssl\\openssl.cnf',
        ]);

        foreach ($candidatos as $ruta) {
            if ($ruta && @is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }
}

if (!function_exists('contabilidad_generar_csr_afip')) {
    function contabilidad_generar_csr_afip(array $data): array
    {
        if (!contabilidad_afip_openssl_available()) {
            throw new RuntimeException('OpenSSL no está disponible en el servidor para generar el CSR.');
        }

        $cuit = preg_replace('/\D+/', '', (string)($data['cuit_representada'] ?? ''));
        $razonSocial = trim((string)($data['razon_social'] ?? ''));
        $alias = trim((string)($data['alias_certificado'] ?? ''));
        $email = trim((string)($data['email_contacto'] ?? ''));
        $provincia = trim((string)($data['provincia'] ?? 'Buenos Aires')) ?: 'Buenos Aires';
        $localidad = trim((string)($data['localidad'] ?? 'CABA')) ?: 'CABA';

        if (strlen($cuit) !== 11) {
            throw new InvalidArgumentException('El CUIT representado debe tener 11 dígitos para generar el CSR.');
        }
        if ($razonSocial === '') {
            throw new InvalidArgumentException('La razón social es obligatoria para generar el CSR.');
        }
        if ($alias === '') {
            $alias = 'AFIP ' . $razonSocial;
        }
        if ($email === '') {
            $email = 'facturacion@localhost.local';
        }

        $dn = [
            'countryName' => 'AR',
            'stateOrProvinceName' => $provincia,
            'localityName' => $localidad,
            'organizationName' => $razonSocial,
            'organizationalUnitName' => 'Facturacion Electronica',
            'commonName' => $alias,
            'emailAddress' => $email,
            'serialNumber' => 'CUIT ' . $cuit,
        ];

        $opensslConfigPath = contabilidad_afip_detect_openssl_config();
        if ($opensslConfigPath) {
            @putenv('OPENSSL_CONF=' . $opensslConfigPath);
        }

        $opensslOptions = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'digest_alg' => 'sha256',
        ];
        if ($opensslConfigPath) {
            $opensslOptions['config'] = $opensslConfigPath;
        }

        $privkey = openssl_pkey_new($opensslOptions);
        if ($privkey === false) {
            $opensslError = function_exists('openssl_error_string') ? (openssl_error_string() ?: '') : '';
            throw new RuntimeException('No se pudo generar la clave privada RSA para el CSR.' . ($opensslError !== '' ? ' OpenSSL: ' . $opensslError : ''));
        }

        $csr = openssl_csr_new($dn, $privkey, $opensslOptions);
        if ($csr === false) {
            $opensslError = function_exists('openssl_error_string') ? (openssl_error_string() ?: '') : '';
            throw new RuntimeException('No se pudo generar el CSR PKCS#10.' . ($opensslError !== '' ? ' OpenSSL: ' . $opensslError : ''));
        }

        $csrPem = '';
        $privateKeyPem = '';
        $csrExportado = openssl_csr_export($csr, $csrPem, true);
        $keyExportada = openssl_pkey_export($privkey, $privateKeyPem, null, $opensslOptions);

        if ($csrExportado === false || $keyExportada === false || trim($csrPem) === '' || trim($privateKeyPem) === '') {
            $errores = [];
            if (function_exists('openssl_error_string')) {
                while (($opensslError = openssl_error_string()) !== false) {
                    $errores[] = $opensslError;
                }
            }
            throw new RuntimeException('El servidor no devolvió el CSR o la clave privada correctamente.' . (!empty($errores) ? ' OpenSSL: ' . implode(' | ', $errores) : ''));
        }

        return [
            'csr_pem' => $csrPem,
            'private_key_pem' => $privateKeyPem,
            'subject' => $dn,
        ];
    }
}

if (!function_exists('contabilidad_afip_endpoints')) {
    function contabilidad_afip_endpoints(string $ambiente = 'homologacion'): array
    {
        $prod = $ambiente === 'produccion';

        return [
            'wsaa' => $prod
                ? 'https://wsaa.afip.gov.ar/ws/services/LoginCms'
                : 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',
            'wsfe' => $prod
                ? 'https://servicios1.afip.gov.ar/wsfev1/service.asmx'
                : 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx',
            'service' => 'wsfe',
        ];
    }
}

if (!function_exists('contabilidad_afip_normalizar_texto')) {
    function contabilidad_afip_normalizar_texto(?string $valor): string
    {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return '';
        }

        $normalizado = function_exists('mb_strtolower') ? mb_strtolower($valor, 'UTF-8') : strtolower($valor);
        return str_replace(['á', 'é', 'í', 'ó', 'ú', 'ñ'], ['a', 'e', 'i', 'o', 'u', 'n'], $normalizado);
    }
}

if (!function_exists('contabilidad_afip_xml_escape')) {
    function contabilidad_afip_xml_escape($value): string
    {
        return htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('contabilidad_afip_xpath_first')) {
    function contabilidad_afip_xpath_first(SimpleXMLElement $xml, string $expr): string
    {
        $nodes = $xml->xpath($expr);
        if (!$nodes || !isset($nodes[0])) {
            return '';
        }

        return trim((string)$nodes[0]);
    }
}

if (!function_exists('contabilidad_afip_collect_messages')) {
    function contabilidad_afip_collect_messages(SimpleXMLElement $xml, string $nodeName): array
    {
        $mensajes = [];
        $nodes = $xml->xpath('//*[local-name()="' . $nodeName . '"]');
        if (!$nodes) {
            return [];
        }

        foreach ($nodes as $node) {
            if (!$node instanceof SimpleXMLElement) {
                continue;
            }
            $code = contabilidad_afip_xpath_first($node, './*[local-name()="Code" or local-name()="code"]');
            $msg = contabilidad_afip_xpath_first($node, './*[local-name()="Msg" or local-name()="msg"]');
            $texto = trim(($code !== '' ? $code . ' - ' : '') . ($msg !== '' ? $msg : (string)$node));
            if ($texto !== '') {
                $mensajes[] = $texto;
            }
        }

        return array_values(array_unique($mensajes));
    }
}

if (!function_exists('contabilidad_afip_soap_request')) {
    function contabilidad_afip_soap_request(string $url, string $soapAction, string $bodyXml): string
    {
        $envelope = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soap:Body>' . $bodyXml . '</soap:Body>'
            . '</soap:Envelope>';

        $response = false;
        $httpCode = 0;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $envelope,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=utf-8',
                    'SOAPAction: "' . $soapAction . '"',
                    'Content-Length: ' . strlen($envelope),
                    'Expect:',
                ],
                CURLOPT_CONNECTTIMEOUT => 20,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);
            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                throw new RuntimeException('No se pudo conectar con ARCA/AFIP: ' . ($curlError !== '' ? $curlError : 'error desconocido de cURL.'));
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"{$soapAction}\"\r\n",
                    'content' => $envelope,
                    'timeout' => 60,
                ],
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response === false) {
                throw new RuntimeException('No se pudo conectar con ARCA/AFIP y cURL no está disponible.');
            }
        }

        if (stripos((string)$response, 'Cache Access Denied') !== false || stripos((string)$response, 'authenticated yourself') !== false) {
            throw new RuntimeException('La salida HTTPS hacia ARCA/AFIP está bloqueada por el proxy o firewall del servidor (Cache Access Denied). Habilitá acceso a wsaahomo.afip.gov.ar / wswhomo.afip.gov.ar y a wsaa.afip.gov.ar / servicios1.afip.gov.ar.');
        }

        if (preg_match('/<faultstring[^>]*>(.*?)<\/faultstring>/is', (string)$response, $m)) {
            $fault = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_XML1, 'UTF-8'));
            throw new RuntimeException('ARCA/AFIP respondió un SOAP Fault' . ($httpCode > 0 ? ' (HTTP ' . $httpCode . ')' : '') . ': ' . $fault);
        }

        if ($httpCode >= 400) {
            $detalle = trim(preg_replace('/\s+/', ' ', strip_tags(substr((string)$response, 0, 500))));
            throw new RuntimeException('ARCA/AFIP devolvió HTTP ' . $httpCode . ($detalle !== '' ? ' · ' . $detalle : '.'));
        }

        return (string)$response;
    }
}

if (!function_exists('contabilidad_afip_build_tra')) {
    function contabilidad_afip_build_tra(string $service = 'wsfe'): string
    {
        $uniqueId = (string)time();
        $generationTime = gmdate('Y-m-d\TH:i:s\Z', time() - 120);
        $expirationTime = gmdate('Y-m-d\TH:i:s\Z', time() + 60 * 60 * 12);

        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<loginTicketRequest version="1.0">'
            . '<header>'
            . '<uniqueId>' . $uniqueId . '</uniqueId>'
            . '<generationTime>' . $generationTime . '</generationTime>'
            . '<expirationTime>' . $expirationTime . '</expirationTime>'
            . '</header>'
            . '<service>' . contabilidad_afip_xml_escape($service) . '</service>'
            . '</loginTicketRequest>';
    }
}

if (!function_exists('contabilidad_afip_sign_tra')) {
    function contabilidad_afip_sign_tra(string $traXml, string $certPem, string $privateKeyPem): string
    {
        if (!function_exists('openssl_pkcs7_sign')) {
            throw new RuntimeException('OpenSSL no permite firmar CMS/PKCS#7 en este servidor.');
        }

        $tmpTra = tempnam(sys_get_temp_dir(), 'afip_tra_');
        $tmpCert = tempnam(sys_get_temp_dir(), 'afip_cert_');
        $tmpKey = tempnam(sys_get_temp_dir(), 'afip_key_');
        $tmpOut = tempnam(sys_get_temp_dir(), 'afip_out_');

        try {
            file_put_contents($tmpTra, $traXml);
            file_put_contents($tmpCert, $certPem);
            file_put_contents($tmpKey, $privateKeyPem);

            $ok = openssl_pkcs7_sign(
                $tmpTra,
                $tmpOut,
                'file://' . $tmpCert,
                ['file://' . $tmpKey, ''],
                [],
                PKCS7_BINARY
            );
            if ($ok === false) {
                $errores = [];
                while (($opensslError = openssl_error_string()) !== false) {
                    $errores[] = $opensslError;
                }
                throw new RuntimeException('No se pudo firmar el Login Ticket Request.' . (!empty($errores) ? ' OpenSSL: ' . implode(' | ', $errores) : ''));
            }

            $contenido = (string)file_get_contents($tmpOut);
            $contenidoNormalizado = str_replace(["\r\n", "\r"], "\n", $contenido);
            $cms = '';

            if (preg_match('/-----BEGIN PKCS7-----(.*?)-----END PKCS7-----/s', $contenidoNormalizado, $m)) {
                $cms = preg_replace('/\s+/', '', trim($m[1]));
            }

            if ($cms === '' && preg_match('/Content-Transfer-Encoding:\s*base64\b.*?\n\n([A-Za-z0-9+\/=\n]+?)(?:\n--[-A-Za-z0-9]+(?:--)?\s*$|\s*$)/si', $contenidoNormalizado, $m)) {
                $cms = preg_replace('/\s+/', '', trim($m[1]));
            }

            if ($cms === '') {
                $partes = preg_split("/\n\n+/", $contenidoNormalizado);
                for ($i = count($partes) - 1; $i >= 0; $i--) {
                    $candidato = preg_replace('/\s+/', '', trim((string)$partes[$i]));
                    if ($candidato !== '' && base64_decode($candidato, true) !== false) {
                        $cms = $candidato;
                        break;
                    }
                }
            }

            if ($cms === '' || base64_decode($cms, true) === false) {
                throw new RuntimeException('No se pudo extraer un CMS PKCS#7 válido para WSAA.');
            }

            return $cms;
        } finally {
            foreach ([$tmpTra, $tmpCert, $tmpKey, $tmpOut] as $tmp) {
                if ($tmp && is_file($tmp)) {
                    @unlink($tmp);
                }
            }
        }
    }
}

if (!function_exists('contabilidad_afip_obtener_ta')) {
    function contabilidad_afip_obtener_ta(PDO $pdo, ?array $afipConfig = null, bool $forceRefresh = false): array
    {
        $afipConfig = $afipConfig ?: contabilidad_get_afip_config($pdo);
        $expiraAt = trim((string)($afipConfig['wsaa_expira_at'] ?? ''));
        if (!$forceRefresh && !empty($afipConfig['wsaa_token']) && !empty($afipConfig['wsaa_sign']) && $expiraAt !== '' && strtotime($expiraAt) > (time() + 300)) {
            return [
                'token' => (string)$afipConfig['wsaa_token'],
                'sign' => (string)$afipConfig['wsaa_sign'],
                'expira_at' => $expiraAt,
            ];
        }

        $certPem = trim((string)($afipConfig['certificado_pem'] ?? ''));
        $privateKeyPem = trim((string)($afipConfig['private_key_pem'] ?? ''));
        if ($certPem === '' || $privateKeyPem === '') {
            throw new RuntimeException('Faltan el certificado PEM o la clave privada para autenticar con ARCA/AFIP.');
        }

        $certInfo = contabilidad_afip_resumen_certificado($certPem);
        $cuitConfigurada = preg_replace('/\D+/', '', (string)($afipConfig['cuit_representada'] ?? ''));
        if ($cuitConfigurada !== '' && !empty($certInfo['cuit_subject']) && $certInfo['cuit_subject'] !== $cuitConfigurada) {
            throw new RuntimeException('El certificado cargado corresponde al CUIT ' . $certInfo['cuit_subject'] . ' pero en la configuración AFIP/ARCA figura el CUIT ' . $cuitConfigurada . '. Deben coincidir antes de pedir CAE.');
        }
        if (!empty($certInfo['valid_to']) && strtotime((string)$certInfo['valid_to']) < time()) {
            throw new RuntimeException('El certificado AFIP/ARCA está vencido desde ' . date('d/m/Y H:i', strtotime((string)$certInfo['valid_to'])) . '.');
        }

        $endpoints = contabilidad_afip_endpoints((string)($afipConfig['ambiente'] ?? 'homologacion'));
        $traXml = contabilidad_afip_build_tra((string)($endpoints['service'] ?? 'wsfe'));
        $cms = contabilidad_afip_sign_tra($traXml, $certPem, $privateKeyPem);

        try {
            $body = '<loginCms xmlns="http://wsaa.view.sua.dvadac.desein.afip.gov"><in0>' . contabilidad_afip_xml_escape($cms) . '</in0></loginCms>';
            $response = contabilidad_afip_soap_request((string)$endpoints['wsaa'], '', $body);
            $soapXml = @simplexml_load_string($response, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$soapXml) {
                throw new RuntimeException('La respuesta SOAP de WSAA no pudo interpretarse.');
            }

            $loginCmsReturn = contabilidad_afip_xpath_first($soapXml, '//*[local-name()="loginCmsReturn"]');
            if ($loginCmsReturn === '') {
                throw new RuntimeException('WSAA no devolvió el token de acceso esperado.');
            }

            $taXml = @simplexml_load_string(trim($loginCmsReturn), 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$taXml) {
                throw new RuntimeException('El Login Ticket de WSAA vino en un formato inválido.');
            }

            $token = contabilidad_afip_xpath_first($taXml, '//*[local-name()="token"]');
            $sign = contabilidad_afip_xpath_first($taXml, '//*[local-name()="sign"]');
            $expirationTime = contabilidad_afip_xpath_first($taXml, '//*[local-name()="expirationTime"]');
            if ($token === '' || $sign === '') {
                throw new RuntimeException('WSAA respondió sin token o sin sign.');
            }

            $afipConfig['wsaa_token'] = $token;
            $afipConfig['wsaa_sign'] = $sign;
            $afipConfig['wsaa_expira_at'] = $expirationTime !== '' ? date('Y-m-d H:i:s', strtotime($expirationTime)) : date('Y-m-d H:i:s', time() + 60 * 60 * 12);
            $afipConfig['ultimo_error'] = null;
            contabilidad_save_afip_config($pdo, $afipConfig);

            return [
                'token' => $token,
                'sign' => $sign,
                'expira_at' => (string)$afipConfig['wsaa_expira_at'],
            ];
        } catch (Throwable $e) {
            $mensaje = $e->getMessage();
            if (stripos($mensaje, 'Certificado no emitido por AC de confianza') !== false) {
                $ambienteActual = (string)($afipConfig['ambiente'] ?? 'homologacion');
                $mensaje .= ' Verificá que el .crt cargado sea el certificado final emitido por ARCA/AFIP para ' . $ambienteActual . ' y no un CSR, un certificado autofirmado o uno generado para el otro ambiente.';
                $e = new RuntimeException($mensaje, 0, $e);
            }
            $afipConfig['ultimo_error'] = $mensaje;
            contabilidad_save_afip_config($pdo, $afipConfig);
            throw $e;
        }
    }
}

if (!function_exists('contabilidad_afip_mapear_doc_tipo')) {
    function contabilidad_afip_mapear_doc_tipo(?string $tipo, ?string $numero): array
    {
        $tipo = strtoupper(trim((string)$tipo));
        $numero = preg_replace('/\D+/', '', (string)$numero);

        $codigo = match ($tipo) {
            'CUIT' => 80,
            'CUIL' => 86,
            'CDI' => 87,
            'DNI' => 96,
            'LE' => 89,
            'LC' => 90,
            'PAS', 'PASAPORTE' => 94,
            default => ($numero === '' ? 99 : (strlen($numero) >= 11 ? 80 : 96)),
        };

        $numeroFinal = $numero !== '' ? $numero : '0';
        if ($codigo === 99) {
            $numeroFinal = '0';
        }

        return [
            'codigo' => $codigo,
            'numero' => $numeroFinal,
        ];
    }
}

if (!function_exists('contabilidad_afip_mapear_cbte_tipo')) {
    function contabilidad_afip_mapear_cbte_tipo(string $tipoFactura): int
    {
        return match (strtoupper(trim($tipoFactura))) {
            'A' => 1,
            'C' => 11,
            default => 6,
        };
    }
}

if (!function_exists('contabilidad_afip_mapear_moneda')) {
    function contabilidad_afip_mapear_moneda(?string $moneda): string
    {
        $moneda = strtoupper(trim((string)$moneda));
        return match ($moneda) {
            '', 'ARS' => 'PES',
            'USD' => 'DOL',
            'EUR' => '060',
            default => substr($moneda, 0, 3),
        };
    }
}

if (!function_exists('contabilidad_afip_mapear_iva_id')) {
    function contabilidad_afip_mapear_iva_id(float $alicuota): int
    {
        $mapa = [
            0.0 => 3,
            2.5 => 9,
            5.0 => 8,
            10.5 => 4,
            21.0 => 5,
            27.0 => 6,
        ];

        foreach ($mapa as $valor => $id) {
            if (abs($alicuota - $valor) < 0.01) {
                return $id;
            }
        }

        return 5;
    }
}

if (!function_exists('contabilidad_afip_preparar_totales_pedido')) {
    function contabilidad_afip_preparar_totales_pedido(array $pedido, string $tipoFactura): array
    {
        $detalle = [];
        if (!empty($pedido['impuestos_json'])) {
            $detalle = json_decode((string)$pedido['impuestos_json'], true) ?: [];
        }

        $impTotal = round((float)($pedido['total'] ?? 0), 2);
        $ivaTotal = 0.0;
        $tributosTotal = 0.0;
        $ivaAgrupado = [];
        $tributos = [];

        foreach ($detalle as $impuesto) {
            $monto = round((float)($impuesto['monto'] ?? 0), 2);
            if ($monto <= 0) {
                continue;
            }

            $base = round((float)($impuesto['base'] ?? 0), 2);
            $alicuota = (float)($impuesto['valor'] ?? 0);
            $nombreNormalizado = contabilidad_afip_normalizar_texto((string)($impuesto['nombre'] ?? '') . ' ' . (string)($impuesto['codigo'] ?? ''));

            if (strpos($nombreNormalizado, 'iva') !== false) {
                $idIva = contabilidad_afip_mapear_iva_id($alicuota);
                $claveIva = $idIva . '|' . number_format($alicuota, 2, '.', '');
                if (!isset($ivaAgrupado[$claveIva])) {
                    $ivaAgrupado[$claveIva] = [
                        'Id' => $idIva,
                        'BaseImp' => 0.0,
                        'Importe' => 0.0,
                        'Alic' => $alicuota,
                    ];
                }
                if ($base <= 0 && $alicuota > 0) {
                    $base = round($monto * 100 / $alicuota, 2);
                }
                $ivaAgrupado[$claveIva]['BaseImp'] += $base;
                $ivaAgrupado[$claveIva]['Importe'] += $monto;
                $ivaTotal += $monto;
            } else {
                if ($base <= 0) {
                    $base = round((float)($pedido['subtotal'] ?? $impTotal), 2);
                }
                $tributos[] = [
                    'Id' => 99,
                    'Desc' => trim((string)($impuesto['nombre'] ?? 'Tributo')),
                    'BaseImp' => $base,
                    'Alic' => $alicuota,
                    'Importe' => $monto,
                ];
                $tributosTotal += $monto;
            }
        }

        if (strtoupper($tipoFactura) === 'C') {
            return [
                'ImpTotal' => $impTotal,
                'ImpTotConc' => 0.0,
                'ImpNeto' => $impTotal,
                'ImpOpEx' => 0.0,
                'ImpIVA' => 0.0,
                'ImpTrib' => 0.0,
                'Iva' => [],
                'Tributos' => [],
            ];
        }

        if ($ivaTotal > 0 && empty($ivaAgrupado)) {
            $ivaAgrupado['5|21.00'] = [
                'Id' => 5,
                'BaseImp' => max(0.0, $impTotal - $ivaTotal - $tributosTotal),
                'Importe' => $ivaTotal,
                'Alic' => 21.0,
            ];
        }

        $impNeto = round($impTotal - $ivaTotal - $tributosTotal, 2);
        if ($impNeto < 0) {
            $impNeto = max(0.0, round((float)($pedido['subtotal'] ?? $impTotal), 2));
        }

        return [
            'ImpTotal' => $impTotal,
            'ImpTotConc' => 0.0,
            'ImpNeto' => $impNeto,
            'ImpOpEx' => 0.0,
            'ImpIVA' => round($ivaTotal, 2),
            'ImpTrib' => round($tributosTotal, 2),
            'Iva' => array_values($ivaAgrupado),
            'Tributos' => $tributos,
        ];
    }
}

if (!function_exists('contabilidad_facturar_pedido_afip')) {
    function contabilidad_facturar_pedido_afip(PDO $pdo, int $pedidoId): array
    {
        ensureContabilidadSchema($pdo);
        if ($pedidoId <= 0) {
            throw new InvalidArgumentException('Pedido inválido para autorizar en ARCA/AFIP.');
        }

        $lockName = 'afip_pedido_' . $pedidoId;
        $lockStmt = $pdo->prepare('SELECT GET_LOCK(?, 15)');
        $lockStmt->execute([$lockName]);
        $lockOk = (int)$lockStmt->fetchColumn() === 1;
        if (!$lockOk) {
            throw new RuntimeException('No se pudo obtener el bloqueo para facturar este pedido. Reintentá en unos segundos.');
        }

        try {
            $stmtPedido = $pdo->prepare("SELECT p.*, c.nombre AS cliente_nombre, c.email AS cliente_email, c.responsabilidad_fiscal AS cliente_responsabilidad_fiscal, c.documento_tipo AS cliente_documento_tipo, c.documento_numero AS cliente_documento_numero FROM ecommerce_pedidos p LEFT JOIN ecommerce_clientes c ON c.id = p.cliente_id WHERE p.id = ? LIMIT 1");
            $stmtPedido->execute([$pedidoId]);
            $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);
            if (!$pedido) {
                throw new RuntimeException('Pedido no encontrado para emitir en ARCA/AFIP.');
            }

            $comprobanteTipo = contabilidad_normalizar_comprobante_tipo((string)($pedido['comprobante_tipo'] ?? 'factura'));
            if ($comprobanteTipo === 'recibo') {
                $configRecibo = contabilidad_get_afip_config($pdo);
                $puntoVentaRecibo = max(1, (int)($configRecibo['punto_venta'] ?? 1));
                $numeroRecibo = trim((string)($pedido['numero_factura'] ?? ''));
                if ($numeroRecibo === '') {
                    $numeroRecibo = 'REC-' . contabilidad_generar_numero_factura($pdo, 'REC', $puntoVentaRecibo);
                }
                $fechaRecibo = !empty($pedido['fecha_facturacion']) ? (string)$pedido['fecha_facturacion'] : date('Y-m-d H:i:s');

                $stmtRecibo = $pdo->prepare('UPDATE ecommerce_pedidos SET comprobante_tipo = ?, tipo_factura = ?, numero_factura = ?, fecha_facturacion = ?, cae = NULL, cae_vencimiento = NULL, afip_resultado = ?, afip_observaciones = ? WHERE id = ?');
                $stmtRecibo->execute([
                    'recibo',
                    'REC',
                    $numeroRecibo,
                    $fechaRecibo,
                    'RECIBO',
                    'Se emitió recibo interno sin conexión a ARCA/AFIP.',
                    $pedidoId,
                ]);

                return [
                    'pedido_id' => $pedidoId,
                    'modo' => 'recibo',
                    'tipo_factura' => 'REC',
                    'numero_factura' => $numeroRecibo,
                    'cae' => '',
                    'cae_vencimiento' => '',
                    'resultado' => 'RECIBO',
                    'observaciones' => 'Se emitió recibo interno sin conexión a ARCA/AFIP.',
                    'ya_autorizado' => false,
                ];
            }

            if (!empty($pedido['cae']) && !empty($pedido['numero_factura'])) {
                return [
                    'pedido_id' => $pedidoId,
                    'modo' => 'factura',
                    'tipo_factura' => (string)($pedido['tipo_factura'] ?? ''),
                    'numero_factura' => (string)($pedido['numero_factura'] ?? ''),
                    'cae' => (string)($pedido['cae'] ?? ''),
                    'cae_vencimiento' => (string)($pedido['cae_vencimiento'] ?? ''),
                    'resultado' => (string)($pedido['afip_resultado'] ?? 'A'),
                    'observaciones' => (string)($pedido['afip_observaciones'] ?? ''),
                    'ya_autorizado' => true,
                ];
            }

            $configContable = contabilidad_get_config($pdo);
            $afipConfig = contabilidad_get_afip_config($pdo);
            $empresa = contabilidad_table_exists($pdo, 'ecommerce_empresa') ? ($pdo->query("SELECT * FROM ecommerce_empresa LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: []) : [];

            $condicionEmisor = trim((string)($configContable['condicion_fiscal'] ?? ''));
            if ($condicionEmisor === '') {
                $condicionEmisor = trim((string)($empresa['regimen_iva'] ?? ($empresa['responsabilidad_fiscal'] ?? 'Responsable Inscripto')));
            }
            $condicionCliente = trim((string)($pedido['cliente_responsabilidad_fiscal'] ?? '')) ?: (!empty($pedido['factura_a']) ? 'Responsable Inscripto' : 'Consumidor Final');
            $documentoTipo = strtoupper(trim((string)($pedido['cliente_documento_tipo'] ?? '')));
            $documentoNumero = preg_replace('/\D+/', '', (string)($pedido['cliente_documento_numero'] ?? ''));
            if ($documentoTipo === '') {
                $documentoTipo = !empty($pedido['factura_a']) ? 'CUIT' : 'DNI';
            }
            if ($documentoNumero === '') {
                $documentoNumero = preg_replace('/\D+/', '', (string)($pedido['cuit'] ?? ''));
            }

            $solicitaFacturaA = !empty($pedido['factura_a']) && $documentoTipo === 'CUIT' && strlen($documentoNumero) >= 11;
            $tipoFacturaInfo = contabilidad_determinar_tipo_factura($condicionEmisor, $condicionCliente, $solicitaFacturaA);
            $tipoFactura = trim((string)($pedido['tipo_factura'] ?? '')) ?: (string)($tipoFacturaInfo['tipo'] ?? 'B');
            if ($tipoFactura === 'A' && ($documentoTipo !== 'CUIT' || strlen($documentoNumero) !== 11)) {
                throw new RuntimeException('Para emitir Factura A el cliente debe tener un CUIT válido informado.');
            }

            $cuitRepresentada = preg_replace('/\D+/', '', (string)($afipConfig['cuit_representada'] ?? ''));
            if (strlen($cuitRepresentada) !== 11) {
                throw new RuntimeException('Completá el CUIT representado en la configuración AFIP/ARCA.');
            }
            if (empty($afipConfig['certificado_pem']) || empty($afipConfig['private_key_pem'])) {
                throw new RuntimeException('Faltan el certificado o la clave privada para conectarse con ARCA/AFIP.');
            }

            $ta = contabilidad_afip_obtener_ta($pdo, $afipConfig);
            $ptoVta = max(1, (int)($afipConfig['punto_venta'] ?? 1));
            $cbteTipo = contabilidad_afip_mapear_cbte_tipo($tipoFactura);
            $endpoints = contabilidad_afip_endpoints((string)($afipConfig['ambiente'] ?? 'homologacion'));

            $authXml = '<Auth><Token>' . contabilidad_afip_xml_escape($ta['token']) . '</Token><Sign>' . contabilidad_afip_xml_escape($ta['sign']) . '</Sign><Cuit>' . contabilidad_afip_xml_escape($cuitRepresentada) . '</Cuit></Auth>';
            $bodyUltimo = '<FECompUltimoAutorizado xmlns="http://ar.gov.afip.dif.FEV1/">' . $authXml . '<PtoVta>' . $ptoVta . '</PtoVta><CbteTipo>' . $cbteTipo . '</CbteTipo></FECompUltimoAutorizado>';
            $ultimoResp = contabilidad_afip_soap_request((string)$endpoints['wsfe'], 'http://ar.gov.afip.dif.FEV1/FECompUltimoAutorizado', $bodyUltimo);
            $ultimoXml = @simplexml_load_string($ultimoResp, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$ultimoXml) {
                throw new RuntimeException('No se pudo interpretar la respuesta de FECompUltimoAutorizado.');
            }
            $ultimoNumero = (int)contabilidad_afip_xpath_first($ultimoXml, '//*[local-name()="CbteNro"]');
            $cbteNro = max(1, $ultimoNumero + 1);

            $docData = contabilidad_afip_mapear_doc_tipo($documentoTipo, $documentoNumero);
            $totales = contabilidad_afip_preparar_totales_pedido($pedido, $tipoFactura);
            $moneda = contabilidad_afip_mapear_moneda((string)($configContable['moneda'] ?? 'ARS'));
            $fechaCbte = date('Ymd');

            $ivaXml = '';
            if (!empty($totales['Iva'])) {
                $ivaXml .= '<Iva>';
                foreach ($totales['Iva'] as $ivaRow) {
                    $ivaXml .= '<AlicIva>'
                        . '<Id>' . (int)($ivaRow['Id'] ?? 5) . '</Id>'
                        . '<BaseImp>' . number_format((float)($ivaRow['BaseImp'] ?? 0), 2, '.', '') . '</BaseImp>'
                        . '<Importe>' . number_format((float)($ivaRow['Importe'] ?? 0), 2, '.', '') . '</Importe>'
                        . '</AlicIva>';
                }
                $ivaXml .= '</Iva>';
            }

            $tributosXml = '';
            if (!empty($totales['Tributos'])) {
                $tributosXml .= '<Tributos>';
                foreach ($totales['Tributos'] as $tributo) {
                    $tributosXml .= '<Tributo>'
                        . '<Id>' . (int)($tributo['Id'] ?? 99) . '</Id>'
                        . '<Desc>' . contabilidad_afip_xml_escape((string)($tributo['Desc'] ?? 'Tributo')) . '</Desc>'
                        . '<BaseImp>' . number_format((float)($tributo['BaseImp'] ?? 0), 2, '.', '') . '</BaseImp>'
                        . '<Alic>' . number_format((float)($tributo['Alic'] ?? 0), 2, '.', '') . '</Alic>'
                        . '<Importe>' . number_format((float)($tributo['Importe'] ?? 0), 2, '.', '') . '</Importe>'
                        . '</Tributo>';
                }
                $tributosXml .= '</Tributos>';
            }

            $bodySolicitar = '<FECAESolicitar xmlns="http://ar.gov.afip.dif.FEV1/">'
                . $authXml
                . '<FeCAEReq>'
                . '<FeCabReq><CantReg>1</CantReg><PtoVta>' . $ptoVta . '</PtoVta><CbteTipo>' . $cbteTipo . '</CbteTipo></FeCabReq>'
                . '<FeDetReq><FECAEDetRequest>'
                . '<Concepto>1</Concepto>'
                . '<DocTipo>' . (int)$docData['codigo'] . '</DocTipo>'
                . '<DocNro>' . contabilidad_afip_xml_escape((string)$docData['numero']) . '</DocNro>'
                . '<CbteDesde>' . $cbteNro . '</CbteDesde>'
                . '<CbteHasta>' . $cbteNro . '</CbteHasta>'
                . '<CbteFch>' . $fechaCbte . '</CbteFch>'
                . '<ImpTotal>' . number_format((float)$totales['ImpTotal'], 2, '.', '') . '</ImpTotal>'
                . '<ImpTotConc>' . number_format((float)$totales['ImpTotConc'], 2, '.', '') . '</ImpTotConc>'
                . '<ImpNeto>' . number_format((float)$totales['ImpNeto'], 2, '.', '') . '</ImpNeto>'
                . '<ImpOpEx>' . number_format((float)$totales['ImpOpEx'], 2, '.', '') . '</ImpOpEx>'
                . '<ImpIVA>' . number_format((float)$totales['ImpIVA'], 2, '.', '') . '</ImpIVA>'
                . '<ImpTrib>' . number_format((float)$totales['ImpTrib'], 2, '.', '') . '</ImpTrib>'
                . '<MonId>' . contabilidad_afip_xml_escape($moneda) . '</MonId>'
                . '<MonCotiz>1.00</MonCotiz>'
                . $tributosXml
                . $ivaXml
                . '</FECAEDetRequest></FeDetReq>'
                . '</FeCAEReq>'
                . '</FECAESolicitar>';

            $solicitudResp = contabilidad_afip_soap_request((string)$endpoints['wsfe'], 'http://ar.gov.afip.dif.FEV1/FECAESolicitar', $bodySolicitar);
            $solicitudXml = @simplexml_load_string($solicitudResp, 'SimpleXMLElement', LIBXML_NOCDATA);
            if (!$solicitudXml) {
                throw new RuntimeException('No se pudo interpretar la respuesta de FECAESolicitar.');
            }

            $mensajes = array_merge(
                contabilidad_afip_collect_messages($solicitudXml, 'Err'),
                contabilidad_afip_collect_messages($solicitudXml, 'Obs'),
                contabilidad_afip_collect_messages($solicitudXml, 'Evt')
            );
            $resultado = contabilidad_afip_xpath_first($solicitudXml, '(//*[local-name()="FECAEDetResponse"]/*[local-name()="Resultado"])[1]');
            if ($resultado === '') {
                $resultado = contabilidad_afip_xpath_first($solicitudXml, '(//*[local-name()="Resultado"])[1]');
            }
            $cae = contabilidad_afip_xpath_first($solicitudXml, '//*[local-name()="CAE"]');
            $caeVtoRaw = contabilidad_afip_xpath_first($solicitudXml, '//*[local-name()="CAEFchVto"]');
            $caeVto = preg_match('/^(\d{4})(\d{2})(\d{2})$/', $caeVtoRaw, $mVto) ? ($mVto[1] . '-' . $mVto[2] . '-' . $mVto[3]) : null;
            $observaciones = implode(' | ', array_filter($mensajes));

            if ($cae === '' || strtoupper($resultado) !== 'A') {
                $stmtFallido = $pdo->prepare('UPDATE ecommerce_pedidos SET afip_resultado = ?, afip_observaciones = ? WHERE id = ?');
                $stmtFallido->execute([strtoupper($resultado ?: 'R'), $observaciones !== '' ? $observaciones : 'ARCA/AFIP no devolvió CAE para el comprobante.', $pedidoId]);
                throw new RuntimeException($observaciones !== '' ? $observaciones : 'ARCA/AFIP rechazó la solicitud del comprobante y no devolvió CAE.');
            }

            $numeroFactura = sprintf('%04d-%08d', $ptoVta, $cbteNro);
            $fechaFacturacion = date('Y-m-d H:i:s');
            $stmtOk = $pdo->prepare('UPDATE ecommerce_pedidos SET tipo_factura = ?, numero_factura = ?, fecha_facturacion = ?, cae = ?, cae_vencimiento = ?, afip_resultado = ?, afip_observaciones = ? WHERE id = ?');
            $stmtOk->execute([
                $tipoFactura,
                $numeroFactura,
                $fechaFacturacion,
                $cae,
                $caeVto,
                strtoupper($resultado),
                $observaciones !== '' ? $observaciones : 'Autorizado por ARCA/AFIP.',
                $pedidoId,
            ]);

            return [
                'pedido_id' => $pedidoId,
                'modo' => 'factura',
                'tipo_factura' => $tipoFactura,
                'numero_factura' => $numeroFactura,
                'cae' => $cae,
                'cae_vencimiento' => (string)$caeVto,
                'resultado' => strtoupper($resultado),
                'observaciones' => $observaciones,
                'token_expira_at' => (string)($ta['expira_at'] ?? ''),
                'ya_autorizado' => false,
            ];
        } finally {
            $unlockStmt = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $unlockStmt->execute([$lockName]);
        }
    }
}

if (!function_exists('contabilidad_afip_resumen_certificado')) {
    function contabilidad_afip_resumen_certificado(string $certificadoPem): array
    {
        $certificadoPem = trim($certificadoPem);
        if ($certificadoPem === '' || !function_exists('openssl_x509_parse')) {
            return [];
        }

        try {
            $parsed = openssl_x509_parse($certificadoPem);
            if (!is_array($parsed)) {
                return [];
            }

            $serialRaw = (string)($parsed['subject']['serialNumber'] ?? '');
            return [
                'subject_cn' => (string)($parsed['subject']['CN'] ?? ''),
                'issuer_cn' => (string)($parsed['issuer']['CN'] ?? ''),
                'issuer_o' => (string)($parsed['issuer']['O'] ?? ''),
                'subject_serial' => $serialRaw,
                'cuit_subject' => preg_replace('/\D+/', '', $serialRaw),
                'valid_from' => !empty($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validFrom_time_t']) : null,
                'valid_to' => !empty($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', (int)$parsed['validTo_time_t']) : null,
            ];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('contabilidad_validar_certificado_pem')) {
    function contabilidad_validar_certificado_pem(string $certificadoPem): bool
    {
        $certificadoPem = trim($certificadoPem);
        if ($certificadoPem === '' || strpos($certificadoPem, 'BEGIN CERTIFICATE') === false) {
            return false;
        }

        if (function_exists('openssl_x509_read')) {
            try {
                $x509 = openssl_x509_read($certificadoPem);
                return $x509 !== false;
            } catch (Throwable $e) {
                return false;
            }
        }

        return true;
    }
}
