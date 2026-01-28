<?php
require 'config.php';
require 'includes/header.php';

$id = $_GET['id'] ?? 0;

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

// Obtener plantillas disponibles
$stmt = $pdo->query("SELECT * FROM plantillas_conceptos WHERE activo = 1 ORDER BY nombre");
$plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener plantilla actual del empleado
$stmt = $pdo->prepare("SELECT plantilla_id FROM empleado_plantilla WHERE empleado_id = ? LIMIT 1");
$stmt->execute([$id]);
$plantilla_actual = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener conceptos del empleado para el mes actual
$mes_actual = date('Y-m');
$stmt = $pdo->prepare("
    SELECT sc.*, c.nombre, c.tipo 
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ? AND sc.mes = ?
    ORDER BY c.tipo DESC, c.nombre
");
$stmt->execute([$id, $mes_actual]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'aplicar_plantilla') {
            // Aplicar plantilla: copiar todos los conceptos al empleado para el mes
            $plantilla_id = intval($_POST['plantilla_id']);
            
            // Obtener items de la plantilla
            $stmt = $pdo->prepare("SELECT * FROM plantilla_items WHERE plantilla_id = ?");
            $stmt->execute([$plantilla_id]);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Limpiar conceptos previos del mes
            $stmt = $pdo->prepare("DELETE FROM sueldo_conceptos WHERE empleado_id = ? AND mes = ?");
            $stmt->execute([$id, $mes_actual]);
            
            // Insertar conceptos de la plantilla
            foreach ($items as $item) {
                $stmt = $pdo->prepare("
                    INSERT INTO sueldo_conceptos (empleado_id, concepto_id, monto, formula, es_porcentaje, mes, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $id,
                    $item['concepto_id'],
                    $item['valor_fijo'],
                    $item['formula'],
                    $item['es_porcentaje'],
                    $mes_actual
                ]);
            }
            
            // Guardar relación empleado-plantilla
            $stmt = $pdo->prepare("
                INSERT INTO empleado_plantilla (empleado_id, plantilla_id) 
                VALUES (?, ?) 
                ON DUPLICATE KEY UPDATE fecha_asignacion = NOW()
            ");
            $stmt->execute([$id, $plantilla_id]);
            
            header("Location: sueldo_conceptos.php?id=$id&success=Plantilla aplicada");
            exit;
        }
        
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM sueldo_conceptos WHERE id = ?");
            $stmt->execute([$_POST['concepto_id']]);
            header("Location: sueldo_conceptos.php?id=$id&success=Concepto eliminado");
            exit;
        }
        
        // Agregar concepto manualmente
        $stmt = $pdo->prepare("
            INSERT INTO sueldo_conceptos (empleado_id, concepto_id, monto, formula, es_porcentaje, mes, fecha_creacion) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id,
            $_POST['concepto_id'],
            isset($_POST['monto']) ? floatval($_POST['monto']) : null,
            $_POST['formula'] ?? null,
            isset($_POST['es_porcentaje']) ? 1 : 0,
            $_POST['mes'] ?? $mes_actual
        ]);
        
        header("Location: sueldo_conceptos.php?id=$id&success=Concepto agregado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <h2>Conceptos de Sueldo - <?= htmlspecialchars($empleado['nombre']) ?></h2>
    <p class="text-muted">Mes: <strong><?= date('F Y', strtotime($mes_actual . '-01')) ?></strong></p>
    
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
    <?php endif; ?>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <!-- Aplicar Plantilla -->
    <div class="card mb-4 border-info">
        <div class="card-header bg-info text-white">
            <h5 class="mb-0">Aplicar Plantilla</h5>
        </div>
        <div class="card-body">
            <p>Selecciona una plantilla para aplicar todos sus conceptos a este empleado:</p>
            <form method="POST" class="d-flex gap-2">
                <input type="hidden" name="action" value="aplicar_plantilla">
                <select class="form-select" name="plantilla_id" required>
                    <option value="">Seleccionar plantilla...</option>
                    <?php foreach ($plantillas as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= $plantilla_actual && $plantilla_actual['plantilla_id'] == $p['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-info">Aplicar Plantilla</button>
            </form>
        </div>
    </div>
    
    <!-- Conceptos Actuales -->
    <div class="card mb-4">
        <div class="card-header">
            <h5>Conceptos del Mes</h5>
        </div>
        <div class="card-body">
            <?php if (empty($conceptos)): ?>
                <p class="text-muted">Sin conceptos. Aplica una plantilla o agrega conceptos manualmente.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Concepto</th>
                            <th>Tipo</th>
                            <th>Valor / Fórmula</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conceptos as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['nombre']) ?></td>
                            <td>
                                <span class="badge <?= $c['tipo'] === 'descuento' ? 'bg-danger' : 'bg-success' ?>">
                                    <?= ucfirst($c['tipo']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($c['formula']): ?>
                                    <code><?= htmlspecialchars($c['formula']) ?></code>
                                <?php elseif ($c['es_porcentaje']): ?>
                                    <?= $c['monto'] ?>%
                                <?php else: ?>
                                    $<?= number_format($c['monto'], 2, ',', '.') ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="concepto_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('¿Eliminar?')">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Agregar Concepto Manualmente -->
    <div class="card">
        <div class="card-header">
            <h5>Agregar Concepto Manualmente</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="row">
                    <div class="col-md-4">
                        <label class="form-label">Concepto</label>
                        <select class="form-control" name="concepto_id" required>
                            <option value="">Seleccionar...</option>
                            <?php
                            $stmt = $pdo->query("SELECT * FROM conceptos WHERE activo = 1 ORDER BY nombre");
                            $todos_conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach ($todos_conceptos as $tc):
                            ?>
                                <option value="<?= $tc['id'] ?>">
                                    <?= htmlspecialchars($tc['nombre']) ?> (<?= ucfirst($tc['tipo']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Monto / Porcentaje</label>
                        <input type="number" class="form-control" name="monto" step="0.01">
                    </div>
                    
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" name="es_porcentaje" id="es_porcentaje">
                            <label class="form-check-label" for="es_porcentaje">
                                ¿Porcentaje?
                            </label>
                        </div>
                    </div>
                    
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">Agregar</button>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-12">
                        <label class="form-label">Fórmula (Opcional)</label>
                        <input type="text" class="form-control" name="formula" placeholder="Ej: sueldo_base * 0.5">
                        <small class="text-muted">Variables disponibles: sueldo_base</small>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="sueldos.php" class="btn btn-secondary">Volver</a>
    </div>
</div>

<?php require 'includes/footer.php'; ?>

