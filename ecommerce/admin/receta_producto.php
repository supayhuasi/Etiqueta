<?php
require 'includes/header.php';
require_once __DIR__ . '/../includes/funciones_recetas.php';

$producto_id = intval($_GET['producto_id'] ?? 0);
if ($producto_id <= 0) {
    die("Producto no especificado");
}

recetas_asegurar_condiciones($pdo);

$stmt = $pdo->prepare("SELECT * FROM ecommerce_productos WHERE id = ?");
$stmt->execute([$producto_id]);
$producto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$producto) {
    die("Producto no encontrado");
}

$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $material_ids = $_POST['material_producto_id'] ?? [];
        $usar = $_POST['usar'] ?? [];
        $tipos = $_POST['tipo_calculo'] ?? [];
        $factores = $_POST['factor'] ?? [];
        $mermas = $_POST['merma_pct'] ?? [];
        $notas = $_POST['notas'] ?? [];
        $condiciones_post = $_POST['condiciones'] ?? [];

        $ids_validos = [];
        foreach ($material_ids as $material_id) {
            $material_id = intval($material_id);
            if ($material_id <= 0) {
                continue;
            }
            if (empty($usar[$material_id])) {
                continue;
            }

            $tipo = $tipos[$material_id] ?? 'fijo';
            $factor = floatval($factores[$material_id] ?? 0);
            $merma = floatval($mermas[$material_id] ?? 0);
            $nota = trim($notas[$material_id] ?? '');

            $condiciones = [];
            foreach (($condiciones_post[$material_id] ?? []) as $fila) {
                $normalizada = recetas_normalizar_condicion_post(
                    $fila['tipo'] ?? '',
                    $fila['operador'] ?? '',
                    $fila['valor'] ?? ''
                );
                if ($normalizada) {
                    $condiciones[] = $normalizada;
                }
            }

            $primera = $condiciones[0] ?? null;
            $tiene_condicion = $primera ? 1 : 0;

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
            $stmt->execute([
                $producto_id,
                $material_id,
                $tipo,
                $factor,
                $merma,
                $nota !== '' ? $nota : null,
                $tiene_condicion,
                $primera['tipo'] ?? null,
                $primera['operador'] ?? null,
                $primera['valor'] ?? null,
                !empty($primera['atributo_id']) ? $primera['atributo_id'] : null,
            ]);

            $stmt_id = $pdo->prepare("SELECT id FROM ecommerce_producto_recetas_productos WHERE producto_id = ? AND material_producto_id = ? LIMIT 1");
            $stmt_id->execute([$producto_id, $material_id]);
            $receta_id = (int)$stmt_id->fetchColumn();
            recetas_guardar_condiciones($pdo, $receta_id, $condiciones);

            $ids_validos[] = $material_id;
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

$stmt = $pdo->prepare("
    SELECT id, nombre, tipo FROM ecommerce_producto_atributos
    WHERE producto_id = ?
    ORDER BY orden, nombre
");
$stmt->execute([$producto_id]);
$atributos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$opciones_por_atributo = [];
if (!empty($atributos)) {
    try {
        $ids = array_column($atributos, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("
            SELECT atributo_id, nombre
            FROM ecommerce_atributo_opciones
            WHERE atributo_id IN ($placeholders)
            ORDER BY orden, nombre
        ");
        $stmt->execute($ids);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
            $opciones_por_atributo[(int)$op['atributo_id']][] = (string)$op['nombre'];
        }
    } catch (Exception $e) {
        $opciones_por_atributo = [];
    }
}

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

$condiciones_por_material = [];
$condiciones_db = recetas_cargar_condiciones_por_receta($pdo, array_column($receta, 'id'));
foreach ($receta as $r) {
    $lista = $condiciones_db[(int)$r['id']] ?? [];
    if (empty($lista)) {
        $lista = recetas_condiciones_legacy($r);
    }
    $condiciones_por_material[(int)$r['material_producto_id']] = $lista;
}

if (!function_exists('receta_opciones_tipo')) {
function receta_opciones_tipo(array $atributos, array $cond = []): string
{
    $tipo_actual = '';
    if (!empty($cond['tipo'])) {
        $tipo_actual = $cond['tipo'] === 'atributo'
            ? 'atributo_' . (int)($cond['atributo_id'] ?? 0)
            : (string)$cond['tipo'];
    }

    $html = '<option value="">--</option>';
    $html .= '<option value="ancho"' . ($tipo_actual === 'ancho' ? ' selected' : '') . '>Ancho (cm)</option>';
    $html .= '<option value="alto"' . ($tipo_actual === 'alto' ? ' selected' : '') . '>Alto (cm)</option>';
    $html .= '<option value="area"' . ($tipo_actual === 'area' ? ' selected' : '') . '>Área (m²)</option>';
    if (!empty($atributos)) {
        $html .= '<optgroup label="Atributos">';
        foreach ($atributos as $attr) {
            $val = 'atributo_' . (int)$attr['id'];
            $sel = $tipo_actual === $val ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($val) . '"' . $sel . '>' . htmlspecialchars($attr['nombre']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}
}

if (!function_exists('receta_opciones_operador')) {
function receta_opciones_operador(array $cond = []): string
{
    $actual = (string)($cond['operador'] ?? 'igual');
    $ops = [
        'igual' => '=',
        'mayor' => '>',
        'mayor_igual' => '≥',
        'menor' => '<',
        'menor_igual' => '≤',
        'diferente' => '≠',
    ];
    $html = '';
    foreach ($ops as $val => $label) {
        $sel = $actual === $val ? ' selected' : '';
        $html .= '<option value="' . $val . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
    }
    return $html;
}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Receta de materiales</h1>
        <p class="text-muted mb-0">Producto: <?= htmlspecialchars($producto['nombre']) ?></p>
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
                <p class="small text-muted mb-3">
                    Marcá cada tela (u otro material) y agregá condiciones. Si el cliente elige esos valores, esa línea entra.
                    Varias condiciones se cumplen <strong>todas juntas</strong> (Tela = Screen <em>y</em> Color = Blanco).
                    En valor podés poner varios separados por coma: <code>Blanco, Negro</code>.
                </p>
                <?php foreach ($atributos as $attr): ?>
                    <datalist id="opciones-attr-<?= (int)$attr['id'] ?>">
                        <?php foreach ($opciones_por_atributo[(int)$attr['id']] ?? [] as $nombre_op): ?>
                            <option value="<?= htmlspecialchars($nombre_op) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                <?php endforeach; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Usar</th>
                                <th>Material</th>
                                <th>Tipo cálculo</th>
                                <th>Factor</th>
                                <th>Merma %</th>
                                <th>Notas</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($materiales as $m):
                                $r = $receta_map[$m['id']] ?? null;
                                $conds = $condiciones_por_material[$m['id']] ?? [];
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
                                    <td><input type="number" step="0.0001" class="form-control form-control-sm" name="factor[<?= $m['id'] ?>]" value="<?= htmlspecialchars((string)($r['factor'] ?? 0)) ?>"></td>
                                    <td><input type="number" step="0.01" class="form-control form-control-sm" name="merma_pct[<?= $m['id'] ?>]" value="<?= htmlspecialchars((string)($r['merma_pct'] ?? 0)) ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="notas[<?= $m['id'] ?>]" value="<?= htmlspecialchars((string)($r['notas'] ?? '')) ?>"></td>
                                </tr>
                                <tr class="receta-cond-block" data-material="<?= (int)$m['id'] ?>">
                                    <td></td>
                                    <td colspan="5">
                                        <div class="small text-muted mb-1">Condiciones (todas deben cumplirse)</div>
                                        <div class="receta-cond-list">
                                            <?php foreach ($conds as $idx => $cond):
                                                $tipo_key = (($cond['tipo'] ?? '') === 'atributo')
                                                    ? 'atributo_' . (int)($cond['atributo_id'] ?? 0)
                                                    : (string)($cond['tipo'] ?? '');
                                                $list_id = strpos($tipo_key, 'atributo_') === 0 ? 'opciones-attr-' . substr($tipo_key, 9) : '';
                                            ?>
                                                <div class="d-flex flex-wrap gap-1 mb-1 receta-cond-row">
                                                    <select name="condiciones[<?= $m['id'] ?>][<?= $idx ?>][tipo]" class="form-select form-select-sm receta-cond-tipo" style="min-width: 140px;">
                                                        <?= receta_opciones_tipo($atributos, $cond) ?>
                                                    </select>
                                                    <select name="condiciones[<?= $m['id'] ?>][<?= $idx ?>][operador]" class="form-select form-select-sm" style="min-width: 70px;">
                                                        <?= receta_opciones_operador($cond) ?>
                                                    </select>
                                                    <input type="text" class="form-control form-control-sm receta-cond-valor" name="condiciones[<?= $m['id'] ?>][<?= $idx ?>][valor]" value="<?= htmlspecialchars((string)($cond['valor'] ?? '')) ?>" placeholder="ej: Screen" <?= $list_id !== '' ? 'list="' . htmlspecialchars($list_id) . '"' : '' ?> style="min-width: 140px;">
                                                    <button type="button" class="btn btn-sm btn-outline-danger receta-cond-del" title="Quitar condición">×</button>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary receta-cond-add">+ Condición</button>
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

<template id="tpl-receta-condicion">
    <div class="d-flex flex-wrap gap-1 mb-1 receta-cond-row">
        <select name="condiciones[__MID__][__IDX__][tipo]" class="form-select form-select-sm receta-cond-tipo" style="min-width: 140px;">
            <?= receta_opciones_tipo($atributos) ?>
        </select>
        <select name="condiciones[__MID__][__IDX__][operador]" class="form-select form-select-sm" style="min-width: 70px;">
            <?= receta_opciones_operador(['operador' => 'igual']) ?>
        </select>
        <input type="text" class="form-control form-control-sm receta-cond-valor" name="condiciones[__MID__][__IDX__][valor]" placeholder="ej: Screen" style="min-width: 140px;">
        <button type="button" class="btn btn-sm btn-outline-danger receta-cond-del" title="Quitar condición">×</button>
    </div>
</template>

<script>
(function () {
    const tpl = document.getElementById('tpl-receta-condicion');

    function syncDatalist(select) {
        const row = select.closest('.receta-cond-row');
        if (!row) return;
        const input = row.querySelector('.receta-cond-valor');
        if (!input) return;
        const v = select.value || '';
        if (v.indexOf('atributo_') === 0) {
            input.setAttribute('list', 'opciones-attr-' + v.replace('atributo_', ''));
        } else {
            input.removeAttribute('list');
        }
    }

    function nextIndex(list) {
        let max = -1;
        list.querySelectorAll('.receta-cond-tipo').forEach(function (el) {
            const m = (el.name || '').match(/\[(\d+)\]\[tipo\]$/);
            if (m) {
                max = Math.max(max, parseInt(m[1], 10));
            }
        });
        return max + 1;
    }

    document.querySelectorAll('.receta-cond-block').forEach(function (block) {
        const materialId = block.getAttribute('data-material');
        const list = block.querySelector('.receta-cond-list');

        block.querySelectorAll('.receta-cond-tipo').forEach(syncDatalist);

        block.addEventListener('change', function (e) {
            if (e.target && e.target.classList.contains('receta-cond-tipo')) {
                syncDatalist(e.target);
            }
        });

        block.addEventListener('click', function (e) {
            const del = e.target.closest('.receta-cond-del');
            if (del) {
                const row = del.closest('.receta-cond-row');
                if (row) row.remove();
            }
        });

        const addBtn = block.querySelector('.receta-cond-add');
        if (addBtn && tpl && list) {
            addBtn.addEventListener('click', function () {
                const idx = nextIndex(list);
                const html = tpl.innerHTML
                    .replace(/__MID__/g, materialId)
                    .replace(/__IDX__/g, String(idx));
                const wrap = document.createElement('div');
                wrap.innerHTML = html.trim();
                const row = wrap.firstElementChild;
                list.appendChild(row);
                const tipo = row.querySelector('.receta-cond-tipo');
                if (tipo) syncDatalist(tipo);
            });
        }
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
