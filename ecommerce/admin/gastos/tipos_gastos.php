<?php
require '../includes/header.php';
require_once __DIR__ . '/gastos_budget_helper.php';
ensureGastosBudgetSchema($pdo);

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($can_access) || !$can_access('gastos')) {
    die("Acceso denegado.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    $color = $_POST['color'] ?? '#000000';
    $presupuesto_mensual = isset($_POST['presupuesto_mensual']) && $_POST['presupuesto_mensual'] !== '' ? (float)$_POST['presupuesto_mensual'] : null;
    $porcentaje_alerta = isset($_POST['porcentaje_alerta']) && $_POST['porcentaje_alerta'] !== '' ? (float)$_POST['porcentaje_alerta'] : 80;
    $bloquear_exceso = !empty($_POST['bloquear_exceso']) ? 1 : 0;
    $activo = !empty($_POST['activo']) ? 1 : 0;

    $errores = [];
    if ($nombre === '') $errores[] = 'El nombre es obligatorio';
    if ($presupuesto_mensual !== null && $presupuesto_mensual < 0) $errores[] = 'El presupuesto mensual no puede ser negativo';
    if ($porcentaje_alerta <= 0 || $porcentaje_alerta > 100) $errores[] = 'El porcentaje de alerta debe estar entre 1 y 100';

    if (empty($errores)) {
        try {
            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE tipos_gastos SET nombre = ?, descripcion = ?, color = ?, presupuesto_mensual = ?, porcentaje_alerta = ?, bloquear_exceso = ?, activo = ? WHERE id = ?");
                $stmt->execute([$nombre, $descripcion, $color, $presupuesto_mensual, $porcentaje_alerta, $bloquear_exceso, $activo, $id]);
                $mensaje = 'Tipo actualizado correctamente';
            } else {
                $stmt = $pdo->prepare("INSERT INTO tipos_gastos (nombre, descripcion, color, presupuesto_mensual, porcentaje_alerta, bloquear_exceso, activo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombre, $descripcion, $color, $presupuesto_mensual, $porcentaje_alerta, $bloquear_exceso, $activo]);
                $mensaje = 'Tipo creado correctamente';
            }
        } catch (Exception $e) {
            $error = 'Error: ' . $e->getMessage();
        }
    } else {
        $error = implode('<br>', $errores);
    }
}

$stmt = $pdo->query("SELECT * FROM tipos_gastos ORDER BY nombre");
$tipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-10 offset-md-1">
            <h2>Tipos de Gastos</h2>
            <p class="text-muted">Configurá presupuesto mensual, porcentaje de alerta y bloqueo por exceso para cada tipo.</p>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert"><?= htmlspecialchars($mensaje) ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Nuevo Tipo de Gasto</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="row g-3">
                        <div class="col-md-4">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>
                        <div class="col-md-2">
                            <label for="color" class="form-label">Color</label>
                            <input type="color" class="form-control form-control-color" id="color" name="color" value="#007BFF">
                        </div>
                        <div class="col-md-3">
                            <label for="presupuesto_mensual" class="form-label">Presupuesto mensual</label>
                            <input type="number" class="form-control" id="presupuesto_mensual" name="presupuesto_mensual" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="col-md-3">
                            <label for="porcentaje_alerta" class="form-label">Alerta desde %</label>
                            <input type="number" class="form-control" id="porcentaje_alerta" name="porcentaje_alerta" step="0.01" min="1" max="100" value="80">
                        </div>
                        <div class="col-12">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="bloquear_exceso" name="bloquear_exceso" checked>
                                <label class="form-check-label" for="bloquear_exceso">Bloquear si supera el presupuesto</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check mt-2">
                                <input type="checkbox" class="form-check-input" id="activo" name="activo" checked>
                                <label class="form-check-label" for="activo">Activo</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Crear Tipo</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5>Tipos Registrados y Configuración</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Color</th>
                                    <th>Descripción</th>
                                    <th>Presupuesto</th>
                                    <th>Alerta %</th>
                                    <th>Bloquear</th>
                                    <th>Activo</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tipos as $tipo): ?>
                                    <tr>
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?= (int)$tipo['id'] ?>">
                                            <td><input type="text" class="form-control form-control-sm" name="nombre" value="<?= htmlspecialchars((string)$tipo['nombre']) ?>" required></td>
                                            <td><input type="color" class="form-control form-control-color" name="color" value="<?= htmlspecialchars((string)($tipo['color'] ?? '#007BFF')) ?>"></td>
                                            <td><input type="text" class="form-control form-control-sm" name="descripcion" value="<?= htmlspecialchars((string)($tipo['descripcion'] ?? '')) ?>"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="presupuesto_mensual" step="0.01" min="0" value="<?= isset($tipo['presupuesto_mensual']) && $tipo['presupuesto_mensual'] !== null ? htmlspecialchars((string)$tipo['presupuesto_mensual']) : '' ?>" placeholder="Sin tope"></td>
                                            <td><input type="number" class="form-control form-control-sm" name="porcentaje_alerta" step="0.01" min="1" max="100" value="<?= htmlspecialchars((string)($tipo['porcentaje_alerta'] ?? 80)) ?>"></td>
                                            <td class="text-center"><input type="checkbox" class="form-check-input" name="bloquear_exceso" <?= !empty($tipo['bloquear_exceso']) ? 'checked' : '' ?>></td>
                                            <td class="text-center"><input type="checkbox" class="form-check-input" name="activo" <?= !empty($tipo['activo']) ? 'checked' : '' ?>></td>
                                            <td><button type="submit" class="btn btn-sm btn-outline-primary">Guardar</button></td>
                                        </form>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
