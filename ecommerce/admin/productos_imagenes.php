<?php
require '../includes/navbar.php';

$producto_id = $_GET['producto_id'] ?? 0;
if ($producto_id <= 0) die("Producto no especificado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Producto no encontrado");

// Obtener imágenes del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_imagenes 
    WHERE producto_id = ? 
    ORDER BY orden, es_principal DESC
");
$stmt->execute([$producto_id]);
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar carga de nueva imagen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['accion'] === 'subir') {
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == 0) {
        $tipos_permitidos = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $tipos_permitidos)) {
            $error = "Tipo de imagen no permitido";
        } else if ($_FILES['imagen']['size'] > 5242880) {
            $error = "La imagen es muy grande (máx 5MB)";
        } else {
            $filename = "prod_" . $producto_id . "_" . time() . "." . $ext;
            if (move_uploaded_file($_FILES['imagen']['tmp_name'], "../uploads/" . $filename)) {
                // Determinar si es la primera imagen
                $es_principal = count($imagenes) === 0 ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_producto_imagenes (producto_id, imagen, orden, es_principal)
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([$producto_id, $filename, count($imagenes), $es_principal]);
                
                // Recargar imágenes
                $stmt = $pdo->prepare("
                    SELECT * FROM ecommerce_producto_imagenes 
                    WHERE producto_id = ? 
                    ORDER BY orden, es_principal DESC
                ");
                $stmt->execute([$producto_id]);
                $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $mensaje = "Imagen subida correctamente";
            } else {
                $error = "Error al subir la imagen";
            }
        }
    } else {
        $error = "Debes seleccionar una imagen";
    }
}

// Procesar cambio de orden
if ($_POST['accion'] === 'cambiar_orden') {
    try {
        $imagen_id = intval($_POST['imagen_id']);
        $nueva_orden = intval($_POST['nueva_orden']);
        
        $pdo->prepare("UPDATE ecommerce_producto_imagenes SET orden = ? WHERE id = ? AND producto_id = ?")
            ->execute([$nueva_orden, $imagen_id, $producto_id]);
        
        // Recargar
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_producto_imagenes 
            WHERE producto_id = ? 
            ORDER BY orden, es_principal DESC
        ");
        $stmt->execute([$producto_id]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Orden actualizado";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar marcar como principal
if ($_POST['accion'] === 'marcar_principal') {
    try {
        $imagen_id = intval($_POST['imagen_id']);
        
        // Desmarcar todas
        $pdo->prepare("UPDATE ecommerce_producto_imagenes SET es_principal = 0 WHERE producto_id = ?")
            ->execute([$producto_id]);
        
        // Marcar la nueva
        $pdo->prepare("UPDATE ecommerce_producto_imagenes SET es_principal = 1 WHERE id = ? AND producto_id = ?")
            ->execute([$imagen_id, $producto_id]);
        
        // Recargar
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_producto_imagenes 
            WHERE producto_id = ? 
            ORDER BY orden, es_principal DESC
        ");
        $stmt->execute([$producto_id]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Imagen principal actualizada";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar eliminación
if ($_POST['accion'] === 'eliminar') {
    try {
        $imagen_id = intval($_POST['imagen_id']);
        
        $stmt = $pdo->prepare("SELECT imagen FROM ecommerce_producto_imagenes WHERE id = ? AND producto_id = ?");
        $stmt->execute([$imagen_id, $producto_id]);
        $img = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($img && file_exists("../uploads/" . $img['imagen'])) {
            unlink("../uploads/" . $img['imagen']);
        }
        
        $pdo->prepare("DELETE FROM ecommerce_producto_imagenes WHERE id = ? AND producto_id = ?")
            ->execute([$imagen_id, $producto_id]);
        
        // Recargar
        $stmt = $pdo->prepare("
            SELECT * FROM ecommerce_producto_imagenes 
            WHERE producto_id = ? 
            ORDER BY orden, es_principal DESC
        ");
        $stmt->execute([$producto_id]);
        $imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $mensaje = "Imagen eliminada";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<h1>Galería de Imágenes - <?= htmlspecialchars($producto['nombre']) ?></h1>

<?php if (isset($mensaje)): ?>
    <div class="alert alert-success"><?= $mensaje ?></div>
<?php endif; ?>
<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-header">
                <h5>Subir Nueva Imagen</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="subir">
                    <div class="mb-3">
                        <label for="imagen" class="form-label">Seleccionar imagen</label>
                        <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*" required>
                        <small class="text-muted">PNG, JPG, GIF (máx 5MB)</small>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Subir Imagen</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5>Instrucciones</h5>
            </div>
            <div class="card-body">
                <ul class="small">
                    <li>Sube múltiples imágenes</li>
                    <li>Ordena arrastrando las imágenes</li>
                    <li>Marca una como principal</li>
                    <li>Elimina las que no necesites</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5>Imágenes del Producto (<?= count($imagenes) ?>)</h5>
            </div>
            <div class="card-body">
                <?php if (empty($imagenes)): ?>
                    <p class="text-muted text-center py-5">No hay imágenes. Sube tu primera imagen.</p>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($imagenes as $img): ?>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="position-relative">
                                        <img src="../uploads/<?= htmlspecialchars($img['imagen']) ?>" 
                                             class="card-img-top" style="height: 200px; object-fit: cover;">
                                        <?php if ($img['es_principal']): ?>
                                            <span class="badge bg-success position-absolute top-0 start-0 m-2">
                                                ⭐ Principal
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body">
                                        <small class="text-muted"><?= htmlspecialchars($img['imagen']) ?></small>
                                        <div class="mt-2 d-flex gap-1 flex-wrap">
                                            <?php if (!$img['es_principal']): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="accion" value="marcar_principal">
                                                    <input type="hidden" name="imagen_id" value="<?= $img['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning">⭐ Principal</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="cambiar_orden">
                                                <input type="hidden" name="imagen_id" value="<?= $img['id'] ?>">
                                                <input type="hidden" name="nueva_orden" value="<?= $img['orden'] - 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary" <?= $img['orden'] <= 0 ? 'disabled' : '' ?>>↑</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="cambiar_orden">
                                                <input type="hidden" name="imagen_id" value="<?= $img['id'] ?>">
                                                <input type="hidden" name="nueva_orden" value="<?= $img['orden'] + 1 ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary">↓</button>
                                            </form>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="imagen_id" value="<?= $img['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar imagen?')">×</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="mt-3">
    <a href="productos.php" class="btn btn-secondary">Volver a Productos</a>
</div>

<?php require 'includes/footer.php'; ?>
