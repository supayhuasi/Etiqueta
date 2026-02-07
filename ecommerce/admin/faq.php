<?php
require 'includes/header.php';

$mensaje = '';
$error = '';

// Migración ligera si la tabla no existe
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'ecommerce_faq'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS ecommerce_faq (
                id INT PRIMARY KEY AUTO_INCREMENT,
                pregunta VARCHAR(255) NOT NULL,
                respuesta TEXT NOT NULL,
                activo TINYINT DEFAULT 1,
                orden INT DEFAULT 0,
                fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
        $mensaje = 'Tabla de preguntas frecuentes creada.';
    }
} catch (Exception $e) {
    $error = 'No se pudo crear la tabla de FAQ: ' . $e->getMessage();
}

$editar_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
$faq_editar = null;

if ($editar_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM ecommerce_faq WHERE id = ?");
        $stmt->execute([$editar_id]);
        $faq_editar = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $faq_editar = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? 'guardar';

    if ($accion === 'guardar') {
        $id = (int)($_POST['id'] ?? 0);
        $pregunta = trim($_POST['pregunta'] ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');
        $orden = (int)($_POST['orden'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;

        if ($pregunta === '' || $respuesta === '') {
            $error = 'La pregunta y la respuesta son obligatorias.';
        } else {
            try {
                if ($id > 0) {
                    $stmt = $pdo->prepare("UPDATE ecommerce_faq SET pregunta = ?, respuesta = ?, activo = ?, orden = ? WHERE id = ?");
                    $stmt->execute([$pregunta, $respuesta, $activo, $orden, $id]);
                    $mensaje = 'FAQ actualizada correctamente.';
                } else {
                    $stmt = $pdo->prepare("INSERT INTO ecommerce_faq (pregunta, respuesta, activo, orden) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$pregunta, $respuesta, $activo, $orden]);
                    $mensaje = 'FAQ creada correctamente.';
                }
                $editar_id = 0;
                $faq_editar = null;
            } catch (Exception $e) {
                $error = 'Error al guardar: ' . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $activo = (int)($_POST['activo'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_faq SET activo = ? WHERE id = ?");
                $stmt->execute([$activo, $id]);
                $mensaje = 'Estado actualizado.';
            } catch (Exception $e) {
                $error = 'Error al actualizar estado.';
            }
        }
    }
}

$faqs = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_faq ORDER BY orden ASC, id DESC");
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
}
?>

<h1>❓ Preguntas Frecuentes</h1>
<p class="text-muted">Administrá las preguntas frecuentes del sitio.</p>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong><?= $faq_editar ? 'Editar pregunta' : 'Nueva pregunta' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= htmlspecialchars($faq_editar['id'] ?? 0) ?>">

            <div class="row">
                <div class="col-md-9 mb-3">
                    <label class="form-label">Pregunta *</label>
                    <input type="text" name="pregunta" class="form-control" value="<?= htmlspecialchars($faq_editar['pregunta'] ?? '') ?>" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= htmlspecialchars($faq_editar['orden'] ?? 0) ?>">
                </div>
                <div class="col-md-1 mb-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= !empty($faq_editar['activo']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">Activo</label>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Respuesta *</label>
                <textarea name="respuesta" rows="4" class="form-control" required><?= htmlspecialchars($faq_editar['respuesta'] ?? '') ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Guardar</button>
            <?php if ($faq_editar): ?>
                <a href="faq.php" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <strong>Listado</strong>
    </div>
    <div class="card-body">
        <?php if (empty($faqs)): ?>
            <div class="alert alert-info">No hay preguntas cargadas.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>Pregunta</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($faqs as $f): ?>
                            <tr>
                                <td><?= (int)$f['orden'] ?></td>
                                <td><?= htmlspecialchars($f['pregunta']) ?></td>
                                <td>
                                    <?php if (!empty($f['activo'])): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="faq.php?editar=<?= (int)$f['id'] ?>">Editar</a>
                                    <form method="POST">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                                        <input type="hidden" name="activo" value="<?= !empty($f['activo']) ? 0 : 1 ?>">
                                        <button type="submit" class="btn btn-sm <?= !empty($f['activo']) ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                            <?= !empty($f['activo']) ? 'Desactivar' : 'Activar' ?>
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
