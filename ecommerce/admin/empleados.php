<?php
require '../includes/header.php';

// Verificar sesión
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Obtener empleados
$stmt = $pdo->query("SELECT * FROM empleados ORDER BY nombre ASC");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2>Empleados</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="empleados_crear.php" class="btn btn-primary">+ Nuevo Empleado</a>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h5>Listado de Empleados</h5>
        </div>
        <div class="card-body">
            <?php if (empty($empleados)): ?>
                <p class="text-muted">No hay empleados registrados</p>
            <?php else: ?>
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Email</th>
                            <th>Documento</th>
                            <th>Puesto</th>
                            <th>Departamento</th>
                            <th>Sueldo Base</th>
                            <th>Activo</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($empleados as $emp): ?>
                        <tr>
                            <td><?= htmlspecialchars($emp['nombre']) ?></td>
                            <td><?= htmlspecialchars($emp['email']) ?></td>
                            <td><?= htmlspecialchars($emp['documento']) ?></td>
                            <td><?= htmlspecialchars($emp['puesto']) ?></td>
                            <td><?= htmlspecialchars($emp['departamento']) ?></td>
                            <td>$<?= number_format($emp['sueldo_base'], 2, ',', '.') ?></td>
                            <td><?= $emp['activo'] ? '<span class="badge bg-success">Sí</span>' : '<span class="badge bg-danger">No</span>' ?></td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="empleados_editar.php?id=<?= $emp['id'] ?>" class="btn btn-warning" title="Editar">✎</a>
                                    <!-- Baja lógica -->
                                    <?php if ($emp['activo']): ?>
                                        <a href="empleados_baja.php?id=<?= $emp['id'] ?>" class="btn btn-danger" title="Dar de baja" onclick="return confirm('¿Dar de baja a este empleado?');">🛑</a>
                                    <?php else: ?>
                                        <a href="empleados_alta.php?id=<?= $emp['id'] ?>" class="btn btn-success" title="Reactivar">✔</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require '../includes/footer.php'; ?>
