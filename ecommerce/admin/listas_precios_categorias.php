<?php
require 'includes/header.php';

$lista_id = $_GET['lista_id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM ecommerce_listas_precios WHERE id = ?");
$stmt->execute([$lista_id]);
$lista = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lista) {
    die("<div class='alert alert-danger'>Lista de precios no encontrada</div>");
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $descuentos = $_POST['descuento'] ?? [];
        $activos = $_POST['activo'] ?? [];

        $stmtUpsert = $pdo->prepare("
            INSERT INTO ecommerce_lista_precio_categorias (lista_precio_id, categoria_id, descuento_porcentaje, activo)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE descuento_porcentaje = VALUES(descuento_porcentaje), activo = VALUES(activo)
        ");

        foreach ($descuentos as $categoria_id => $descuento) {
            $descuento = floatval($descuento);
            $activo = isset($activos[$categoria_id]) ? 1 : 0;
            $stmtUpsert->execute([$lista_id, $categoria_id, $descuento, $activo]);
        }

        echo "<div class='alert alert-success'>✓ Descuentos por categoría actualizados</div>";
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Obtener categorías
$stmt = $pdo->query("SELECT * FROM ecommerce_categorias ORDER BY orden, nombre");
$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener descuentos existentes
$stmt = $pdo->prepare("SELECT * FROM ecommerce_lista_precio_categorias WHERE lista_precio_id = ?");
$stmt->execute([$lista_id]);
$descuentos_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
$descuentos_map = [];
foreach ($descuentos_db as $d) {
    $descuentos_map[$d['categoria_id']] = $d;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🏷️ Descuentos por Categoría</h1>
        <p class="text-muted">Lista: <?= htmlspecialchars($lista['nombre']) ?></p>
    </div>
    <a href="listas_precios.php" class="btn btn-secondary">← Volver</a>
</div>

<div class="card">
    <div class="card-body">
        <form method="POST">
            <div class="table-responsive">
                <table class="table">
                    <thead class="table-light">
                        <tr>
                            <th>Categoría</th>
                            <th>Descuento (%)</th>
                            <th>Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categorias as $cat):
                            $descuento = $descuentos_map[$cat['id']]['descuento_porcentaje'] ?? 0;
                            $activo = $descuentos_map[$cat['id']]['activo'] ?? 0;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($cat['nombre']) ?></td>
                                <td style="width: 180px;">
                                    <input type="number" step="0.01" min="0" max="100" name="descuento[<?= $cat['id'] ?>]" class="form-control" value="<?= htmlspecialchars($descuento) ?>">
                                </td>
                                <td style="width: 120px;">
                                    <input type="checkbox" name="activo[<?= $cat['id'] ?>]" <?= $activo ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
