<?php
require 'config.php';
require 'includes/header.php';

$id_empleado = $_GET['id'] ?? 0;

// Obtener datos del empleado
$stmt = $pdo->prepare("SELECT * FROM empleados WHERE id = ?");
$stmt->execute([$id_empleado]);
$empleado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empleado) {
    die("Empleado no encontrado");
}

// Obtener conceptos del empleado
$stmt = $pdo->prepare("
    SELECT sc.*, c.nombre, c.tipo 
    FROM sueldo_conceptos sc
    JOIN conceptos c ON sc.concepto_id = c.id
    WHERE sc.empleado_id = ?
    ORDER BY c.nombre
");
$stmt->execute([$id_empleado]);
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener todos los conceptos disponibles
$stmt = $pdo->query("SELECT * FROM conceptos WHERE activo = 1 ORDER BY nombre");
$todos_conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Eliminar concepto existente si se pasa action=delete
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM sueldo_conceptos WHERE id = ?");
            $stmt->execute([$_POST['concepto_id']]);
            header("Location: sueldo_conceptos.php?id=$id_empleado&success=Concepto eliminado");
            exit;
        }
        
        // Agregar nuevo concepto
        $stmt = $pdo->prepare("
            INSERT INTO sueldo_conceptos (empleado_id, concepto_id, monto, fecha_creacion) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([
            $id_empleado,
            $_POST['concepto_id'],
            floatval($_POST['monto'])
        ]);
        
        header("Location: sueldo_conceptos.php?id=$id_empleado&success=Concepto agregado");
        exit;
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-10">
            <h2>Conceptos de Sueldo - <?= htmlspecialchars($empleado['nombre']) ?></h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <!-- Tabla de Conceptos Actuales -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Conceptos Actuales</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($conceptos)): ?>
                        <p class="text-muted">Sin conceptos adicionales configurados</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th>Tipo</th>
                                    <th>Monto</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($conceptos as $concepto): ?>
                                <tr>
                                    <td><?= htmlspecialchars($concepto['nombre']) ?></td>
                                    <td>
                                        <span class="badge <?= $concepto['tipo'] === 'descuento' ? 'bg-danger' : 'bg-success' ?>">
                                            <?= ucfirst($concepto['tipo']) ?>
                                        </span>
                                    </td>
                                    <td>$<?= number_format($concepto['monto'], 2, ',', '.') ?></td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="concepto_id" value="<?= $concepto['id'] ?>">
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
            
            <!-- Agregar Nuevo Concepto -->
            <div class="card">
                <div class="card-header">
                    <h5>Agregar Concepto</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-5">
                                <label class="form-label">Concepto</label>
                                <select class="form-control" name="concepto_id" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($todos_conceptos as $c): ?>
                                        <option value="<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['nombre']) ?> (<?= ucfirst($c['tipo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monto ($)</label>
                                <input type="number" class="form-control" name="monto" step="0.01" required>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Agregar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="sueldos.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
