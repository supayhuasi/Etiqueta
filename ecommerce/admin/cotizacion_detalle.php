<?php
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);

// Obtener cotización (compatible con empresa o direccion)
$stmt = $pdo->prepare("SELECT c.*, cc.nombre AS cliente_nombre, cc.email AS cliente_email, cc.telefono AS cliente_telefono FROM ecommerce_cotizaciones c LEFT JOIN ecommerce_cotizacion_clientes cc ON c.cliente_id = cc.id WHERE c.id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

// Agregar campo empresa/direccion según qué columna exista
if ($cotizacion && !empty($cotizacion['cliente_id'])) {
    $stmt_empresa = $pdo->prepare("SELECT empresa FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
    try {
        $stmt_empresa->execute([$cotizacion['cliente_id']]);
        $emp_data = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        if ($emp_data) {
            $cotizacion['cliente_empresa'] = $emp_data['empresa'] ?? null;
        }
    } catch (Exception $e) {
        // Si falla, intentar con direccion
        $stmt_dir = $pdo->prepare("SELECT direccion FROM ecommerce_cotizacion_clientes WHERE id = ? LIMIT 1");
        $stmt_dir->execute([$cotizacion['cliente_id']]);
        $dir_data = $stmt_dir->fetch(PDO::FETCH_ASSOC);
        if ($dir_data) {
            $cotizacion['cliente_direccion'] = $dir_data['direccion'] ?? null;
        }
    }
}
// Agregar validaciones y conversión a pedido

if (!$cotizacion) {
    die("Cotización no encontrada");
}

$lista_precio = null;
if (!empty($cotizacion['lista_precio_id'])) {
    $stmt = $pdo->prepare("SELECT nombre FROM ecommerce_listas_precios WHERE id = ?");
    $stmt->execute([$cotizacion['lista_precio_id']]);
    $lista_precio = $stmt->fetch(PDO::FETCH_ASSOC);
}

$items = json_decode($cotizacion['items'], true) ?? [];
$mensaje = $_GET['mensaje'] ?? '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'cambiar_estado') {
            $nuevo_estado = $_POST['estado'];
            $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id]);
            
            if ($nuevo_estado === 'enviada') {
                $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET fecha_envio = NOW() WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            } elseif ($accion === 'convertir_pedido') {
                if ($cotizacion['estado'] === 'convertida') {
                    throw new Exception('La cotización ya fue convertida');
                }

                $items = json_decode($cotizacion['items'], true) ?? [];
                if (empty($items)) {
                    throw new Exception('La cotización no tiene items');
                }

                foreach ($items as $it) {
                    if (empty($it['producto_id'])) {
                        throw new Exception('Todos los items deben pertenecer a un producto para convertir');
                    }
                }

                $pdo->beginTransaction();

                // Resolver cliente - usar siempre los datos de la cotización
                // Los datos pueden venir del JOIN con cotizacion_clientes o de los campos directos de la cotización
                $email = trim((string)($cotizacion['cliente_email'] ?? $cotizacion['email'] ?? ''));
                $nombre = trim((string)($cotizacion['cliente_nombre'] ?? $cotizacion['nombre_cliente'] ?? ''));
                $telefono = trim((string)($cotizacion['cliente_telefono'] ?? $cotizacion['telefono'] ?? ''));
                // Compatibilidad con empresa/direccion
                $direccion = trim((string)($cotizacion['cliente_direccion'] ?? $cotizacion['direccion'] ?? $cotizacion['cliente_empresa'] ?? $cotizacion['empresa'] ?? ''));

                if ($telefono === '') {
                    throw new Exception('Teléfono requerido para crear cliente del pedido');
                }

                // Buscar si ya existe un cliente con ese teléfono en ecommerce_clientes
                $stmt = $pdo->prepare("SELECT id FROM ecommerce_clientes WHERE telefono = ? LIMIT 1");
                $stmt->execute([$telefono]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($row) {
                    // Cliente existe, usar ese ID
                    $cliente_id = (int)$row['id'];
                    // Actualizar datos del cliente con la info más reciente
                    $stmt = $pdo->prepare("
                        UPDATE ecommerce_clientes 
                        SET nombre = ?, email = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$nombre ?: $telefono, $email ?: ($telefono . '@cliente.local'), $cliente_id]);
                } else {
                    // Cliente no existe, crear uno nuevo
                    $stmt = $pdo->prepare("
                        INSERT INTO ecommerce_clientes (telefono, nombre, email)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->execute([$telefono, $nombre ?: $telefono, $email ?: ($telefono . '@cliente.local')]);
                    $cliente_id = (int)$pdo->lastInsertId();
                }

                $numero_pedido = 'PED-COT-' . date('YmdHis') . '-' . rand(1000, 9999);
                $metodo_pago = 'Cotización';
                $estado_pedido = 'pendiente_pago';

                $public_token = bin2hex(random_bytes(16));
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_pedidos (numero_pedido, cliente_id, total, metodo_pago, estado, public_token)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $numero_pedido,
                    $cliente_id,
                    (float)$cotizacion['total'],
                    $metodo_pago,
                    $estado_pedido,
                    $public_token
                ]);
                $pedido_id = (int)$pdo->lastInsertId();

                $stmtItem = $pdo->prepare("
                    INSERT INTO ecommerce_pedido_items (pedido_id, producto_id, cantidad, precio_unitario, alto_cm, ancho_cm, subtotal, atributos)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");

                foreach ($items as $it) {
                    $cantidad = (int)($it['cantidad'] ?? 1);
                    $precio_unitario = (float)($it['precio_unitario'] ?? $it['precio_base'] ?? 0);
                    $alto = !empty($it['alto']) ? (int)$it['alto'] : null;
                    $ancho = !empty($it['ancho']) ? (int)$it['ancho'] : null;
                    $atributos_json = !empty($it['atributos']) ? json_encode($it['atributos']) : null;
                    $subtotal_item = $precio_unitario * $cantidad;

                    $stmtItem->execute([
                        $pedido_id,
                        (int)$it['producto_id'],
                        $cantidad,
                        $precio_unitario,
                        $alto,
                        $ancho,
                        $subtotal_item,
                        $atributos_json
                    ]);
                }

                $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET estado = 'convertida' WHERE id = ?");
                $stmt->execute([$id]);

                $pdo->commit();

                header("Location: pedidos_detalle.php?id=" . $pedido_id . "&mensaje=convertida");
                exit;
                
        } elseif ($accion === 'cambiar_estado') {
            $nuevo_estado = $_POST['estado'];
            $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET estado = ? WHERE id = ?");
            $stmt->execute([$nuevo_estado, $id]);
            
            if ($nuevo_estado === 'enviada') {
                $stmt = $pdo->prepare("UPDATE ecommerce_cotizaciones SET fecha_envio = NOW() WHERE id = ?");
                $stmt->execute([$id]);
            }
            
            $mensaje = "Estado actualizado";
            
            // Recargar
            $stmt = $pdo->prepare("SELECT c.*, cc.nombre AS cliente_nombre, cc.empresa AS cliente_empresa, cc.email AS cliente_email, cc.telefono AS cliente_telefono FROM ecommerce_cotizaciones c LEFT JOIN ecommerce_cotizacion_clientes cc ON c.cliente_id = cc.id WHERE c.id = ?");
            $stmt->execute([$id]);
            $cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💼 Cotización <?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></h1>
        <p class="text-muted">
            <?php
            $badges = [
                'pendiente' => 'warning',
                'enviada' => 'info',
                'aceptada' => 'success',
                'rechazada' => 'danger',
                'convertida' => 'secondary'
            ];
            $badge = $badges[$cotizacion['estado']] ?? 'secondary';
            ?>
            Estado: <span class="badge bg-<?= $badge ?>"><?= ucfirst($cotizacion['estado']) ?></span>
        </p>
    </div>
    <div>
        <a href="cotizacion_pdf.php?id=<?= $id ?>" class="btn btn-info" target="_blank">📄 Descargar PDF</a>
        <a href="cotizacion_editar.php?id=<?= $id ?>" class="btn btn-warning">✏️ Editar</a>
        <a href="cotizaciones.php" class="btn btn-secondary">← Volver</a>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success">
        <?php if ($mensaje === 'creada'): ?>
            ✅ Cotización creada exitosamente
        <?php else: ?>
            <?= htmlspecialchars($mensaje) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Información del Cliente -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">👤 Información del Cliente</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="30%">Nombre:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_nombre'] ?? $cotizacion['nombre_cliente']) ?></td>
                    </tr>
                    <?php if (!empty($cotizacion['cliente_empresa']) || $cotizacion['empresa']): ?>
                    <tr>
                        <th>Empresa:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_empresa'] ?? $cotizacion['empresa']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Email:</th>
                        <td><a href="mailto:<?= htmlspecialchars($cotizacion['cliente_email'] ?? $cotizacion['email']) ?>"><?= htmlspecialchars($cotizacion['cliente_email'] ?? $cotizacion['email']) ?></a></td>
                    </tr>
                    <?php if (!empty($cotizacion['cliente_telefono']) || $cotizacion['telefono']): ?>
                    <tr>
                        <th>Teléfono:</th>
                        <td><?= htmlspecialchars($cotizacion['cliente_telefono'] ?? $cotizacion['telefono']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Información de la Cotización -->
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">📋 Detalles de la Cotización</h5>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <th width="40%">Número:</th>
                        <td><?= htmlspecialchars($cotizacion['numero_cotizacion']) ?></td>
                    </tr>
                    <tr>
                        <th>Fecha Creación:</th>
                        <td><?= date('d/m/Y H:i', strtotime($cotizacion['fecha_creacion'])) ?></td>
                    </tr>
                    <?php if ($cotizacion['fecha_envio']): ?>
                    <tr>
                        <th>Fecha Envío:</th>
                        <td><?= date('d/m/Y H:i', strtotime($cotizacion['fecha_envio'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Validez:</th>
                        <td><?= $cotizacion['validez_dias'] ?> días</td>
                    </tr>
                    <tr>
                        <th>Vence:</th>
                        <td>
                            <?php
                            $fecha_vence = date('d/m/Y', strtotime($cotizacion['fecha_creacion'] . ' + ' . $cotizacion['validez_dias'] . ' days'));
                            echo $fecha_vence;
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Lista de Precios:</th>
                        <td><?= htmlspecialchars($lista_precio['nombre'] ?? 'Sin lista') ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Items de la Cotización -->
<div class="card mb-4">
    <div class="card-header bg-success text-white">
        <h5 class="mb-0">📦 Items</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table">
                <thead class="table-light">
                    <tr>
                        <th>Producto</th>
                        <th>Descripción</th>
                        <th>Medidas</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($item['nombre']) ?></strong></td>
                            <td><?= htmlspecialchars($item['descripcion'] ?? '') ?></td>
                            <td>
                                <?php if ($item['ancho'] || $item['alto']): ?>
                                    <?= $item['ancho'] ?? '-' ?> x <?= $item['alto'] ?? '-' ?> cm
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="text-center"><?= $item['cantidad'] ?></td>
                            <td class="text-end">$<?= number_format($item['precio_unitario'], 2) ?></td>
                            <td class="text-end"><strong>$<?= number_format($item['precio_total'], 2) ?></strong></td>
                        </tr>
                        <?php if (!empty($item['atributos']) && is_array($item['atributos'])): ?>
                            <tr class="table-light">
                                <td colspan="6" class="p-2">
                                    <small class="text-muted">
                                        <strong>🎨 Atributos:</strong><br>
                                        <?php foreach ($item['atributos'] as $attr): ?>
                                            • <?= htmlspecialchars($attr['nombre']) ?>: <?= htmlspecialchars($attr['valor']) ?>
                                            <?php if ($attr['costo_adicional'] > 0): ?>
                                                <span class="badge bg-warning text-dark">+$<?= number_format($attr['costo_adicional'], 2) ?></span>
                                            <?php endif; ?>
                                            <br>
                                        <?php endforeach; ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="5" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end"><strong>$<?= number_format($cotizacion['subtotal'], 2) ?></strong></td>
                    </tr>
                    <?php if ($cotizacion['descuento'] > 0): ?>
                    <tr>
                        <td colspan="5" class="text-end text-success"><strong>Descuento:</strong></td>
                        <td class="text-end text-success"><strong>-$<?= number_format($cotizacion['descuento'], 2) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($cotizacion['cupon_descuento'])): ?>
                    <tr>
                        <td colspan="5" class="text-end text-primary"><strong>Cupón<?= !empty($cotizacion['cupon_codigo']) ? ' (' . htmlspecialchars($cotizacion['cupon_codigo']) . ')' : '' ?>:</strong></td>
                        <td class="text-end text-primary"><strong>-$<?= number_format($cotizacion['cupon_descuento'], 2) ?></strong></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td colspan="5" class="text-end"><strong>TOTAL:</strong></td>
                        <td class="text-end"><strong style="font-size: 1.2em;">$<?= number_format($cotizacion['total'], 2) ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Observaciones -->
<?php if ($cotizacion['observaciones']): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">📝 Observaciones</h5>
    </div>
    <div class="card-body">
        <p class="mb-0"><?= nl2br(htmlspecialchars($cotizacion['observaciones'])) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Acciones -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">⚙️ Acciones</h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="accion" value="cambiar_estado">
            <div class="col-md-4">
                <label class="form-label">Cambiar Estado</label>
                <select name="estado" class="form-select">
                    <option value="pendiente" <?= $cotizacion['estado'] === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="enviada" <?= $cotizacion['estado'] === 'enviada' ? 'selected' : '' ?>>Enviada</option>
                    <option value="aceptada" <?= $cotizacion['estado'] === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                    <option value="rechazada" <?= $cotizacion['estado'] === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="convertida" <?= $cotizacion['estado'] === 'convertida' ? 'selected' : '' ?>>Convertida a Pedido</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary">💾 Actualizar Estado</button>
            </div>
        </form>
        
        <hr>
        
        <div class="d-flex gap-2">
            <a href="cotizacion_pdf.php?id=<?= $id ?>" class="btn btn-info" target="_blank">📄 Ver/Descargar PDF</a>
            <a href="cotizacion_editar.php?id=<?= $id ?>" class="btn btn-warning">✏️ Editar Cotización</a>
            <?php if ($cotizacion['estado'] !== 'convertida'): ?>
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="accion" value="convertir_pedido">
                    <button type="submit" class="btn btn-success" onclick="return confirm('¿Convertir esta cotización a pedido?')">🛒 Convertir a Pedido</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
