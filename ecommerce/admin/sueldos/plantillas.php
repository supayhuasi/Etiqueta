<?php

require '../../config.php';
require '../includes/header.php';
if (!isset($_SESSION)) {
    session_start();
}
// Solo admins
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin'){
    die("Acceso denegado");
}

// Obtener plantillas
$stmt = $pdo->query("SELECT * FROM plantillas_conceptos WHERE activo = 1 ORDER BY nombre");
$plantillas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container mt-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2>Plantillas de Conceptos de Sueldo</h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="plantillas_crear.php" class="btn btn-primary">+ Nueva Plantilla</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <?php if (empty($plantillas)): ?>
                <p class="text-muted">No hay plantillas creadas</p>
            <?php else: ?>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Descripción</th>
                            <th>Conceptos</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($plantillas as $p): ?>
                            <?php
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM plantilla_items WHERE plantilla_id = ?");
                            $stmt->execute([$p['id']]);
                            $cant = $stmt->fetchColumn();
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($p['nombre']) ?></td>
                                <td><?= htmlspecialchars($p['descripcion']) ?></td>
                                <td><span class="badge bg-info"><?= $cant ?></span></td>
                                <td>
                                    <a href="plantillas_editar.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning">Editar</a>
                                    <a href="plantillas_items.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-info">Conceptos</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <a href="sueldos.php" class="btn btn-secondary">Volver a Sueldos</a>
    </div>
</div>

<?php require '../includes/footer.php'; ?>
