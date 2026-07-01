<?php
require '../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../../auth/login.php');
    exit;
}

require 'includes/header.php';
require_once 'includes/cuentas_helper.php';
ensureCuentasSchema($pdo);

$id = intval($_GET['id'] ?? 0);
$cuenta = $id > 0 ? cuentas_get($pdo, $id) : null;
if ($id > 0 && !$cuenta) {
    die("Cuenta no encontrada");
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $nombre = trim((string)($_POST['nombre'] ?? ''));
        $tipo = trim((string)($_POST['tipo'] ?? '')) ?: 'Operativa';
        $descripcion = trim((string)($_POST['descripcion'] ?? ''));
        $activo = !empty($_POST['activo']) ? 1 : 0;

        if ($nombre === '') {
            throw new Exception('El nombre es obligatorio');
        }

        $stmt = $pdo->prepare("SELECT id FROM cuentas WHERE nombre = ? AND id <> ?");
        $stmt->execute([$nombre, $id]);
        if ($stmt->fetch()) {
            throw new Exception('Ya existe una cuenta con ese nombre');
        }

        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE cuentas SET nombre = ?, tipo = ?, descripcion = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $tipo, $descripcion ?: null, $activo, $id]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO cuentas (nombre, tipo, descripcion, activo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nombre, $tipo, $descripcion ?: null, $activo]);
        }

        header('Location: cuentas.php');
        exit;
    } catch (Exception $e) {
        $error = $e->getMessage();
        $cuenta = [
            'id' => $id,
            'nombre' => $_POST['nombre'] ?? '',
            'tipo' => $_POST['tipo'] ?? '',
            'descripcion' => $_POST['descripcion'] ?? '',
            'activo' => !empty($_POST['activo']) ? 1 : 0,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $id > 0 ? 'Editar Cuenta' : 'Nueva Cuenta' ?></title>
    <link href="../../assets/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container my-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1><?= $id > 0 ? '✏️ Editar Cuenta' : '➕ Nueva Cuenta' ?></h1>
        </div>
        <div class="col-md-4 text-end">
            <a href="cuentas.php" class="btn btn-secondary">← Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><strong>Error:</strong> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label for="nombre" class="form-label">Nombre *</label>
                    <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($cuenta['nombre'] ?? '') ?>" placeholder="Ej: Caja / Operativa, Cuenta Inversión, Cuenta Producción" required>
                </div>
                <div class="mb-3">
                    <label for="tipo" class="form-label">Tipo</label>
                    <input type="text" class="form-control" id="tipo" name="tipo" list="tipos_sugeridos" value="<?= htmlspecialchars($cuenta['tipo'] ?? 'Operativa') ?>" placeholder="Ej: Operativa, Inversión, Producción">
                    <datalist id="tipos_sugeridos">
                        <option value="Operativa">
                        <option value="Caja">
                        <option value="Banco">
                        <option value="Inversión">
                        <option value="Producción">
                        <option value="Ahorro">
                    </datalist>
                    <small class="text-muted">Es un campo libre: escribí el que mejor organice tu negocio.</small>
                </div>
                <div class="mb-3">
                    <label for="descripcion" class="form-label">Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($cuenta['descripcion'] ?? '') ?></textarea>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" value="1" id="activo" name="activo" <?= (!isset($cuenta['activo']) || (int)$cuenta['activo'] === 1) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="activo">Cuenta activa</label>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">💾 Guardar</button>
                    <a href="cuentas.php" class="btn btn-secondary btn-lg">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
