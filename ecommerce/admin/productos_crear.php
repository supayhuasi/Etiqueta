<?php
require 'includes/header.php';

$id = $_GET['id'] ?? 0;
$producto = null;
$titulo = 'Nuevo Producto';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
    $stmt->execute([$id]);
    $producto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$producto) die("Producto no encontrado");
    $titulo = 'Editar Producto';
}

// Obtener categorías
$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_categorias WHERE activo = 1 ORDER BY nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $codigo = $_POST['codigo'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $descripcion = $_POST['descripcion'] ?? '';
    $categoria_id = intval($_POST['categoria_id'] ?? 0);
    $precio_base = floatval($_POST['precio_base'] ?? 0);
    $tipo_precio = $_POST['tipo_precio'] ?? 'fijo';
    $orden = intval($_POST['orden'] ?? 0);
    $activo = isset($_POST['activo']) ? 1 : 0;
    $mostrar_ecommerce = isset($_POST['mostrar_ecommerce']) ? 1 : 0;
    $es_material = isset($_POST['es_material']) ? 1 : 0;
    
    if (empty($nombre) || empty($codigo) || $categoria_id <= 0 || ($precio_base <= 0 && !$es_material)) {
        $error = "Falta completar campos obligatorios";
    } else {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_productos 
                    SET codigo = ?, nombre = ?, descripcion = ?, categoria_id = ?, 
                        precio_base = ?, tipo_precio = ?, orden = ?, activo = ?, mostrar_ecommerce = ?, es_material = ?
                    WHERE id = ?
                ");
                $stmt->execute([$codigo, $nombre, $descripcion, $categoria_id, $precio_base, $tipo_precio, $orden, $activo, $mostrar_ecommerce, $es_material, $id]);
                $mensaje = "Producto actualizado";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_productos (codigo, nombre, descripcion, categoria_id, precio_base, tipo_precio, orden, activo, mostrar_ecommerce, es_material)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$codigo, $nombre, $descripcion, $categoria_id, $precio_base, $tipo_precio, $orden, $activo, $mostrar_ecommerce, $es_material]);
                $producto_id = $pdo->lastInsertId();
                $mensaje = "Producto creado";
            }
            
            header("Location: productos.php");
            exit;
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<h1><?= $titulo ?></h1>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="codigo" class="form-label">Código *</label>
                    <input type="text" class="form-control" id="codigo" name="codigo" value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="descripcion" class="form-label">Descripción</label>
                <textarea class="form-control" id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label for="categoria_id" class="form-label">Categoría *</label>
                    <select class="form-select" id="categoria_id" name="categoria_id" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= ($producto['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="precio_base" class="form-label">Precio Base *</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="precio_base" name="precio_base" step="0.01" value="<?= $producto['precio_base'] ?? 0 ?>" required>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label for="tipo_precio" class="form-label">Tipo de Precio</label>
                    <select class="form-select" id="tipo_precio" name="tipo_precio">
                        <option value="fijo" <?= ($producto['tipo_precio'] ?? 'fijo') === 'fijo' ? 'selected' : '' ?>>Fijo</option>
                        <option value="variable" <?= ($producto['tipo_precio'] ?? '') === 'variable' ? 'selected' : '' ?>>Variable (Cortinas/Toldos)</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label for="orden" class="form-label">Orden</label>
                <input type="number" class="form-control" id="orden" name="orden" value="<?= $producto['orden'] ?? 0 ?>">
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="es_material" id="es_material" <?= !empty($producto['es_material']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="es_material">Es material (para recetas)</label>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="activo" name="activo" <?= ($producto['activo'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="mostrar_ecommerce" name="mostrar_ecommerce" <?= ($producto['mostrar_ecommerce'] ?? 1) ? 'checked' : '' ?>>
                <label class="form-check-label" for="mostrar_ecommerce">Mostrar en Ecommerce</label>
            </div>

            <div class="d-flex gap-2">
                <a href="productos.php" class="btn btn-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary">Guardar</button>
                <?php if ($id > 0): ?>
                    <a href="productos_atributos.php?producto_id=<?= $id ?>" class="btn btn-info">⚙️ Atributos</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
