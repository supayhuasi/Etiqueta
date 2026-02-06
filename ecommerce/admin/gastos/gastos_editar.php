<?php
require '../includes/header.php';

session_start();
if (!isset($_SESSION['user'])) {
    header("Location: auth/login.php");
    exit;
}

if ($_SESSION['rol'] !== 'admin') {
    die("Acceso denegado.");
}

$id = $_GET['id'] ?? 0;

// Obtener datos del gasto
$stmt = $pdo->prepare("SELECT * FROM gastos WHERE id = ?");
$stmt->execute([$id]);
$gasto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$gasto) {
    die("Gasto no encontrado");
}

// Obtener tipos y estados
$stmt_tipos = $pdo->query("SELECT id, nombre FROM tipos_gastos WHERE activo = 1 ORDER BY nombre");
$tipos = $stmt_tipos->fetchAll(PDO::FETCH_ASSOC);

$stmt_estados = $pdo->query("SELECT id, nombre FROM estados_gastos WHERE activo = 1 ORDER BY nombre");
$estados = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

// Obtener empleados
$stmt_empleados = $pdo->query("SELECT id, nombre FROM empleados WHERE activo = 1 ORDER BY nombre");
$empleados = $stmt_empleados->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fecha = $_POST['fecha'] ?? '';
    $tipo_gasto_id = $_POST['tipo_gasto_id'] ?? 0;
    $estado_gasto_id = $_POST['estado_gasto_id'] ?? 0;
    $descripcion = $_POST['descripcion'] ?? '';
    $monto = floatval($_POST['monto'] ?? 0);
    $empleado_id = $_POST['empleado_id'] ?? null;
    $observaciones = $_POST['observaciones'] ?? '';
    
    $errores = [];
    
    if (empty($fecha)) $errores[] = "La fecha es obligatoria";
    if ($tipo_gasto_id <= 0) $errores[] = "Debe seleccionar un tipo de gasto";
    if ($estado_gasto_id <= 0) $errores[] = "Debe seleccionar un estado";
    if (empty($descripcion)) $errores[] = "La descripción es obligatoria";
    if ($monto <= 0) $errores[] = "El monto debe ser mayor a 0";
    
    $archivo = $gasto['archivo'];
    
    // Procesar archivo si existe
    if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] == 0) {
        $tipos_permitidos = ['pdf', 'jpg', 'jpeg', 'png', 'xlsx', 'xls', 'docx', 'doc'];
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($ext, $tipos_permitidos)) {
            $errores[] = "Tipo de archivo no permitido";
        } else if ($_FILES['archivo']['size'] > 5242880) {
            $errores[] = "El archivo es muy grande";
        } else {
            // Eliminar archivo anterior si existe
            if (!empty($gasto['archivo']) && file_exists("upload/gastos/" . $gasto['archivo'])) {
                unlink("uploads/gastos/" . $gasto['archivo']);
            }
            
            $archivo = "gasto_" . time() . "." . $ext;
            if (!move_uploaded_file($_FILES['archivo']['tmp_name'], "uploads/gastos/" . $archivo)) {
                $errores[] = "Error al subir el archivo";
                $archivo = $gasto['archivo'];
            }
        }
    }
    
    if (empty($errores)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE gastos 
                SET fecha = ?, tipo_gasto_id = ?, empleado_id = ?, estado_gasto_id = ?, descripcion = ?, monto = ?, 
                    observaciones = ?, archivo = ?
                WHERE id = ?
            ");
            $stmt->execute([$fecha, $tipo_gasto_id, $empleado_id, $estado_gasto_id, $descripcion, $monto, 
                           $observaciones, $archivo, $id]);
            
            // Si el nuevo estado es "Pagado", registrar en flujo de caja
            $stmt_pagado = $pdo->prepare("SELECT id FROM estados_gastos WHERE LOWER(nombre) = 'pagado' LIMIT 1");
            $stmt_pagado->execute();
            $pagado_id = $stmt_pagado->fetchColumn();

            if ($pagado_id && (int)$estado_gasto_id === (int)$pagado_id) {
                try {
                    // Verificar si ya existe en flujo_caja
                    $stmt_fc_check = $pdo->prepare("
                        SELECT id FROM flujo_caja 
                        WHERE id_referencia = ? AND categoria = 'Gasto'
                        LIMIT 1
                    ");
                    $stmt_fc_check->execute([$id]);
                    $fc_existe = $stmt_fc_check->fetch(PDO::FETCH_ASSOC);

                    if ($fc_existe) {
                        $stmt_fc_update = $pdo->prepare("
                            UPDATE flujo_caja
                            SET fecha = ?, descripcion = ?, monto = ?, observaciones = ?
                            WHERE id_referencia = ? AND categoria = 'Gasto'
                        ");
                        $stmt_fc_update->execute([
                            $fecha,
                            $descripcion,
                            $monto,
                            $observaciones ?: 'Actualizado desde edición en estado Pagado',
                            $id
                        ]);
                    } else {
                        // Solo crear si no existe
                        $stmt_fc = $pdo->prepare("
                            INSERT INTO flujo_caja 
                            (fecha, tipo, categoria, descripcion, monto, referencia, id_referencia, usuario_id, observaciones)
                            VALUES (?, 'egreso', 'Gasto', ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt_fc->execute([
                            $fecha,
                            $descripcion,
                            $monto,
                            $gasto['numero_gasto'],
                            $id,
                            $_SESSION['user']['id'],
                            $observaciones ?: 'Registrado desde edición'
                        ]);
                    }
                } catch (Exception $e) {
                    // Si falla el flujo de caja, no afecta el cambio de estado
                }
            }
            
            $mensaje = "Gasto actualizado correctamente";
            
            // Recargar datos
            $stmt = $pdo->prepare("SELECT * FROM gastos WHERE id = ?");
            $stmt->execute([$id]);
            $gasto = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $error = "Error al guardar: " . $e->getMessage();
        }
    } else {
        $error = implode("<br>", $errores);
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <h2>Editar Gasto</h2>

            <?php if (isset($mensaje)): ?>
                <div class="alert alert-success" role="alert">
                    <?= $mensaje ?>
                    <br><a href="gastos.php" class="btn btn-primary btn-sm mt-2">Volver a Gastos</a>
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
                                <label for="fecha" class="form-label">Fecha *</label>
                                <input type="date" class="form-control" id="fecha" name="fecha" 
                                       value="<?= $gasto['fecha'] ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="monto" class="form-label">Monto *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="monto" name="monto" 
                                           value="<?= $gasto['monto'] ?>" step="0.01" min="0" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="tipo_gasto_id" class="form-label">Tipo de Gasto *</label>
                                <select class="form-select" id="tipo_gasto_id" name="tipo_gasto_id" required>
                                    <?php foreach ($tipos as $tipo): ?>
                                        <option value="<?= $tipo['id'] ?>" <?= $tipo['id'] == $gasto['tipo_gasto_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($tipo['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="estado_gasto_id" class="form-label">Estado *</label>
                                <select class="form-select" id="estado_gasto_id" name="estado_gasto_id" required>
                                    <?php foreach ($estados as $estado): ?>
                                        <option value="<?= $estado['id'] ?>" <?= $estado['id'] == $gasto['estado_gasto_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($estado['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3" required><?= htmlspecialchars($gasto['descripcion']) ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="beneficiario" class="form-label">Beneficiario (Empleado)</label>
                            <select class="form-select" id="beneficiario" name="empleado_id">
                                <option value="">Seleccionar empleado...</option>
                                <?php foreach ($empleados as $empleado): ?>
                                    <option value="<?= $empleado['id'] ?>" <?= $empleado['id'] == ($gasto['empleado_id'] ?? 0) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($empleado['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="observaciones" class="form-label">Observaciones</label>
                            <textarea class="form-control" id="observaciones" name="observaciones" rows="2"><?= htmlspecialchars($gasto['observaciones'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="archivo" class="form-label">Archivo (Comprobante)</label>
                            <?php if (!empty($gasto['archivo'])): ?>
                                <p><small>Archivo actual: <?= htmlspecialchars($gasto['archivo']) ?></small></p>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="archivo" name="archivo">
                            <small class="form-text text-muted">Dejar vacío para mantener el archivo actual</small>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <a href="gastos.php" class="btn btn-secondary">Cancelar</a>
                            <button type="submit" class="btn btn-primary">Actualizar Gasto</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
