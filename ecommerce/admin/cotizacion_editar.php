<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/descuentos.php';

$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("Cotización no encontrada");
}

$items = json_decode($cotizacion['items'], true) ?? [];
$mensaje = '';
$error = '';

// Tabla de cupones
$pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_cupones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL UNIQUE,
    tipo ENUM('porcentaje','monto') NOT NULL,
    valor DECIMAL(10,2) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    fecha_inicio DATE NULL,
    fecha_fin DATE NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Columnas de cupón en cotizaciones
$cols_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('cupon_codigo', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_codigo VARCHAR(50) NULL");
}
if (!in_array('cupon_descuento', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cupon_descuento DECIMAL(10,2) NULL");
}
if (!in_array('cuit', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN cuit VARCHAR(20) NULL AFTER telefono");
}
if (!in_array('factura_a', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
}
if (!in_array('es_empresa', $cols_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizaciones ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
}

$cols_cli_cot = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizacion_clientes")->fetchAll(PDO::FETCH_COLUMN, 0);
if (!in_array('direccion', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN direccion VARCHAR(255) NULL AFTER telefono");
}
if (!in_array('cuit', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN cuit VARCHAR(20) NULL AFTER direccion");
}
if (!in_array('factura_a', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN factura_a TINYINT(1) NOT NULL DEFAULT 0 AFTER cuit");
}
if (!in_array('es_empresa', $cols_cli_cot, true)) {
    $pdo->exec("ALTER TABLE ecommerce_cotizacion_clientes ADD COLUMN es_empresa TINYINT(1) NOT NULL DEFAULT 0 AFTER factura_a");
}

$cols_cot_actuales = $pdo->query("SHOW COLUMNS FROM ecommerce_cotizaciones")->fetchAll(PDO::FETCH_COLUMN, 0);

$direccion_col = in_array('direccion', $cols_cot_actuales, true) ? 'direccion' : (in_array('empresa', $cols_cot_actuales, true) ? 'empresa' : null);
$dni_col = in_array('dni', $cols_cot_actuales, true) ? 'dni' : null;
$cuit_col = in_array('cuit', $cols_cot_actuales, true) ? 'cuit' : null;
$factura_a_col = in_array('factura_a', $cols_cot_actuales, true) ? 'factura_a' : null;
$es_empresa_col = in_array('es_empresa', $cols_cot_actuales, true) ? 'es_empresa' : null;
$tiene_lista_precio = in_array('lista_precio_id', $cols_cot_actuales, true);

$direccion_actual = '';
if ($direccion_col !== null) {
    $direccion_actual = (string)($cotizacion[$direccion_col] ?? '');
}
$dni_actual = $dni_col !== null ? (string)($cotizacion[$dni_col] ?? '') : '';
$cuit_actual = $cuit_col !== null ? preg_replace('/\D+/', '', (string)($cotizacion[$cuit_col] ?? '')) : '';
$factura_a_actual = $factura_a_col !== null ? (int)($cotizacion[$factura_a_col] ?? 0) : 0;
$es_empresa_actual = $es_empresa_col !== null ? (int)($cotizacion[$es_empresa_col] ?? 0) : 0;

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $items_originales = $items;
    $items_nuevos = $items;
    try {
        $nombre_cliente = $_POST['nombre_cliente'] ?? '';
        $email = $_POST['email'] ?? '';
        $telefono = $_POST['telefono'] ?? '';
        $direccion = $_POST['direccion'] ?? '';
        $dni = trim((string)($_POST['dni'] ?? ''));
        $cuit = preg_replace('/\D+/', '', (string)($_POST['cuit'] ?? ''));
        $es_empresa = !empty($_POST['es_empresa']) ? 1 : 0;
        $factura_a = !empty($_POST['factura_a']) ? 1 : 0;
        $observaciones = $_POST['observaciones'] ?? '';
        $validez_dias = intval($_POST['validez_dias'] ?? 15);
        $lista_precio_id = !empty($_POST['lista_precio_id']) ? intval($_POST['lista_precio_id']) : null;

        if (empty($nombre_cliente)) {
            throw new Exception("Nombre es obligatorio");
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email no válido");
        }
        if ($es_empresa && $factura_a && strlen($cuit) !== 11) {
            throw new Exception("Si es empresa con Factura A, el CUIT debe tener 11 dígitos");
        }

        $items_nuevos = [];
        $subtotal = 0;

        // Aceptar items como JSON (más fiable) o como array POST
        $items_post = $_POST['items'] ?? [];
        $items_json_raw = trim((string)($_POST['items_json'] ?? ''));
        if ($items_json_raw !== '') {
            $decoded = json_decode($items_json_raw, true);
            if (is_array($decoded)) {
                $items_post = $decoded;
            }
        }

        if ((!is_array($items_post) || empty($items_post)) && !empty($items_originales) && is_array($items_originales)) {
            $items_post = $items_originales;
        }

        if (!empty($items_post) && is_array($items_post)) {
            foreach ($items_post as $item) {
                $nombre = trim((string)($item['nombre'] ?? ''));
                $cantidad = (int)($item['cantidad'] ?? 0);
                $precio = floatval($item['precio'] ?? ($item['precio_base'] ?? ($item['precio_unitario'] ?? 0)));
                if ($nombre === '' || $cantidad < 1 || $precio < 0) {
                    continue;
                }

                $total_item = $cantidad * $precio;

                $atributos = [];
                $costo_atributos_total = 0;
                if (!empty($item['atributos']) && is_array($item['atributos'])) {
                    foreach ($item['atributos'] as $attr) {
                        if (!empty($attr['nombre'])) {
                            $costo = floatval($attr['costo'] ?? 0);
                            $atributos[] = [
                                'nombre' => $attr['nombre'],
                                'valor' => $attr['valor'] ?? '',
                                'costo_adicional' => $costo
                            ];
                            $costo_atributos_total += $costo;
                        }
                    }
                }

                $precio_total_unitario = $precio + $costo_atributos_total;
                $total_item = $cantidad * $precio_total_unitario;

                $items_nuevos[] = [
                    'producto_id' => !empty($item['producto_id']) ? intval($item['producto_id']) : null,
                    'nombre' => $nombre,
                    'descripcion' => $item['descripcion'] ?? '',
                    'cantidad' => $cantidad,
                    'ancho' => !empty($item['ancho']) ? floatval($item['ancho']) : null,
                    'alto' => !empty($item['alto']) ? floatval($item['alto']) : null,
                    'precio_base' => $precio,
                    'atributos' => $atributos,
                    'descuento_pct' => max(0, min(100, floatval($item['descuento_pct'] ?? 0))),
                    'precio_unitario' => $precio_total_unitario,
                    'precio_total' => $total_item
                ];

                $subtotal += $total_item;
            }
        }

        if (empty($items_nuevos)) {
            throw new Exception("Debe agregar al menos un item");
        }

        $descuento = floatval($_POST['descuento'] ?? 0);
        $cupon_codigo = normalizar_codigo_descuento((string)($_POST['cupon_codigo'] ?? ''));
        $cupon_descuento = floatval($_POST['cupon_descuento'] ?? 0);
        if ($cupon_codigo !== '') {
            $descuento_row = obtener_descuento_por_codigo($pdo, $cupon_codigo);
            if (!$descuento_row) {
                throw new Exception('Cupón inválido');
            }
            $validacion = validar_descuento($descuento_row, $subtotal);
            if (!$validacion['valido']) {
                throw new Exception($validacion['mensaje']);
            }
            $cupon_descuento = calcular_monto_descuento($descuento_row['tipo'], (float)$descuento_row['valor'], $subtotal);
        } else {
            $cupon_descuento = 0;
        }

        $total = $subtotal - $descuento - $cupon_descuento;
            $set_parts = [];
            $params = [];

            $pushIfColumnExists = static function (string $column, $value) use (&$set_parts, &$params, $cols_cot_actuales): void {
                if (in_array($column, $cols_cot_actuales, true)) {
                    $set_parts[] = $column . ' = ?';
                    $params[] = $value;
                }
            };

            $pushIfColumnExists('nombre_cliente', $nombre_cliente);
            $pushIfColumnExists('email', $email);
            $pushIfColumnExists('telefono', $telefono);
            $pushIfColumnExists('lista_precio_id', $lista_precio_id);
            $pushIfColumnExists('items', json_encode($items_nuevos, JSON_UNESCAPED_UNICODE));
            $pushIfColumnExists('subtotal', $subtotal);
            $pushIfColumnExists('descuento', $descuento);
            $pushIfColumnExists('cupon_codigo', $cupon_codigo ?: null);
            $pushIfColumnExists('cupon_descuento', $cupon_descuento);
            $pushIfColumnExists('total', $total);
            $pushIfColumnExists('observaciones', $observaciones);
            $pushIfColumnExists('validez_dias', $validez_dias);

            if ($direccion_col !== null) {
                $set_parts[] = $direccion_col . ' = ?';
                $params[] = $direccion;
            }

            if ($dni_col !== null) {
                $set_parts[] = $dni_col . ' = ?';
                $params[] = ($dni !== '' ? $dni : null);
            }
            if ($cuit_col !== null) {
                $set_parts[] = $cuit_col . ' = ?';
                $params[] = ($cuit !== '' ? $cuit : null);
            }
            if ($factura_a_col !== null) {
                $set_parts[] = $factura_a_col . ' = ?';
                $params[] = $factura_a;
            }
            if ($es_empresa_col !== null) {
                $set_parts[] = $es_empresa_col . ' = ?';
                $params[] = $es_empresa;
            }

            if (empty($set_parts)) {
                throw new Exception('No se pudo guardar: no hay columnas editables disponibles en ecommerce_cotizaciones.');
            }

            $sql_update = "UPDATE ecommerce_cotizaciones SET " . implode(', ', $set_parts) . " WHERE id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql_update);
            $stmt->execute($params);

            $cliente_id_rel = (int)($cotizacion['cliente_id'] ?? 0);
            if ($cliente_id_rel <= 0) {
                $email_norm = strtolower(trim((string)$email));
                if ($email_norm !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE email = ? LIMIT 1");
                    $stmt->execute([$email_norm]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $cliente_id_rel = (int)$row['id'];
                    }
                }
                if ($cliente_id_rel <= 0 && trim((string)$telefono) !== '') {
                    $stmt = $pdo->prepare("SELECT id FROM ecommerce_cotizacion_clientes WHERE telefono = ? LIMIT 1");
                    $stmt->execute([trim((string)$telefono)]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($row) {
                        $cliente_id_rel = (int)$row['id'];
                    }
                }
                if ($cliente_id_rel <= 0) {
                    $stmt = $pdo->prepare("INSERT INTO ecommerce_cotizacion_clientes (nombre, email, telefono, direccion, cuit, factura_a, es_empresa, activo) VALUES (?, ?, ?, ?, ?, ?, ?, 1)");
                    $stmt->execute([
                        $nombre_cliente,
                        $email !== '' ? strtolower(trim((string)$email)) : null,
                        $telefono !== '' ? trim((string)$telefono) : null,
                        $direccion !== '' ? $direccion : null,
                        $cuit !== '' ? $cuit : null,
                        $factura_a,
                        $es_empresa,
                    ]);
                    $cliente_id_rel = (int)$pdo->lastInsertId();
                }
            }

            if ($cliente_id_rel > 0) {
                $stmt = $pdo->prepare("UPDATE ecommerce_cotizacion_clientes SET nombre = ?, email = ?, telefono = ?, direccion = ?, cuit = ?, factura_a = ?, es_empresa = ?, activo = 1 WHERE id = ?");
                $stmt->execute([
                    $nombre_cliente,
                    $email !== '' ? strtolower(trim((string)$email)) : null,
                    $telefono !== '' ? trim((string)$telefono) : null,
                    $direccion !== '' ? $direccion : null,
                    $cuit !== '' ? $cuit : null,
                    $factura_a,
                    $es_empresa,
                    $cliente_id_rel,
                ]);

                if (in_array('cliente_id', $cols_cot_actuales, true) && (int)($cotizacion['cliente_id'] ?? 0) <= 0) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET cliente_id = ? WHERE id = ?");
                    $stmt->execute([$cliente_id_rel, $id]);
                }
            }

        header("Location: cotizacion_detalle.php?id=" . $id);
        exit;

    } catch (Exception $e) {
        $items = !empty($items_nuevos) ? $items_nuevos : $items_originales;
        $error = $e->getMessage();
    }
}

// Obtener productos activos para el selector (incluir tipo_origen si existe)
$cols_prod = $pdo->query("SHOW COLUMNS FROM ecommerce_productos")->fetchAll(PDO::FETCH_COLUMN, 0);
$select_tipo = in_array('tipo_origen', $cols_prod, true) ? 'tipo_origen' : "'fabricacion_propia' as tipo_origen";
$stmt = $pdo->query("
    SELECT id, nombre, tipo_precio, precio_base, categoria_id, $select_tipo
    FROM ecommerce_productos
    WHERE activo = 1
    ORDER BY nombre
");
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Listas de precios activas
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_listas_precios WHERE activo = 1 ORDER BY nombre");
$listas_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT lista_precio_id, producto_id, precio_nuevo, descuento_porcentaje FROM ecommerce_lista_precio_items WHERE activo = 1");
$lista_items_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_items_map = [];
foreach ($lista_items_rows as $row) {
    $lista_items_map[$row['lista_precio_id']][$row['producto_id']] = [
        'precio_nuevo' => (float)$row['precio_nuevo'],
        'descuento_porcentaje' => (float)$row['descuento_porcentaje']
    ];
}

$stmt = $pdo->query("SELECT lista_precio_id, categoria_id, descuento_porcentaje FROM ecommerce_lista_precio_categorias WHERE activo = 1");
$lista_cat_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lista_cat_map = [];
foreach ($lista_cat_rows as $row) {
    $lista_cat_map[$row['lista_precio_id']][$row['categoria_id']] = (float)$row['descuento_porcentaje'];
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>✏️ Editar Cotización <?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></h1>
    </div>
    <a href="cotizacion_detalle.php?id=<?= $id ?>" class="btn btn-secondary">← Volver</a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="formCotizacion" action="cotizacion_editar.php?id=<?= (int)$id ?>">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="items_json" id="items_json" value="">
    <style>
        .attr-option-item {
            border: 2px solid #ddd;
            border-radius: 6px;
            padding: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #fff;
        }
        .attr-option-item.selected {
            border-color: #0d6efd;
            box-shadow: 0 0 0 2px rgba(13,110,253,.2);
            background: #e7f1ff;
        }
        .item-resumen-title {
            font-weight: 600;
            font-size: 1rem;
        }
        .item-resumen-meta {
            font-size: 0.85rem;
            color: #6c757d;
        }
        .item-resumen-attrs .badge {
            font-weight: 500;
        }
    </style>
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">👤 Información del Cliente</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="nombre_cliente" class="form-label">Nombre Completo *</label>
                        <input type="text" class="form-control" id="nombre_cliente" name="nombre_cliente" value="<?= htmlspecialchars($cotizacion['nombre_cliente'] ?? '') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($cotizacion['email'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="telefono" class="form-label">Teléfono</label>
                        <input type="text" class="form-control" id="telefono" name="telefono" value="<?= htmlspecialchars($cotizacion['telefono'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="dni" class="form-label">DNI</label>
                        <input type="text" class="form-control" id="dni" name="dni" value="<?= htmlspecialchars($dni_actual) ?>" placeholder="Opcional en cotización">
                    </div>
                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($direccion_actual) ?>">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" id="es_empresa" name="es_empresa" <?= $es_empresa_actual === 1 ? 'checked' : '' ?> onchange="toggleEmpresaFields()">
                        <label class="form-check-label" for="es_empresa">Es empresa</label>
                    </div>
                    <div id="empresaFields" style="display:none;">
                        <div class="mb-3">
                            <label for="cuit" class="form-label">CUIT</label>
                            <input type="text" class="form-control" id="cuit" name="cuit" value="<?= htmlspecialchars($cuit_actual) ?>" maxlength="13" placeholder="Ej: 30-12345678-9">
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" value="1" id="factura_a" name="factura_a" <?= $factura_a_actual === 1 ? 'checked' : '' ?> onchange="toggleEmpresaFields()">
                            <label class="form-check-label" for="factura_a">Necesita Factura A</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">⚙️ Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="validez_dias" class="form-label">Validez (días)</label>
                        <input type="number" class="form-control" id="validez_dias" name="validez_dias" value="<?= (int)$cotizacion['validez_dias'] ?>" min="1" max="90">
                        <small class="text-muted">Días de validez del presupuesto</small>
                    </div>
                    <div class="mb-3">
                        <label for="lista_precio_id" class="form-label">Lista de Precios</label>
                        <select class="form-select" id="lista_precio_id" name="lista_precio_id" onchange="aplicarListaPrecios()">
                            <option value="">-- Sin lista --</option>
                            <?php foreach ($listas_precios as $lista): ?>
                                <option value="<?= $lista['id'] ?>" <?= (int)($cotizacion['lista_precio_id'] ?? 0) === (int)$lista['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lista['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Aplica descuentos por producto o categoría</small>
                    </div>
                    <div class="mb-3">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea class="form-control" id="observaciones" name="observaciones" rows="4"><?= htmlspecialchars($cotizacion['observaciones'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📦 Items de la Cotización</h5>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-light btn-sm" onclick="actualizarPreciosCotizacion()">🔄 Actualizar precios</button>
                <button type="button" class="btn btn-light btn-sm" onclick="agregarItem()">➕ Agregar Item</button>
            </div>
        </div>
        <div class="card-body">
            <div id="itemsContainer"></div>

            <div class="row mt-4">
                <div class="col-md-8"></div>
                <div class="col-md-4">
                    <table class="table">
                        <tr>
                            <th>Subtotal:</th>
                            <td class="text-end"><span id="subtotal">$0.00</span></td>
                        </tr>
                        <tr>
                            <th><label for="descuento">Descuento:</label></th>
                            <td class="text-end">
                                <input type="number" class="form-control form-control-sm text-end" id="descuento" name="descuento" value="<?= (float)($cotizacion['descuento'] ?? 0) ?>" step="0.01" min="0" onchange="calcularTotales()">
                                <small id="descuento_lista_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="cupon_codigo">Cupón:</label></th>
                            <td class="text-end">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control text-end" id="cupon_codigo" name="cupon_codigo" value="<?= htmlspecialchars($cotizacion['cupon_codigo'] ?? '') ?>" placeholder="Código">
                                    <button class="btn btn-outline-secondary" type="button" onclick="aplicarCupon()">Aplicar</button>
                                </div>
                                <input type="hidden" id="cupon_descuento" name="cupon_descuento" value="<?= (float)($cotizacion['cupon_descuento'] ?? 0) ?>">
                                <small id="cupon_info" class="text-muted d-block"></small>
                            </td>
                        </tr>
                        <tr class="table-primary">
                            <th>TOTAL:</th>
                            <th class="text-end"><span id="total">$0.00</span></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg">💾 Guardar Cambios</button>
        <a href="cotizacion_detalle.php?id=<?= $id ?>" class="btn btn-secondary btn-lg">Cancelar</a>
    </div>
</form>

<style>
    .modal-backdrop.show { opacity: 0.4; backdrop-filter: blur(3px); -webkit-backdrop-filter: blur(3px); }
    .modal-content { border-radius: 12px; }
</style>
<!-- Modal fuera del form para evitar formularios anidados -->
<div class="modal fade" id="itemModal" tabindex="-1" aria-labelledby="itemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header px-4 py-3">
                <h5 class="modal-title" id="itemModalLabel">Agregar item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <form id="itemModalForm" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-7">
                            <label class="form-label">Producto del catálogo</label>
                            <input type="text" class="form-control" list="productos-datalist" id="producto_input_modal" placeholder="Escriba para buscar..." oninput="cargarProductoDesdeModalInput()">
                            <input type="hidden" id="producto_id_modal">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" value="1" id="filtrar_propios" checked>
                                <label class="form-check-label" for="filtrar_propios">Mostrar solo productos de fabricación propia</label>
                            </div>
                            <small class="text-muted">O completá manualmente los campos.</small>
                        </div>
                        <div class="col-md-5">
                            <div id="precio-info-modal" class="alert alert-info mt-4" style="display:none; padding: 8px; margin: 0;"></div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre del Producto *</label>
                            <input type="text" class="form-control" id="nombre_modal" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Descripción</label>
                            <input type="text" class="form-control" id="descripcion_modal">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Ancho (cm)</label>
                            <input type="number" class="form-control" id="ancho_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Alto (cm)</label>
                            <input type="number" class="form-control" id="alto_modal" step="0.01" min="0" onchange="actualizarPrecioItemModal()">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Cant. *</label>
                            <input type="number" class="form-control" id="cantidad_modal" value="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Precio Unit. *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" class="form-control" id="precio_modal" step="0.01" min="0" required onchange="actualizarBasePrecioModal()">
                            </div>
                            <small class="text-muted">Se guarda como precio base, sin atributos.</small>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Desc. %</label>
                            <input type="number" class="form-control" id="descuento_pct_modal" step="0.01" min="0" max="100" value="0">
                        </div>
                    </div>
                    <div id="atributos-container-modal" style="display:none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                        <h6 class="mb-3">🎨 Atributos del Producto</h6>
                        <div id="atributos-list-modal"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer px-4 py-3">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="guardarItemBtn">Guardar item</button>
            </div>
        </div>
    </div>
</div>

<script>
let itemIndex = 0;
let modalEditIndex = null;
let recalculandoPrecioModal = false;
const itemsExistentes = <?= json_encode($items) ?>;
const productos = <?= json_encode($productos) ?>;
const listaItems = <?= json_encode($lista_items_map) ?>;
const listaCategorias = <?= json_encode($lista_cat_map) ?>;
const cotizacionItemsState = new Map();

function clonarItemData(item) {
    try {
        return JSON.parse(JSON.stringify(item ?? {}));
    } catch (e) {
        return { ...(item || {}) };
    }
}

function setItemState(index, itemData) {
    if (index === null || index === undefined) return;
    cotizacionItemsState.set(String(index), clonarItemData(normalizarItemData(itemData || {})));
}

function getItemState(index) {
    return cotizacionItemsState.get(String(index)) || null;
}

function removeItemState(index) {
    cotizacionItemsState.delete(String(index));
}

function leerAtributosDesdeRow(row, index) {
    if (!row) return [];

    const grouped = {};
    const selector = `input[name^="items[${index}][atributos]"], .item-attr-field`;
    row.querySelectorAll(selector).forEach(input => {
        let attrId = input.dataset.attrId || '';
        let key = input.dataset.attrKey || '';

        if ((!attrId || !key) && input.name) {
            const match = input.name.match(/atributos\]\[([^\]]+)\]\[(\w+)\]/);
            if (match) {
                attrId = match[1];
                key = match[2];
            }
        }

        if (!attrId || !key) return;

        grouped[attrId] = grouped[attrId] || { id: attrId, nombre: '', valor: '', costo: 0 };
        if (key === 'costo') {
            grouped[attrId].costo = parseFloat(input.value || 0) || 0;
        } else {
            grouped[attrId][key] = input.value || '';
        }
    });

    return Object.values(grouped).filter(attr => attr.nombre || attr.valor || attr.costo);
}

function productoLabel(p) {
    const precioLabel = p.tipo_precio === 'variable'
        ? '(Precio variable)'
        : '($' + parseFloat(p.precio_base).toFixed(2) + ')';
    return `${p.nombre} ${precioLabel}`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}

function obtenerListaSeleccionada() {
    const select = document.getElementById('lista_precio_id');
    return select ? parseInt(select.value || '0', 10) : 0;
}

function calcularPrecioConLista(productoId, precioBase) {
    const listaId = obtenerListaSeleccionada();
    if (!listaId) {
        return precioBase;
    }

    const item = listaItems?.[listaId]?.[productoId];
    if (item) {
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        const descItem = parseFloat(item.descuento_porcentaje || 0);
        if (precioNuevo > 0) {
            return precioNuevo;
        }
        if (descItem > 0) {
            return precioBase * (1 - descItem / 100);
        }
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    const categoriaId = producto?.categoria_id;
    const descCat = listaCategorias?.[listaId]?.[categoriaId] ?? 0;
    if (descCat > 0) {
        return precioBase * (1 - descCat / 100);
    }

    return precioBase;
}

function calcularDescuentoPorLista(productoId, precioBase) {
    const listaId = obtenerListaSeleccionada();
    const base = parseFloat(precioBase || 0);
    if (!listaId || !isFinite(base) || base <= 0) {
        return 0;
    }

    const item = listaItems?.[listaId]?.[productoId];
    if (item) {
        const descItem = parseFloat(item.descuento_porcentaje || 0);
        if (descItem > 0) {
            return Math.min(100, Math.max(0, descItem));
        }
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        if (precioNuevo > 0 && precioNuevo < base) {
            return Math.min(100, Math.max(0, ((base - precioNuevo) / base) * 100));
        }
    }

    const producto = productos.find(p => String(p.id) === String(productoId));
    const categoriaId = producto?.categoria_id;
    const descCat = parseFloat(listaCategorias?.[listaId]?.[categoriaId] ?? 0);
    if (descCat > 0) {
        return Math.min(100, Math.max(0, descCat));
    }

    return 0;
}

function asegurarDatalistProductos() {
    let existing = document.getElementById('productos-datalist');
    if (existing) existing.remove();
    const datalist = document.createElement('datalist');
    datalist.id = 'productos-datalist';
    const filterPropios = document.getElementById('filtrar_propios');
    const listaProductos = Array.isArray(productos) ? productos.filter(p => {
        try {
            if (!filterPropios) return true;
            if (!filterPropios.checked) return true;
            return (p.tipo_origen || '') === 'fabricacion_propia';
        } catch (e) { return true; }
    }) : [];
    listaProductos.forEach(p => {
        const option = document.createElement('option');
        option.value = productoLabel(p);
        datalist.appendChild(option);
    });
    document.body.appendChild(datalist);
    if (filterPropios) {
        filterPropios.removeEventListener('change', asegurarDatalistProductos);
        filterPropios.addEventListener('change', asegurarDatalistProductos);
    }
}

function agregarItem(itemData = null) {
    if (!itemData) {
        abrirModalItem();
        return;
    }

    itemIndex++;
    const normalizado = normalizarItemData(itemData);
    const html = renderItemResumen(itemIndex, normalizado);
    document.getElementById('itemsContainer').insertAdjacentHTML('beforeend', html);
    setItemState(itemIndex, normalizado);
    calcularTotales();
}

function normalizarItemData(item) {
    const costoAtributosItem = Array.isArray(item.atributos)
        ? item.atributos.reduce((sum, attr) => {
            const costo = parseFloat(attr?.costo_adicional ?? attr?.costo ?? 0) || 0;
            return sum + costo;
        }, 0)
        : 0;

    let precioBaseCalculado = null;
    if (item.precio_base !== undefined && item.precio_base !== null && String(item.precio_base) !== '') {
        precioBaseCalculado = parseFloat(item.precio_base);
    } else if (item.precio_unitario !== undefined && item.precio_unitario !== null && String(item.precio_unitario) !== '') {
        precioBaseCalculado = parseFloat(item.precio_unitario) - costoAtributosItem;
    } else if (item.precio !== undefined && item.precio !== null && String(item.precio) !== '') {
        precioBaseCalculado = parseFloat(item.precio);
    }

    if (!isFinite(precioBaseCalculado) || precioBaseCalculado < 0) {
        precioBaseCalculado = 0;
    }

    return {
        producto_id: item.producto_id || '',
        nombre: item.nombre || '',
        descripcion: item.descripcion || '',
        ancho: item.ancho || '',
        alto: item.alto || '',
        cantidad: item.cantidad || 1,
        precio: precioBaseCalculado.toFixed(2),
        precio_base: precioBaseCalculado.toFixed(2),
        descuento_pct: maxDescuentoInicial(item, precioBaseCalculado),
        atributos: Array.isArray(item.atributos) ? item.atributos.map((a, i) => ({
            id: a.id != null && a.id !== '' ? a.id : i,
            nombre: a.nombre,
            valor: a.valor,
            costo: parseFloat(a.costo_adicional ?? a.costo ?? 0) || 0
        })) : []
    };
}

function maxDescuentoInicial(item, precioBase) {
    const actual = parseFloat(item?.descuento_pct ?? 0);
    if (isFinite(actual) && actual > 0) {
        return Math.min(100, Math.max(0, actual)).toFixed(2);
    }
    const productoId = item?.producto_id || '';
    const fromList = calcularDescuentoPorLista(productoId, precioBase);
    return Math.min(100, Math.max(0, fromList)).toFixed(2);
}

function abrirModalItem(editIndex = null) {
    modalEditIndex = editIndex;
    asegurarDatalistProductos();
    resetearModalItem();

    // Guardar el elemento que tenía el foco antes de abrir el modal
    try {
        const prev = document.activeElement;
        window.__lastFocusedBeforeModal = prev;
    } catch (e) {}

    if (editIndex !== null && editIndex !== undefined) {
        const itemData = obtenerItemDesdeDOM(editIndex);
        if (itemData) {
            document.getElementById('itemModalLabel').textContent = 'Editar item';
            document.getElementById('producto_input_modal').value = itemData.productoLabel || '';
            document.getElementById('producto_id_modal').value = itemData.producto_id || '';
            document.getElementById('nombre_modal').value = itemData.nombre || '';
            document.getElementById('descripcion_modal').value = itemData.descripcion || '';
            document.getElementById('ancho_modal').value = itemData.ancho || '';
            document.getElementById('alto_modal').value = itemData.alto || '';
            document.getElementById('cantidad_modal').value = itemData.cantidad || 1;
            const precioInput = document.getElementById('precio_modal');
            precioInput.value = itemData.precio || '';
            precioInput.dataset.base = itemData.precio_base || itemData.precio || '';
            const descuentoInput = document.getElementById('descuento_pct_modal');
            if (descuentoInput) descuentoInput.value = parseFloat(itemData.descuento_pct || 0).toFixed(2);
            if (itemData.producto_id) {
                cargarAtributosProductoModal(itemData.producto_id, itemData.atributos || []);
            }
        }
    } else {
        document.getElementById('itemModalLabel').textContent = 'Agregar item';
    }

    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
    if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        try { modalEl._previouslyFocused = window.__lastFocusedBeforeModal || null; } catch(e){}
        try {
            if (!modalEl._bsListenersAdded) {
                modalEl.addEventListener('hide.bs.modal', function() {
                    try {
                        const active = document.activeElement;
                        if (active && modalEl.contains(active)) {
                            const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
                            if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
                        }
                    } catch(e){}
                });
                modalEl.addEventListener('hidden.bs.modal', function() {
                    try { modalEl.setAttribute('aria-hidden', 'true'); } catch(e){}
                    try { if ('inert' in modalEl) modalEl.inert = true; } catch(e){}
                });
                modalEl._bsListenersAdded = true;
            }
        } catch(e){}
        modal.show();
        return;
    }
    modalEl.classList.add('show');
    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    try { if ('inert' in modalEl) modalEl.inert = false; } catch(e){}
    document.body.classList.add('modal-open');
    if (!document.querySelector('.modal-backdrop')) {
        const backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        document.body.appendChild(backdrop);
    }
}

function resetearModalItem() {
    const form = document.getElementById('itemModalForm');
    if (form) form.reset();
    const productoIdInput = document.getElementById('producto_id_modal');
    if (productoIdInput) productoIdInput.value = '';
    const precioInput = document.getElementById('precio_modal');
    if (precioInput) {
        precioInput.value = '';
        precioInput.dataset.base = '';
    }
    const info = document.getElementById('precio-info-modal');
    if (info) info.style.display = 'none';
    const attrsContainer = document.getElementById('atributos-list-modal');
    if (attrsContainer) attrsContainer.innerHTML = '';
    const attrsWrapper = document.getElementById('atributos-container-modal');
    if (attrsWrapper) attrsWrapper.style.display = 'none';
}

function obtenerProductoPorTexto(texto) {
    const textoNormalizado = texto.toLowerCase();
    return productos.find(p => productoLabel(p).toLowerCase() === textoNormalizado);
}

function cargarProductoDesdeModalInput() {
    const input = document.getElementById('producto_input_modal');
    const texto = input.value.trim();
    const producto = obtenerProductoPorTexto(texto);

    if (!producto) {
        document.getElementById('producto_id_modal').value = '';
        if (texto === '') {
            resetearModalItem();
        }
        return;
    }

    document.getElementById('producto_id_modal').value = producto.id;
    aplicarProductoModal(producto);
}

function aplicarProductoModal(producto) {
    document.getElementById('nombre_modal').value = producto.nombre;
    cargarAtributosProductoModal(producto.id);

    const precioInput = document.getElementById('precio_modal');
    const info = document.getElementById('precio-info-modal');

    if (producto.tipo_precio === 'fijo') {
        const precioBase = parseFloat(producto.precio_base || 0);
        precioInput.dataset.base = precioBase.toFixed(2);
        precioInput.value = precioBase.toFixed(2);
        const descInput = document.getElementById('descuento_pct_modal');
        if (descInput && (modalEditIndex === null || modalEditIndex === undefined)) {
            descInput.value = calcularDescuentoPorLista(producto.id, precioBase).toFixed(2);
        }
        if (info) {
            info.innerHTML = '✓ Precio fijo del producto';
            info.style.display = 'block';
        }
    } else {
        precioInput.value = '';
        precioInput.dataset.base = '';
        if (info) {
            info.innerHTML = '⚠️ Ingrese ancho y alto para calcular precio';
            info.style.display = 'block';
        }
    }
}

function cargarAtributosProductoModal(productoId, valoresExistentes = []) {
    fetch(`productos_atributos.php?accion=obtener&producto_id=${productoId}`)
        .then(response => response.json())
        .then(data => {
            const atributosContainer = document.getElementById('atributos-list-modal');
            atributosContainer.innerHTML = '';

            if (data.atributos && data.atributos.length > 0) {
                document.getElementById('atributos-container-modal').style.display = 'block';

                data.atributos.forEach(attr => {
                    const requerido = attr.es_obligatorio ? 'required' : '';
                    let inputHTML = '';

                    if (attr.tipo === 'text') {
                        inputHTML = `<input type="text" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'number') {
                        inputHTML = `<input type="number" class="form-control form-control-sm mb-2" data-attr-valor="${attr.id}" step="0.01" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">`;
                    } else if (attr.tipo === 'color') {
                        const inputId = `modal_attr_${attr.id}`;
                        const previewId = `modal_color_preview_${attr.id}`;
                        inputHTML = `
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <input type="color" class="form-control form-control-color" id="${inputId}" data-attr-valor="${attr.id}" value="#000000" ${requerido} oninput="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, 0, this.value)">
                                <div class="border rounded" id="${previewId}" style="width: 28px; height: 28px; background-color: #000000;"></div>
                            </div>
                        `;
                    } else if (attr.tipo === 'select') {
                        const opciones = Array.isArray(attr.opciones) && attr.opciones.length > 0
                            ? attr.opciones.map(o => ({
                                valor: o.nombre,
                                color: o.color,
                                imagen: o.imagen,
                                costo: o.costo_adicional
                            }))
                            : (attr.valores ? attr.valores.split(',').map(v => ({ valor: v.trim() })) : []);

                        inputHTML = `
                            <div class="d-flex gap-2 flex-wrap mb-2">
                                ${opciones.map((o, i) => {
                                    const hasColor = o.color && /^#[0-9A-Fa-f]{6}$/.test(o.color);
                                    const colorBox = hasColor ? `<div class="rounded" style="width: 80px; height: 80px; background-color: ${o.color}; border: 1px solid #ddd;"></div>` : '';
                                    const imgTag = o.imagen ? `<img src="../uploads/atributos/${o.imagen}" alt="${o.valor}" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block;">` : '';
                                    const placeholder = !hasColor && !o.imagen ? `<div class="d-flex align-items-center justify-content-center bg-light rounded" style="width: 80px; height: 80px;"><small class="text-center text-muted">${o.valor}</small></div>` : '';
                                    const costoBadge = o.costo > 0 ? `<span class="badge bg-success position-absolute" style="top: -8px; right: -8px;">+$${parseFloat(o.costo).toFixed(2)}</span>` : '';
                                    const label = `<small class="d-block text-center mt-1 text-muted">${o.valor}</small>`;
                                    return `
                                        <div class="position-relative">
                                            <label class="cursor-pointer position-relative" style="cursor: pointer;">
                                                <input type="radio" name="modal_attr_${attr.id}" value="${o.valor}" class="d-none attr-radio" data-attr-id="${attr.id}" data-costo="${o.costo || 0}" ${requerido} onchange="actualizarCostoAtributoModal(${attr.id}, ${attr.costo_adicional}, this.dataset.costo, this.value); marcarOpcionAtributo(this);">
                                                <div class="attr-option position-relative" style="cursor: pointer; border: 2px solid #ddd; border-radius: 6px; padding: 4px; transition: all 0.2s ease; background: #fff;">
                                                    ${colorBox || imgTag || placeholder}
                                                    ${costoBadge}
                                                    ${label}
                                                </div>
                                            </label>
                                        </div>
                                    `;
                                }).join('')}
                            </div>
                        `;
                    }

                    var attrHTML = '<div class="mb-2 modal-attr-item" data-attr-id="' + attr.id + '" data-attr-nombre="' + attr.nombre + '" data-required="' + (attr.es_obligatorio ? 1 : 0) + '">' +
                        '<label class="form-label small mb-1">' + attr.nombre +
                        (attr.costo_adicional > 0 ? '<span class="badge bg-warning text-dark">+$' + parseFloat(attr.costo_adicional).toFixed(2) + '</span>' : '') +
                        '</label>' + inputHTML +
                        '<input type="hidden" id="modal_attr_costo_' + attr.id + '" value="0" data-base="' + attr.costo_adicional + '">' +
                        '</div>';
                    atributosContainer.insertAdjacentHTML('beforeend', attrHTML);

                    if (attr.tipo === 'color') {
                        const colorInput = document.getElementById(`modal_attr_${attr.id}`);
                        const preview = document.getElementById(`modal_color_preview_${attr.id}`);
                        if (colorInput && preview) {
                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                            colorInput.addEventListener('input', updatePreview);
                            updatePreview();
                        }
                    }
                });

                if (Array.isArray(valoresExistentes) && valoresExistentes.length > 0) {
                    const normalizarTxt = (txt) => String(txt || '').trim().toLowerCase();

                    valoresExistentes.forEach(v => {
                        let wrapper = null;

                        if (v && v.id !== undefined && v.id !== null && String(v.id).trim() !== '') {
                            wrapper = document.querySelector(`.modal-attr-item[data-attr-id="${String(v.id).trim()}"]`);
                        }

                        if (!wrapper && v && v.nombre) {
                            const nombreBuscado = normalizarTxt(v.nombre);
                            const wrappers = Array.from(document.querySelectorAll('.modal-attr-item'));
                            wrapper = wrappers.find(w => normalizarTxt(w.dataset.attrNombre) === nombreBuscado) || null;
                        }

                        if (!wrapper) return;

                        const attrId = wrapper.dataset.attrId;
                        const valorExistente = String(v.valor ?? '').trim();

                        const radios = Array.from(wrapper.querySelectorAll('input[type="radio"]'));
                        if (radios.length > 0) {
                            const radio = radios.find(r => normalizarTxt(r.value) === normalizarTxt(valorExistente));
                            if (radio) {
                                radio.checked = true;
                                marcarOpcionAtributo(radio);
                            }
                        }

                        const input = wrapper.querySelector(`[data-attr-valor="${attrId}"]`);
                        if (input) {
                            input.value = valorExistente;
                            if (input.type === 'color') {
                                const preview = document.getElementById(`modal_color_preview_${attrId}`);
                                if (preview) {
                                    preview.style.backgroundColor = valorExistente || '#000000';
                                }
                            }
                        }

                        const baseInput = document.getElementById(`modal_attr_costo_${attrId}`);
                        const baseCosto = baseInput ? parseFloat(baseInput.dataset.base || 0) : 0;
                        const costoExistente = parseFloat(v.costo ?? v.costo_adicional ?? 0) || 0;
                        actualizarCostoAtributoModal(attrId, baseCosto || 0, costoExistente, valorExistente);
                    });
                }
            } else {
                document.getElementById('atributos-container-modal').style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error cargando atributos:', error);
            document.getElementById('atributos-container-modal').style.display = 'none';
        });
}

function actualizarCostoAtributoModal(attrId, costoBase, costoOpcion, valorSeleccionado) {
    const inputCosto = document.getElementById(`modal_attr_costo_${attrId}`);
    if (!inputCosto) return;

    const base = parseFloat(costoBase || 0);
    const opcion = parseFloat(costoOpcion || 0);
    const tieneValor = valorSeleccionado !== undefined && valorSeleccionado !== null && String(valorSeleccionado).trim() !== '';

    if (!tieneValor) {
        inputCosto.value = '0';
    } else if (opcion > 0) {
        inputCosto.value = opcion.toFixed(2);
    } else if (base > 0) {
        inputCosto.value = base.toFixed(2);
    } else {
        inputCosto.value = '0';
    }
}

async function actualizarPrecioItemModal(opciones = {}) {
    const forzarValidacion = !!opciones.forzarValidacion;
    const productoId = document.getElementById('producto_id_modal')?.value;
    if (!productoId) return true;

    const producto = productos.find(p => String(p.id) === String(productoId));
    if (producto?.tipo_precio !== 'variable') {
        return true;
    }

    const ancho = parseFloat(document.getElementById('ancho_modal').value || 0);
    const alto = parseFloat(document.getElementById('alto_modal').value || 0);

    if (!(ancho > 0 && alto > 0)) {
        if (forzarValidacion) {
            throw new Error('Para este producto debés ingresar ancho y alto válidos para calcular el precio.');
        }
        return false;
    }

    recalculandoPrecioModal = true;
    try {
        const response = await fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`);
        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        const precioBase = parseFloat(data.precio || 0);
        if (!isFinite(precioBase) || precioBase <= 0) {
            throw new Error('No se pudo calcular un precio válido para esas medidas.');
        }

        const precioInput = document.getElementById('precio_modal');
        precioInput.dataset.base = precioBase.toFixed(2);
        precioInput.value = precioBase.toFixed(2);
        const descInput = document.getElementById('descuento_pct_modal');
        if (descInput && (modalEditIndex === null || modalEditIndex === undefined)) {
            descInput.value = calcularDescuentoPorLista(productoId, precioBase).toFixed(2);
        }
        const info = document.getElementById('precio-info-modal');
        if (info) {
            info.innerHTML = '✓ ' + (data.precio_info || 'Precio actualizado por medidas');
            info.style.display = 'block';
        }

        return true;
    } catch (error) {
        console.error('Error recalculando precio por medidas:', error);
        if (forzarValidacion) {
            throw error;
        }
        return false;
    } finally {
        recalculandoPrecioModal = false;
    }
}

function actualizarBasePrecioModal() {
    const precioInput = document.getElementById('precio_modal');
    if (!precioInput) return;
    precioInput.dataset.base = precioInput.value || '';
}

function obtenerItemDesdeDOM(index) {
    const row = document.getElementById(`item_${index}`);
    if (!row) return getItemState(index);

    const state = getItemState(index) || {};
    const getVal = (id, fallback = '') => {
        const el = document.getElementById(id);
        return el ? el.value : fallback;
    };

    const productoId = getVal(`producto_id_${index}`, state.producto_id || '');
    const producto = productos.find(p => String(p.id) === String(productoId));
    const precioInput = document.getElementById(`precio_${index}`);
    const precioBase = getVal(`precio_base_${index}`, precioInput?.dataset?.base || state.precio_base || precioInput?.value || state.precio || '');
    const atributos = leerAtributosDesdeRow(row, index);

    return normalizarItemData({
        ...state,
        producto_id: productoId,
        productoLabel: producto ? productoLabel(producto) : (state.productoLabel || ''),
        nombre: getVal(`nombre_${index}`, state.nombre || ''),
        descripcion: getVal(`descripcion_${index}`, state.descripcion || ''),
        ancho: getVal(`ancho_${index}`, state.ancho || ''),
        alto: getVal(`alto_${index}`, state.alto || ''),
        cantidad: getVal(`cantidad_${index}`, state.cantidad || 1),
        precio: getVal(`precio_${index}`, state.precio || 0),
        precio_base: precioBase,
        descuento_pct: getVal(`descuento_pct_${index}`, state.descuento_pct || 0),
        atributos: atributos.length > 0 ? atributos : (Array.isArray(state.atributos) ? state.atributos : [])
    });
}

function obtenerAtributosDesdeModal() {
    const atributos = [];
    let faltan = false;

    document.querySelectorAll('#atributos-list-modal .modal-attr-item').forEach(wrapper => {
        const attrId = wrapper.dataset.attrId;
        const nombre = wrapper.dataset.attrNombre || '';
        const requerido = wrapper.dataset.required === '1';
        let valor = '';

        const radio = wrapper.querySelector('input[type="radio"]:checked');
        if (radio) {
            valor = radio.value;
        } else {
            const input = wrapper.querySelector(`[data-attr-valor="${attrId}"]`);
            if (input) valor = input.value;
        }

        if (requerido && !valor) {
            faltan = true;
        }

        const costoInput = document.getElementById(`modal_attr_costo_${attrId}`);
        const costo = parseFloat(costoInput?.value || 0) || 0;

        if (valor) {
            atributos.push({ id: attrId, nombre, valor, costo });
        }
    });

    if (faltan) {
        alert('Completa los atributos obligatorios antes de guardar.');
        return null;
    }

    return atributos;
}

function renderItemResumen(index, itemData) {
    const atributos = itemData.atributos || [];
    const atributosResumen = atributos.length
        ? atributos.map(a => `<span class="badge bg-light text-dark me-1">${escapeHtml(a.nombre)}: ${escapeHtml(a.valor)}${a.costo > 0 ? ` (+$${parseFloat(a.costo).toFixed(2)})` : ''}</span>`).join('')
        : '<span class="text-muted">Sin atributos</span>';

    const dimensiones = (itemData.ancho && itemData.alto)
        ? `${escapeHtml(itemData.ancho)} x ${escapeHtml(itemData.alto)} cm`
        : '—';
    const descuentoPct = Math.max(0, Math.min(100, parseFloat(itemData.descuento_pct || 0) || 0));
    const precioBase = parseFloat(itemData.precio_base ?? itemData.precio ?? 0) || 0;
    const precioVisible = parseFloat(itemData.precio || 0) || 0;
    const nombre = escapeHtml(itemData.nombre || 'Producto sin nombre');
    const descripcion = escapeHtml(itemData.descripcion || '');

    const html = `
        <div class="card mb-3 item-row" id="item_${index}">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div class="flex-grow-1">
                        <div class="item-resumen-title">${nombre}</div>
                        ${descripcion ? `<div class="item-resumen-meta">${descripcion}</div>` : ''}
                        <div class="item-resumen-meta">Cantidad: <strong>${itemData.cantidad}</strong> · Medidas: <strong>${dimensiones}</strong></div>
                        <div class="item-resumen-meta">Precio base: <strong>$${precioVisible.toFixed(2)}</strong></div>
                        <div class="item-resumen-meta">Descuento item: <strong id="descuento_pct_text_${index}">${descuentoPct.toFixed(2)}%</strong></div>
                        <div class="item-resumen-attrs mt-2">${atributosResumen}</div>
                    </div>
                    <div class="text-end">
                        <div class="badge bg-primary-subtle text-primary border" style="font-size: 0.95rem;">
                            Subtotal: $<span class="item-subtotal-text" id="item_subtotal_text_${index}">0.00</span>
                        </div>
                        <div class="mt-2">
                            <div class="input-group input-group-sm mb-2" style="max-width:160px; margin-left:auto;">
                                <span class="input-group-text">Desc %</span>
                                <input type="number" class="form-control text-end" id="descuento_pct_input_${index}" value="${descuentoPct.toFixed(2)}" min="0" max="100" step="0.01" onchange="actualizarDescuentoItem(${index}, this.value)">
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="abrirModalItem(${index})">✏️ Editar</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="eliminarItem(${index})">🗑️ Eliminar</button>
                        </div>
                    </div>
                </div>

                <input type="hidden" class="item-nombre" id="nombre_${index}" name="items[${index}][nombre]" value="${escapeAttr(itemData.nombre || '')}">
                <input type="hidden" id="descripcion_${index}" name="items[${index}][descripcion]" value="${escapeAttr(itemData.descripcion || '')}">
                <input type="hidden" class="item-ancho" id="ancho_${index}" name="items[${index}][ancho]" value="${escapeAttr(itemData.ancho || '')}">
                <input type="hidden" class="item-alto" id="alto_${index}" name="items[${index}][alto]" value="${escapeAttr(itemData.alto || '')}">
                <input type="hidden" class="item-cantidad" id="cantidad_${index}" name="items[${index}][cantidad]" value="${itemData.cantidad || 1}">
                <input type="hidden" class="item-precio" id="precio_${index}" name="items[${index}][precio]" value="${precioVisible.toFixed(2)}" data-base="${precioBase.toFixed(2)}">
                <input type="hidden" class="item-precio-base" id="precio_base_${index}" value="${precioBase.toFixed(2)}">
                <input type="hidden" class="item-descuento-pct" id="descuento_pct_${index}" name="items[${index}][descuento_pct]" value="${descuentoPct.toFixed(2)}">
                <input type="hidden" id="producto_id_${index}" name="items[${index}][producto_id]" value="${itemData.producto_id || ''}">
                <input type="text" class="form-control item-subtotal" id="subtotal_${index}" readonly style="display:none;">
                <div id="precio-info-${index}" style="display:none;"></div>
                ${atributos.map(a => `
                    <input type="hidden" class="item-attr-field" data-attr-id="${escapeAttr(a.id)}" data-attr-key="nombre" name="items[${index}][atributos][${a.id}][nombre]" value="${escapeAttr(a.nombre)}">
                    <input type="hidden" class="item-attr-field" data-attr-id="${escapeAttr(a.id)}" data-attr-key="valor" name="items[${index}][atributos][${a.id}][valor]" value="${escapeAttr(a.valor)}">
                    <input type="hidden" class="item-attr-field" data-attr-id="${escapeAttr(a.id)}" data-attr-key="costo" name="items[${index}][atributos][${a.id}][costo]" value="${parseFloat(a.costo || 0).toFixed(2)}">
                `).join('')}
            </div>
        </div>
    `;

    return html;
}

async function guardarItemDesdeModal() {
    const guardarBtn = document.getElementById('guardarItemBtn');
    if (recalculandoPrecioModal) {
        alert('Esperá un momento, se está recalculando el precio por medidas.');
        return;
    }

    if (guardarBtn) guardarBtn.disabled = true;
    try {
    const productoIdActual = document.getElementById('producto_id_modal')?.value || '';
    const productoActual = productos.find(p => String(p.id) === String(productoIdActual));
    if (productoActual?.tipo_precio === 'variable') {
        await actualizarPrecioItemModal({ forzarValidacion: true });
    }

    const form = document.getElementById('itemModalForm') || document.querySelector('#itemModal form');
    if (form && !form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const atributos = obtenerAtributosDesdeModal();
    if (atributos === null) return;

    const cantidadValue = parseInt(document.getElementById('cantidad_modal').value || '0', 10);
    const precioRaw = String(document.getElementById('precio_modal').value || '').replace(',', '.');
    const precioValue = parseFloat(precioRaw);

    if (!isFinite(cantidadValue) || cantidadValue <= 0) {
        alert('Ingresá una cantidad válida.');
        return;
    }
    if (!isFinite(precioValue) || precioValue <= 0) {
        alert('Ingresá un precio válido.');
        return;
    }

    const itemData = {
        producto_id: document.getElementById('producto_id_modal').value || '',
        nombre: document.getElementById('nombre_modal').value.trim(),
        descripcion: document.getElementById('descripcion_modal').value.trim(),
        ancho: document.getElementById('ancho_modal').value,
        alto: document.getElementById('alto_modal').value,
        cantidad: cantidadValue,
        precio: precioValue.toFixed(2),
        precio_base: (document.getElementById('precio_modal')?.dataset?.base || precioValue.toFixed(2)),
        descuento_pct: Math.max(0, Math.min(100, parseFloat(document.getElementById('descuento_pct_modal')?.value || 0) || 0)),
        atributos
    };

    if (!itemData.nombre || !itemData.cantidad || !itemData.precio) {
        alert('Completa los campos obligatorios del item.');
        return;
    }

    try {
        let index = modalEditIndex;
        const itemsContainer = document.getElementById('itemsContainer');
        if (!itemsContainer) {
            alert('No se encontró el contenedor de items.');
            return;
        }
        // Si index es null o undefined, es un nuevo item
        const itemNormalizado = normalizarItemData(itemData);

        if (index === null || index === undefined) {
            // Buscar el mayor índice actual
            let maxIndex = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                const id = row.id;
                const m = id.match(/item_(\d+)/);
                if (m && m[1]) {
                    const idx = parseInt(m[1], 10);
                    if (idx > maxIndex) maxIndex = idx;
                }
            });
            index = maxIndex + 1;
            itemIndex = index;
            const html = renderItemResumen(index, itemNormalizado);
            itemsContainer.insertAdjacentHTML('beforeend', html);
        } else {
            const html = renderItemResumen(index, itemNormalizado);
            const row = document.getElementById(`item_${index}`);
            if (row) {
                // Reemplazar el nodo por uno nuevo para evitar problemas de referencias
                const newNode = document.createElement('div');
                newNode.innerHTML = html;
                const newItem = newNode.firstElementChild;
                itemsContainer.replaceChild(newItem, row);
            } else {
                itemsContainer.insertAdjacentHTML('beforeend', html);
            }
        }

        setItemState(index, itemNormalizado);
    } catch (err) {
        console.error('Error guardando item:', err);
        alert('No se pudo guardar el item.');
        return;
    }

    try {
        calcularTotales();
    } catch (err) {
        console.error('Error calculando totales:', err);
    }
    modalEditIndex = null;
    const modalEl = document.getElementById('itemModal');
    if (!modalEl) return;
    if (window.bootstrap && bootstrap.Modal) {
        const modal = bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl);
        // Asegurar que modal no permanezca inert/aria-hidden al reabrir
        try { modalEl.removeAttribute('aria-hidden'); } catch(e){}
        try { if ('inert' in modalEl) modalEl.inert = false; } catch(e){}
        // Restaurar/limpiar foco antes de cerrar
        try {
            const active = document.activeElement;
            if (active && modalEl.contains(active)) {
                const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
                if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
            }
        } catch(e){}
        if (modal) modal.hide();
        return;
    }
    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    try {
        const active = document.activeElement;
        if (active && modalEl.contains(active)) {
            const prev = modalEl._previouslyFocused || window.__lastFocusedBeforeModal;
            if (prev && typeof prev.focus === 'function') prev.focus(); else active.blur();
        }
    } catch(e){}
    // aplicar aria-hidden/inert en el siguiente tick, tras permitir que blur/focus se efectúe
    setTimeout(() => {
        try { modalEl.setAttribute('aria-hidden', 'true'); } catch(e){}
        try { if ('inert' in modalEl) modalEl.inert = true; } catch(e){}
    }, 0);
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.modal-backdrop').forEach(b => b.remove());
    } catch (err) {
        alert(err.message || 'No se pudo guardar el item.');
    } finally {
        if (guardarBtn) guardarBtn.disabled = false;
    }
}

function eliminarItem(index) {
    try {
        const el = document.getElementById('item_' + index);
        if (el && typeof el.remove === 'function') el.remove();
        removeItemState(index);
    } catch(e) { console.error('eliminarItem error', e); }
    try { calcularTotales(); } catch(e) { console.error('calcularTotales error', e); }
}

function actualizarDescuentoItem(index, valor) {
    const pct = Math.max(0, Math.min(100, parseFloat(valor || 0) || 0));
    const hidden = document.getElementById(`descuento_pct_${index}`);
    const input = document.getElementById(`descuento_pct_input_${index}`);
    const text = document.getElementById(`descuento_pct_text_${index}`);
    if (hidden) hidden.value = pct.toFixed(2);
    if (input) input.value = pct.toFixed(2);
    if (text) text.textContent = pct.toFixed(2) + '%';

    const current = getItemState(index);
    if (current) {
        current.descuento_pct = pct.toFixed(2);
        setItemState(index, current);
    }

    calcularTotales();
}

function calcularTotales() {
    let subtotal = 0;
    let descuentoListaTotal = 0;

    document.querySelectorAll('.item-row').forEach(row => {
        const index = obtenerIndexDesdeRow(row);
        const cantidad = parseFloat(row.querySelector('.item-cantidad')?.value || 0);
        const precioInput = row.querySelector('.item-precio');
        const precioBaseHidden = index !== null ? document.getElementById(`precio_base_${index}`) : null;
        if (precioInput && (precioInput.dataset.base === undefined || precioInput.dataset.base === '')) {
            precioInput.dataset.base = precioBaseHidden?.value || precioInput.value || '';
        }
        const precioBaseData = parseFloat(precioInput?.dataset.base ?? '');
        const precioBaseHiddenValue = parseFloat(precioBaseHidden?.value ?? '');
        const precioBaseValue = parseFloat(precioInput?.value ?? '');
        const precioBase = Number.isFinite(precioBaseData)
            ? precioBaseData
            : (Number.isFinite(precioBaseHiddenValue)
                ? precioBaseHiddenValue
                : (Number.isFinite(precioBaseValue) ? precioBaseValue : 0));
        let costoAtributos = 0;
        row.querySelectorAll('.item-attr-field[data-attr-key="costo"], input[name*="[atributos]"][name$="[costo]"]').forEach(input => {
            const costo = parseFloat(input.value ?? '');
            costoAtributos += Number.isFinite(costo) ? costo : 0;
        });

        if (precioBaseHidden) {
            precioBaseHidden.value = precioBase.toFixed(2);
        }

        const cantidadSafe = Number.isFinite(cantidad) ? cantidad : 0;
        const subtotalItem = cantidadSafe * (precioBase + costoAtributos);

        const subtotalInput = row.querySelector('.item-subtotal');
        if (subtotalInput) {
            subtotalInput.value = subtotalItem.toFixed(2);
        }
        const subtotalText = row.querySelector('.item-subtotal-text');
        if (subtotalText) {
            subtotalText.textContent = subtotalItem.toFixed(2);
        }

        subtotal += Number.isFinite(subtotalItem) ? subtotalItem : 0;

        const descuentoPct = parseFloat(row.querySelector('.item-descuento-pct')?.value || 0) || 0;
        const descuentoItem = (precioBase + costoAtributos) * cantidadSafe * (descuentoPct / 100);
        descuentoListaTotal += descuentoItem;
    });

    const descuentoInput = document.getElementById('descuento');
    const descuentoInfo = document.getElementById('descuento_lista_info');

    if (descuentoInput) {
        descuentoInput.value = descuentoListaTotal.toFixed(2);
        if (descuentoInfo) {
            descuentoInfo.textContent = `Base: $${subtotal.toFixed(2)} | Descuento items: $${descuentoListaTotal.toFixed(2)}`;
        }
    } else if (descuentoInfo) {
        descuentoInfo.textContent = '';
    }

    const descuento = parseFloat(descuentoInput?.value || 0);
    const descuentoCupon = parseFloat(document.getElementById('cupon_descuento')?.value || 0);
    const total = subtotal - descuento - descuentoCupon;

    document.getElementById('subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('total').textContent = '$' + total.toFixed(2);
}

function aplicarCupon() {
    const codigo = document.getElementById('cupon_codigo')?.value?.trim() || '';
    const info = document.getElementById('cupon_info');
    const descuentoInput = document.getElementById('cupon_descuento');
    const subtotalText = document.getElementById('subtotal')?.textContent || '$0';
    const subtotal = parseFloat(subtotalText.replace(/[^0-9.]/g, '')) || 0;

    if (!codigo) {
        if (info) info.textContent = 'Ingresá un cupón.';
        if (descuentoInput) descuentoInput.value = '0';
        calcularTotales();
        return;
    }

    fetch(`cupones_validar.php?codigo=${encodeURIComponent(codigo)}&subtotal=${subtotal}`)
        .then(r => r.json())
        .then(data => {
            if (!data.valido) {
                if (info) info.textContent = data.mensaje || 'Cupón inválido.';
                if (descuentoInput) descuentoInput.value = '0';
            } else {
                if (descuentoInput) descuentoInput.value = data.descuento || 0;
                if (info) info.textContent = data.mensaje
                    ? `${data.mensaje} (-$${Number(data.descuento || 0).toFixed(2)})`
                    : `Descuento aplicado: $${Number(data.descuento || 0).toFixed(2)}`;
            }
            calcularTotales();
        })
        .catch(() => {
            if (info) info.textContent = 'No se pudo validar el cupón.';
        });
}

function marcarOpcionAtributo(radio) {
    if (!radio || !radio.name) return;
    const label = radio.closest('label');
    if (!label) return;
    const divPadre = label.parentElement;
    if (!divPadre || !divPadre.parentElement) return;
    const contenedorOpciones = divPadre.parentElement;

    contenedorOpciones.querySelectorAll('input[type="radio"][name="' + radio.name + '"]').forEach(r => {
        const l = r.closest('label');
        if (l) {
            const divOpcion = l.querySelector('.attr-option');
            if (divOpcion) {
                divOpcion.style.borderColor = '#ddd';
                divOpcion.style.boxShadow = 'none';
                divOpcion.style.background = '#fff';
            }
        }
    });

    if (radio.checked) {
        const divOpcion = label.querySelector('.attr-option');
        if (divOpcion) {
            divOpcion.style.borderColor = '#0d6efd';
            divOpcion.style.boxShadow = '0 0 0 2px rgba(13,110,253,.2)';
            divOpcion.style.background = '#e7f1ff';
        }
    }
}

function aplicarListaPrecios() {
    document.querySelectorAll('.item-row').forEach(row => {
        const index = obtenerIndexDesdeRow(row);
        if (!index) return;
        const productoId = document.getElementById(`producto_id_${index}`)?.value;
        const precioInput = document.getElementById(`precio_${index}`);
        const precioBase = parseFloat(precioInput?.dataset.base || precioInput?.value || 0) || 0;
        if (!productoId || precioBase <= 0) return;
        const pct = calcularDescuentoPorLista(productoId, precioBase);
        const hidden = document.getElementById(`descuento_pct_${index}`);
        const input = document.getElementById(`descuento_pct_input_${index}`);
        const text = document.getElementById(`descuento_pct_text_${index}`);
        if (hidden) hidden.value = pct.toFixed(2);
        if (input) input.value = pct.toFixed(2);
        if (text) text.textContent = pct.toFixed(2) + '%';
    });
    calcularTotales();
}

function obtenerIndexDesdeRow(row) {
    const id = row?.id || '';
    const match = id.match(/item_(\d+)/);
    return match ? parseInt(match[1], 10) : null;
}

function actualizarPreciosCotizacion() {
    const filas = Array.from(document.querySelectorAll('.item-row'));
    if (filas.length === 0) {
        return;
    }

    const tareas = filas.map(row => {
        const index = obtenerIndexDesdeRow(row);
        if (!index) return Promise.resolve();

        const productoId = document.getElementById(`producto_id_${index}`)?.value;
        if (!productoId) return Promise.resolve();

        const producto = productos.find(p => String(p.id) === String(productoId));
        if (!producto) return Promise.resolve();

        if (producto.tipo_precio === 'fijo') {
            const precioBase = parseFloat(producto.precio_base || 0);
            const precioInput = document.getElementById(`precio_${index}`);
            if (precioInput) {
                precioInput.dataset.base = precioBase.toFixed(2);
                precioInput.value = precioBase.toFixed(2);
            }
            return Promise.resolve();
        }

        const ancho = parseFloat(document.getElementById(`ancho_${index}`)?.value || 0);
        const alto = parseFloat(document.getElementById(`alto_${index}`)?.value || 0);
        if (ancho <= 0 || alto <= 0) {
            return Promise.resolve();
        }

        return fetch(`cotizacion_producto_precio.php?producto_id=${productoId}&ancho=${ancho}&alto=${alto}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    return;
                }
                const precioBase = parseFloat(data.precio || 0);
                const precioInput = document.getElementById(`precio_${index}`);
                if (precioInput) {
                    precioInput.dataset.base = precioBase.toFixed(2);
                    precioInput.value = precioBase.toFixed(2);
                }
            })
            .catch(() => {});
    });

    Promise.all(tareas).then(() => {
        calcularTotales();
    });
}

document.addEventListener('change', function(e) {
    const radio = e.target;
    if (radio && radio.matches('input[type="radio"].attr-radio')) {
        marcarOpcionAtributo(radio);
    }
});

function collectItemsForSubmit() {
    const items = [];
    const formCot = document.getElementById('formCotizacion');
    const container = formCot ? formCot.querySelector('#itemsContainer') : null;
    const rows = container ? container.querySelectorAll('.item-row') : document.querySelectorAll('.item-row');

    rows.forEach(function(row) {
        const idMatch = (row.id || '').match(/item_(\d+)/);
        if (!idMatch) return;

        const idx = idMatch[1];
        const item = obtenerItemDesdeDOM(idx);
        if (!item) return;

        const atributos = Array.isArray(item.atributos)
            ? item.atributos.map(attr => ({
                id: attr.id ?? '',
                nombre: String(attr.nombre || '').trim(),
                valor: String(attr.valor || '').trim(),
                costo: parseFloat(String(attr.costo ?? attr.costo_adicional ?? 0).replace(',', '.')) || 0
            })).filter(attr => attr.nombre || attr.valor || attr.costo)
            : [];

        items.push({
            producto_id: item.producto_id || null,
            nombre: String(item.nombre || '').trim(),
            descripcion: String(item.descripcion || '').trim(),
            ancho: item.ancho || null,
            alto: item.alto || null,
            cantidad: parseInt(item.cantidad || 0, 10),
            precio: parseFloat(String(item.precio || 0).replace(',', '.')) || 0,
            precio_base: parseFloat(String(item.precio_base ?? item.precio ?? 0).replace(',', '.')) || 0,
            descuento_pct: parseFloat(String(item.descuento_pct || 0).replace(',', '.')) || 0,
            atributos: atributos
        });
    });

    return items;
}

function toggleEmpresaFields() {
    const esEmpresa = document.getElementById('es_empresa');
    const facturaA = document.getElementById('factura_a');
    const cuit = document.getElementById('cuit');
    const wrapper = document.getElementById('empresaFields');
    const activo = !!(esEmpresa && esEmpresa.checked);
    if (wrapper) {
        wrapper.style.display = activo ? '' : 'none';
    }
    if (!activo && facturaA) {
        facturaA.checked = false;
    }
    if (cuit) {
        cuit.required = !!(activo && facturaA && facturaA.checked);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    toggleEmpresaFields();
    if (itemsExistentes.length > 0) {
        itemsExistentes.forEach(item => {
            agregarItem(item);
        });
    }
    aplicarListaPrecios();
    const btnGuardar = document.getElementById('guardarItemBtn');
    if (btnGuardar) {
        btnGuardar.addEventListener('click', guardarItemDesdeModal);
    }
    const formModal = document.getElementById('itemModalForm') || document.querySelector('#itemModal form');
    if (formModal) {
        formModal.addEventListener('submit', function(e) {
            e.preventDefault();
            guardarItemDesdeModal();
        });
    }
    const formCot = document.getElementById('formCotizacion');
    if (formCot) {
        formCot.addEventListener('submit', function(e) {
            e.preventDefault();
            const rows = document.querySelectorAll('.item-row');
            if (!rows || rows.length === 0) {
                alert('Debes agregar al menos un item antes de guardar.');
                return false;
            }
            var itemsArray = collectItemsForSubmit();
            if (!itemsArray || itemsArray.length === 0) {
                alert('No se pudieron leer los items. Revisa que cada item tenga nombre, cantidad y precio.');
                return false;
            }
            // Validar que cada item tenga nombre, cantidad y precio válidos
            for (let i = 0; i < itemsArray.length; i++) {
                const it = itemsArray[i];
                if (!it.nombre || !it.cantidad || !it.precio || isNaN(it.cantidad) || isNaN(it.precio) || it.cantidad <= 0 || it.precio < 0) {
                    alert('Todos los items deben tener nombre, cantidad mayor a 0 y precio válido.');
                    return false;
                }
            }
            let jsonStr = '';
            try {
                jsonStr = JSON.stringify(itemsArray);
            } catch (err) {
                alert('Error serializando los items. Intenta recargar la página.');
                return false;
            }
            if (!jsonStr || jsonStr === '[]') {
                alert('No se detectaron items para guardar.');
                return false;
            }
            var input = formCot.querySelector('input[name="items_json"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'items_json';
                formCot.appendChild(input);
            }
            input.value = jsonStr;

            // Enviar solo JSON para evitar límites de max_input_vars con muchos items/atributos
            formCot.querySelectorAll('.item-row [name^="items["]').forEach(function(el) {
                el.removeAttribute('name');
            });

            // Validación final antes de submit
            if (!input.value || input.value === '[]') {
                alert('Error: los items no se enviaron correctamente.');
                return false;
            }

            const nativeSubmit = HTMLFormElement.prototype.submit;
            if (typeof nativeSubmit === 'function') {
                nativeSubmit.call(formCot);
            } else {
                formCot.submit();
            }
        });
    }
});
</script>

<?php require 'includes/footer.php'; ?>
