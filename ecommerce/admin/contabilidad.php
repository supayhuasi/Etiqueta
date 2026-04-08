<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/contabilidad_helper.php';

if (!isset($can_access) || !$can_access('finanzas')) {
    die('Acceso denegado.');
}

ensureContabilidadSchema($pdo);

$mensaje = '';
$error = '';
$editandoId = max(0, (int)($_GET['editar'] ?? 0));
$moneda = 'ARS';
$config = contabilidad_get_config($pdo);
$afipConfig = contabilidad_get_afip_config($pdo);
$afipOpenSslDisponible = contabilidad_afip_openssl_available();

$descargaAfip = (string)($_GET['descargar_afip'] ?? '');
if ($descargaAfip !== '') {
    $cuitArchivo = preg_replace('/\D+/', '', (string)($afipConfig['cuit_representada'] ?? '')) ?: 'sin_cuit';
    $ambienteArchivo = preg_replace('/[^a-z0-9_-]+/i', '_', (string)($afipConfig['ambiente'] ?? 'homologacion')) ?: 'homologacion';
    $archivosAfip = [
        'csr' => [
            'contenido' => trim((string)($afipConfig['csr_pem'] ?? '')),
            'nombre' => 'ARCA_CSR_' . $cuitArchivo . '_' . $ambienteArchivo . '.csr',
            'tipo' => 'application/pkcs10',
        ],
        'key' => [
            'contenido' => trim((string)($afipConfig['private_key_pem'] ?? '')),
            'nombre' => 'ARCA_CLAVE_PRIVADA_' . $cuitArchivo . '_' . $ambienteArchivo . '.key',
            'tipo' => 'application/x-pem-file',
        ],
        'crt' => [
            'contenido' => trim((string)($afipConfig['certificado_pem'] ?? '')),
            'nombre' => 'ARCA_CERTIFICADO_' . $cuitArchivo . '_' . $ambienteArchivo . '.crt',
            'tipo' => 'application/x-x509-ca-cert',
        ],
    ];

    if (isset($archivosAfip[$descargaAfip]) && $archivosAfip[$descargaAfip]['contenido'] !== '') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $archivosAfip[$descargaAfip]['tipo'] . '; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $archivosAfip[$descargaAfip]['nombre'] . '"');
        header('Content-Length: ' . strlen($archivosAfip[$descargaAfip]['contenido']));
        echo $archivosAfip[$descargaAfip]['contenido'];
        exit;
    }

    $error = 'No hay un archivo AFIP/ARCA disponible para descargar todavía.';
}

if (!empty($config['moneda'])) {
    $moneda = (string)$config['moneda'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'guardar_config') {
            $monedaInput = strtoupper(trim((string)($_POST['moneda'] ?? 'ARS')));
            if ($monedaInput === '') {
                $monedaInput = 'ARS';
            }

            contabilidad_save_config($pdo, [
                'moneda' => substr($monedaInput, 0, 10),
                'condicion_fiscal' => trim((string)($_POST['condicion_fiscal'] ?? '')),
                'redondear_totales' => !empty($_POST['redondear_totales']) ? 1 : 0,
                'notas_fiscales' => trim((string)($_POST['notas_fiscales'] ?? '')),
            ]);

            $mensaje = 'Configuración contable guardada correctamente.';
        } elseif (in_array($action, ['guardar_afip_config', 'generar_csr_afip', 'guardar_certificado_afip'], true)) {
            $afipData = [
                'ambiente' => (string)($_POST['afip_ambiente'] ?? ($afipConfig['ambiente'] ?? 'homologacion')),
                'punto_venta' => max(1, (int)($_POST['afip_punto_venta'] ?? ($afipConfig['punto_venta'] ?? 1))),
                'cuit_representada' => preg_replace('/\D+/', '', (string)($_POST['afip_cuit_representada'] ?? ($afipConfig['cuit_representada'] ?? ''))),
                'razon_social' => trim((string)($_POST['afip_razon_social'] ?? ($afipConfig['razon_social'] ?? ''))),
                'alias_certificado' => trim((string)($_POST['afip_alias_certificado'] ?? ($afipConfig['alias_certificado'] ?? ''))),
                'email_contacto' => trim((string)($_POST['afip_email_contacto'] ?? ($afipConfig['email_contacto'] ?? ''))),
                'provincia' => trim((string)($_POST['afip_provincia'] ?? ($afipConfig['provincia'] ?? ''))),
                'localidad' => trim((string)($_POST['afip_localidad'] ?? ($afipConfig['localidad'] ?? ''))),
                'private_key_pem' => (string)($afipConfig['private_key_pem'] ?? ''),
                'csr_pem' => (string)($afipConfig['csr_pem'] ?? ''),
                'certificado_pem' => (string)($afipConfig['certificado_pem'] ?? ''),
            ];

            if ($action === 'generar_csr_afip') {
                if (!$afipOpenSslDisponible) {
                    throw new Exception('OpenSSL no está disponible en este servidor para generar el CSR.');
                }
                $generado = contabilidad_generar_csr_afip($afipData);
                $afipData['csr_pem'] = $generado['csr_pem'];
                $afipData['private_key_pem'] = $generado['private_key_pem'];
                contabilidad_save_afip_config($pdo, $afipData);
                $mensaje = 'CSR PKCS#10 generado correctamente. Descargalo y subilo en ARCA para solicitar el nuevo certificado.';
            } elseif ($action === 'guardar_certificado_afip') {
                $certificadoPem = trim((string)($_POST['afip_certificado_pem'] ?? ''));
                if ($certificadoPem === '') {
                    throw new Exception('Pegá el certificado devuelto por AFIP en formato PEM.');
                }
                if (!contabilidad_validar_certificado_pem($certificadoPem)) {
                    throw new Exception('El certificado cargado no tiene un formato PEM válido.');
                }
                $afipData['certificado_pem'] = $certificadoPem;
                contabilidad_save_afip_config($pdo, $afipData);
                $mensaje = 'Certificado AFIP guardado correctamente.';
            } else {
                contabilidad_save_afip_config($pdo, $afipData);
                $mensaje = 'Configuración AFIP guardada correctamente.';
            }
        } elseif ($action === 'guardar_impuesto') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $codigo = trim((string)($_POST['codigo'] ?? ''));
            $descripcion = trim((string)($_POST['descripcion'] ?? ''));
            $tipoCalculo = (string)($_POST['tipo_calculo'] ?? 'porcentaje');
            $valor = (float)($_POST['valor'] ?? 0);
            $aplicaA = (string)($_POST['aplica_a'] ?? 'ambos');
            $baseCalculo = (string)($_POST['base_calculo'] ?? 'subtotal');
            $incluidoEnPrecio = !empty($_POST['incluido_en_precio']) ? 1 : 0;
            $activo = !empty($_POST['activo']) ? 1 : 0;
            $ordenVisual = (int)($_POST['orden_visual'] ?? 0);

            if ($nombre === '') {
                throw new Exception('El nombre del impuesto es obligatorio.');
            }
            if (!in_array($tipoCalculo, ['porcentaje', 'fijo'], true)) {
                $tipoCalculo = 'porcentaje';
            }
            if (!in_array($aplicaA, ['pedido', 'cotizacion', 'ambos'], true)) {
                $aplicaA = 'ambos';
            }
            if (!in_array($baseCalculo, ['subtotal', 'total'], true)) {
                $baseCalculo = 'subtotal';
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ecommerce_contabilidad_impuestos SET nombre = ?, codigo = ?, descripcion = ?, tipo_calculo = ?, valor = ?, aplica_a = ?, base_calculo = ?, incluido_en_precio = ?, activo = ?, orden_visual = ? WHERE id = ?");
                $stmt->execute([$nombre, $codigo !== '' ? $codigo : null, $descripcion !== '' ? $descripcion : null, $tipoCalculo, $valor, $aplicaA, $baseCalculo, $incluidoEnPrecio, $activo, $ordenVisual, $id]);
                $mensaje = 'Impuesto actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO ecommerce_contabilidad_impuestos (nombre, codigo, descripcion, tipo_calculo, valor, aplica_a, base_calculo, incluido_en_precio, activo, orden_visual) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $codigo !== '' ? $codigo : null, $descripcion !== '' ? $descripcion : null, $tipoCalculo, $valor, $aplicaA, $baseCalculo, $incluidoEnPrecio, $activo, $ordenVisual]);
                $mensaje = 'Impuesto creado correctamente.';
            }

            $editandoId = 0;
        } elseif ($action === 'toggle_activo') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            if ($id <= 0) {
                throw new Exception('Impuesto inválido.');
            }
            $stmt = $pdo->prepare("UPDATE ecommerce_contabilidad_impuestos SET activo = IF(activo = 1, 0, 1) WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = 'Estado del impuesto actualizado.';
        } elseif ($action === 'eliminar_impuesto') {
            $id = max(0, (int)($_POST['id'] ?? 0));
            if ($id <= 0) {
                throw new Exception('Impuesto inválido.');
            }
            $stmt = $pdo->prepare("DELETE FROM ecommerce_contabilidad_impuestos WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $mensaje = 'Impuesto eliminado correctamente.';
            $editandoId = 0;
        }

        $config = contabilidad_get_config($pdo);
        $afipConfig = contabilidad_get_afip_config($pdo);
        if (!empty($config['moneda'])) {
            $moneda = (string)$config['moneda'];
        }
    } catch (Throwable $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

$impuestos = contabilidad_get_impuestos($pdo, false);
$impuestosActivos = array_values(array_filter($impuestos, static function ($row) {
    return !empty($row['activo']);
}));
$impuestoEditar = $editandoId > 0 ? contabilidad_get_impuesto($pdo, $editandoId) : null;

$montoDemo = max(0, (float)($_GET['monto_demo'] ?? 100000));
$ambitoDemo = (string)($_GET['ambito_demo'] ?? 'pedido');
if (!in_array($ambitoDemo, ['pedido', 'cotizacion'], true)) {
    $ambitoDemo = 'pedido';
}
$resumenDemo = contabilidad_calcular_impuestos($impuestosActivos, $montoDemo, $montoDemo, $ambitoDemo);

$impuestosActivosCount = count($impuestosActivos);
$cargaPorcentualActiva = 0.0;
foreach ($impuestosActivos as $imp) {
    if ((string)($imp['tipo_calculo'] ?? '') === 'porcentaje' && empty($imp['incluido_en_precio'])) {
        $cargaPorcentualActiva += (float)($imp['valor'] ?? 0);
    }
}

$ventasMesBase = 0.0;
$impuestosEstimadosMes = 0.0;
$mesActual = date('Y-m');
if (contabilidad_table_exists($pdo, 'ecommerce_pedidos')) {
    try {
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(total), 0) FROM ecommerce_pedidos WHERE estado != 'cancelado' AND DATE_FORMAT(fecha_pedido, '%Y-%m') = ?");
        $stmt->execute([$mesActual]);
        $ventasMesBase = (float)$stmt->fetchColumn();
        $resumenMes = contabilidad_calcular_impuestos($impuestosActivos, $ventasMesBase, $ventasMesBase, 'pedido');
        $impuestosEstimadosMes = (float)($resumenMes['total_adicionales'] ?? 0) + (float)($resumenMes['total_incluidos'] ?? 0);
    } catch (Throwable $e) {
        $ventasMesBase = 0.0;
        $impuestosEstimadosMes = 0.0;
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h1 class="mb-1">📚 Contabilidad e Impuestos</h1>
        <p class="text-muted mb-0">Configurá impuestos, régimen fiscal y simulá cómo impactan en pedidos y cotizaciones.</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="finanzas.php" class="btn btn-outline-secondary">← Volver a finanzas</a>
    </div>
</div>

<?php if ($mensaje !== ''): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card h-100 border-primary">
            <div class="card-body">
                <div class="small text-muted">Impuestos activos</div>
                <div class="display-6 fw-semibold"><?= number_format($impuestosActivosCount, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-success">
            <div class="card-body">
                <div class="small text-muted">Carga adicional activa</div>
                <div class="display-6 fw-semibold"><?= number_format($cargaPorcentualActiva, 2, ',', '.') ?>%</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-info">
            <div class="card-body">
                <div class="small text-muted">Ventas base del mes</div>
                <div class="h4 mb-0"><?= htmlspecialchars($moneda) ?> $<?= number_format($ventasMesBase, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100 border-warning">
            <div class="card-body">
                <div class="small text-muted">Carga impositiva estimada</div>
                <div class="h4 mb-0"><?= htmlspecialchars($moneda) ?> $<?= number_format($impuestosEstimadosMes, 0, ',', '.') ?></div>
                <small class="text-muted">Referencia sobre pedidos del mes actual</small>
            </div>
        </div>
    </div>
</div>

<div class="alert alert-info">
    Este módulo agrega una capa <strong>contable/fiscal</strong> al sistema. Las configuraciones sirven como referencia para pedidos y cotizaciones sin modificar el historial ya guardado.
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light"><strong>ARCA / AFIP · Certificado digital</strong></div>
            <div class="card-body">
                <p class="text-muted small mb-3">Para obtener un nuevo certificado de factura electrónica, ARCA solicita subir un <strong>CSR (Certificate Signing Request) en formato PKCS#10</strong>. Desde acá podés generarlo, descargarlo y guardar luego el certificado devuelto.</p>

                <?php if (!$afipOpenSslDisponible): ?>
                    <div class="alert alert-danger">OpenSSL no está disponible en PHP, por lo que no se puede generar el CSR desde el sistema.</div>
                <?php endif; ?>

                <?php if (!empty($afipConfig['certificado_vencimiento']) || !empty($afipConfig['wsaa_expira_at']) || !empty($afipConfig['ultimo_error'])): ?>
                    <div class="alert alert-secondary small py-2">
                        <?php if (!empty($afipConfig['certificado_vencimiento'])): ?>
                            <div><strong>Certificado:</strong> válido hasta <?= htmlspecialchars(date('d/m/Y', strtotime((string)$afipConfig['certificado_vencimiento']))) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($afipConfig['wsaa_expira_at'])): ?>
                            <div><strong>Sesión WSAA:</strong> vigente hasta <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$afipConfig['wsaa_expira_at']))) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($afipConfig['ultimo_error'])): ?>
                            <div class="text-danger"><strong>Último error ARCA/AFIP:</strong> <?= htmlspecialchars((string)$afipConfig['ultimo_error']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_afip_config">
                    <div class="col-md-4">
                        <label class="form-label">Ambiente</label>
                        <?php $ambienteAfip = (string)($afipConfig['ambiente'] ?? 'homologacion'); ?>
                        <select name="afip_ambiente" class="form-select">
                            <option value="homologacion" <?= $ambienteAfip === 'homologacion' ? 'selected' : '' ?>>Homologación</option>
                            <option value="produccion" <?= $ambienteAfip === 'produccion' ? 'selected' : '' ?>>Producción</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Punto de venta</label>
                        <input type="number" name="afip_punto_venta" class="form-control" min="1" value="<?= (int)($afipConfig['punto_venta'] ?? 1) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CUIT representado</label>
                        <input type="text" name="afip_cuit_representada" class="form-control" maxlength="20" value="<?= htmlspecialchars((string)($afipConfig['cuit_representada'] ?? '')) ?>" placeholder="30XXXXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Razón social</label>
                        <input type="text" name="afip_razon_social" class="form-control" value="<?= htmlspecialchars((string)($afipConfig['razon_social'] ?? '')) ?>" placeholder="Nombre legal de la empresa">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Alias del certificado</label>
                        <input type="text" name="afip_alias_certificado" class="form-control" value="<?= htmlspecialchars((string)($afipConfig['alias_certificado'] ?? '')) ?>" placeholder="Ej: AFIP Tucu Roller">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Email de contacto</label>
                        <input type="email" name="afip_email_contacto" class="form-control" value="<?= htmlspecialchars((string)($afipConfig['email_contacto'] ?? '')) ?>" placeholder="facturacion@empresa.com">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Provincia</label>
                        <input type="text" name="afip_provincia" class="form-control" value="<?= htmlspecialchars((string)($afipConfig['provincia'] ?? '')) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Localidad</label>
                        <input type="text" name="afip_localidad" class="form-control" value="<?= htmlspecialchars((string)($afipConfig['localidad'] ?? '')) ?>">
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-outline-primary">Guardar datos AFIP</button>
                    </div>
                </form>

                <hr>

                <div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="generar_csr_afip">
                        <input type="hidden" name="afip_ambiente" value="<?= htmlspecialchars((string)($afipConfig['ambiente'] ?? 'homologacion')) ?>">
                        <input type="hidden" name="afip_punto_venta" value="<?= (int)($afipConfig['punto_venta'] ?? 1) ?>">
                        <input type="hidden" name="afip_cuit_representada" value="<?= htmlspecialchars((string)($afipConfig['cuit_representada'] ?? '')) ?>">
                        <input type="hidden" name="afip_razon_social" value="<?= htmlspecialchars((string)($afipConfig['razon_social'] ?? '')) ?>">
                        <input type="hidden" name="afip_alias_certificado" value="<?= htmlspecialchars((string)($afipConfig['alias_certificado'] ?? '')) ?>">
                        <input type="hidden" name="afip_email_contacto" value="<?= htmlspecialchars((string)($afipConfig['email_contacto'] ?? '')) ?>">
                        <input type="hidden" name="afip_provincia" value="<?= htmlspecialchars((string)($afipConfig['provincia'] ?? '')) ?>">
                        <input type="hidden" name="afip_localidad" value="<?= htmlspecialchars((string)($afipConfig['localidad'] ?? '')) ?>">
                        <button type="submit" class="btn btn-primary" <?= !$afipOpenSslDisponible ? 'disabled' : '' ?>>Generar CSR PKCS#10</button>
                    </form>
                </div>

                <ol class="small text-muted mb-0 ps-3">
                    <li>Completá los datos fiscales de AFIP.</li>
                    <li>Generá el <strong>CSR PKCS#10</strong>.</li>
                    <li>Copialo y subilo en AFIP para pedir el certificado.</li>
                    <li>Cuando AFIP te devuelva el certificado, pegalo abajo para dejarlo guardado.</li>
                </ol>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header bg-light"><strong>CSR generado y certificado PEM</strong></div>
            <div class="card-body">
                <label class="form-label">CSR para subir a ARCA (PKCS#10)</label>
                <textarea class="form-control font-monospace" rows="8" readonly><?= htmlspecialchars((string)($afipConfig['csr_pem'] ?? '')) ?></textarea>
                <small class="text-muted d-block mt-2">Este bloque empieza con <code>-----BEGIN CERTIFICATE REQUEST-----</code> y es el que ARCA te pide subir.</small>

                <div class="d-flex gap-2 flex-wrap mt-3">
                    <a href="contabilidad.php?descargar_afip=csr" class="btn btn-outline-primary <?= empty($afipConfig['csr_pem']) ? 'disabled' : '' ?>" <?= empty($afipConfig['csr_pem']) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Descargar CSR (.csr)</a>
                    <a href="contabilidad.php?descargar_afip=key" class="btn btn-outline-danger <?= empty($afipConfig['private_key_pem']) ? 'disabled' : '' ?>" <?= empty($afipConfig['private_key_pem']) ? 'tabindex="-1" aria-disabled="true"' : '' ?>>Descargar clave privada (.key)</a>
                    <?php if (!empty($afipConfig['certificado_pem'])): ?>
                        <a href="contabilidad.php?descargar_afip=crt" class="btn btn-outline-success">Descargar certificado (.crt)</a>
                    <?php endif; ?>
                </div>
                <small class="text-muted d-block mt-2">Subí a ARCA solamente el archivo <code>.csr</code>. La clave privada <code>.key</code> guardala de forma segura y no la compartas.</small>

                <details class="mt-3">
                    <summary class="fw-semibold">Ver clave privada generada</summary>
                    <div class="alert alert-warning mt-2 py-2 mb-2">Guardá esta clave privada en un lugar seguro. Se usará luego para firmar comprobantes.</div>
                    <textarea class="form-control font-monospace" rows="7" readonly><?= htmlspecialchars((string)($afipConfig['private_key_pem'] ?? '')) ?></textarea>
                </details>

                <hr>

                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_certificado_afip">
                    <input type="hidden" name="afip_ambiente" value="<?= htmlspecialchars((string)($afipConfig['ambiente'] ?? 'homologacion')) ?>">
                    <input type="hidden" name="afip_punto_venta" value="<?= (int)($afipConfig['punto_venta'] ?? 1) ?>">
                    <input type="hidden" name="afip_cuit_representada" value="<?= htmlspecialchars((string)($afipConfig['cuit_representada'] ?? '')) ?>">
                    <input type="hidden" name="afip_razon_social" value="<?= htmlspecialchars((string)($afipConfig['razon_social'] ?? '')) ?>">
                    <input type="hidden" name="afip_alias_certificado" value="<?= htmlspecialchars((string)($afipConfig['alias_certificado'] ?? '')) ?>">
                    <input type="hidden" name="afip_email_contacto" value="<?= htmlspecialchars((string)($afipConfig['email_contacto'] ?? '')) ?>">
                    <input type="hidden" name="afip_provincia" value="<?= htmlspecialchars((string)($afipConfig['provincia'] ?? '')) ?>">
                    <input type="hidden" name="afip_localidad" value="<?= htmlspecialchars((string)($afipConfig['localidad'] ?? '')) ?>">
                    <div class="col-12">
                        <label class="form-label">Certificado devuelto por AFIP (PEM)</label>
                        <textarea name="afip_certificado_pem" class="form-control font-monospace" rows="8" placeholder="-----BEGIN CERTIFICATE-----"><?= htmlspecialchars((string)($afipConfig['certificado_pem'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap align-items-center">
                        <button type="submit" class="btn btn-success">Guardar certificado</button>
                        <?php if (!empty($afipConfig['certificado_vencimiento'])): ?>
                            <span class="badge bg-info text-dark">Vence: <?= htmlspecialchars(date('d/m/Y', strtotime((string)$afipConfig['certificado_vencimiento']))) ?></span>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light"><strong>Configuración fiscal general</strong></div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_config">
                    <div class="col-md-4">
                        <label class="form-label">Moneda</label>
                        <input type="text" name="moneda" class="form-control" maxlength="10" value="<?= htmlspecialchars((string)($config['moneda'] ?? 'ARS')) ?>">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Condición fiscal</label>
                        <input type="text" name="condicion_fiscal" class="form-control" value="<?= htmlspecialchars((string)($config['condicion_fiscal'] ?? '')) ?>" placeholder="Ej: Responsable Inscripto">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas fiscales</label>
                        <textarea name="notas_fiscales" class="form-control" rows="4" placeholder="Observaciones para facturación, alícuotas o criterios contables"><?= htmlspecialchars((string)($config['notas_fiscales'] ?? '')) ?></textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="redondear_totales" name="redondear_totales" value="1" <?= !empty($config['redondear_totales']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="redondear_totales">Redondear totales en simulaciones contables</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Guardar configuración</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-light"><strong><?= $impuestoEditar ? 'Editar impuesto' : 'Nuevo impuesto' ?></strong></div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <input type="hidden" name="action" value="guardar_impuesto">
                    <input type="hidden" name="id" value="<?= (int)($impuestoEditar['id'] ?? 0) ?>">
                    <div class="col-md-6">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars((string)($impuestoEditar['nombre'] ?? '')) ?>" placeholder="Ej: IVA 21%">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Código</label>
                        <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars((string)($impuestoEditar['codigo'] ?? '')) ?>" placeholder="IVA21">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Orden</label>
                        <input type="number" name="orden_visual" class="form-control" value="<?= (int)($impuestoEditar['orden_visual'] ?? 0) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Descripción</label>
                        <input type="text" name="descripcion" class="form-control" value="<?= htmlspecialchars((string)($impuestoEditar['descripcion'] ?? '')) ?>" placeholder="Descripción opcional">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <?php $tipoActual = (string)($impuestoEditar['tipo_calculo'] ?? 'porcentaje'); ?>
                        <select name="tipo_calculo" class="form-select">
                            <option value="porcentaje" <?= $tipoActual === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                            <option value="fijo" <?= $tipoActual === 'fijo' ? 'selected' : '' ?>>Monto fijo</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        <input type="number" step="0.0001" min="0" name="valor" class="form-control" value="<?= htmlspecialchars((string)($impuestoEditar['valor'] ?? '0')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Aplica a</label>
                        <?php $aplicaActual = (string)($impuestoEditar['aplica_a'] ?? 'ambos'); ?>
                        <select name="aplica_a" class="form-select">
                            <option value="pedido" <?= $aplicaActual === 'pedido' ? 'selected' : '' ?>>Pedidos</option>
                            <option value="cotizacion" <?= $aplicaActual === 'cotizacion' ? 'selected' : '' ?>>Cotizaciones</option>
                            <option value="ambos" <?= $aplicaActual === 'ambos' ? 'selected' : '' ?>>Ambos</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Base</label>
                        <?php $baseActual = (string)($impuestoEditar['base_calculo'] ?? 'subtotal'); ?>
                        <select name="base_calculo" class="form-select">
                            <option value="subtotal" <?= $baseActual === 'subtotal' ? 'selected' : '' ?>>Subtotal</option>
                            <option value="total" <?= $baseActual === 'total' ? 'selected' : '' ?>>Total</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="incluido_en_precio" name="incluido_en_precio" value="1" <?= !empty($impuestoEditar['incluido_en_precio']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="incluido_en_precio">Ya viene incluido en el precio</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="activo" name="activo" value="1" <?= !array_key_exists('activo', $impuestoEditar ?: []) || !empty($impuestoEditar['activo']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="activo">Impuesto activo</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary"><?= $impuestoEditar ? 'Actualizar impuesto' : 'Crear impuesto' ?></button>
                        <?php if ($impuestoEditar): ?>
                            <a href="contabilidad.php" class="btn btn-outline-secondary">Cancelar edición</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-light"><strong>Simulador contable</strong></div>
            <div class="card-body">
                <form method="GET" class="row g-3 mb-3">
                    <div class="col-md-7">
                        <label class="form-label">Monto base</label>
                        <input type="number" name="monto_demo" step="0.01" min="0" class="form-control" value="<?= htmlspecialchars((string)$montoDemo) ?>">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Ámbito</label>
                        <select name="ambito_demo" class="form-select">
                            <option value="pedido" <?= $ambitoDemo === 'pedido' ? 'selected' : '' ?>>Pedido</option>
                            <option value="cotizacion" <?= $ambitoDemo === 'cotizacion' ? 'selected' : '' ?>>Cotización</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-outline-primary">Simular</button>
                    </div>
                </form>

                <div class="border rounded p-3 bg-light">
                    <div><strong>Base:</strong> <?= htmlspecialchars($moneda) ?> $<?= number_format($montoDemo, 2, ',', '.') ?></div>
                    <div><strong>Impuestos incluidos:</strong> <?= htmlspecialchars($moneda) ?> $<?= number_format((float)($resumenDemo['total_incluidos'] ?? 0), 2, ',', '.') ?></div>
                    <div><strong>Impuestos adicionales:</strong> <?= htmlspecialchars($moneda) ?> $<?= number_format((float)($resumenDemo['total_adicionales'] ?? 0), 2, ',', '.') ?></div>
                    <div class="mt-2"><strong>Total estimado:</strong> <?= htmlspecialchars($moneda) ?> $<?= number_format((float)($resumenDemo['total_con_impuestos'] ?? $montoDemo), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-light"><strong>Desglose aplicado</strong></div>
            <div class="card-body">
                <?php if (empty($resumenDemo['detalle'])): ?>
                    <div class="text-muted">No hay impuestos activos para el ámbito seleccionado.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Impuesto</th>
                                    <th>Tipo</th>
                                    <th>Base</th>
                                    <th>Monto</th>
                                    <th>Tratamiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resumenDemo['detalle'] as $det): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$det['nombre']) ?></td>
                                        <td><?= htmlspecialchars((string)$det['tipo_calculo']) ?> <?= $det['tipo_calculo'] === 'porcentaje' ? '(' . number_format((float)$det['valor'], 2, ',', '.') . '%)' : '' ?></td>
                                        <td><?= htmlspecialchars($moneda) ?> $<?= number_format((float)$det['base'], 2, ',', '.') ?></td>
                                        <td><?= htmlspecialchars($moneda) ?> $<?= number_format((float)$det['monto'], 2, ',', '.') ?></td>
                                        <td><?= !empty($det['incluido_en_precio']) ? 'Incluido' : 'Sumado' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
        <strong>Impuestos configurados</strong>
        <span class="text-muted small"><?= count($impuestos) ?> registro(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($impuestos)): ?>
            <div class="p-4 text-center text-muted">No hay impuestos cargados todavía.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Ámbito</th>
                            <th>Base</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th>Tratamiento</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($impuestos as $imp): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars((string)$imp['nombre']) ?></strong>
                                    <?php if (!empty($imp['codigo'])): ?>
                                        <div class="small text-muted">Código: <?= htmlspecialchars((string)$imp['codigo']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($imp['descripcion'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string)$imp['descripcion']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars(ucfirst((string)$imp['aplica_a'])) ?></td>
                                <td><?= htmlspecialchars(ucfirst((string)$imp['base_calculo'])) ?></td>
                                <td>
                                    <?php if ((string)$imp['tipo_calculo'] === 'porcentaje'): ?>
                                        <?= number_format((float)$imp['valor'], 2, ',', '.') ?>%
                                    <?php else: ?>
                                        <?= htmlspecialchars($moneda) ?> $<?= number_format((float)$imp['valor'], 2, ',', '.') ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= !empty($imp['activo']) ? 'success' : 'secondary' ?>">
                                        <?= !empty($imp['activo']) ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>
                                <td><?= !empty($imp['incluido_en_precio']) ? 'Incluido en precio' : 'Se suma al total' ?></td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <a href="contabilidad.php?editar=<?= (int)$imp['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="toggle_activo">
                                            <input type="hidden" name="id" value="<?= (int)$imp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= !empty($imp['activo']) ? 'Desactivar' : 'Activar' ?></button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este impuesto?');">
                                            <input type="hidden" name="action" value="eliminar_impuesto">
                                            <input type="hidden" name="id" value="<?= (int)$imp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
