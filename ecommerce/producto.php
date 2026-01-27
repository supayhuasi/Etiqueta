<?php
require 'config.php';
require 'includes/header.php';

$producto_id = $_GET['id'] ?? 0;

// Obtener producto
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM ecommerce_productos p
    LEFT JOIN ecommerce_categorias c ON p.categoria_id = c.id
    WHERE p.id = ? AND p.activo = 1
");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado");
}

// Obtener matriz de precios si es producto variable
$matriz_precios = [];
if ($producto['tipo_precio'] === 'variable') {
    $stmt = $pdo->prepare("
        SELECT * FROM ecommerce_matriz_precios 
        WHERE producto_id = ? 
        ORDER BY alto_cm, ancho_cm
    ");
    $stmt->execute([$producto_id]);
    $matriz_precios = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Procesar agregar al carrito
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $cantidad = intval($_POST['cantidad'] ?? 1);
    $alto = intval($_POST['alto'] ?? 0);
    $ancho = intval($_POST['ancho'] ?? 0);
    
    if ($cantidad < 1) $cantidad = 1;
    
    // Validar medidas si es producto variable
    $precio = $producto['precio_base'];
    if ($producto['tipo_precio'] === 'variable') {
        if ($alto <= 0 || $ancho <= 0) {
            $error = "Debe especificar alto y ancho";
        } else {
            // Buscar precio en matriz
            $stmt = $pdo->prepare("
                SELECT precio FROM ecommerce_matriz_precios 
                WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?
            ");
            $stmt->execute([$producto_id, $alto, $ancho]);
            $resultado = $stmt->fetch();
            if ($resultado) {
                $precio = $resultado['precio'];
            } else {
                $error = "Combinación de medidas no disponible";
            }
        }
    }
    
    if (!isset($error)) {
        // Agregar al carrito
        if (!isset($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
        }
        
        $item_key = $producto_id . '_' . $alto . '_' . $ancho;
        
        if (isset($_SESSION['carrito'][$item_key])) {
            $_SESSION['carrito'][$item_key]['cantidad'] += $cantidad;
        } else {
            $_SESSION['carrito'][$item_key] = [
                'id' => $producto_id,
                'nombre' => $producto['nombre'],
                'precio' => $precio,
                'cantidad' => $cantidad,
                'alto' => $alto,
                'ancho' => $ancho
            ];
        }
        
        $mensaje = "Producto agregado al carrito";
    }
}
?>

<div class="container py-5">
    <div class="row">
        <!-- Imagen del producto -->
        <div class="col-md-5">
            <?php if (!empty($producto['imagen'])): ?>
                <img src="uploads/<?= htmlspecialchars($producto['imagen']) ?>" class="img-fluid rounded" alt="<?= htmlspecialchars($producto['nombre']) ?>">
            <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 400px;">
                    <span class="text-muted" style="font-size: 80px;">📦</span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Información del producto -->
        <div class="col-md-7">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="tienda.php">Tienda</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') ?></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($producto['nombre']) ?></li>
                </ol>
            </nav>

            <h1><?= htmlspecialchars($producto['nombre']) ?></h1>
            
            <p class="text-muted"><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success">✓ <?= $mensaje ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">✗ <?= $error ?></div>
            <?php endif; ?>

            <div class="card mt-4 p-4">
                <form method="POST">
                    <?php if ($producto['tipo_precio'] === 'variable'): ?>
                        <div class="alert alert-info">
                            <h6>📏 Este producto tiene precio según medidas</h6>
                            <p>Selecciona el alto y ancho deseado</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="alto" class="form-label">Alto (cm) *</label>
                                <select class="form-select" id="alto" name="alto" required onchange="actualizarPrecio()">
                                    <option value="">Seleccionar alto...</option>
                                    <?php 
                                    $altos = array_unique(array_column($matriz_precios, 'alto_cm'));
                                    sort($altos);
                                    foreach ($altos as $a): 
                                    ?>
                                        <option value="<?= $a ?>"><?= $a ?> cm</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="ancho" class="form-label">Ancho (cm) *</label>
                                <select class="form-select" id="ancho" name="ancho" required onchange="actualizarPrecio()">
                                    <option value="">Seleccionar ancho...</option>
                                    <?php 
                                    $anchos = array_unique(array_column($matriz_precios, 'ancho_cm'));
                                    sort($anchos);
                                    foreach ($anchos as $a): 
                                    ?>
                                        <option value="<?= $a ?>"><?= $a ?> cm</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Matriz de precios -->
                        <div class="price-matrix mb-4">
                            <h6>Matriz de Precios</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Alto \ Ancho</th>
                                            <?php 
                                            $anchos_unicos = array_unique(array_column($matriz_precios, 'ancho_cm'));
                                            sort($anchos_unicos);
                                            foreach ($anchos_unicos as $ancho): 
                                            ?>
                                                <th><?= $ancho ?> cm</th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                        $altos_unicos = array_unique(array_column($matriz_precios, 'alto_cm'));
                                        sort($altos_unicos);
                                        foreach ($altos_unicos as $alto): 
                                        ?>
                                            <tr>
                                                <td><strong><?= $alto ?> cm</strong></td>
                                                <?php foreach ($anchos_unicos as $ancho): 
                                                    $precio_item = null;
                                                    foreach ($matriz_precios as $mp) {
                                                        if ($mp['alto_cm'] == $alto && $mp['ancho_cm'] == $ancho) {
                                                            $precio_item = $mp['precio'];
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                    <td class="price-cell">
                                                        <?php if ($precio_item !== null): ?>
                                                            $<?= number_format($precio_item, 2, ',', '.') ?>
                                                        <?php else: ?>
                                                            N/A
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php else: ?>
                        <h3 class="text-primary mb-3">Precio: $<?= number_format($producto['precio_base'], 2, ',', '.') ?></h3>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="cantidad" class="form-label">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" value="1" min="1" max="100">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">🛒 Agregar al Carrito</button>
                        <a href="tienda.php" class="btn btn-outline-secondary">Continuar Comprando</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function actualizarPrecio() {
    const alto = document.getElementById('alto').value;
    const ancho = document.getElementById('ancho').value;
    
    if (alto && ancho) {
        console.log('Medidas seleccionadas: ' + alto + 'x' + ancho);
        // Aquí se podría hacer una llamada AJAX para actualizar el precio dinámicamente
    }
}
</script>

<?php require 'includes/footer.php'; ?>
