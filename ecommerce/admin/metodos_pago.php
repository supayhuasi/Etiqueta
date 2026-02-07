<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

$editar_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$metodo_editar = null;

if ($editar_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_metodos_pago WHERE id = ?");
        $stmt->execute([$editar_id]);
        $metodo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $metodo_editar = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? 'manual';
        $instrucciones_html = trim($_POST['instrucciones_html'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($codigo === '' || !preg_match('/^[A-Z0-9_\-]+$/', $codigo)) {
            $error = 'El código es obligatorio y solo puede contener letras, números, guiones o guión bajo.';
        } elseif ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif (!in_array($tipo, ['manual', 'mercadopago'], true)) {
            $error = 'Tipo inválido.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_metodos_pago SET codigo = ?, nombre = ?, tipo = ?, instrucciones_html = ?, activo = ?, orden = ? WHERE id = ?");
                    $stmt->execute([$codigo, $nombre, $tipo, $instrucciones_html, $activo, $orden, $id]);
                    $mensaje = 'Método actualizado correctamente.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$codigo, $nombre, $tipo, $instrucciones_html, $activo, $orden]);
                    $mensaje = 'Método creado correctamente.';
                }
                $editar_id = 0;
                $metodo_editar = null;
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_metodos_pago SET activo = ? WHERE id = ?");
                $stmt->execute([$activo, $id]);
                $mensaje = 'Estado actualizado.';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado.';
            }
        }
    }
}

$metodos = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_metodos_pago ORDER BY orden ASC, nombre ASC");
    $metodos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>

<h1>💳 Métodos de Pago</h1>
<p class="text-muted">Administrá las opciones de pago disponibles en el checkout.</p>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong><?= $metodo_editar ? 'Editar método' : 'Nuevo método' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($metodo_editar['id'] ?? 0) ?>">

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Código *</label>
                    <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($metodo_editar['codigo'] ?? '') ?>" placeholder="TRANSFERENCIA_BANCARIA" required>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($metodo_editar['nombre'] ?? '') ?>" placeholder="Transferencia Bancaria" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Tipo *</label>
                    <?php $tipo_sel = $metodo_editar['tipo'] ?? 'manual'; ?>
                    <select name="tipo" class="form-select" required>
                        <option value="manual" <?= $tipo_sel === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="mercadopago" <?= $tipo_sel === 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= htmlspecialchars($metodo_editar['orden'] ?? 0) ?>">
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !empty($metodo_editar['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Instrucciones (HTML)</label>
                <textarea name="instrucciones_html" rows="4" class="form-control" placeholder="Datos bancarios, pasos de pago, etc."><?= htmlspecialchars($metodo_editar['instrucciones_html'] ?? '') ?></textarea>
                <small class="text-muted">Se muestra en checkout y en la pantalla de confirmación.</small>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
            <?php if ($metodo_editar): ?>
                <a href="metodos_pago.php" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Listado</strong>
    </div>
    <div class="card-body">
        <?php if (empty($metodos)): ?>
            <div class="alert alert-info">No hay métodos cargados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($metodos as $m): ?>
                            <tr>
                                <td><?= (int)$m['orden'] ?></td>
                                <td><strong><?= htmlspecialchars($m['codigo']) ?></strong></td>
                                <td><?= htmlspecialchars($m['nombre']) ?></td>
                                <td><?= $m['tipo'] === 'mercadopago' ? 'Mercado Pago' : 'Manual' ?></td>
                                <td>
                                    <?php if (!empty($m['activo'])): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="metodos_pago.php?editar=<?= (int)$m['id'] ?>">Editar</a>
                                    <form method="POST">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= !empty($m['activo']) ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($m['activo']) ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <?= !empty($m['activo']) ? 'Desactivar' : 'Activar' ?>
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
