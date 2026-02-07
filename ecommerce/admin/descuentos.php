<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

$editar_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$descuento_editar = null;

if ($editar_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_descuentos WHERE id = ?");
        $stmt->execute([$editar_id]);
        $descuento_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $descuento_editar = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $tipo = $_POST['tipo'] ?? '';
        $valor = (float)($_POST['valor'] ?? 0);
        $minimo_subtotal = trim($_POST['minimo_subtotal'] ?? '');
        $fecha_inicio = trim($_POST['fecha_inicio'] ?? '');
        $fecha_fin = trim($_POST['fecha_fin'] ?? '');
        $usos_max = trim($_POST['usos_max'] ?? '');
        $activo = isset($_POST['activo']) ? 1 : 0;

        $minimo_subtotal_val = $minimo_subtotal === '' ? null : (float)$minimo_subtotal;
        $usos_max_val = $usos_max === '' ? null : (int)$usos_max;
        $fecha_inicio_val = $fecha_inicio === '' ? null : $fecha_inicio;
        $fecha_fin_val = $fecha_fin === '' ? null : $fecha_fin;

        if ($codigo === '' || !preg_match('/^[A-Z0-9_-]+$/', $codigo)) {
            $error = 'El código es obligatorio y solo puede contener letras, números, guiones o guión bajo.';
        } elseif (!in_array($tipo, ['porcentaje', 'monto'], true)) {
            $error = 'Tipo de descuento inválido.';
        } elseif ($valor <= 0) {
            $error = 'El valor debe ser mayor a 0.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_descuentos SET codigo = ?, tipo = ?, valor = ?, minimo_subtotal = ?, fecha_inicio = ?, fecha_fin = ?, usos_max = ?, activo = ? WHERE id = ?");
                    $stmt->execute([$codigo, $tipo, $valor, $minimo_subtotal_val, $fecha_inicio_val, $fecha_fin_val, $usos_max_val, $activo, $id]);
                    $mensaje = 'Descuento actualizado correctamente.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ecommerce_descuentos (codigo, tipo, valor, minimo_subtotal, fecha_inicio, fecha_fin, usos_max, activo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo, $tipo, $valor, $minimo_subtotal_val, $fecha_inicio_val, $fecha_fin_val, $usos_max_val, $activo]);
                    $mensaje = 'Descuento creado correctamente.';
                }
                $editar_id = 0;
                $descuento_editar = null;
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_descuentos SET activo = ? WHERE id = ?");
                $stmt->execute([$activo, $id]);
                $mensaje = 'Estado actualizado.';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado.';
            }
        }
    }
}

$descuentos = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_descuentos ORDER BY fecha_creacion DESC");
    $descuentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>

<h1>🎟️ Códigos de Descuento</h1>
<p class="text-muted">Creá y administrá códigos de descuento para el checkout.</p>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong><?= $descuento_editar ? 'Editar descuento' : 'Nuevo descuento' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($descuento_editar['id'] ?? 0) ?>">

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Código *</label>
                    <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($descuento_editar['codigo'] ?? '') ?>" placeholder="PROMO10" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tipo *</label>
                    <select name="tipo" class="form-select" required>
                        <?php $tipo_sel = $descuento_editar['tipo'] ?? 'porcentaje'; ?>
                        <option value="porcentaje" <?= $tipo_sel === 'porcentaje' ? 'selected' : '' ?>>Porcentaje</option>
                        <option value="monto" <?= $tipo_sel === 'monto' ? 'selected' : '' ?>>Monto fijo</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Valor *</label>
                    <input type="number" name="valor" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($descuento_editar['valor'] ?? 0) ?>" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Mínimo Subtotal</label>
                    <input type="number" name="minimo_subtotal" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars($descuento_editar['minimo_subtotal'] ?? '') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Válido desde</label>
                    <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($descuento_editar['fecha_inicio'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Válido hasta</label>
                    <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($descuento_editar['fecha_fin'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Usos máximos</label>
                    <input type="number" name="usos_max" class="form-control" min="0" value="<?= htmlspecialchars($descuento_editar['usos_max'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3 d-flex align-items-center">
                    <div class="form-check mt-4">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !empty($descuento_editar['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
            <?php if ($descuento_editar): ?>
                <a href="descuentos.php" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Listado</strong>
    </div>
    <div class="card-body">
        <?php if (empty($descuentos)): ?>
            <div class="alert alert-info">No hay descuentos cargados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Código</th>
                            <th>Tipo</th>
                            <th>Valor</th>
                            <th>Mínimo</th>
                            <th>Vigencia</th>
                            <th>Usos</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($descuentos as $d): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($d['codigo']) ?></strong></td>
                                <td><?= $d['tipo'] === 'porcentaje' ? 'Porcentaje' : 'Monto' ?></td>
                                <td><?= number_format((float)$d['valor'], 2, ',', '.') ?><?= $d['tipo'] === 'porcentaje' ? '%' : '' ?></td>
                                <td><?= $d['minimo_subtotal'] !== null ? '$' . number_format((float)$d['minimo_subtotal'], 2, ',', '.') : '-' ?></td>
                                <td>
                                    <?= $d['fecha_inicio'] ?: '-' ?>
                                    <?= ($d['fecha_fin'] ? ' → ' . $d['fecha_fin'] : '') ?>
                                </td>
                                <td><?= (int)($d['usos_usados'] ?? 0) ?><?= $d['usos_max'] ? ' / ' . (int)$d['usos_max'] : '' ?></td>
                                <td>
                                    <?php if (!empty($d['activo'])): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="descuentos.php?editar=<?= (int)$d['id'] ?>">Editar</a>
                                    <form method="POST">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= !empty($d['activo']) ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($d['activo']) ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <?= !empty($d['activo']) ? 'Desactivar' : 'Activar' ?>
                                        </button>
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

<?php require 'includes/footer.php'; ?>
