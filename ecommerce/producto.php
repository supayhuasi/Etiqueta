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

// Obtener imágenes del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_imagenes 
    WHERE producto_id = ? 
    ORDER BY orden, es_principal DESC
");
$stmt->execute([$producto_id]);
$imagenes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener atributos del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_atributos 
    WHERE producto_id = ? 
    ORDER BY orden
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Función para encontrar el precio más cercano
function encontrarPrecioCercano($alto, $ancho, $matriz_precios) {
    $precio_encontrado = null;
    $distancia_minima = PHP_INT_MAX;
    
    foreach ($matriz_precios as $item) {
        $distancia = abs($item['alto_cm'] - $alto) + abs($item['ancho_cm'] - $ancho);
        if ($distancia < $distancia_minima) {
            $distancia_minima = $distancia;
            $precio_encontrado = [
                'precio' => $item['precio'],
                'alto_original' => $item['alto_cm'],
                'ancho_original' => $item['ancho_cm']
            ];
        }
    }
    
    return $precio_encontrado;
}

// Procesar agregar al carrito
$error = null;
$mensaje = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $cantidad = intval($_POST['cantidad'] ?? 1);
    $alto = intval($_POST['alto'] ?? 0);
    $ancho = intval($_POST['ancho'] ?? 0);
    $atributos_seleccionados = [];
    
    if ($cantidad < 1) $cantidad = 1;
    
    // Validar medidas si es producto variable
    $precio = $producto['precio_base'];
    $medidas_info = null;
    
    if ($producto['tipo_precio'] === 'variable') {
        if ($alto <= 0 || $ancho <= 0) {
            $error = "Debe especificar alto y ancho";
        } else {
            $precio_cercano = encontrarPrecioCercano($alto, $ancho, $matriz_precios);
            if ($precio_cercano) {
                $precio = $precio_cercano['precio'];
                $medidas_info = $precio_cercano;
            } else {
                $error = "No hay precios disponibles para esas medidas";
            }
        }
    }
    
    // Validar atributos obligatorios
    if (!isset($error)) {
        foreach ($atributos as $attr) {
            if ($attr['es_obligatorio']) {
                $valor = $_POST['attr_' . $attr['id']] ?? '';
                if (empty($valor)) {
                    $error = "El atributo '{$attr['nombre']}' es obligatorio";
                    break;
                }
                $atributos_seleccionados[$attr['id']] = $valor;
            } else {
                $valor = $_POST['attr_' . $attr['id']] ?? '';
                if (!empty($valor)) {
                    $atributos_seleccionados[$attr['id']] = $valor;
                }
            }
        }
    }
    
    if (!isset($error)) {
        // Agregar al carrito
        if (!isset($_SESSION['carrito'])) {
            $_SESSION['carrito'] = [];
        }
        
        $attrs_json = json_encode($atributos_seleccionados);
        $item_key = $producto_id . '_' . $alto . '_' . $ancho . '_' . md5($attrs_json);
        
        if (isset($_SESSION['carrito'][$item_key])) {
            $_SESSION['carrito'][$item_key]['cantidad'] += $cantidad;
        } else {
            $_SESSION['carrito'][$item_key] = [
                'id' => $producto_id,
                'nombre' => $producto['nombre'],
                'precio' => $precio,
                'cantidad' => $cantidad,
                'alto' => $alto,
                'ancho' => $ancho,
                'medidas_originales' => $medidas_info,
                'atributos' => $atributos_seleccionados,
                'imagen' => !empty($imagenes) ? $imagenes[0]['imagen'] : $producto['imagen']
            ];
        }
        
        $mensaje = "Producto agregado al carrito ✓";
    }
}
?>

<div class="container py-5">
    <div class="row">
        <!-- Galería de imágenes -->
        <div class="col-md-5">
            <?php if (!empty($imagenes)): ?>
                <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner rounded overflow-hidden">
                        <?php foreach ($imagenes as $idx => $img): ?>
                            <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                                <img src="uploads/<?= htmlspecialchars($img['imagen']) ?>" 
                                     class="d-block w-100" 
                                     alt="<?= htmlspecialchars($producto['nombre']) ?>"
                                     style="object-fit: cover; height: 500px;">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (count($imagenes) > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#imageCarousel" data-bs-slide="prev">
                            <span class="carousel-control-prev-icon"></span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#imageCarousel" data-bs-slide="next">
                            <span class="carousel-control-next-icon"></span>
                        </button>
                        
                        <!-- Miniaturas -->
                        <div class="row mt-3 g-2">
                            <?php foreach ($imagenes as $idx => $img): ?>
                                <div class="col-4 col-sm-3">
                                    <img src="uploads/<?= htmlspecialchars($img['imagen']) ?>" 
                                         class="img-thumbnail cursor-pointer" 
                                         alt="Miniatura"
                                         style="cursor: pointer; object-fit: cover; height: 80px;">
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 500px;">
                    <div class="text-center">
                        <span style="font-size: 100px;">📦</span>
                        <p class="text-muted mt-2">Sin imágenes disponibles</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Información del producto -->
        <div class="col-md-7">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="tienda.php">Tienda</a></li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') ?></li>
                </ol>
            </nav>

            <h1><?= htmlspecialchars($producto['nombre']) ?></h1>
            
            <p class="text-muted" style="font-size: 0.95rem;">
                <?= nl2br(htmlspecialchars($producto['descripcion'])) ?>
            </p>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= $mensaje ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= $error ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mt-4 p-4">
                <form method="POST">
                    <!-- Mostrar precio base -->
                    <div class="mb-4 pb-3 border-bottom">
                        <h3 id="precio_display" class="text-primary">
                            Precio: <strong>$<?= number_format($producto['precio_base'], 2, ',', '.') ?></strong>
                        </h3>
                        <small class="text-muted" id="medidas_info" style="display: none;"></small>
                    </div>

                    <!-- Medidas si es variable -->
                    <?php if ($producto['tipo_precio'] === 'variable'): ?>
                        <div class="alert alert-info mb-4">
                            <h6 class="mb-2">📏 Precio según medidas</h6>
                            <p class="mb-0 small">El precio se calcula automáticamente según el alto y ancho seleccionado</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="alto" class="form-label fw-bold">Alto (cm) *</label>
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
                                <label for="ancho" class="form-label fw-bold">Ancho (cm) *</label>
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
                    <?php endif; ?>

                    <!-- Atributos personalizados -->
                    <?php foreach ($atributos as $attr): ?>
                        <div class="mb-3">
                            <label for="attr_<?= $attr['id'] ?>" class="form-label">
                                <?= htmlspecialchars($attr['nombre']) ?>
                                <?php if ($attr['es_obligatorio']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($attr['tipo'] === 'text'): ?>
                                <input type="text" class="form-control" id="attr_<?= $attr['id'] ?>" 
                                       name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?>>
                            
                            <?php elseif ($attr['tipo'] === 'number'): ?>
                                <input type="number" class="form-control" id="attr_<?= $attr['id'] ?>" 
                                       name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?>>
                            
                            <?php elseif ($attr['tipo'] === 'select'): ?>
                                <select class="form-select" id="attr_<?= $attr['id'] ?>" 
                                        name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?>>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach (array_map('trim', explode(',', $attr['valores'])) as $valor): ?>
                                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($valor) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <div class="mb-4">
                        <label for="cantidad" class="form-label fw-bold">Cantidad</label>
                        <input type="number" class="form-control" id="cantidad" name="cantidad" value="1" min="1" max="100">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg fw-bold">🛒 Agregar al Carrito</button>
                        <a href="tienda.php" class="btn btn-outline-secondary">Continuar Comprando</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Matriz de precios expandible -->
    <?php if ($producto['tipo_precio'] === 'variable' && !empty($matriz_precios)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <div class="accordion" id="accordionMatriz">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#matrizPrecios">
                                📊 Ver Matriz de Precios Completa
                            </button>
                        </h2>
                        <div id="matrizPrecios" class="accordion-collapse collapse" data-bs-parent="#accordionMatriz">
                            <div class="accordion-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">Alto \ Ancho</th>
                                                <?php 
                                                $anchos_unicos = array_unique(array_column($matriz_precios, 'ancho_cm'));
                                                sort($anchos_unicos);
                                                foreach ($anchos_unicos as $ancho): 
                                                ?>
                                                    <th class="text-center"><?= $ancho ?> cm</th>
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
                                                    <td class="fw-bold text-center"><?= $alto ?> cm</td>
                                                    <?php foreach ($anchos_unicos as $ancho): 
                                                        $precio_item = null;
                                                        foreach ($matriz_precios as $mp) {
                                                            if ($mp['alto_cm'] == $alto && $mp['ancho_cm'] == $ancho) {
                                                                $precio_item = $mp['precio'];
                                                                break;
                                                            }
                                                        }
                                                    ?>
                                                        <td class="text-center">
                                                            <?php if ($precio_item !== null): ?>
                                                                <strong>$<?= number_format($precio_item, 2, ',', '.') ?></strong>
                                                            <?php else: ?>
                                                                <span class="text-muted">-</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
const matrizPrecios = <?= json_encode($matriz_precios) ?>;
const precioBase = <?= $producto['precio_base'] ?>;

function actualizarPrecio() {
    const alto = parseInt(document.getElementById('alto').value) || 0;
    const ancho = parseInt(document.getElementById('ancho').value) || 0;
    
    if (alto > 0 && ancho > 0) {
        let precioEncontrado = null;
        let distanciaMinima = Infinity;
        let medidaOriginal = null;
        
        // Encontrar el precio más cercano a las medidas seleccionadas
        matrizPrecios.forEach(item => {
            const distancia = Math.abs(item.alto_cm - alto) + Math.abs(item.ancho_cm - ancho);
            if (distancia < distanciaMinima) {
                distanciaMinima = distancia;
                precioEncontrado = parseFloat(item.precio);
                medidaOriginal = `${item.alto_cm}×${item.ancho_cm}cm`;
            }
        });
        
        if (precioEncontrado !== null) {
            const precioFormatado = precioEncontrado.toLocaleString('es-AR', {
                style: 'currency',
                currency: 'ARS',
                minimumFractionDigits: 2
            }).replace('ARS', '$');
            
            document.getElementById('precio_display').innerHTML = 
                `Precio: <strong>${precioFormatado}</strong>`;
            
            const infoDiv = document.getElementById('medidas_info');
            if (distanciaMinima > 0) {
                infoDiv.textContent = `(Redondeado a medida más cercana: ${medidaOriginal})`;
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }
    }
}
</script>

<?php require 'includes/footer.php'; ?>
