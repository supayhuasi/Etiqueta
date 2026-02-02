<?php
// Endpoint JSON para obtener atributos (usado por cotizaciones)
if (isset($_GET['accion']) && $_GET['accion'] === 'obtener' && isset($_GET['producto_id'])) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user']) || ($_SESSION['rol'] ?? '') !== 'admin') {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Acceso denegado']);
        exit;
    }

    require '../../config.php';
    header('Content-Type: application/json');

    $producto_id = intval($_GET['producto_id']);

    $stmt = $pdo->prepare("
        SELECT id, nombre, tipo, valores, costo_adicional, es_obligatorio, orden
        FROM ecommerce_producto_atributos 
        WHERE producto_id = ? 
        ORDER BY orden
    ");
    $stmt->execute([$producto_id]);
    $atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cargar opciones si existen
    $opciones_map = [];
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
        if ($stmt->rowCount() > 0 && !empty($atributos)) {
            $ids = array_column($atributos, 'id');
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("
                SELECT id, atributo_id, nombre, color, imagen, costo_adicional
                FROM ecommerce_atributo_opciones
                WHERE atributo_id IN ($placeholders)
                ORDER BY orden
            ");
            $stmt->execute($ids);
            $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($opciones as $op) {
                $opciones_map[$op['atributo_id']][] = $op;
            }
        }
    } catch (Exception $e) {
        $opciones_map = [];
    }

    foreach ($atributos as &$attr) {
        $attr['opciones'] = $opciones_map[$attr['id']] ?? [];
    }
    unset($attr);

    echo json_encode([
        'atributos' => $atributos
    ]);
    exit;
}

require 'includes/header.php';

$producto_id = $_GET['producto_id'] ?? 0;
if ($producto_id <= 0) die("Producto no especificado");

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) die("Producto no encontrado");

// Inicializar variables
$mensaje = '';
$error = '';
$atributo_id = $_GET['atributo_id'] ?? 0;
$editar_atributo_id = $_GET['editar_atributo'] ?? 0;
$editar_opcion_id = $_GET['editar_opcion'] ?? 0;

// Cargar datos del atributo a editar
$atributo_editar = null;
if ($editar_atributo_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_producto_atributos WHERE id = ? AND producto_id = ?");
    $stmt->execute([$editar_atributo_id, $producto_id]);
    $atributo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Cargar datos de la opción a editar
$opcion_editar = null;
if ($editar_opcion_id > 0 && $atributo_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_atributo_opciones WHERE id = ? AND atributo_id = ?");
    $stmt->execute([$editar_opcion_id, $atributo_id]);
    $opcion_editar = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Obtener atributos del producto
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_producto_atributos 
    WHERE producto_id = ? 
    ORDER BY orden
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener opciones de atributo si se está editando
$opciones = [];
if ($atributo_id > 0) {
    try {
        // Verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
        if ($stmt->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_atributo_opciones 
                WHERE atributo_id = ? 
                ORDER BY orden
            ");
            $stmt->execute([$atributo_id]);
            $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        // Tabla aún no existe, ignorar
    }
}

// Procesar agregar/editar atributo
if (($_POST['accion'] ?? '') === 'guardar_atributo') {
    try {
        $id = intval($_POST['id'] ?? 0);
        $nombre = $_POST['nombre'] ?? '';
        $tipo = $_POST['tipo'] ?? '';
        $costo_adicional = floatval($_POST['costo_adicional'] ?? 0);
        $es_obligatorio = isset($_POST['es_obligatorio']) ? 1 : 0;
        $orden = intval($_POST['orden'] ?? 0);
        
        if (empty($nombre)) {
            $error = "El nombre es obligatorio";
        } elseif (empty($tipo)) {
            $error = "El tipo es obligatorio";
        } else {
            if ($id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_producto_atributos 
                    SET nombre = ?, tipo = ?, costo_adicional = ?, es_obligatorio = ?, orden = ?
                    WHERE id = ? AND producto_id = ?
                ");
                $stmt->execute([$nombre, $tipo, $costo_adicional, $es_obligatorio, $orden, $id, $producto_id]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_producto_atributos (producto_id, nombre, tipo, costo_adicional, es_obligatorio, orden, valores)
                    VALUES (?, ?, ?, ?, ?, ?, '')
                ");
                $stmt->execute([$producto_id, $nombre, $tipo, $costo_adicional, $es_obligatorio, $orden]);
                $atributo_id = $pdo->lastInsertId();
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
if (($_POST['accion'] ?? '') === 'eliminar_atributo') {
    try {
        $id = intval($_POST['id']);
        $pdo->prepare("DELETE FROM ecommerce_producto_atributos WHERE id = ? AND producto_id = ?")
            ->execute([$id, $producto_id]);
        
        $atributo_id = 0;
        $opciones = [];
        
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

// Procesar agregar opción a atributo
if (($_POST['accion'] ?? '') === 'guardar_opcion') {
    try {
        // Verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
        if ($stmt->rowCount() === 0) {
            throw new Exception("La tabla de opciones aún no existe. Ejecuta la migración: admin/migrar_atributo_opciones.php");
        }
        
        $opcion_id = intval($_POST['opcion_id'] ?? 0);
        $nombre = $_POST['opcion_nombre'];
        $color = $_POST['opcion_color'] ?? null;
        $orden = intval($_POST['opcion_orden'] ?? 0);
        $costo_opcion = floatval($_POST['opcion_costo'] ?? 0);
        
        // Validar color hexadecimal si se proporciona
        if ($color && !preg_match('/^#[0-9A-F]{6}$/i', $color)) {
            $color = null; // Ignorar color inválido
        }
        
        // Manejo de upload de imagen
        $imagen = null;
        if (isset($_FILES['imagen']) && $_FILES['imagen']['size'] > 0) {
            $file = $_FILES['imagen'];
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array(strtolower($ext), $allowed)) {
                throw new Exception("Formato de imagen no permitido");
            }
            
            $filename = 'atributo_' . $atributo_id . '_' . time() . '.' . $ext;
            $dir = '../../uploads/atributos/';
            
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            
            if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
                $imagen = $filename;
            } else {
                throw new Exception("Error al subir la imagen");
            }
        }
        
        if (empty($nombre)) {
            $error = "El nombre de la opción es obligatorio";
        } else {
            if ($opcion_id > 0) {
                // Actualizar opción existente
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_atributo_opciones 
                    SET nombre = ?, color = ?, orden = ?, costo_adicional = ?
                    WHERE id = ? AND atributo_id = ?
                ");
                $stmt->execute([$nombre, $color, $orden, $costo_opcion, $opcion_id, $atributo_id]);
                
                // Si hay nueva imagen, actualizar
                if ($imagen) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_atributo_opciones SET imagen = ? WHERE id = ?");
                    $stmt->execute([$imagen, $opcion_id]);
                }
                
                // Limpiar edición
                $editar_opcion_id = 0;
                $opcion_editar = null;
            } else {
                // Nueva opción
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_atributo_opciones (atributo_id, nombre, imagen, color, costo_adicional, orden)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$atributo_id, $nombre, $imagen, $color, $costo_opcion, $orden]);
            }
            
            // Recargar opciones
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_atributo_opciones 
                WHERE atributo_id = ? 
                ORDER BY orden
            ");
            $stmt->execute([$atributo_id]);
            $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mensaje = "Opción guardada";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Procesar eliminación de opción
if (($_POST['accion'] ?? '') === 'eliminar_opcion') {
    try {
        // Verificar si la tabla existe
        $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_atributo_opciones'");
        if ($stmt->rowCount() > 0) {
            $opcion_id = intval($_POST['opcion_id'] ?? 0);
            $pdo->prepare("DELETE FROM ecommerce_atributo_opciones WHERE id = ? AND atributo_id = ?")
                ->execute([$opcion_id, $atributo_id]);
            
            // Recargar opciones
            $stmt = $pdo->prepare("
                SELECT * FROM ecommerce_atributo_opciones 
                WHERE atributo_id = ? 
                ORDER BY orden
            ");
            $stmt->execute([$atributo_id]);
            $opciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $mensaje = "Opción eliminada";
        }
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
                <h5><?= $atributo_editar ? 'Editar Atributo' : 'Nuevo Atributo' ?></h5>
                <?php if ($atributo_editar): ?>
                    <a href="?producto_id=<?= $producto_id ?>" class="btn btn-sm btn-secondary">Cancelar edición</a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="accion" value="guardar_atributo">
                    <?php if ($atributo_editar): ?>
                        <input type="hidden" name="id" value="<?= $atributo_editar['id'] ?>">
                    <?php endif; ?>
                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre *</label>
                        <input type="text" class="form-control" id="nombre" name="nombre" 
                               value="<?= htmlspecialchars($atributo_editar['nombre'] ?? '') ?>" 
                               placeholder="Ej: Alto" required>
                    </div>
                    <div class="mb-3">
                        <label for="tipo" class="form-label">Tipo *</label>
                        <select class="form-select" id="tipo" name="tipo" required>
                            <option value="">Seleccionar...</option>
                            <option value="text" <?= ($atributo_editar['tipo'] ?? '') === 'text' ? 'selected' : '' ?>>Texto</option>
                            <option value="number" <?= ($atributo_editar['tipo'] ?? '') === 'number' ? 'selected' : '' ?>>Número</option>
                            <option value="select" <?= ($atributo_editar['tipo'] ?? '') === 'select' ? 'selected' : '' ?>>Selección</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="costo_adicional" class="form-label">Costo Adicional ($)</label>
                        <input type="number" class="form-control" id="costo_adicional" name="costo_adicional" 
                               step="0.01" value="<?= $atributo_editar['costo_adicional'] ?? 0 ?>" min="0">
                        <small class="text-muted">Se suma al precio total del producto</small>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="es_obligatorio" name="es_obligatorio"
                               <?= ($atributo_editar['es_obligatorio'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="es_obligatorio">Obligatorio</label>
                    </div>
                    <div class="mb-3">
                        <label for="orden" class="form-label">Orden</label>
                        <input type="number" class="form-control" id="orden" name="orden" 
                               value="<?= $atributo_editar['orden'] ?? 0 ?>">
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <?= $atributo_editar ? '💾 Actualizar Atributo' : '➕ Agregar Atributo' ?>
                    </button>
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
                                            <?php if ($attr['tipo'] === 'select'): ?>
                                                <a href="?producto_id=<?= $producto_id ?>&atributo_id=<?= $attr['id'] ?>" class="btn btn-sm btn-info" title="Opciones con imágenes">🖼️</a>
                                            <?php endif; ?>
                                            <a href="?producto_id=<?= $producto_id ?>&editar_atributo=<?= $attr['id'] ?>" class="btn btn-sm btn-primary" title="Editar">✏️</a>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="eliminar_atributo">
                                                <input type="hidden" name="id" value="<?= $attr['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')" title="Eliminar">🗑️</button>
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
            <strong>Nota:</strong> Para atributos de tipo "Selección", haz clic en el botón 🖼️ para agregar opciones con imágenes.
        </div>
    </div>
</div>

<?php if ($atributo_id > 0 && !empty($atributos)): 
    $atributo_actual = null;
    foreach ($atributos as $a) {
        if ($a['id'] == $atributo_id) {
            $atributo_actual = $a;
            break;
        }
    }
    if ($atributo_actual && $atributo_actual['tipo'] === 'select'):
?>
    <div class="row mt-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5><?= $opcion_editar ? 'Editar Opción' : 'Nueva Opción' ?></h5>
                    <small class="text-muted">para: <strong><?= htmlspecialchars($atributo_actual['nombre']) ?></strong></small>
                    <?php if ($opcion_editar): ?>
                        <a href="?producto_id=<?= $producto_id ?>&atributo_id=<?= $atributo_id ?>" class="btn btn-sm btn-secondary">Cancelar</a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="guardar_opcion">
                        <?php if ($opcion_editar): ?>
                            <input type="hidden" name="opcion_id" value="<?= $opcion_editar['id'] ?>">
                        <?php endif; ?>
                        <div class="mb-3">
                            <label for="opcion_nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="opcion_nombre" name="opcion_nombre" 
                                   value="<?= htmlspecialchars($opcion_editar['nombre'] ?? '') ?>" 
                                   placeholder="Ej: Rojo" required>
                        </div>
                        <div class="mb-3">
                            <label for="imagen" class="form-label">Imagen</label>
                            <?php if ($opcion_editar && $opcion_editar['imagen']): ?>
                                <div class="mb-2">
                                    <img src="../../uploads/atributos/<?= htmlspecialchars($opcion_editar['imagen']) ?>" 
                                         alt="Actual" class="img-thumbnail" style="max-width: 150px;">
                                    <small class="d-block text-muted">Imagen actual (se reemplazará si subes una nueva)</small>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="imagen" name="imagen" accept="image/*">
                            <small class="text-muted">JPG, PNG, GIF o WEBP (máx. 2MB)</small>
                        </div>
                        <div class="mb-3">
                            <label for="opcion_color" class="form-label">Color (Opcional)</label>
                            <div class="input-group">
                                <input type="color" class="form-control form-control-color" id="opcion_color_picker" 
                                       value="<?= $opcion_editar['color'] ?? '#000000' ?>" 
                                       onchange="document.getElementById('opcion_color').value = this.value">
                                <input type="text" class="form-control" id="opcion_color" name="opcion_color" 
                                       value="<?= htmlspecialchars($opcion_editar['color'] ?? '') ?>" 
                                       placeholder="#FF0000" pattern="#[0-9A-Fa-f]{6}" 
                                       onchange="document.getElementById('opcion_color_picker').value = this.value || '#000000'">
                            </div>
                            <small class="text-muted">Formato hexadecimal: #RRGGBB</small>
                        </div>
                        <div class="mb-3">
                            <label for="opcion_orden" class="form-label">Orden</label>
                            <input type="number" class="form-control" id="opcion_orden" name="opcion_orden" 
                                   value="<?= $opcion_editar['orden'] ?? 0 ?>">
                        </div>
                        <div class="mb-3">
                            <label for="opcion_costo" class="form-label">Costo adicional ($)</label>
                            <input type="number" step="0.01" class="form-control" id="opcion_costo" name="opcion_costo" 
                                   value="<?= $opcion_editar['costo_adicional'] ?? 0 ?>">
                            <small class="text-muted">Se suma al precio total cuando se elige esta opción</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <?= $opcion_editar ? '💾 Actualizar Opción' : '➕ Agregar Opción' ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5>Opciones de "<?= htmlspecialchars($atributo_actual['nombre']) ?>" (<?= count($opciones) ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($opciones)): ?>
                        <p class="text-muted">No hay opciones. Agrega las opciones disponibles para este atributo.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($opciones as $opcion): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card h-100">
                                        <?php if ($opcion['imagen']): ?>
                                            <img src="../../uploads/atributos/<?= htmlspecialchars($opcion['imagen']) ?>" 
                                                 class="card-img-top" alt="<?= htmlspecialchars($opcion['nombre']) ?>" 
                                                 style="height: 150px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                                                <span class="text-muted">Sin imagen</span>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h6 class="card-title"><?= htmlspecialchars($opcion['nombre']) ?></h6>
                                            <?php if ($opcion['color']): ?>
                                                <div class="mb-2">
                                                    <span class="badge" style="background-color: <?= htmlspecialchars($opcion['color']) ?>; color: white;">
                                                        <?= htmlspecialchars($opcion['color']) ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            <small class="text-muted d-block">Orden: <?= $opcion['orden'] ?></small>
                                        </div>
                                        <div class="card-footer bg-light">
                                            <div class="d-grid gap-2">
                                                <a href="?producto_id=<?= $producto_id ?>&atributo_id=<?= $atributo_id ?>&editar_opcion=<?= $opcion['id'] ?>" 
                                                   class="btn btn-sm btn-primary">✏️ Editar</a>
                                                <form method="POST">
                                                    <input type="hidden" name="accion" value="eliminar_opcion">
                                                    <input type="hidden" name="opcion_id" value="<?= $opcion['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger w-100" onclick="return confirm('¿Eliminar?')">🗑️ Eliminar</button>
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
<?php endif; ?>
<?php endif; ?>

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