<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Migración ligera si la tabla no existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_metodos_pago'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_metodos_pago (
                id INT PRIMARY KEY AUTO_INCREMENT,
                codigo VARCHAR(50) NOT NULL UNIQUE,
                nombre VARCHAR(100) NOT NULL,
                tipo ENUM('manual','mercadopago') NOT NULL DEFAULT 'manual',
                instrucciones_html TEXT NULL,
                activo TINYINT DEFAULT 1,
                orden INT DEFAULT 0,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_activo (activo)
            )
        ");

        $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
            SELECT 'transferencia_bancaria', 'Transferencia Bancaria', 'manual',
            '<p><strong>Datos para transferencia:</strong></p><ul><li>Banco: Banco Ejemplo</li><li>CBU: 0000000000000000000000</li><li>Alias: TUCU.ROLLER</li><li>Titular: Tucu Roller</li></ul><p>Luego de transferir, envíanos el comprobante.</p>',
            1, 1
            WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'transferencia_bancaria')
        ");
        $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
            SELECT 'mercadopago_tarjeta', 'Tarjeta de Crédito (Mercado Pago)', 'mercadopago',
            '<p>Serás redirigido a Mercado Pago para completar el pago con tarjeta.</p>',
            1, 2
            WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'mercadopago_tarjeta')
        ");
        $pdo->exec("INSERT INTO ecommerce_metodos_pago (codigo, nombre, tipo, instrucciones_html, activo, orden)
            SELECT 'efectivo_entrega', 'Efectivo contra Entrega', 'manual',
            '<p>Pagás en efectivo al recibir tu pedido.</p>',
            1, 3
            WHERE NOT EXISTS (SELECT 1 FROM ecommerce_metodos_pago WHERE codigo = 'efectivo_entrega')
        ");

        $mensaje = 'Tabla de métodos de pago creada y cargada.';
    }
} catch (Exception $e) {
    $error = 'No se pudo crear la tabla de métodos de pago: ' . $e->getMessage();
}

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
        $metodo_existente = null;
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM ecommerce_metodos_pago WHERE id = ?");
                $stmt->execute([$id]);
                $metodo_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $metodo_existente = null;
            }
        }
        $codigo = strtoupper(trim($_POST['codigo'] ?? ''));
        $nombre = trim($_POST['nombre'] ?? '');
        $tipo = $_POST['tipo'] ?? 'manual';
        $instrucciones_html = trim($_POST['instrucciones_html'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($metodo_existente && ($metodo_existente['tipo'] ?? '') === 'mercadopago') {
            $error = 'El método de Mercado Pago no se puede editar. Solo podés activarlo o desactivarlo.';
        } elseif ($codigo === '' || !preg_match('/^[A-Z0-9_\-]+$/', $codigo)) {
            $error = 'El código es obligatorio y solo puede contener letras, números, guiones o guión bajo.';
        } elseif ($nombre === '') {
            $error = 'El nombre es obligatorio.';
        } elseif (!in_array($tipo, ['manual', 'mercadopago'], true)) {
            $error = 'Tipo inválido.';
        } elseif ($id === 0 && $tipo === 'mercadopago') {
            $error = 'No se pueden crear métodos de Mercado Pago desde aquí.';
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
        <?php $is_mp = !empty($metodo_editar) && ($metodo_editar['tipo'] ?? '') === 'mercadopago'; ?>
        <?php if ($is_mp): ?>
            <div class="alert alert-warning">Este método pertenece a Mercado Pago y no se puede editar. Podés activarlo o desactivarlo desde el listado.</div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($metodo_editar['id'] ?? 0) ?>">

            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">Código *</label>
                    <input type="text" name="codigo" class="form-control" value="<?= htmlspecialchars($metodo_editar['codigo'] ?? '') ?>" placeholder="TRANSFERENCIA_BANCARIA" required <?= $is_mp ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($metodo_editar['nombre'] ?? '') ?>" placeholder="Transferencia Bancaria" required <?= $is_mp ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Tipo *</label>
                    <?php $tipo_sel = $metodo_editar['tipo'] ?? 'manual'; ?>
                    <select name="tipo" class="form-select" required <?= $is_mp ? 'disabled' : '' ?>>
                        <option value="manual" <?= $tipo_sel === 'manual' ? 'selected' : '' ?>>Manual</option>
                        <option value="mercadopago" <?= $tipo_sel === 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= htmlspecialchars($metodo_editar['orden'] ?? 0) ?>" <?= $is_mp ? 'readonly' : '' ?>>
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
                <textarea name="instrucciones_html" rows="4" class="form-control" placeholder="Datos bancarios, pasos de pago, etc." <?= $is_mp ? 'readonly' : '' ?>><?= htmlspecialchars($metodo_editar['instrucciones_html'] ?? '') ?></textarea>
                <small class="text-muted">Se muestra en checkout y en la pantalla de confirmación.</small>
            </div>

            <button type="submit" class="btn btn-primary" <?= $is_mp ? 'disabled' : '' ?>>Guardar</button>
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
                                    <?php if ($m['tipo'] !== 'mercadopago'): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="metodos_pago.php?editar=<?= (int)$m['id'] ?>">Editar</a>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-outline-secondary" disabled>Editar</button>
                                    <?php endif; ?>
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
