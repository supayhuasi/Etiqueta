<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/materials_helper.php';
require_once __DIR__ . '/includes/audit_helper.php';
require_once __DIR__ . '/../includes/funciones_recetas.php';

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
// rol y resumen inicial
$role = $_SESSION['user']['role'] ?? null;
$material_summary = [];
// Permisos: solo roles permitidos pueden editar overrides
$allowed_edit_roles = ['admin', 'finanzas'];
$can_edit = isset($role) && in_array($role, $allowed_edit_roles, true);
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_override') {
        if (!$can_edit) throw new Exception('No tenés permisos para editar este pedido.');
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

    // Manejo de guardado por-material
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar_overrides') {
        if (!$can_edit) throw new Exception('No tenés permisos para editar overrides por material.');
        $saved = 0;
        $saved_details = [];
        foreach ($_POST as $key => $val) {
            if (strpos($key, 'override_unit_') !== 0) continue;
            $mid = (int)str_replace('override_unit_', '', $key);
            $raw = str_replace(',', '.', trim((string)$val));
            if ($raw === '' || !is_numeric($raw)) continue;
            $unit = (float)$raw;
            $comentario = trim((string)($_POST['comentario_' . $mid] ?? '')) ?: null;
            $ok = materials_guardar_override($pdo, $pedido_id, $mid, $unit, $comentario, $_SESSION['user']['id'] ?? null);
            if ($ok) {
                $saved++;
                $saved_details[] = ['material_id' => $mid, 'unit' => $unit];
            }
        }
        if ($saved > 0) {
            auditoria_registrar($pdo, 'ecommerce_pedidos', $pedido_id, 'override_materiales_guardar', ['count' => $saved, 'details' => $saved_details], $_SESSION['user']['id'] ?? null);
            $mensaje = "Se guardaron {$saved} overrides.";
            // Recargar summary
            $stmt = $pdo->prepare("SELECT * FROM ecommerce_pedido_items WHERE pedido_id = ?");
            $stmt->execute([$pedido_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $costo_calculado = calcular_costo_material_pedido($pdo, $items, $pedido_id);
            $override_actual = materials_obtener_override_total($pdo, $pedido_id);
            // Recompute material summary below (we'll rely on outer code to recompute)
        } else {
            $error = 'No se guardaron overrides (ningún valor válido).';
        }
    }

    // Export CSV
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="pedido_' . $pedido_id . '_materiales.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['material_id', 'nombre', 'cantidad_total', 'unit_calc', 'override_unit', 'costo_total_calc', 'costo_total_override']);
        foreach ($material_summary as $mat) {
            fputcsv($out, [
                $mat['material_id'],
                $mat['nombre'],
                $mat['cantidad_total'],
                $mat['unit_calc'],
                $mat['override_unit'] ?? '',
                $mat['costo_total_calc'],
                $mat['costo_total_override'] ?? ''
            ]);
        }
        fclose($out);
        exit;
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

    // Construir desglose por material usado en las recetas del pedido
    $material_summary = [];
    foreach ($items as $it) {
        $producto_id = (int)($it['producto_id'] ?? 0);
        $cantidad = (float)($it['cantidad'] ?? 0);
        $ancho_cm = isset($it['ancho_cm']) ? (float)$it['ancho_cm'] : (isset($it['ancho']) ? (float)$it['ancho'] : 0.0);
        $alto_cm = isset($it['alto_cm']) ? (float)$it['alto_cm'] : (isset($it['alto']) ? (float)$it['alto'] : 0.0);

        $atributos = [];
        if (!empty($it['atributos'])) {
            $decoded = json_decode((string)$it['atributos'], true);
            if (is_array($decoded)) $atributos = $decoded;
        }

        if ($producto_id <= 0 || $cantidad <= 0) continue;

        $recetas = obtener_receta_con_condiciones($pdo, $producto_id, $ancho_cm, $alto_cm, $atributos);
        if (empty($recetas)) continue;

        $alto_m = $alto_cm / 100.0;
        $ancho_m = $ancho_cm / 100.0;
        $area_m2 = $alto_m * $ancho_m;

        foreach ($recetas as $receta) {
            $factor = (float)($receta['factor'] ?? 0);
            $merma = (float)($receta['merma_pct'] ?? 0);
            $tipo = $receta['tipo_calculo'] ?? 'fijo';
            $cantidad_base = 0.0;

            if ($tipo === 'fijo') {
                $cantidad_base = $factor;
            } elseif ($tipo === 'por_area') {
                $cantidad_base = $area_m2 * $factor;
            } elseif ($tipo === 'por_ancho') {
                $cantidad_base = $ancho_m * $factor;
            } elseif ($tipo === 'por_alto') {
                $cantidad_base = $alto_m * $factor;
            }

            $cantidad_total = $cantidad_base * (1 + ($merma / 100.0)) * $cantidad;
            if ($cantidad_total <= 0) continue;

            $material_id = (int)($receta['material_producto_id'] ?? 0);
            if ($material_id <= 0) continue;

            if (!isset($material_summary[$material_id])) {
                $material_summary[$material_id] = [
                    'material_id' => $material_id,
                    'nombre' => $receta['material_nombre'] ?? (string)$material_id,
                    'cantidad_total' => 0.0,
                    'unit_calc' => 0.0,
                    'override_unit' => null,
                ];
            }

            $material_summary[$material_id]['cantidad_total'] += $cantidad_total;
        }
    }

    // Completar datos de costos por material
    foreach ($material_summary as $mid => &$m) {
        $m['unit_calc'] = obtener_costo_unitario_material($pdo, $mid);
        $m['override_unit'] = materials_obtener_override($pdo, $pedido_id, $mid);
        $m['costo_total_calc'] = round($m['cantidad_total'] * $m['unit_calc'], 2);
        $m['costo_total_override'] = $m['override_unit'] !== null ? round($m['cantidad_total'] * $m['override_unit'], 2) : null;
    }
    unset($m);

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
            <h5>Desglose por material</h5>

            <form method="POST" class="mb-3">
                <input type="hidden" name="accion" value="guardar_overrides">
                <input type="hidden" name="pedido_id" value="<?= (int)$pedido_id ?>">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Cantidad total</th>
                                <th>Costo unit. calculado</th>
                                <th>Override unitario</th>
                                <th>Costo total (calc)</th>
                                <th>Costo total (override)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($material_summary)): ?>
                            <tr><td colspan="6"><em>No se encontraron materiales para este pedido.</em></td></tr>
                        <?php else: ?>
                            <?php foreach ($material_summary as $mat): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($mat['nombre']) ?> (ID <?= (int)$mat['material_id'] ?>)</td>
                                        <td><?= number_format($mat['cantidad_total'], 3, ',', '.') ?></td>
                                        <td>$<?= number_format($mat['unit_calc'], 4, ',', '.') ?></td>
                                        <td>
                                            <input type="text" name="override_unit_<?= (int)$mat['material_id'] ?>" class="form-control form-control-sm" value="<?= $mat['override_unit'] !== null ? htmlspecialchars((string)$mat['override_unit']) : '' ?>" placeholder="Ej: 12.50" <?= $can_edit ? '' : 'disabled' ?>>
                                        </td>
                                        <td>$<?= number_format($mat['costo_total_calc'], 2, ',', '.') ?></td>
                                        <td><?= $mat['costo_total_override'] !== null ? '$' . number_format($mat['costo_total_override'], 2, ',', '.') : '<em>-</em>' ?></td>
                                    </tr>
                                    <tr>
                                        <td colspan="6">
                                            <div class="form-text">Comentario (opcional):
                                                <input type="text" name="comentario_<?= (int)$mat['material_id'] ?>" class="form-control form-control-sm d-inline-block ms-2" style="width:50%;" value="<?= htmlspecialchars((string)($_POST['comentario_' . $mat['material_id']] ?? '')) ?>" <?= $can_edit ? '' : 'disabled' ?>>
                                            </div>
                                        </td>
                                    </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm">Guardar overrides por material</button>
                    <a href="pedidos_detalle.php?pedido_id=<?= (int)$pedido_id ?>" class="btn btn-secondary btn-sm">Cancelar</a>
                </div>
            </form>

            <hr>

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
