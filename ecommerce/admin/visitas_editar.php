<?php
require 'includes/header.php';

$visita_id = (int)($_GET['id'] ?? 0);
$estados_validos = ['pendiente', 'en_proceso', 'completada', 'cancelada'];
$error = '';

if ($visita_id <= 0) {
    die('Visita no valida');
}

$stmt = $pdo->prepare("SELECT * FROM ecommerce_visitas WHERE id = ?");
$stmt->execute([$visita_id]);
$visita = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$visita) {
    die('Visita no encontrada');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $titulo = trim($_POST['titulo'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $cliente_nombre = trim($_POST['cliente_nombre'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $direccion = trim($_POST['direccion'] ?? '');
        $fecha_visita = trim($_POST['fecha_visita'] ?? '');
        $hora_visita = trim($_POST['hora_visita'] ?? '');
        $estado = trim($_POST['estado'] ?? 'pendiente');

        if ($titulo === '' || $fecha_visita === '') {
            throw new Exception('El titulo y la fecha de visita son obligatorios.');
        }

        if (!in_array($estado, $estados_validos, true)) {
            throw new Exception('El estado seleccionado no es valido.');
        }

        if ($hora_visita !== '' && !preg_match('/^([01]\\d|2[0-3]):([0-5]\\d)$/', $hora_visita)) {
            throw new Exception('La hora de visita es invalida.');
        }

        $hora_visita_db = $hora_visita !== '' ? ($hora_visita . ':00') : null;

        $stmt = $pdo->prepare("UPDATE ecommerce_visitas
            SET titulo = ?, descripcion = ?, cliente_nombre = ?, telefono = ?, direccion = ?, fecha_visita = ?, hora_visita = ?, estado = ?
            WHERE id = ?");
        $stmt->execute([
            $titulo,
            $descripcion !== '' ? $descripcion : null,
            $cliente_nombre !== '' ? $cliente_nombre : null,
            $telefono !== '' ? $telefono : null,
            $direccion !== '' ? $direccion : null,
            $fecha_visita,
            $hora_visita_db,
            $estado,
            $visita_id
        ]);

        header('Location: visitas.php?mensaje=editada');
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    // Mantener en pantalla los valores enviados si hubo error.
    $visita['titulo'] = $titulo ?? $visita['titulo'];
    $visita['descripcion'] = $descripcion ?? $visita['descripcion'];
    $visita['cliente_nombre'] = $cliente_nombre ?? $visita['cliente_nombre'];
    $visita['telefono'] = $telefono ?? $visita['telefono'];
    $visita['direccion'] = $direccion ?? $visita['direccion'];
    $visita['fecha_visita'] = $fecha_visita ?? $visita['fecha_visita'];
    $visita['hora_visita'] = $hora_visita !== '' ? ($hora_visita . ':00') : null;
    $visita['estado'] = $estado ?? $visita['estado'];
}

$hora_form = '';
if (!empty($visita['hora_visita'])) {
    $hora_form = date('H:i', strtotime($visita['hora_visita']));
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>Editar visita</h1>
        <p class="text-muted mb-0">Actualiza los datos de la visita programada.</p>
    </div>
    <a href="visitas.php" class="btn btn-outline-secondary">Volver</a>
</div>

<?php if ($error !== ''): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Titulo</label>
                <input type="text" name="titulo" class="form-control" maxlength="180" required value="<?= htmlspecialchars($visita['titulo'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Fecha de visita</label>
                <input type="date" name="fecha_visita" class="form-control" required value="<?= htmlspecialchars($visita['fecha_visita'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Hora de visita</label>
                <input type="time" name="hora_visita" class="form-control" value="<?= htmlspecialchars($hora_form) ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select" required>
                    <option value="pendiente" <?= ($visita['estado'] ?? '') === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="en_proceso" <?= ($visita['estado'] ?? '') === 'en_proceso' ? 'selected' : '' ?>>En proceso</option>
                    <option value="completada" <?= ($visita['estado'] ?? '') === 'completada' ? 'selected' : '' ?>>Completada</option>
                    <option value="cancelada" <?= ($visita['estado'] ?? '') === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Cliente</label>
                <input type="text" name="cliente_nombre" class="form-control" maxlength="150" value="<?= htmlspecialchars($visita['cliente_nombre'] ?? '') ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label">Telefono</label>
                <input type="text" name="telefono" class="form-control" maxlength="60" value="<?= htmlspecialchars($visita['telefono'] ?? '') ?>">
            </div>

            <div class="col-md-12">
                <label class="form-label">Direccion</label>
                <input type="text" name="direccion" class="form-control" maxlength="255" value="<?= htmlspecialchars($visita['direccion'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Descripcion</label>
                <textarea name="descripcion" class="form-control" rows="4" placeholder="Detalles de la visita..."><?= htmlspecialchars($visita['descripcion'] ?? '') ?></textarea>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Guardar cambios</button>
                <a href="visitas.php" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
