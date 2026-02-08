<?php
require 'includes/header.php';

$search = trim($_GET['q'] ?? '');
$params = [];
$where = '';
if ($search !== '') {
    $where = "WHERE c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?";
    $like = '%' . $search . '%';
    $params = [$like, $like, $like];
}

$sql = "
    SELECT c.id, c.nombre, c.email, c.telefono, c.provincia, c.localidad, c.ciudad, c.direccion,
           c.activo, c.email_verificado, c.auth_provider, c.fecha_creacion
    FROM ecommerce_clientes c
    $where
    ORDER BY c.fecha_creacion DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>👥 Clientes Registrados</h1>
        <p class="text-muted">Listado de clientes que se registraron en la web</p>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-center" method="GET">
            <div class="col-md-6">
                <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, email o teléfono" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">Buscar</button>
                <a href="clientes_web.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
            <div class="col-md-3 text-end">
                <span class="text-muted">Total: <strong><?= count($clientes) ?></strong></span>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="alert alert-info">No hay clientes registrados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Ubicación</th>
                            <th>Registro</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $c): ?>
                            <tr>
                                <td>#<?= (int)$c['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                                    <div class="text-muted small">
                                        <?= htmlspecialchars($c['auth_provider'] ?: 'email') ?>
                                    </div>
                                </td>
                                <td>
                                    <?= htmlspecialchars($c['email']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($c['telefono'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <small>
                                        <?= htmlspecialchars(trim(($c['localidad'] ?? '') . ' ' . ($c['ciudad'] ?? ''))) ?><br>
                                        <?= htmlspecialchars($c['provincia'] ?? '-') ?>
                                    </small>
                                </td>
                                <td>
                                    <?= !empty($c['fecha_creacion']) ? date('d/m/Y H:i', strtotime($c['fecha_creacion'])) : '-' ?>
                                </td>
                                <td>
                                    <?php if ((int)$c['activo'] === 1): ?>
                                        <span class="badge bg-success">Activo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactivo</span>
                                    <?php endif; ?>
                                    <?php if ((int)$c['email_verificado'] === 1): ?>
                                        <span class="badge bg-info text-dark">Email verificado</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
