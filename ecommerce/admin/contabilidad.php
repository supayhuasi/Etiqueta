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
