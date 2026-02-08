<?php
require 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_activo'])) {
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $nuevo_estado = (int)($_POST['nuevo_estado'] ?? 0);
    if ($cliente_id > 0) {
        $stmt = $pdo->prepare("UPDATE ecommerce_clientes SET activo = ? WHERE id = ?");
        $stmt->execute([$nuevo_estado, $cliente_id]);
    }
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: clientes_web.php' . ($qs ? '?' . $qs : ''));
    exit;
}

$search = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? '';
$verificado = $_GET['verificado'] ?? '';
$provider = $_GET['provider'] ?? '';
$fecha_desde = $_GET['desde'] ?? '';
$fecha_hasta = $_GET['hasta'] ?? '';

$params = [];
$where_parts = [];

if ($search !== '') {
    $where_parts[] = "(c.nombre LIKE ? OR c.email LIKE ? OR c.telefono LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like]);
}
if ($estado !== '') {
    $where_parts[] = "c.activo = ?";
    $params[] = (int)$estado;
}
if ($verificado !== '') {
    $where_parts[] = "c.email_verificado = ?";
    $params[] = (int)$verificado;
}
if ($provider !== '') {
    $where_parts[] = "c.auth_provider = ?";
    $params[] = $provider;
}
if ($fecha_desde !== '') {
    $where_parts[] = "DATE(c.fecha_creacion) >= ?";
    $params[] = $fecha_desde;
}
if ($fecha_hasta !== '') {
    $where_parts[] = "DATE(c.fecha_creacion) <= ?";
    $params[] = $fecha_hasta;
}

$where = '';
if (!empty($where_parts)) {
    $where = 'WHERE ' . implode(' AND ', $where_parts);
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
        <form class="row g-2 align-items-end" method="GET">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" placeholder="Nombre, email o teléfono" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $estado === '1' ? 'selected' : '' ?>>Activos</option>
                    <option value="0" <?= $estado === '0' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Verificación</label>
                <select name="verificado" class="form-select">
                    <option value="">Todos</option>
                    <option value="1" <?= $verificado === '1' ? 'selected' : '' ?>>Verificados</option>
                    <option value="0" <?= $verificado === '0' ? 'selected' : '' ?>>No verificados</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Proveedor</label>
                <select name="provider" class="form-select">
                    <option value="">Todos</option>
                    <option value="email" <?= $provider === 'email' ? 'selected' : '' ?>>Email</option>
                    <option value="google" <?= $provider === 'google' ? 'selected' : '' ?>>Google</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Desde</label>
                <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hasta</label>
                <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary">Filtrar</button>
                <a href="clientes_web.php" class="btn btn-outline-secondary">Limpiar</a>
            </div>
            <div class="col-md-8 text-end">
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
                            <th>Acciones</th>
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
                                <td>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="cliente_id" value="<?= (int)$c['id'] ?>">
                                        <?php if ((int)$c['activo'] === 1): ?>
                                            <input type="hidden" name="nuevo_estado" value="0">
                                            <button type="submit" name="toggle_activo" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Inhabilitar cliente?')">Inhabilitar</button>
                                        <?php else: ?>
                                            <input type="hidden" name="nuevo_estado" value="1">
                                            <button type="submit" name="toggle_activo" class="btn btn-sm btn-outline-success" onclick="return confirm('¿Habilitar cliente?')">Habilitar</button>
                                        <?php endif; ?>
                                    </form>
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
