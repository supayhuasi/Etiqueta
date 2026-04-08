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
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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

                if ($tablaDocumento === 'ecommerce_pedidos') {
                    if (!in_array('tipo_factura', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN tipo_factura VARCHAR(5) NULL AFTER impuestos_adicionales");
                    }
                    if (!in_array('numero_factura', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN numero_factura VARCHAR(30) NULL AFTER tipo_factura");
                    }
                    if (!in_array('fecha_facturacion', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN fecha_facturacion DATETIME NULL AFTER numero_factura");
                    }
                    if (!in_array('factura_archivo', $columnasDocumento, true)) {
                        $pdo->exec("ALTER TABLE {$tablaDocumento} ADD COLUMN factura_archivo VARCHAR(255) NULL AFTER fecha_facturacion");
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

        $stmt = $pdo->prepare("INSERT INTO ecommerce_contabilidad_afip_config (id, ambiente, punto_venta, cuit_representada, razon_social, alias_certificado, email_contacto, provincia, localidad, private_key_pem, csr_pem, certificado_pem, certificado_vencimiento)
            VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
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
                certificado_vencimiento = VALUES(certificado_vencimiento)");
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
