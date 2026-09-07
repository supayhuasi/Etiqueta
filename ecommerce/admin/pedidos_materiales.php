<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/materials_helper.php';
require_once __DIR__ . '/includes/audit_helper.php';

$pdo = $GLOBALS['pdo'] ?? ($pdo ?? null);
if (!($pdo instanceof PDO)) {
    throw new RuntimeException('Conexion PDO no disponible en editor de costos de material.');
}

$pedido_id = intval($_GET['pedido_id'] ?? $_POST['pedido_id'] ?? 0);
if ($pedido_id <= 0) {
    die('Pedido inválido');
}

 $mensaje = '';
 $error = '';
 $costo_calculado = 0.0;
 $override_actual = null;
 $items = [];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_override') {
        $valor_raw = str_replace(',', '.', trim((string)($_POST['costo_override'] ?? '0')));
        if ($valor_raw === '' || !is_numeric($valor_raw)) {
            $valor_raw = '0';
        }
        $valor = (float)$valor_raw;
        $ok = materials_guardar_override_total($pdo, $pedido_id, $valor, $_SESSION['user']['id'] ?? null);
        if ($ok) {
            auditoria_registrar($pdo, 'ecommerce_pedidos', $pedido_id, 'override_costo_material_total', ['costo_override' => $valor], $_SESSION['user']['id'] ?? null);
            $mensaje = 'Override guardado correctamente';
        } else {
            $error = 'No se pudo guardar el override';
        }
    }

    // Obtener pedido y items
    $stmt = $pdo->prepare("SELECT id, numero_pedido, total FROM ecommerce_pedidos WHERE id = ? LIMIT 1");
    $stmt->execute([$pedido_id]);
    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$pedido) throw new Exception('Pedido no encontrado');

    $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_items WHERE pedido_id = ?");
    $stmt->execute([$pedido_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $costo_calculado = calcular_costo_material_pedido($pdo, $items, $pedido_id);
    $override_actual = materials_obtener_override_total($pdo, $pedido_id);

} catch (Throwable $e) {
    $error = $e->getMessage();
}

?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>Editar costo material - Pedido <?= htmlspecialchars($pedido['numero_pedido'] ?? $pedido_id) ?></h1>
        <a href="pedidos_detalle.php?pedido_id=<?= (int)$pedido_id ?>" class="btn btn-secondary">Volver</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <p>Este formulario permite establecer un <strong>costo total de material</strong> que reemplaza el cálculo estimado. Usá esto cuando conocés el costo real de compras/mermas para este pedido.</p>
            <p><strong>Costo calculado:</strong> $<?= number_format($costo_calculado, 2, ',', '.') ?></p>
            <p><strong>Override actual:</strong> <?= $override_actual !== null ? '$' . number_format($override_actual, 2, ',', '.') : '<em>Sin override</em>' ?></p>

            <form method="POST" class="row g-2">
                <input type="hidden" name="accion" value="guardar_override">
                <input type="hidden" name="pedido_id" value="<?= (int)$pedido_id ?>">
                <div class="col-auto">
                    <label class="form-label">Costo total override</label>
                    <input type="text" name="costo_override" class="form-control" value="<?= htmlspecialchars((string)($override_actual ?? '')) ?>" placeholder="Ej: 1234.56">
                </div>
                <div class="col-auto align-self-end">
                    <button class="btn btn-primary">Guardar override</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <h5>Items del pedido</h5>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th>Cant.</th><th>Precio unit.</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $it): ?>
                        <tr>
                            <td><?= htmlspecialchars($it['producto_id'] . ' - ' . ($it['nombre'] ?? '')) ?></td>
                            <td><?= htmlspecialchars($it['cantidad']) ?></td>
                            <td>$<?= number_format((float)($it['precio_unitario'] ?? 0), 2, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
