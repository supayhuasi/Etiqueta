<?php
require 'includes/header.php';

$compra_id = (int)($_GET['id'] ?? 0);
$error = '';

$stmt = $pdo->prepare("SELECT * FROM ecommerce_compras WHERE id = ?");
$stmt->execute([$compra_id]);
$compra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$compra) {
    die('Compra no encontrada');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT ci.producto_id, ci.cantidad, ci.alto_cm, ci.ancho_cm, p.tipo_precio
            FROM ecommerce_compra_items ci
            JOIN ecommerce_productos p ON p.id = ci.producto_id
            WHERE ci.compra_id = ?
        ");
        $stmt->execute([$compra_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tiene_matriz = $pdo->query("SHOW TABLES LIKE 'ecommerce_matriz_precios'")->rowCount() > 0;

        foreach ($items as $item) {
            $producto_id = (int)$item['producto_id'];
            $cantidad = (int)$item['cantidad'];
            $alto = !empty($item['alto_cm']) ? (int)$item['alto_cm'] : null;
            $ancho = !empty($item['ancho_cm']) ? (int)$item['ancho_cm'] : null;
            $tipo_precio = $item['tipo_precio'] ?? 'fijo';

            if ($tipo_precio === 'variable' && $alto && $ancho && $tiene_matriz) {
                $stmtUpd = $pdo->prepare("
                    UPDATE ecommerce_matriz_precios
                    SET stock = GREATEST(stock - ?, 0)
                    WHERE producto_id = ? AND alto_cm = ? AND ancho_cm = ?
                ");
                $stmtUpd->execute([$cantidad, $producto_id, $alto, $ancho]);
            } else {
                $stmtUpd = $pdo->prepare("UPDATE ecommerce_productos SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
                $stmtUpd->execute([$cantidad, $producto_id]);
            }
        }

        $stmt = $pdo->prepare("DELETE FROM ecommerce_inventario_movimientos WHERE tipo = 'compra' AND referencia = ?");
        $stmt->execute([$compra['numero_compra']]);

        $stmt = $pdo->prepare("DELETE FROM ecommerce_compras WHERE id = ?");
        $stmt->execute([$compra_id]);

        $pdo->commit();

        header('Location: compras.php?mensaje=eliminada');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = 'No se pudo eliminar la compra: ' . $e->getMessage();
    }
}
?>

<div class="row">
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0">Eliminar Compra</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <p>Vas a eliminar la compra <strong><?= htmlspecialchars($compra['numero_compra']) ?></strong>.</p>
                <div class="alert alert-warning">
                    Esta accion eliminara sus items, descontara stock cargado y borrara movimientos de inventario asociados.
                </div>

                <form method="POST" class="d-flex gap-2">
                    <a href="compras.php" class="btn btn-secondary">Cancelar</a>
                    <button type="submit" class="btn btn-danger">Si, eliminar compra</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
