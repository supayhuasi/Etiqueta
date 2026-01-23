<?php
require 'config.php';
require 'includes/header.php';

// Solo admins
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    die("Acceso denegado");
}

// Obtener lista de empleados
$stmt = $pdo->query("
    SELECT e.id, e.nombre, e.email, e.sueldo_base, e.activo, ep.plantilla_id, pc.nombre as plantilla_nombre
    FROM empleados e
    LEFT JOIN empleado_plantilla ep ON e.id = ep.empleado_id
    LEFT JOIN plantillas_conceptos pc ON ep.plantilla_id = pc.id
    ORDER BY e.nombre ASC
");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mes actual para el recibo
$mes_actual = date('Y-m');
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Gestión de Sueldos</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="empleados_crear.php" class="btn btn-primary">+ Nuevo Empleado</a>
            <a href="plantillas.php" class="btn btn-success">📋 Plantillas</a>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>Empleados</h5>
        </div>
        <div class="card-body">
            <?php if (empty($empleados)): ?>
                <p class="text-muted">No hay empleados registrados</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Empleado</th>
                            <th>Sueldo Base</th>
                            <th>Plantilla Actual</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($emp['nombre']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($emp['email']) ?></small>
                            </td>
                            <td>$<?= number_format($emp['sueldo_base'], 2, ',', '.') ?></td>
                            <td>
                                <?php if ($emp['plantilla_nombre']): ?>
                                    <span class="badge bg-info"><?= htmlspecialchars($emp['plantilla_nombre']) ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Sin plantilla</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="sueldo_editar.php?id=<?= $emp['id'] ?>" class="btn btn-warning" title="Editar datos">✎</a>
                                    <a href="sueldo_conceptos.php?id=<?= $emp['id'] ?>" class="btn btn-info" title="Conceptos y plantilla">💰</a>
                                    <a href="sueldo_recibo.php?id=<?= $emp['id'] ?>&mes=<?= $mes_actual ?>" class="btn btn-primary" title="Ver recibo">🧾</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Resumen de Acciones -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">📋 Plantillas</h5>
                    <p class="card-text">Crear y gestionar plantillas de conceptos</p>
                    <a href="plantillas.php" class="btn btn-success">Ir a Plantillas</a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">👥 Empleados</h5>
                    <p class="card-text">Crear y editar empleados</p>
                    <a href="empleados_crear.php" class="btn btn-primary">Nuevo Empleado</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
