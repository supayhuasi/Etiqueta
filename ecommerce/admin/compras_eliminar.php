<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/compras_workflow.php';
ensureComprasWorkflowSchema($pdo);

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
            SELECT ci.producto_id, ci.color_opcion_id, ci.cantidad, ci.cantidad_recibida, ci.alto_cm, ci.ancho_cm, p.tipo_precio
            FROM ecommerce_compra_items ci
            JOIN ecommerce_productos p ON p.id = ci.producto_id
            WHERE ci.compra_id = ?
        ");
        $stmt->execute([$compra_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $cantidadRecibida = (int)($item['cantidad_recibida'] ?? $item['cantidad'] ?? 0);
            if ($cantidadRecibida > 0) {
                aplicarStockCompraDelta($pdo, $item, -1 * $cantidadRecibida, (string)($compra['numero_compra'] ?? 'COMPRA'));
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

                <p>Vas a eliminar el registro <strong><?= htmlspecialchars((string)($compra['numero_compra'] ?? '')) ?></strong>.</p>
                <div class="alert alert-warning">
                    Se eliminarán sus items y, si ya hubo recepción, se descontará solo el stock efectivamente ingresado.
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
