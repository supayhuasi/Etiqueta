<?php
require 'config.php';
require 'includes/header.php';

if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

$plantilla_id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT * FROM plantillas_conceptos WHERE id = ?");
$stmt->execute([$plantilla_id]);
$plantilla = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$plantilla) {
    die("Plantilla no encontrada");
}

// Obtener conceptos en la plantilla
$stmt = $pdo->prepare("
    SELECT pi.*, c.nombre, c.tipo 
    FROM plantilla_items pi
    JOIN conceptos c ON pi.concepto_id = c.id
    WHERE pi.plantilla_id = ?
    ORDER BY pi.orden
");
$stmt->execute([$plantilla_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener conceptos disponibles
$stmt = $pdo->query("SELECT * FROM conceptos WHERE activo = 1 ORDER BY nombre");
$conceptos = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['action']) && $_POST['action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM plantilla_items WHERE id = ?");
            $stmt->execute([$_POST['item_id']]);
            header("Location: plantillas_items.php?id=$plantilla_id&success=Concepto eliminado");
            exit;
        } else {
            // Agregar nuevo concepto a plantilla
            $stmt = $pdo->prepare("
                INSERT INTO plantilla_items (plantilla_id, concepto_id, formula, valor_fijo, es_porcentaje, orden)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $plantilla_id,
                $_POST['concepto_id'],
                $_POST['formula'] ?: null,
                isset($_POST['valor_fijo']) ? floatval($_POST['valor_fijo']) : null,
                isset($_POST['es_porcentaje']) ? 1 : 0,
                intval($_POST['orden'] ?? 0)
            ]);
            header("Location: plantillas_items.php?id=$plantilla_id&success=Concepto agregado");
            exit;
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-10">
            <h2>Conceptos - <?= htmlspecialchars($plantilla['nombre']) ?></h2>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            
            <!-- Conceptos Actuales -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>Conceptos Configurados</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($items)): ?>
                        <p class="text-muted">Sin conceptos agregados</p>
                    <?php else: ?>
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Orden</th>
                                    <th>Concepto</th>
                                    <th>Tipo</th>
                                    <th>Valor / Fórmula</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?= $item['orden'] ?></td>
                                    <td><?= htmlspecialchars($item['nombre']) ?></td>
                                    <td>
                                        <span class="badge <?= $item['tipo'] === 'descuento' ? 'bg-danger' : 'bg-success' ?>">
                                            <?= ucfirst($item['tipo']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['formula']): ?>
                                            <code><?= htmlspecialchars($item['formula']) ?></code>
                                        <?php elseif ($item['es_porcentaje']): ?>
                                            <?= $item['valor_fijo'] ?>%
                                        <?php else: ?>
                                            $<?= number_format($item['valor_fijo'], 2, ',', '.') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
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
            
            <!-- Agregar Concepto -->
            <div class="card">
                <div class="card-header">
                    <h5>Agregar Concepto a Plantilla</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-3">
                                <label class="form-label">Concepto</label>
                                <select class="form-control" name="concepto_id" required>
                                    <option value="">Seleccionar...</option>
                                    <?php foreach ($conceptos as $c): ?>
                                        <option value="<?= $c['id'] ?>">
                                            <?= htmlspecialchars($c['nombre']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label class="form-label">Orden</label>
                                <input type="number" class="form-control" name="orden" value="<?= count($items) + 1 ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label class="form-label">Valor / Fórmula</label>
                                <input type="text" class="form-control" name="valor_fijo" placeholder="Ej: 1000 o porcentaje">
                                <small class="text-muted">Valor fijo o porcentaje</small>
                            </div>
                            
                            <div class="col-md-2">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="es_porcentaje" id="es_porcentaje">
                                    <label class="form-check-label" for="es_porcentaje">
                                        ¿Es porcentaje?
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Agregar</button>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-12">
                                <label class="form-label">Fórmula (Opcional)</label>
                                <input type="text" class="form-control" name="formula" placeholder="Ej: sueldo_base * 0.5">
                                <small class="text-muted">Fórmula dinámica: sueldo_base, variable1, etc. Se ignora si hay valor fijo</small>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="plantillas.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
