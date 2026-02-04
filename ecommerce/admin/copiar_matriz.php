<?php
require 'includes/header.php';

$producto_destino_id = $_GET['producto_id'] ?? 0;
if ($producto_destino_id <= 0) die("Producto destino no especificado");

// Obtener producto destino
$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ? AND tipo_precio = 'variable'");
$stmt->execute([$producto_destino_id]);
$producto_destino = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto_destino) die("Producto variable no encontrado");

// Obtener lista de productos variables para seleccionar origen
$stmt = $pdo->prepare("
    SELECT id, nombre 
    FROM ecommerce_productos 
    WHERE tipo_precio = 'variable' AND id != ? 
    ORDER BY nombre ASC
");
$stmt->execute([$producto_destino_id]);
$productos_disponibles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar copia de matriz
if ($_POST['accion'] === 'copiar' && isset($_POST['producto_origen_id'])) {
    try {
        $producto_origen_id = intval($_POST['producto_origen_id']);
        $porcentaje_ajuste = floatval($_POST['porcentaje_ajuste'] ?? 0);
        $reemplazar = isset($_POST['reemplazar']) && $_POST['reemplazar'] === '1';
        
        // Validar que el producto origen existe y tiene matriz
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cantidad 
            FROM ecommerce_matriz_precios 
            WHERE producto_id = ?
        ");
        $stmt->execute([$producto_origen_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result['cantidad'] === 0) {
            $error = "El producto de origen no tiene matriz de precios";
        } else {
            $pdo->beginTransaction();
            
            // Reemplazar matriz si es necesario
            if ($reemplazar) {
                $pdo->prepare("DELETE FROM ecommerce_matriz_precios WHERE producto_id = ?")
                    ->execute([$producto_destino_id]);
            }
            
            // Obtener matriz del producto origen
            $stmt = $pdo->prepare("
                SELECT alto_cm, ancho_cm, precio, stock 
                FROM ecommerce_matriz_precios 
                WHERE producto_id = ?
            ");
            $stmt->execute([$producto_origen_id]);
            $matriz_origen = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Copiar a producto destino con ajuste de porcentaje
            $stmtCheck = $pdo->prepare("
                SELECT id FROM ecommerce_matriz_precios 
                WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?
            ");
            $stmtUpd = $pdo->prepare("
                UPDATE ecommerce_matriz_precios 
                SET precio = ? 
                WHERE id = ?
            ");
            $stmtIns = $pdo->prepare("
                INSERT INTO ecommerce_matriz_precios (producto_id, alto_cm, ancho_cm, precio, stock)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $copiados = 0;
            $actualizados = 0;
            
            foreach ($matriz_origen as $item) {
                // Calcular nuevo precio con ajuste porcentual
                $factor_ajuste = 1 + ($porcentaje_ajuste / 100);
                $nuevo_precio = round($item['precio'] * $factor_ajuste, 2);
                
                // Verificar si ya existe
                $stmtCheck->execute([
                    $producto_destino_id,
                    $item['alto_cm'],
                    $item['ancho_cm']
                ]);
                $existing = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                
                if ($existing) {
                    // Actualizar existente
                    $stmtUpd->execute([$nuevo_precio, $existing['id']]);
                    $actualizados++;
                } else {
                    // Insertar nuevo
                    $stmtIns->execute([
                        $producto_destino_id,
                        $item['alto_cm'],
                        $item['ancho_cm'],
                        $nuevo_precio,
                        $item['stock']
                    ]);
                    $copiados++;
                }
            }
            
            $pdo->commit();
            
            $mensaje = "Matriz copiada exitosamente: {$copiados} nuevas entradas agregadas";
            if ($actualizados > 0) {
                $mensaje .= ", {$actualizados} actualizadas";
            }
            if ($porcentaje_ajuste != 0) {
                $operador = $porcentaje_ajuste > 0 ? '+' : '';
                $mensaje .= " (con ajuste {$operador}{$porcentaje_ajuste}%)";
            }
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Error: " . $e->getMessage();
    }
}
?>

<h1>Copiar Matriz de Precios</h1>
<p class="text-muted">Producto destino: <strong><?= htmlspecialchars($producto_destino['nombre']) ?></strong></p>

<?php if (isset($mensaje)): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= $mensaje ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (isset($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5>Copiar desde Otro Producto</h5>
            </div>
            <div class="card-body">
                <?php if (empty($productos_disponibles)): ?>
                    <p class="text-danger">No hay otros productos variables disponibles</p>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="accion" value="copiar">
                        
                        <div class="mb-3">
                            <label for="producto_origen_id" class="form-label">Seleccionar Producto Origen</label>
                            <select class="form-select" id="producto_origen_id" name="producto_origen_id" required>
                                <option value="">-- Seleccionar --</option>
                                <?php foreach ($productos_disponibles as $prod): ?>
                                    <option value="<?= $prod['id'] ?>">
                                        <?= htmlspecialchars($prod['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="porcentaje_ajuste" class="form-label">
                                Ajuste de Porcentaje (%)
                                <span class="badge bg-info">Opcional</span>
                            </label>
                            <div class="input-group">
                                <input type="number" 
                                       class="form-control" 
                                       id="porcentaje_ajuste" 
                                       name="porcentaje_ajuste" 
                                       step="0.01" 
                                       value="0"
                                       placeholder="0">
                                <span class="input-group-text">%</span>
                            </div>
                            <small class="text-muted d-block mt-2">
                                <strong>Ejemplos:</strong><br>
                                • <code>10</code> = aumenta 10% cada precio<br>
                                • <code>-15</code> = reduce 15% cada precio<br>
                                • <code>0</code> = copia sin cambios
                            </small>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" 
                                   type="checkbox" 
                                   value="1" 
                                   id="reemplazar" 
                                   name="reemplazar">
                            <label class="form-check-label" for="reemplazar">
                                <strong>Reemplazar</strong> matriz existente
                            </label>
                            <small class="d-block text-muted mt-1">
                                Si NO está marcado, fusionará los datos (actualizará coincidencias)
                            </small>
                        </div>
                        
                        <button type="submit" 
                                class="btn btn-primary w-100" 
                                onclick="return confirm('¿Copiar matriz de precios?\n\nEsto puede tomar un momento...')">
                            <i class="bi bi-files"></i> Copiar Matriz
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card bg-light">
            <div class="card-header">
                <h5>ℹ️ Información</h5>
            </div>
            <div class="card-body">
                <p><strong>¿Cómo funciona?</strong></p>
                <ol>
                    <li>Selecciona el producto del cual copiar la matriz de precios</li>
                    <li>Ingresa un porcentaje de ajuste (opcional)</li>
                    <li>Elige si reemplazar completamente la matriz actual o fusionar con la existente</li>
                    <li>Haz clic en "Copiar Matriz"</li>
                </ol>
                
                <hr>
                
                <p><strong>Modos de Operación:</strong></p>
                <ul>
                    <li><strong>Reemplazar:</strong> Borra toda la matriz actual y copia la nueva</li>
                    <li><strong>Fusionar:</strong> Mantiene entradas únicas y actualiza coincidencias</li>
                </ul>
                
                <hr>
                
                <p><strong>Ejemplos de Ajuste:</strong></p>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td>Producto Origen</td>
                        <td><strong>100.00</strong></td>
                    </tr>
                    <tr class="table-warning">
                        <td>Con +10%</td>
                        <td><strong>110.00</strong></td>
                    </tr>
                    <tr class="table-danger">
                        <td>Con -10%</td>
                        <td><strong>90.00</strong></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="matriz_precios.php?producto_id=<?= $producto_destino_id ?>" class="btn btn-secondary">
        Volver a Matriz
    </a>
    <a href="productos.php" class="btn btn-outline-secondary">
        Volver a Productos
    </a>
</div>

<?php require 'includes/footer.php'; ?>
