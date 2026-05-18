<?php
require 'includes/header.php';

$editar = false;
$material = null;

if (isset($_GET['id'])) {
    $editar = true;
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_materiales WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $material = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$material) {
        die("<div class='alert alert-danger'>Material no encontrado</div>");
    }
}

// Auto-migración ubicacion
try { $pdo->query("ALTER TABLE `ecommerce_materiales` ADD COLUMN `ubicacion` VARCHAR(120) NULL DEFAULT NULL"); } catch (Throwable $e) {}

// Ubicaciones existentes para autocomplete
$ubicaciones_existentes = [];
try {
    $ubicaciones_existentes = $pdo->query("SELECT DISTINCT ubicacion FROM ecommerce_materiales WHERE ubicacion IS NOT NULL AND ubicacion != '' ORDER BY ubicacion")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $unidad = trim($_POST['unidad'] ?? '');
    $costo = $_POST['costo'] !== '' ? floatval($_POST['costo']) : null;
    $activo = isset($_POST['activo']) ? 1 : 0;
    $tipo_origen = $_POST['tipo_origen'] ?? 'compra';
    $stock_minimo = floatval($_POST['stock_minimo'] ?? 0);
    $proveedor_habitual_id = !empty($_POST['proveedor_habitual_id']) ? intval($_POST['proveedor_habitual_id']) : null;
    $unidad_medida = trim($_POST['unidad_medida'] ?? $unidad);
    $ubicacion = trim($_POST['ubicacion'] ?? '');

    if ($nombre === '' || $unidad === '') {
        echo "<div class='alert alert-danger'>Nombre y unidad son obligatorios</div>";
    } else {
        try {
            if ($editar) {
                $stmt = $pdo->prepare("
                    UPDATE ecommerce_materiales
                    SET nombre = ?, unidad = ?, costo = ?, activo = ?, tipo_origen = ?, stock_minimo = ?, proveedor_habitual_id = ?, unidad_medida = ?, ubicacion = ?
                    WHERE id = ?
                ");
                $stmt->execute([$nombre, $unidad, $costo, $activo, $tipo_origen, $stock_minimo, $proveedor_habitual_id, $unidad_medida, $ubicacion ?: null, $_GET['id']]);
                $mensaje = "✓ Material actualizado";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_materiales (nombre, unidad, costo, activo, tipo_origen, stock_minimo, proveedor_habitual_id, unidad_medida, ubicacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$nombre, $unidad, $costo, $activo, $tipo_origen, $stock_minimo, $proveedor_habitual_id, $unidad_medida, $ubicacion ?: null]);
                $mensaje = "✓ Material creado";
            }
            echo "<div class='alert alert-success'>$mensaje</div>";
            header("refresh:1; url=materiales.php");
        } catch (Exception $e) {
            echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><?= $editar ? '✏️ Editar Material' : '🧵 Nuevo Material' ?></h1>
    <a href="materiales.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($material['nombre'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Unidad *</label>
                    <input type="text" name="unidad" class="form-control" placeholder="m, m2, un" value="<?= htmlspecialchars($material['unidad'] ?? '') ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Costo</label>
                    <input type="number" step="0.01" name="costo" class="form-control" value="<?= htmlspecialchars($material['costo'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo de Origen *</label>
                    <select name="tipo_origen" class="form-select" id="tipo_origen" onchange="toggleProveedor()">
                        <option value="compra" <?= ($material['tipo_origen'] ?? 'compra') === 'compra' ? 'selected' : '' ?>>🛒 Compra</option>
                        <option value="fabricacion_propia" <?= ($material['tipo_origen'] ?? '') === 'fabricacion_propia' ? 'selected' : '' ?>>🏭 Fabricación Propia</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Stock Mínimo</label>
                    <input type="number" step="0.01" name="stock_minimo" class="form-control" value="<?= htmlspecialchars($material['stock_minimo'] ?? '0') ?>">
                    <small class="text-muted">Generar alerta cuando stock sea menor</small>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Unidad de Medida</label>
                    <input type="text" name="unidad_medida" class="form-control" placeholder="metros, kg, unidades" value="<?= htmlspecialchars($material['unidad_medida'] ?? $material['unidad'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">📍 Ubicación / Sector</label>
                    <input type="text" name="ubicacion" class="form-control" placeholder="Ej: Estante A, Depósito 2"
                        value="<?= htmlspecialchars($material['ubicacion'] ?? '') ?>"
                        list="ubicaciones_list">
                    <datalist id="ubicaciones_list">
                        <?php foreach ($ubicaciones_existentes as $ub): ?>
                            <option value="<?= htmlspecialchars($ub) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small class="text-muted">Sector o lugar donde está guardado</small>
                </div>
                <div class="col-md-3 mb-3" id="proveedor_container">
                    <label class="form-label">Proveedor Habitual</label>
                    <select name="proveedor_habitual_id" class="form-select">
                        <option value="">-- Ninguno --</option>
                        <?php
                        $stmt_prov = $pdo->query("SELECT id, nombre FROM ecommerce_proveedores WHERE activo = 1 ORDER BY nombre");
                        while ($prov = $stmt_prov->fetch(PDO::FETCH_ASSOC)):
                        ?>
                            <option value="<?= $prov['id'] ?>" <?= ($material['proveedor_habitual_id'] ?? 0) == $prov['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prov['nombre']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>

            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !isset($material) || $material['activo'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="activo">Activo</label>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
        </form>
    </div>
</div>

<script>
function toggleProveedor() {
    const tipoOrigen = document.getElementById('tipo_origen').value;
    const proveedorContainer = document.getElementById('proveedor_container');
    if (tipoOrigen === 'compra') {
        proveedorContainer.style.display = 'block';
    } else {
        proveedorContainer.style.display = 'none';
    }
}
// Ejecutar al cargar
toggleProveedor();
</script>

<?php require 'includes/footer.php'; ?>
