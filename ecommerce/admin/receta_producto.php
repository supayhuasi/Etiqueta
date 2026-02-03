<?php
require 'includes/header.php';

$producto_id = intval($_GET['producto_id'] ?? 0);
if ($producto_id <= 0) {
    die("Producto no especificado");
}

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado");
}

// Guardar receta
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $material_ids = $_POST['material_producto_id'] ?? [];
        $usar = $_POST['usar'] ?? [];
        $tipos = $_POST['tipo_calculo'] ?? [];
        $factores = $_POST['factor'] ?? [];
        $mermas = $_POST['merma_pct'] ?? [];
        $notas = $_POST['notas'] ?? [];
        $con_condicion = $_POST['con_condicion'] ?? [];
        $condicion_tipo = $_POST['condicion_tipo'] ?? [];
        $condicion_operador = $_POST['condicion_operador'] ?? [];
        $condicion_valor = $_POST['condicion_valor'] ?? [];

        foreach ($material_ids as $material_id) {
            $material_id = intval($material_id);
            if ($material_id <= 0) continue;
            if (empty($usar[$material_id])) {
                continue;
            }
            $tipo = $tipos[$material_id] ?? 'fijo';
            $factor = floatval($factores[$material_id] ?? 0);
            $merma = floatval($mermas[$material_id] ?? 0);
            $nota = trim($notas[$material_id] ?? '');
            
            // Procesar condición
            $tiene_condicion = isset($con_condicion[$material_id]) ? 1 : 0;
            $cond_tipo = null;
            $cond_operador = null;
            $cond_valor = null;
            $cond_atributo_id = null;
            
            if ($tiene_condicion) {
                $cond_tipo = $condicion_tipo[$material_id] ?? null;
                $cond_operador = $condicion_operador[$material_id] ?? null;
                $cond_valor = !empty($condicion_valor[$material_id]) ? trim($condicion_valor[$material_id]) : null;
                if (!empty($cond_tipo) && str_starts_with($cond_tipo, 'atributo_')) {
                    $cond_atributo_id = intval(str_replace('atributo_', '', $cond_tipo));
                    $cond_tipo = 'atributo';
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_producto_recetas_productos 
                (producto_id, material_producto_id, tipo_calculo, factor, merma_pct, notas, con_condicion, condicion_tipo, condicion_operador, condicion_valor, condicion_atributo_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    tipo_calculo = VALUES(tipo_calculo), 
                    factor = VALUES(factor), 
                    merma_pct = VALUES(merma_pct), 
                    notas = VALUES(notas),
                    con_condicion = VALUES(con_condicion),
                    condicion_tipo = VALUES(condicion_tipo),
                    condicion_operador = VALUES(condicion_operador),
                    condicion_valor = VALUES(condicion_valor),
                    condicion_atributo_id = VALUES(condicion_atributo_id)
            ");
            $stmt->execute([$producto_id, $material_id, $tipo, $factor, $merma, $nota ?: null, $tiene_condicion, $cond_tipo, $cond_operador, $cond_valor, $cond_atributo_id]);
        }

        // Eliminar materiales no enviados
        $ids_validos = [];
        foreach ($material_ids as $material_id) {
            if (!empty($usar[$material_id])) {
                $ids_validos[] = intval($material_id);
            }
        }
        if (!empty($ids_validos)) {
            $placeholders = implode(',', array_fill(0, count($ids_validos), '?'));
            $params = array_merge([$producto_id], $ids_validos);
            $stmt = $pdo->prepare("DELETE FROM ecommerce_producto_recetas_productos WHERE producto_id = ? AND material_producto_id NOT IN ($placeholders)");
            $stmt->execute($params);
        } else {
            $stmt = $pdo->prepare("DELETE FROM ecommerce_producto_recetas_productos WHERE producto_id = ?");
            $stmt->execute([$producto_id]);
        }

        $mensaje = "✓ Receta actualizada";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT id, nombre FROM ecommerce_productos WHERE activo = 1 AND es_material = 1 ORDER BY nombre");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener atributos del producto (para usar en condiciones)
$stmt = $pdo->prepare("
    SELECT id, nombre, tipo FROM ecommerce_producto_atributos 
    WHERE producto_id = ? 
    ORDER BY nombre
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT r.*, m.nombre
    FROM ecommerce_producto_recetas_productos r
    JOIN ecommerce_productos m ON r.material_producto_id = m.id
    WHERE r.producto_id = ?
    ORDER BY m.nombre
");
$stmt->execute([$producto_id]);
$receta = $stmt->fetchAll(PDO::FETCH_ASSOC);
$receta_map = [];
foreach ($receta as $r) {
    $receta_map[$r['material_producto_id']] = $r;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🧪 Receta de Materiales</h1>
        <p class="text-muted">Producto: <?= htmlspecialchars($producto['nombre']) ?></p>
    </div>
    <a href="productos.php" class="btn btn-secondary">← Volver</a>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
    <div class="card">
        <div class="card-body">
            <?php if (empty($materiales)): ?>
                <div class="alert alert-info">No hay productos marcados como material. Marcá productos con “Es material”.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Usar</th>
                                <th>Material (Producto)</th>
                                <th>Tipo cálculo</th>
                                <th>Factor</th>
                                <th>Merma %</th>
                                <th>Notas</th>
                                <th colspan="3">Condición (Opcional)</th>
                            </tr>
                            <tr style="background-color: #f8f9fa; font-size: 0.85em;">
                                <th colspan="6"></th>
                                <th>Tipo</th>
                                <th>Operador</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiales as $m):
                                $r = $receta_map[$m['id']] ?? null;
                            ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="usar[<?= $m['id'] ?>]" value="1" <?= $r ? 'checked' : '' ?>>
                                    </td>
                                    <td>
                                        <input type="hidden" name="material_producto_id[]" value="<?= $m['id'] ?>">
                                        <strong><?= htmlspecialchars($m['nombre']) ?></strong>
                                    </td>
                                    <td>
                                        <select name="tipo_calculo[<?= $m['id'] ?>]" class="form-select form-select-sm">
                                            <option value="fijo" <?= ($r['tipo_calculo'] ?? '') === 'fijo' ? 'selected' : '' ?>>Fijo</option>
                                            <option value="por_area" <?= ($r['tipo_calculo'] ?? '') === 'por_area' ? 'selected' : '' ?>>Por área (m²)</option>
                                            <option value="por_ancho" <?= ($r['tipo_calculo'] ?? '') === 'por_ancho' ? 'selected' : '' ?>>Por ancho (m)</option>
                                            <option value="por_alto" <?= ($r['tipo_calculo'] ?? '') === 'por_alto' ? 'selected' : '' ?>>Por alto (m)</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.0001" class="form-control form-control-sm" name="factor[<?= $m['id'] ?>]" value="<?= htmlspecialchars($r['factor'] ?? 0) ?>"></td>
                                    <td><input type="number" step="0.01" class="form-control form-control-sm" name="merma_pct[<?= $m['id'] ?>]" value="<?= htmlspecialchars($r['merma_pct'] ?? 0) ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="notas[<?= $m['id'] ?>]" value="<?= htmlspecialchars($r['notas'] ?? '') ?>"></td>
                                    <td>
                                        <input type="checkbox" name="con_condicion[<?= $m['id'] ?>]" value="1" <?= ($r['con_condicion'] ?? 0) ? 'checked' : '' ?> class="condicion-toggle">
                                        <span class="small text-muted">Usar</span>
                                    </td>
                                    <td>
                                        <select name="condicion_tipo[<?= $m['id'] ?>]" class="form-select form-select-sm condicion-select" <?= ($r['con_condicion'] ?? 0) ? '' : 'disabled' ?>>
                                            <option value="">--</option>
                                            <option value="ancho" <?= ($r['condicion_tipo'] ?? '') === 'ancho' ? 'selected' : '' ?>>Ancho (cm)</option>
                                            <option value="alto" <?= ($r['condicion_tipo'] ?? '') === 'alto' ? 'selected' : '' ?>>Alto (cm)</option>
                                            <option value="area" <?= ($r['condicion_tipo'] ?? '') === 'area' ? 'selected' : '' ?>>Área (m²)</option>
                                            <?php if (!empty($atributos)): ?>
                                                <optgroup label="Atributos">
                                                    <?php foreach ($atributos as $attr): ?>
                                                        <option value="atributo_<?= $attr['id'] ?>" <?= ($r['condicion_tipo'] ?? '') === 'atributo_' . $attr['id'] ? 'selected' : '' ?>>
                                                            <?= htmlspecialchars($attr['nombre']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endif; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="condicion_operador[<?= $m['id'] ?>]" class="form-select form-select-sm" <?= ($r['con_condicion'] ?? 0) ? '' : 'disabled' ?>>
                                            <option value="">--</option>
                                            <option value="igual" <?= ($r['condicion_operador'] ?? '') === 'igual' ? 'selected' : '' ?>>=</option>
                                            <option value="mayor" <?= ($r['condicion_operador'] ?? '') === 'mayor' ? 'selected' : '' ?>>&gt;</option>
                                            <option value="mayor_igual" <?= ($r['condicion_operador'] ?? '') === 'mayor_igual' ? 'selected' : '' ?>>&gt;=</option>
                                            <option value="menor" <?= ($r['condicion_operador'] ?? '') === 'menor' ? 'selected' : '' ?>>&lt;</option>
                                            <option value="menor_igual" <?= ($r['condicion_operador'] ?? '') === 'menor_igual' ? 'selected' : '' ?>>&lt;=</option>
                                            <option value="diferente" <?= ($r['condicion_operador'] ?? '') === 'diferente' ? 'selected' : '' ?>>&ne;</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control form-control-sm" name="condicion_valor[<?= $m['id'] ?>]" value="<?= htmlspecialchars($r['condicion_valor'] ?? '') ?>" placeholder="Valor" <?= ($r['con_condicion'] ?? 0) ? '' : 'disabled' ?>>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-end">
            <button type="submit" class="btn btn-primary">Guardar Receta</button>
        </div>
    </div>
</form>

<script>
document.querySelectorAll('.condicion-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const row = this.closest('tr');
        const isChecked = this.checked;
        if (!row) return;

        row.querySelectorAll('select, input[type="text"]').forEach(field => {
            if (field.name && (field.name.includes('condicion_tipo') || field.name.includes('condicion_operador') || field.name.includes('condicion_valor'))) {
                field.disabled = !isChecked;
            }
        });
    });
});
</script>

<?php require 'includes/footer.php'; ?>
