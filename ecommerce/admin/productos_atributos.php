<?php
require 'includes/header.php';

$producto_id = $_GET['producto_id'] ?? 0;
if ($producto_id <= 0) die("Producto no especificado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ? AND tipo_precio = 'variable'");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Producto variable no encontrado");

// Obtener atributos del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_atributos 
    WHERE producto_id = ? 
    ORDER BY orden
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar agregar/editar atributo
if ($_POST['accion'] === 'guardar_atributo') {
    try {
        $id = intval($_POST['id'] ?? 0);
        $nombre = $_POST['nombre'];
        $tipo = $_POST['tipo'];
        $valores = $_POST['valores'] ?? '';
        $costo_adicional = floatval($_POST['costo_adicional'] ?? 0);
        $es_obligatorio = isset($_POST['es_obligatorio']) ? 1 : 0;
        $orden = intval($_POST['orden'] ?? 0);
        
        if (empty($nombre)) {
            $error = "El nombre es obligatorio";
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_producto_atributos 
                    SET nombre = ?, tipo = ?, valores = ?, costo_adicional = ?, es_obligatorio = ?, orden = ?
                    WHERE id = ? AND producto_id = ?
                ");
                $stmt->execute([$nombre, $tipo, $valores, $costo_adicional, $es_obligatorio, $orden, $id, $producto_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_producto_atributos (producto_id, nombre, tipo, valores, costo_adicional, es_obligatorio, orden)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$producto_id, $nombre, $tipo, $valores, $costo_adicional, $es_obligatorio, $orden]);
            }
            
            // Recargar atributos
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_producto_atributos 
                WHERE producto_id = ? 
                ORDER BY orden
            ");
            $stmt->execute([$producto_id]);
            $atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mensaje = "Atributo guardado";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar eliminación de atributo
if ($_POST['accion'] === 'eliminar_atributo') {
    try {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM ecommerce_producto_atributos WHERE id = ? AND producto_id = ?")
            ->execute([$id, $producto_id]);
        
        // Recargar
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_producto_atributos 
            WHERE producto_id = ? 
            ORDER BY orden
        ");
        $stmt->execute([$producto_id]);
        $atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Atributo eliminado";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<h1>Atributos - <?= htmlspecialchars($producto['nombre']) ?></h1>

<?php if (isset($mensaje)): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5>Nuevo Atributo</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_atributo">
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Ej: Alto" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select class="form-select" id="tipo" name="tipo" required onchange="toggleValores()">
                            <option value="">Seleccionar...</option>
                            <option value="text">Texto</option>
                            <option value="number">Número</option>
                            <option value="select">Selección</option>
                        </select>
                    </div>
                    <div class="mb-3" id="valores_container" style="display: none;">
                        <label for="valores" class="form-label">Valores (separados por coma)</label>
                        <textarea class="form-control" id="valores" name="valores" rows="3" placeholder="Ej: 10cm, 20cm, 30cm"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="costo_adicional" class="form-label">Costo Adicional ($)</label>
                        <input type="number" class="form-control" id="costo_adicional" name="costo_adicional" step="0.01" value="0" min="0">
                        <small class="text-muted">Se suma al precio total del producto</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="es_obligatorio" name="es_obligatorio">
                        <label class="form-check-label" for="es_obligatorio">Obligatorio</label>
                    </div>
                    <div class="mb-3">
                        <label for="orden" class="form-label">Orden</label>
                        <input type="number" class="form-control" id="orden" name="orden" value="0">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Agregar Atributo</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Atributos del Producto (<?= count($atributos) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($atributos)): ?>
                    <p class="text-muted">No hay atributos. Agrega los atributos que usará este producto (como Alto, Ancho, Color, etc.)</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Nombre</th>
                                    <th>Tipo</th>
                                    <th>Valores</th>
                                    <th>Costo Adicional</th>
                                    <th>Obligatorio</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($atributos as $attr): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($attr['nombre']) ?></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($attr['tipo']) ?></span></td>
                                        <td>
                                            <?php if ($attr['tipo'] === 'select'): ?>
                                                <small><?= htmlspecialchars(substr($attr['valores'], 0, 50)) ?><?= strlen($attr['valores']) > 50 ? '...' : '' ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attr['costo_adicional'] > 0): ?>
                                                <span class="badge bg-info">+$<?= number_format($attr['costo_adicional'], 2) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">Gratis</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attr['es_obligatorio']): ?>
                                                <span class="badge bg-danger">Sí</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar_atributo">
                                                <input type="hidden" name="id" value="<?= $attr['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">×</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="alert alert-info mt-3">
            <strong>Nota:</strong> Para productos de tipo variable (cortinas, toldos), se recomienda crear atributos "Alto" y "Ancho" como números obligatorios. El sistema calculará el precio automáticamente redondeando a la medida más cercana en la matriz de precios.
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="productos.php" class="btn btn-secondary">Volver a Productos</a>
</div>

<script>
function toggleValores() {
    const tipo = document.getElementById('tipo').value;
    const container = document.getElementById('valores_container');
    container.style.display = tipo === 'select' ? 'block' : 'none';
}
</script>

<?php require 'includes/footer.php'; ?>
