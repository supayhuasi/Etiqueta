<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if (!isset($can_access) || !$can_access('seguros')) {
    die("Acceso denegado.");
}

$stmt_tipos = $pdo->query("SELECT id, nombre FROM tipos_seguros_permisos WHERE activo = 1 ORDER BY nombre");
$tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

// Precargar datos si viene de "Renovar" un registro existente
$base = [
    'vehiculo_patente' => '',
    'vehiculo_descripcion' => '',
    'tipo_id' => '',
    'numero' => '',
    'entidad' => '',
    'costo' => ''
];
$renovar_id = (int)($_GET['renovar_id'] ?? 0);
if ($renovar_id > 0) {
    $stmt_ren = $pdo->prepare("SELECT * FROM seguros_permisos WHERE id = ?");
    $stmt_ren->execute([$renovar_id]);
    $origen = $stmt_ren->fetch(PDO::FETCH_ASSOC);
    if ($origen) {
        $base = [
            'vehiculo_patente' => $origen['vehiculo_patente'],
            'vehiculo_descripcion' => $origen['vehiculo_descripcion'],
            'tipo_id' => $origen['tipo_id'],
            'numero' => $origen['numero'],
            'entidad' => $origen['entidad'],
            'costo' => $origen['costo']
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vehiculo_patente = strtoupper(trim($_POST['vehiculo_patente'] ?? ''));
    $vehiculo_descripcion = trim($_POST['vehiculo_descripcion'] ?? '');
    $tipo_id = (int)($_POST['tipo_id'] ?? 0);
    $numero = trim($_POST['numero'] ?? '');
    $entidad = trim($_POST['entidad'] ?? '');
    $fecha_emision = trim($_POST['fecha_emision'] ?? '') ?: null;
    $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    $costo = $_POST['costo'] !== '' ? floatval($_POST['costo']) : null;
    $observaciones = trim($_POST['observaciones'] ?? '');

    $errores = [];
    if (empty($vehiculo_patente)) $errores[] = "La patente del vehículo es obligatoria";
    if ($tipo_id <= 0) $errores[] = "Debe seleccionar un tipo";
    if (empty($fecha_vencimiento)) $errores[] = "La fecha de vencimiento es obligatoria";

    // Procesar archivo si existe
    $archivo = null;
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
        $tipos_permitidos = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'doc'];
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $tipos_permitidos)) {
            $errores[] = "Tipo de archivo no permitido";
        } elseif ($_FILES['archivo']['size'] > 5242880) { // 5MB
            $errores[] = "El archivo es muy grande (máximo 5MB)";
        } else {
            $archivo = "seguro_" . time() . "." . $ext;
            $upload_dir = __DIR__ . '/uploads/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0775, true);
            }

            $upload_error = $_FILES['archivo']['error'] ?? UPLOAD_ERR_OK;
            if ($upload_error !== UPLOAD_ERR_OK) {
                $errores[] = "Error al subir el archivo (código: $upload_error)";
                $archivo = null;
            } elseif (!is_writable($upload_dir)) {
                $errores[] = "La carpeta de subida no tiene permisos de escritura: $upload_dir";
                $archivo = null;
            } elseif (!is_uploaded_file($_FILES['archivo']['tmp_name'])) {
                $errores[] = "El archivo temporal no es válido";
                $archivo = null;
            } elseif (!move_uploaded_file($_FILES['archivo']['tmp_name'], $upload_dir . $archivo)) {
                $errores[] = "Error al subir el archivo";
                $archivo = null;
            }
        }
    }

    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO seguros_permisos
                    (vehiculo_patente, vehiculo_descripcion, tipo_id, numero, entidad, fecha_emision, fecha_vencimiento, costo, archivo, observaciones, usuario_registra)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $vehiculo_patente, $vehiculo_descripcion, $tipo_id, $numero, $entidad,
                $fecha_emision, $fecha_vencimiento, $costo, $archivo, $observaciones,
                $_SESSION['user']['id']
            ]);

            $mensaje = "Registro creado correctamente";
            $base = [
                'vehiculo_patente' => '', 'vehiculo_descripcion' => '', 'tipo_id' => '',
                'numero' => '', 'entidad' => '', 'costo' => ''
            ];
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errores);
        $base = [
            'vehiculo_patente' => $vehiculo_patente,
            'vehiculo_descripcion' => $vehiculo_descripcion,
            'tipo_id' => $tipo_id,
            'numero' => $numero,
            'entidad' => $entidad,
            'costo' => $costo
        ];
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Nuevo Seguro / Permiso</h2>

            <?php if ($renovar_id > 0 && !isset($mensaje)): ?>
                <div class="alert alert-info" role="alert">Renovando a partir del registro #<?= $renovar_id ?>. Completá la nueva fecha de vencimiento (y el nuevo número, si cambió).</div>
            <?php endif; ?>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $mensaje ?>
                    <br><a href="seguros.php" class="btn btn-primary btn-sm mt-2">Volver al listado</a>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger" role="alert"><?= $error ?></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="vehiculo_patente" class="form-label">Patente *</label>
                                <input type="text" class="form-control" id="vehiculo_patente" name="vehiculo_patente" value="<?= htmlspecialchars($base['vehiculo_patente']) ?>" placeholder="Ej: AB123CD" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vehiculo_descripcion" class="form-label">Vehículo</label>
                                <input type="text" class="form-control" id="vehiculo_descripcion" name="vehiculo_descripcion" value="<?= htmlspecialchars($base['vehiculo_descripcion']) ?>" placeholder="Ej: Renault Kangoo - Reparto">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="tipo_id" class="form-label">Tipo *</label>
                                <select class="form-select" id="tipo_id" name="tipo_id" required>
                                    <option value="">Seleccionar tipo...</option>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <option value="<?= $tipo['id'] ?>" <?= (string)$base['tipo_id'] === (string)$tipo['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tipo['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="numero" class="form-label">Número de póliza / permiso</label>
                                <input type="text" class="form-control" id="numero" name="numero" value="<?= htmlspecialchars($base['numero']) ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="entidad" class="form-label">Compañía / Organismo</label>
                                <input type="text" class="form-control" id="entidad" name="entidad" value="<?= htmlspecialchars($base['entidad']) ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de emisión</label>
                                <input type="date" class="form-control" id="fecha_emision" name="fecha_emision">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento *</label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="fecha_vencimiento" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="costo" class="form-label">Costo</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="costo" name="costo" step="0.01" min="0" value="<?= htmlspecialchars((string)$base['costo']) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo (póliza / permiso escaneado)</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.xls,.docx,.doc">
                            <small class="form-text text-muted">PDF, imágenes, Excel, Word (máx 5MB)</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="seguros.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Crear Registro</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
