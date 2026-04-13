<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/nota_credito_helper.php';

// Inicializar esquema
try {
    ensureNotaCreditoSchema($pdo);
} catch (Throwable $e) {
    die('Error: ' . $e->getMessage());
}

// Obtener NC
$nc_id = (int)($_GET['id'] ?? $_POST['nota_credito_id'] ?? 0);
if ($nc_id <= 0) {
    die('Nota de crédito no especificada');
}

$nc = nota_credito_obtener($pdo, $nc_id);
if (!$nc) {
    die('Nota de crédito no encontrada');
}

// Procesamiento de acciones
$accion = $_POST['accion'] ?? '';
$resultado = ['ok' => false];

// Agregar item
if ($accion === 'agregar_item') {
    try {
        nota_credito_agregar_item($pdo, $nc_id, [
            'pedido_item_id' => $_POST['pedido_item_id'] ?? null,
            'descripcion' => $_POST['descripcion'] ?? '',
            'cantidad' => $_POST['cantidad'] ?? 1,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0
        ]);
        $resultado = ['ok' => true, 'message' => 'Item agregado'];
        $nc = nota_credito_obtener($pdo, $nc_id);
    } catch (Throwable $e) {
        $resultado = ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Eliminar item
if ($accion === 'eliminar_item') {
    try {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM ecommerce_notas_credito_items WHERE id = ? AND nota_credito_id = ?");
        $stmt->execute([$item_id, $nc_id]);
        
        // Recalcular monto
        $stmtRecalc = $pdo->prepare("UPDATE ecommerce_notas_credito SET monto_total = (SELECT COALESCE(SUM(subtotal), 0) FROM ecommerce_notas_credito_items WHERE nota_credito_id = ?) WHERE id = ?");
        $stmtRecalc->execute([$nc_id, $nc_id]);
        
        $resultado = ['ok' => true, 'message' => 'Item eliminado'];
        $nc = nota_credito_obtener($pdo, $nc_id);
    } catch (Throwable $e) {
        $resultado = ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Si es AJAX
if (!empty($_POST['accion']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode($resultado);
    exit;
}

// Obtener items del pedido original para referencia
$pedido_items = [];
if ($nc['pedido_id']) {
    $stmt = $pdo->prepare("
        SELECT id, descripcion, cantidad, precio_unitario, subtotal
        FROM ecommerce_pedido_items
        WHERE pedido_id = ?
        ORDER BY id ASC
    ");
    $stmt->execute([$nc['pedido_id']]);
    $pedido_items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Detalle Nota de Crédito #<?= htmlspecialchars($nc['numero_nc']) ?></title>
</head>
<body>
<div class="container-fluid mt-4">

    <!-- Encabezado -->
    <div class="row mb-4">
        <div class="col">
            <h1>Nota de Crédito #<?= htmlspecialchars($nc['numero_nc'] ?? 'NUEVO') ?></h1>
            <p class="text-muted">Pedido: <?= htmlspecialchars($nc['factura_original'] ?? 'Sin factura') ?> | Cliente: <?= htmlspecialchars($nc['cliente_nombre'] ?? 'Desconocido') ?></p>
        </div>
        <div class="col-auto">
            <a href="nota_credito.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if ($resultado['ok']): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <strong>✓ Éxito</strong> <?= htmlspecialchars($resultado['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (!$resultado['ok'] && !empty($resultado['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>✗ Error</strong> <?= htmlspecialchars($resultado['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Información de la NC -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Información de Nota de Crédito</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">Número NC:</dt>
                        <dd class="col-sm-7"><strong><?= htmlspecialchars($nc['numero_nc'] ?? 'N/A') ?></strong></dd>
                        
                        <dt class="col-sm-5">Tipo:</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($nc['tipo_nc'] ?? '03') ?> (<?= ['03' => 'NC A', '08' => 'NC B', '13' => 'NC C'][$nc['tipo_nc'] ?? '03'] ?? 'Desconocido' ?>)</dd>
                        
                        <dt class="col-sm-5">Comprobante:</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?= $nc['comprobante_tipo'] === 'factura' ? 'bg-primary' : 'bg-secondary' ?>">
                                <?= ucfirst($nc['comprobante_tipo']) ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Estado:</dt>
                        <dd class="col-sm-7">
                            <span class="badge <?= $nc['estado'] === 'borrador' ? 'bg-warning' : ($nc['estado'] === 'emitida' ? 'bg-success' : 'bg-danger') ?>">
                                <?= ucfirst($nc['estado']) ?>
                            </span>
                        </dd>
                        
                        <dt class="col-sm-5">Motivo:</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($nc['motivo'] ?? '') ?></dd>
                        
                        <dt class="col-sm-5">Descripción:</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($nc['descripcion'] ?? '') ?></dd>
                        
                        <dt class="col-sm-5">Monto Total:</dt>
                        <dd class="col-sm-7"><strong class="text-success">$<?= number_format($nc['monto_total'], 2, ',', '.') ?></strong></dd>
                        
                        <dt class="col-sm-5">Creada:</dt>
                        <dd class="col-sm-7"><?= date('d/m/Y H:i', strtotime($nc['created_at'])) ?></dd>
                        
                        <?php if (!empty($nc['fecha_emision'])): ?>
                            <dt class="col-sm-5">Emitida:</dt>
                            <dd class="col-sm-7"><?= date('d/m/Y H:i', strtotime($nc['fecha_emision'])) ?></dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($nc['cae'])): ?>
                            <dt class="col-sm-5">CAE:</dt>
                            <dd class="col-sm-7"><code><?= htmlspecialchars($nc['cae']) ?></code></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Información del pedido original -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Pedido Original</h5>
                </div>
                <div class="card-body">
                    <dl class="row">
                        <dt class="col-sm-5">Factura:</dt>
                        <dd class="col-sm-7"><strong><?= htmlspecialchars($nc['factura_original'] ?? 'N/A') ?></strong></dd>
                        
                        <dt class="col-sm-5">Cliente:</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($nc['cliente_nombre'] ?? 'Desconocido') ?></dd>
                        
                        <dt class="col-sm-5">Email:</dt>
                        <dd class="col-sm-7"><?= htmlspecialchars($nc['cliente_email'] ?? '') ?></dd>
                        
                        <dt class="col-sm-5">Total Original:</dt>
                        <dd class="col-sm-7">$<?= number_format($nc['pedido_total'] ?? 0, 2, ',', '.') ?></dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>

    <!-- Items de la NC -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Items de Nota de Crédito</h5>
            <?php if ($nc['estado'] === 'borrador'): ?>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#modalAgregarItem">
                    ➕ Agregar Item
                </button>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Descripción</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-end">Precio Unit.</th>
                        <th class="text-end">Subtotal</th>
                        <?php if ($nc['estado'] === 'borrador'): ?><th class="text-center">Acción</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($nc['items'])): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-4">Sin items agregados</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($nc['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['descripcion']) ?></td>
                                <td class="text-end"><?= number_format($item['cantidad'], 2, ',', '.') ?></td>
                                <td class="text-end">$<?= number_format($item['precio_unitario'], 2, ',', '.') ?></td>
                                <td class="text-end"><strong>$<?= number_format($item['subtotal'], 2, ',', '.') ?></strong></td>
                                <?php if ($nc['estado'] === 'borrador'): ?>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-danger btn-eliminar-item" data-item-id="<?= $item['id'] ?>" title="Eliminar">
                                            ✕
                                        </button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer text-end">
            <strong>Total NC: $<?= number_format($nc['monto_total'], 2, ',', '.') ?></strong>
        </div>
    </div>

    <!-- Acciones -->
    <div class="card">
        <div class="card-body">
            <div class="btn-group" role="group">
                <?php if ($nc['estado'] === 'borrador'): ?>
                    <button class="btn btn-success btn-emitir-nc" data-id="<?= $nc_id ?>">
                        ✓ Emitir Nota de Crédito
                    </button>
                    <button class="btn btn-danger btn-cancelar-nc" data-id="<?= $nc_id ?>">
                        ✕ Cancelar
                    </button>
                <?php endif; ?>
                
                <?php if ($nc['estado'] !== 'borrador'): ?>
                    <a href="nota_credito_pdf.php?id=<?= $nc_id ?>" class="btn btn-info" target="_blank">
                        📄 Ver PDF
                    </a>
                <?php endif; ?>
                
                <a href="nota_credito.php" class="btn btn-secondary">
                    ← Volver
                </a>
            </div>
        </div>
    </div>

</div>

<!-- Modal: Agregar Item -->
<div class="modal fade" id="modalAgregarItem" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Agregar Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formAgregarItem">
                <div class="modal-body">
                    <input type="hidden" name="accion" value="agregar_item">
                    
                    <?php if (!empty($pedido_items)): ?>
                        <div class="mb-3">
                            <label for="pedido_item_id" class="form-label">Del Pedido Original (opcional)</label>
                            <select class="form-select" id="pedido_item_id" name="pedido_item_id">
                                <option value="">-- Ingresar manualmente --</option>
                                <?php foreach ($pedido_items as $pi): ?>
                                    <option value="<?= $pi['id'] ?>">
                                        <?= htmlspecialchars(substr($pi['descripcion'], 0, 50)) ?> (Cant: <?= $pi['cantidad'] ?>, $<?= number_format($pi['precio_unitario'], 2, ',', '.') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-text text-muted">Si selecciona un item, se completarán los datos automáticamente</small>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <label for="descripcion_item" class="form-label">Descripción *</label>
                        <input type="text" class="form-control" id="descripcion_item" name="descripcion" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="cantidad_item" class="form-label">Cantidad *</label>
                                <input type="number" class="form-control" id="cantidad_item" name="cantidad" step="0.01" value="1" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="precio_item" class="form-label">Precio Unitario ($) *</label>
                                <input type="number" class="form-control" id="precio_item" name="precio_unitario" step="0.01" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Auto-llenar datos del pedido original
document.getElementById('pedido_item_id')?.addEventListener('change', function() {
    const items = <?= json_encode($pedido_items) ?>;
    const selectedId = parseInt(this.value);
    const item = items.find(i => i.id === selectedId);
    
    if (item) {
        document.getElementById('descripcion_item').value = item.descripcion;
        document.getElementById('cantidad_item').value = item.cantidad;
        document.getElementById('precio_item').value = item.precio_unitario;
    }
});

// Agregar item
document.getElementById('formAgregarItem')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const formData = new FormData(e.target);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        
        if (result.ok) {
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        alert('Error al procesar');
    }
});

// Eliminar item
document.querySelectorAll('.btn-eliminar-item').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('¿Eliminar este item?')) return;
        
        const formData = new FormData();
        formData.append('accion', 'eliminar_item');
        formData.append('item_id', this.dataset.itemId);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.ok) {
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (err) {
            alert('Error al procesar');
        }
    });
});

// Emitir NC
document.querySelector('.btn-emitir-nc')?.addEventListener('click', async function() {
    if (!confirm('¿Emitir esta nota de crédito?')) return;
    
    const formData = new FormData();
    formData.append('accion', 'emitir');
    formData.append('nota_credito_id', this.dataset.id);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        
        if (result.ok) {
            alert(result.message);
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        alert('Error al procesar');
    }
});

// Cancelar NC
document.querySelector('.btn-cancelar-nc')?.addEventListener('click', async function() {
    if (!confirm('¿Cancelar esta nota de crédito?')) return;
    
    const formData = new FormData();
    formData.append('accion', 'cancelar');
    formData.append('nota_credito_id', this.dataset.id);
    
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        
        if (result.ok) {
            alert(result.message);
            window.location.href = 'nota_credito.php';
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        alert('Error al procesar');
    }
});
</script>

</body>
</html>
