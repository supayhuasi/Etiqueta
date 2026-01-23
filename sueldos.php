<?php
require 'config.php';
require 'includes/header.php';

// Obtener lista de empleados
$stmt = $pdo->query("
    SELECT id, nombre, email, sueldo_base, activo 
    FROM empleados 
    ORDER BY nombre ASC
");
$empleados = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Gestión de Sueldos</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="empleados_crear.php" class="btn btn-primary">+ Nuevo Empleado</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Sueldo Base</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleados as $emp): ?>
                    <tr>
                        <td><?= htmlspecialchars($emp['nombre']) ?></td>
                        <td>$<?= number_format($emp['sueldo_base'], 2, ',', '.') ?></td>
                        <td>
                            <a href="sueldo_editar.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                            <a href="sueldo_conceptos.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-info">Conceptos</a>
                            <a href="sueldo_recibo.php?id=<?= $emp['id'] ?>" class="btn btn-sm btn-secondary">Ver Recibo</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
