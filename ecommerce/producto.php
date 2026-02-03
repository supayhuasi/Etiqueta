<?php
require 'config.php';
require 'includes/header.php';
require 'includes/precios_publico.php';

$producto_id = $_GET['id'] ?? 0;

// Determinar la ruta correcta para las imágenes
$image_path = '../uploads/';

// Configuración de lista de precios pública
$lista_publica_id = obtener_lista_precio_publica($pdo);
$mapas_lista_publica = cargar_mapas_lista_publica($pdo, $lista_publica_id);

// Obtener producto
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre as categoria_nombre 
    FROM ecommerce_productos p
    LEFT JOIN ecommerce_categorias c ON p.categoria_id = c.id
        WHERE p.id = ? AND p.activo = 1 AND p.mostrar_ecommerce = 1
");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado");
}

$tipo_precio = strtolower(trim($producto['tipo_precio'] ?? 'fijo'));

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
if ($tipo_precio === 'variable') {
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
    
    if ($tipo_precio === 'variable') {
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

    // Aplicar lista de precios pública al precio base
    if (!isset($error)) {
        $precio_info = calcular_precio_publico(
            (int)$producto['id'],
            (int)($producto['categoria_id'] ?? 0),
            (float)$precio,
            $lista_publica_id,
            $mapas_lista_publica['items'],
            $mapas_lista_publica['categorias']
        );
        $precio = $precio_info['precio'];
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
                $costo_opcion = floatval($_POST['attr_costo_' . $attr['id']] ?? $attr['costo_adicional']);
                $atributos_seleccionados[$attr['id']] = [
                    'nombre' => $attr['nombre'],
                    'valor' => $valor,
                    'costo_adicional' => $costo_opcion
                ];
            } else {
                $valor = $_POST['attr_' . $attr['id']] ?? '';
                if (!empty($valor)) {
                    $costo_opcion = floatval($_POST['attr_costo_' . $attr['id']] ?? $attr['costo_adicional']);
                    $atributos_seleccionados[$attr['id']] = [
                        'nombre' => $attr['nombre'],
                        'valor' => $valor,
                        'costo_adicional' => $costo_opcion
                    ];
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
    <style>
        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
            color: #fff;
        }
        .btn-primary:hover,
        .btn-primary:focus {
            background-color: #0b5ed7;
            border-color: #0a58ca;
            color: #fff;
        }
        .btn-outline-secondary {
            color: #495057;
            border-color: #6c757d;
        }
        .btn-outline-secondary:hover,
        .btn-outline-secondary:focus {
            color: #fff;
            background-color: #6c757d;
            border-color: #6c757d;
        }
    </style>
    <div class="row">
        <!-- Galería de imágenes -->
        <div class="col-md-5">
            <?php 
            // Si no hay imágenes en la tabla dedicada, usar la imagen principal del producto
            if (empty($imagenes) && !empty($producto['imagen'])) {
                $imagenes = [['imagen' => $producto['imagen']]];
            }
            ?>
            <?php if (!empty($imagenes)): ?>
                <div id="imageCarousel" class="carousel slide" data-bs-ride="carousel">
                    <div class="carousel-inner rounded overflow-hidden">
                        <?php foreach ($imagenes as $idx => $img): ?>
                            <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
                                <img src="<?= $image_path . htmlspecialchars($img['imagen']) ?>" 
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
                                    <img src="<?= $image_path . htmlspecialchars($img['imagen']) ?>" 
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
                        <?php
                        $precio_info_base = calcular_precio_publico(
                            (int)$producto['id'],
                            (int)($producto['categoria_id'] ?? 0),
                            (float)$producto['precio_base'],
                            $lista_publica_id,
                            $mapas_lista_publica['items'],
                            $mapas_lista_publica['categorias']
                        );
                        ?>
                        <h3 id="precio_display" class="text-primary">
                            Precio:
                            <?php if ($precio_info_base['descuento_pct'] > 0): ?>
                                <span class="text-muted text-decoration-line-through">$<?= number_format($precio_info_base['precio_original'], 2, ',', '.') ?></span>
                            <?php endif; ?>
                            <strong>$<?= number_format($precio_info_base['precio'], 2, ',', '.') ?></strong>
                        </h3>
                        <small class="text-muted" id="medidas_info" style="display: none;"></small>
                    </div>

                    <!-- Medidas si es variable -->
                    <?php if ($tipo_precio === 'variable'): ?>
                        <div class="alert alert-info mb-4">
                            <h6 class="mb-2">📏 Precio según medidas</h6>
                            <p class="mb-0 small">Ingresa el ancho y alto deseado en centímetros. El sistema buscará el precio más cercano a esas medidas.</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="ancho" class="form-label fw-bold">Ancho (cm) *</label>
                                <input type="number" class="form-control" id="ancho" name="ancho" min="10" max="600" step="1" required onchange="actualizarPrecio()" onkeyup="actualizarPrecio()">
                                <small class="text-muted">Rango: 10 a 600 cm</small>
                            </div>
                            <div class="col-md-6">
                                <label for="alto" class="form-label fw-bold">Alto (cm) *</label>
                                <input type="number" class="form-control" id="alto" name="alto" min="10" max="600" step="1" required onchange="actualizarPrecio()" onkeyup="actualizarPrecio()">
                                <small class="text-muted">Rango: 10 a 600 cm</small>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Atributos personalizados -->
                    <?php $attr_index = 0; ?>
                    <?php foreach ($atributos as $attr): 
                        // Si es select, obtener opciones con imágenes
                        $opciones_attr = [];
                        if ($attr['tipo'] === 'select') {
                            try {
                                // Verificar si la tabla existe
                                $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
                                if ($stmt->rowCount() > 0) {
                                    $stmt = $pdo->prepare("
                                        SELECT * FROM ecommerce_atributo_opciones 
                                        WHERE atributo_id = ? 
                                        ORDER BY orden
                                    ");
                                    $stmt->execute([$attr['id']]);
                                    $opciones_attr = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                }
                            } catch (Exception $e) {
                                // Tabla no existe, usar opciones vacías
                                $opciones_attr = [];
                            }
                        }
                    ?>
                        <div class="mb-3 attr-step" data-step="<?= $attr_index ?>">
                            <label for="attr_<?= $attr['id'] ?>" class="form-label">
                                <?= htmlspecialchars($attr['nombre']) ?>
                                <?php if ($attr['es_obligatorio']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </label>
                            
                            <?php if ($attr['tipo'] === 'text'): ?>
                                <input type="text" class="form-control" id="attr_<?= $attr['id'] ?>" 
                                       name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?> onchange="actualizarPrecio()" onkeyup="actualizarPrecio()">
                            
                            <?php elseif ($attr['tipo'] === 'number'): ?>
                                <input type="number" class="form-control" id="attr_<?= $attr['id'] ?>" 
                                       name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?> onchange="actualizarPrecio()" onkeyup="actualizarPrecio()">

                            <?php elseif ($attr['tipo'] === 'color'): ?>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" class="form-control form-control-color" id="attr_<?= $attr['id'] ?>" 
                                           name="attr_<?= $attr['id'] ?>" value="#000000" <?= $attr['es_obligatorio'] ? 'required' : '' ?> onchange="actualizarPrecio()">
                                    <div class="border rounded" id="color_preview_<?= $attr['id'] ?>" style="width: 36px; height: 36px; background-color: #000000;"></div>
                                </div>
                                <script>
                                    (function() {
                                        const colorInput = document.getElementById('attr_<?= $attr['id'] ?>');
                                        const preview = document.getElementById('color_preview_<?= $attr['id'] ?>');
                                        if (colorInput && preview) {
                                            const updatePreview = () => { preview.style.backgroundColor = colorInput.value || '#000000'; };
                                            colorInput.addEventListener('input', updatePreview);
                                            updatePreview();
                                        }
                                    })();
                                </script>
                            
                            <?php elseif ($attr['tipo'] === 'select'): ?>
                                <?php if (!empty($opciones_attr)): ?>
                                    <!-- Selector con imágenes -->
                                    <style>
                                        .attr-option {
                                            border: 2px solid #ddd;
                                            border-radius: 6px;
                                            padding: 4px;
                                            transition: all 0.2s ease;
                                            background: #fff;
                                        }
                                        .attr-option.selected {
                                            border-color: #0d6efd;
                                            box-shadow: 0 0 0 2px rgba(13,110,253,.2);
                                            background: #e7f1ff;
                                        }
                                    </style>
                                    <div class="d-flex gap-2 flex-wrap mb-3">
                                        <input type="hidden" id="attr_<?= $attr['id'] ?>" name="attr_<?= $attr['id'] ?>" 
                                               <?= $attr['es_obligatorio'] ? 'required' : '' ?>>
                                        <input type="hidden" id="attr_costo_<?= $attr['id'] ?>" name="attr_costo_<?= $attr['id'] ?>" value="0">
                                        <?php foreach ($opciones_attr as $opcion): ?>
                                            <div class="position-relative">
                                                <label class="cursor-pointer position-relative" style="cursor: pointer;">
                                                    <input type="radio" name="attr_<?= $attr['id'] ?>" value="<?= htmlspecialchars($opcion['nombre']) ?>" 
                                                           class="d-none attr-radio" data-attr-id="<?= $attr['id'] ?>" data-costo="<?= (float)($opcion['costo_adicional'] ?? 0) ?>"
                                                           onchange="actualizarPrecio()">
                                                    <div class="attr-option position-relative" id="option_<?= $opcion['id'] ?>" style="cursor: pointer;">
                                                        <?php if (!empty($opcion['color']) && preg_match('/^#[0-9A-F]{6}$/i', $opcion['color'])): ?>
                                                            <div class="rounded" style="width: 80px; height: 80px; background-color: <?= htmlspecialchars($opcion['color']) ?>; border: 1px solid #ddd;"></div>
                                                        <?php elseif (!empty($opcion['imagen'])): ?>
                                                            <img src="<?= $image_path . 'atributos/' . htmlspecialchars($opcion['imagen']) ?>" 
                                                                 alt="<?= htmlspecialchars($opcion['nombre']) ?>" 
                                                                 style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px; display: block;">
                                                        <?php else: ?>
                                                            <div class="d-flex align-items-center justify-content-center bg-light rounded" 
                                                                 style="width: 80px; height: 80px;">
                                                                <small class="text-center text-muted"><?= htmlspecialchars($opcion['nombre']) ?></small>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if ((float)($opcion['costo_adicional'] ?? 0) > 0): ?>
                                                            <span class="badge bg-success position-absolute" style="top: -8px; right: -8px;">+$<?= number_format($opcion['costo_adicional'], 2) ?></span>
                                                        <?php endif; ?>
                                                        <small class="d-block text-center mt-1 text-muted"><?= htmlspecialchars($opcion['nombre']) ?></small>
                                                    </div>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <script>
                                        document.querySelectorAll('input[name="attr_<?= $attr['id'] ?>"]').forEach(radio => {
                                            radio.addEventListener('change', function() {
                                                // Actualizar visual de selección
                                                document.querySelectorAll('input[name="attr_<?= $attr['id'] ?>"]').forEach(r => {
                                                    const option = r.closest('label').querySelector('div[id^="option_"]');
                                                    if (option) {
                                                        option.classList.remove('selected');
                                                    }
                                                });
                                                const selectedOption = this.closest('label').querySelector('div[id^="option_"]');
                                                if (selectedOption) {
                                                    selectedOption.classList.add('selected');
                                                }
                                                // Actualizar el hidden input
                                                document.getElementById('attr_<?= $attr['id'] ?>').value = this.value;
                                                const costoHidden = document.getElementById('attr_costo_<?= $attr['id'] ?>');
                                                if (costoHidden) {
                                                    costoHidden.value = this.dataset.costo || '0';
                                                }
                                            });
                                        });
                                    </script>
                                <?php else: ?>
                                    <select class="form-select" id="attr_<?= $attr['id'] ?>" 
                                            name="attr_<?= $attr['id'] ?>" <?= $attr['es_obligatorio'] ? 'required' : '' ?> onchange="actualizarPrecio()">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                    <small class="text-muted">No hay opciones configuradas para este atributo</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php $attr_index++; ?>
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

</div>

<script>
const matrizPrecios = <?= json_encode($matriz_precios) ?>;
const atributosData = <?= json_encode($atributos) ?>;
const precioBase = <?= $producto['precio_base'] ?>;
const listaId = <?= (int)$lista_publica_id ?>;
const listaItems = <?= json_encode($mapas_lista_publica['items']) ?>;
const listaCategorias = <?= json_encode($mapas_lista_publica['categorias']) ?>;
const productoId = <?= (int)$producto['id'] ?>;
const categoriaId = <?= (int)($producto['categoria_id'] ?? 0) ?>;

function aplicarDescuento(precioBaseActual) {
    let descuento = 0;
    let precioFinal = precioBaseActual;

    if (listaId && listaItems?.[productoId]) {
        const item = listaItems[productoId];
        const precioNuevo = parseFloat(item.precio_nuevo || 0);
        const descItem = parseFloat(item.descuento_porcentaje || 0);

        if (precioNuevo > 0) {
            precioFinal = precioNuevo;
            if (precioBaseActual > 0) {
                descuento = Math.max(0, Math.round((1 - (precioNuevo / precioBaseActual)) * 10000) / 100);
            }
        } else if (descItem > 0) {
            descuento = descItem;
            precioFinal = precioBaseActual * (1 - descuento / 100);
        }
    }

    if (descuento <= 0 && listaId && categoriaId && listaCategorias?.[categoriaId]) {
        const descCat = parseFloat(listaCategorias[categoriaId] || 0);
        if (descCat > 0) {
            descuento = descCat;
            precioFinal = precioBaseActual * (1 - descuento / 100);
        }
    }

    return { precio: precioFinal, descuento };
}

function actualizarPrecio() {
    const alto = parseInt(document.getElementById('alto').value) || 0;
    const ancho = parseInt(document.getElementById('ancho').value) || 0;
    
    let precioTotal = precioBase;
    let medidaOriginal = null;
    let distanciaMinima = Infinity;
    let costosAdicionales = [];
    
    // Si es producto variable, buscar el precio más cercano
    if (matrizPrecios.length > 0) {
        if (alto > 0 && ancho > 0) {
            // Encontrar el precio más cercano a las medidas seleccionadas
            matrizPrecios.forEach(item => {
                const distancia = Math.abs(item.alto_cm - alto) + Math.abs(item.ancho_cm - ancho);
                if (distancia < distanciaMinima) {
                    distanciaMinima = distancia;
                    precioTotal = parseFloat(item.precio);
                    medidaOriginal = `${item.alto_cm}×${item.ancho_cm}cm`;
                }
            });
        } else {
            // No hay medidas, mostrar precio base
            precioTotal = precioBase;
            medidaOriginal = null;
        }
    }
    
    // Aplicar descuento de lista pública
    const precioConDescuento = aplicarDescuento(precioTotal);
    let precioFinal = precioConDescuento.precio;

    // Agregar costos adicionales de atributos
    atributosData.forEach(attr => {
        const valorInput = document.getElementById('attr_' + attr.id);
        if (valorInput && valorInput.value) {
            const costoHidden = document.getElementById('attr_costo_' + attr.id);
            const costoOpcion = costoHidden ? parseFloat(costoHidden.value || 0) : 0;
            if (costoOpcion > 0) {
                precioFinal += costoOpcion;
                costosAdicionales.push({
                    nombre: valorInput.value,
                    costo: costoOpcion
                });
            } else if (attr.costo_adicional > 0) {
                precioFinal += parseFloat(attr.costo_adicional);
                costosAdicionales.push({
                    nombre: attr.nombre,
                    costo: parseFloat(attr.costo_adicional)
                });
            }
        }
    });
    
    const precioFormatado = precioFinal.toLocaleString('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2
    }).replace('ARS', '$');

    let precioDisplayHTML = '';
    
    if (precioConDescuento.descuento > 0) {
        const precioOriginalFormatado = precioTotal.toLocaleString('es-AR', {
            style: 'currency',
            currency: 'ARS',
            minimumFractionDigits: 2
        }).replace('ARS', '$');
        precioDisplayHTML = `Precio: <span class="text-muted text-decoration-line-through">${precioOriginalFormatado}</span> <strong>${precioFormatado}</strong>`;
    } else {
        precioDisplayHTML = `Precio: <strong>${precioFormatado}</strong>`;
    }
    
    // Agregar desglose de costos adicionales si existen
    if (costosAdicionales.length > 0) {
        precioDisplayHTML += '<br><small class="text-muted mt-2 d-block">';
        costosAdicionales.forEach((costo, idx) => {
            precioDisplayHTML += `<span class="badge bg-light text-dark ms-${idx > 0 ? 2 : 0}">+ ${costo.nombre}: $${costo.costo.toFixed(2)}</span>`;
        });
        precioDisplayHTML += '</small>';
    }
    
    document.getElementById('precio_display').innerHTML = precioDisplayHTML;
    
    const infoDiv = document.getElementById('medidas_info');
    if (medidaOriginal && distanciaMinima > 0) {
        infoDiv.textContent = `(Redondeado a medida más cercana: ${medidaOriginal})`;
        infoDiv.style.display = 'block';
    } else if (medidaOriginal && distanciaMinima === 0) {
        infoDiv.style.display = 'none';
    } else {
        infoDiv.style.display = 'none';
    }
}

// Actualizar precio cuando cambia un atributo con costo adicional
document.addEventListener('DOMContentLoaded', function() {
    document.addEventListener('change', function(e) {
        if (e.target && e.target.classList.contains('attr-radio')) {
            const name = e.target.name;
            document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
                const option = r.closest('label')?.querySelector('.attr-option');
                if (option) option.classList.remove('selected');
            });
            const selectedOption = e.target.closest('label')?.querySelector('.attr-option');
            if (selectedOption) selectedOption.classList.add('selected');
        }
    });

    atributosData.forEach(attr => {
        if (attr.costo_adicional > 0) {
            const valorInput = document.getElementById('attr_' + attr.id);
            if (valorInput) {
                valorInput.addEventListener('change', actualizarPrecio);
                valorInput.addEventListener('keyup', actualizarPrecio);
            }
        }
    });

});
</script>

<?php require 'includes/footer.php'; ?>
