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
        $material_ids = $_POST['material_id'] ?? [];
        $tipos = $_POST['tipo_calculo'] ?? [];
        $factores = $_POST['factor'] ?? [];
        $mermas = $_POST['merma_pct'] ?? [];
        $notas = $_POST['notas'] ?? [];

        foreach ($material_ids as $idx => $material_id) {
            $material_id = intval($material_id);
            if ($material_id <= 0) continue;
            $tipo = $tipos[$idx] ?? 'fijo';
            $factor = floatval($factores[$idx] ?? 0);
            $merma = floatval($mermas[$idx] ?? 0);
            $nota = trim($notas[$idx] ?? '');

            $stmt = $pdo->prepare("
                INSERT INTO ecommerce_producto_recetas (producto_id, material_id, tipo_calculo, factor, merma_pct, notas)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE tipo_calculo = VALUES(tipo_calculo), factor = VALUES(factor), merma_pct = VALUES(merma_pct), notas = VALUES(notas)
            ");
            $stmt->execute([$producto_id, $material_id, $tipo, $factor, $merma, $nota ?: null]);
        }

        // Eliminar materiales no enviados
        $ids_validos = array_map('intval', $material_ids);
        if (!empty($ids_validos)) {
            $placeholders = implode(',', array_fill(0, count($ids_validos), '?'));
            $params = array_merge([$producto_id], $ids_validos);
            $stmt = $pdo->prepare("DELETE FROM ecommerce_producto_recetas WHERE producto_id = ? AND material_id NOT IN ($placeholders)");
            $stmt->execute($params);
        }

        $mensaje = "✓ Receta actualizada";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->query("SELECT * FROM ecommerce_materiales WHERE activo = 1 ORDER BY nombre");
$materiales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT r.*, m.nombre, m.unidad
    FROM ecommerce_producto_recetas r
    JOIN ecommerce_materiales m ON r.material_id = m.id
    WHERE r.producto_id = ?
    ORDER BY m.nombre
");
$stmt->execute([$producto_id]);
$receta = $stmt->fetchAll(PDO::FETCH_ASSOC);
$receta_map = [];
foreach ($receta as $r) {
    $receta_map[$r['material_id']] = $r;
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
                <div class="alert alert-info">No hay materiales cargados.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Material</th>
                                <th>Unidad</th>
                                <th>Tipo cálculo</th>
                                <th>Factor</th>
                                <th>Merma %</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiales as $m):
                                $r = $receta_map[$m['id']] ?? null;
                            ?>
                                <tr>
                                    <td>
                                        <input type="hidden" name="material_id[]" value="<?= $m['id'] ?>">
                                        <strong><?= htmlspecialchars($m['nombre']) ?></strong>
                                    </td>
                                    <td><?= htmlspecialchars($m['unidad']) ?></td>
                                    <td>
                                        <select name="tipo_calculo[]" class="form-select form-select-sm">
                                            <option value="fijo" <?= ($r['tipo_calculo'] ?? '') === 'fijo' ? 'selected' : '' ?>>Fijo</option>
                                            <option value="por_area" <?= ($r['tipo_calculo'] ?? '') === 'por_area' ? 'selected' : '' ?>>Por área (m²)</option>
                                            <option value="por_ancho" <?= ($r['tipo_calculo'] ?? '') === 'por_ancho' ? 'selected' : '' ?>>Por ancho (m)</option>
                                            <option value="por_alto" <?= ($r['tipo_calculo'] ?? '') === 'por_alto' ? 'selected' : '' ?>>Por alto (m)</option>
                                        </select>
                                    </td>
                                    <td><input type="number" step="0.0001" class="form-control form-control-sm" name="factor[]" value="<?= htmlspecialchars($r['factor'] ?? 0) ?>"></td>
                                    <td><input type="number" step="0.01" class="form-control form-control-sm" name="merma_pct[]" value="<?= htmlspecialchars($r['merma_pct'] ?? 0) ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="notas[]" value="<?= htmlspecialchars($r['notas'] ?? '') ?>"></td>
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

<?php require 'includes/footer.php'; ?>
