<?php
require 'includes/header.php';

// Filtros
$estado_filtro = $_GET['estado'] ?? '';
$buscar = $_GET['buscar'] ?? '';

// Construir query
$where = ["1=1"];
$params = [];

if ($estado_filtro) {
    $where[] = "estado = ?";
    $params[] = $estado_filtro;
}

if ($buscar) {
    $where[] = "(c.numero_cotizacion LIKE ? OR c.nombre_cliente LIKE ? OR c.email LIKE ? OR cc.nombre LIKE ? OR cc.email LIKE ?)";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$sql = "
    SELECT c.*, cc.nombre AS cliente_nombre, cc.empresa AS cliente_empresa, cc.email AS cliente_email, cc.telefono AS cliente_telefono
    FROM ecommerce_cotizaciones c
    LEFT JOIN ecommerce_cotizacion_clientes cc ON c.cliente_id = cc.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY COALESCE(cc.nombre, c.nombre_cliente), c.fecha_creacion DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$cotizaciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Estadísticas
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END) as pendientes,
        SUM(CASE WHEN estado = 'enviada' THEN 1 ELSE 0 END) as enviadas,
        SUM(CASE WHEN estado = 'aceptada' THEN 1 ELSE 0 END) as aceptadas,
        SUM(CASE WHEN estado = 'convertida' THEN 1 ELSE 0 END) as convertidas
    FROM ecommerce_cotizaciones
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>💼 Cotizaciones</h1>
        <p class="text-muted">Gestiona solicitudes de cotización y presupuestos</p>
    </div>
    <div>
        <a href="cotizacion_crear.php" class="btn btn-primary">➕ Nueva Cotización</a>
    </div>
</div>

<!-- Estadísticas -->
<div class="row mb-4">
    <div class="col-md-2">
        <div class="card bg-primary text-white">
            <div class="card-body text-center">
                <h6>Total</h6>
                <h3><?= $stats['total'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-warning text-dark">
            <div class="card-body text-center">
                <h6>Pendientes</h6>
                <h3><?= $stats['pendientes'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-info text-white">
            <div class="card-body text-center">
                <h6>Enviadas</h6>
                <h3><?= $stats['enviadas'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-success text-white">
            <div class="card-body text-center">
                <h6>Aceptadas</h6>
                <h3><?= $stats['aceptadas'] ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="card bg-secondary text-white">
            <div class="card-body text-center">
                <h6>Convertidas</h6>
                <h3><?= $stats['convertidas'] ?></h3>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Buscar</label>
                <input type="text" name="buscar" class="form-control" value="<?= htmlspecialchars($buscar) ?>" placeholder="Número, cliente, email...">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="pendiente" <?= $estado_filtro === 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                    <option value="enviada" <?= $estado_filtro === 'enviada' ? 'selected' : '' ?>>Enviada</option>
                    <option value="aceptada" <?= $estado_filtro === 'aceptada' ? 'selected' : '' ?>>Aceptada</option>
                    <option value="rechazada" <?= $estado_filtro === 'rechazada' ? 'selected' : '' ?>>Rechazada</option>
                    <option value="convertida" <?= $estado_filtro === 'convertida' ? 'selected' : '' ?>>Convertida a Pedido</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">🔍 Filtrar</button>
                <a href="cotizaciones.php" class="btn btn-secondary">🔄 Limpiar</a>
            </div>
        </form>
    </div>
</div>

<!-- Lista de cotizaciones -->
<div class="card">
    <div class="card-body">
        <?php if (empty($cotizaciones)): ?>
            <div class="alert alert-info">
                No hay cotizaciones registradas. <a href="cotizacion_crear.php">Crear la primera cotización</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Número</th>
                            <th>Cliente</th>
                            <th>Contacto</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cotizaciones as $cot): 
                            $items = json_decode($cot['items'], true) ?? [];
                            $num_items = count($items);
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cot['numero_cotizacion']) ?></strong>
                                    <br><small class="text-muted"><?= $num_items ?> item(s)</small>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($cot['cliente_nombre'] ?? $cot['nombre_cliente']) ?></strong>
                                    <?php if (!empty($cot['cliente_empresa']) || $cot['empresa']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($cot['cliente_empresa'] ?? $cot['empresa']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    📧 <?= htmlspecialchars($cot['cliente_email'] ?? $cot['email']) ?><br>
                                    <?php if (!empty($cot['cliente_telefono']) || $cot['telefono']): ?>
                                        📞 <?= htmlspecialchars($cot['cliente_telefono'] ?? $cot['telefono']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= date('d/m/Y', strtotime($cot['fecha_creacion'])) ?><br>
                                    <small class="text-muted"><?= date('H:i', strtotime($cot['fecha_creacion'])) ?></small>
                                </td>
                                <td>
                                    <strong>$<?= number_format($cot['total'], 2) ?></strong>
                                    <?php if ($cot['descuento'] > 0): ?>
                                        <br><small class="text-success">-$<?= number_format($cot['descuento'], 2) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $badges = [
                                        'pendiente' => 'warning',
                                        'enviada' => 'info',
                                        'aceptada' => 'success',
                                        'rechazada' => 'danger',
                                        'convertida' => 'secondary'
                                    ];
                                    $badge = $badges[$cot['estado']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badge ?>"><?= ucfirst($cot['estado']) ?></span>
                                </td>
                                <td>
                                    <a href="cotizacion_detalle.php?id=<?= $cot['id'] ?>" class="btn btn-sm btn-primary" title="Ver detalle">👁️</a>
                                    <a href="cotizacion_editar.php?id=<?= $cot['id'] ?>" class="btn btn-sm btn-warning" title="Editar">✏️</a>
                                    <a href="cotizacion_pdf.php?id=<?= $cot['id'] ?>" class="btn btn-sm btn-info" title="Descargar PDF" target="_blank">📄</a>
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
