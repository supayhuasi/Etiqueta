<?php
require 'includes/header.php';

$id = intval($_GET['id'] ?? 0);

// Obtener cotización
$stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizaciones WHERE id = ?");
$stmt->execute([$id]);
$cotizacion = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cotizacion) {
    die("Cotización no encontrada");
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
            
            $mensaje = "Estado actualizado";
            
            // Recargar
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_cotizaciones WHERE id = ?");
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
                        <td><?= htmlspecialchars($cotizacion['nombre_cliente']) ?></td>
                    </tr>
                    <?php if ($cotizacion['empresa']): ?>
                    <tr>
                        <th>Empresa:</th>
                        <td><?= htmlspecialchars($cotizacion['empresa']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <th>Email:</th>
                        <td><a href="mailto:<?= htmlspecialchars($cotizacion['email']) ?>"><?= htmlspecialchars($cotizacion['email']) ?></a></td>
                    </tr>
                    <?php if ($cotizacion['telefono']): ?>
                    <tr>
                        <th>Teléfono:</th>
                        <td><?= htmlspecialchars($cotizacion['telefono']) ?></td>
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
                <button type="button" class="btn btn-success" onclick="alert('Funcionalidad de conversión a pedido próximamente')">🛒 Convertir a Pedido</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
