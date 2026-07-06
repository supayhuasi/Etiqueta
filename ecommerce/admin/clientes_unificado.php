<?php
require 'includes/header.php';

// Obtener tipo de vista (clientes web, clientes cotización, o todos)
$vista = $_GET['vista'] ?? 'todos';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_estado = $_GET['estado'] ?? '';
$orden = $_GET['orden'] ?? 'nombre';

// Validar vista
if (!in_array($vista, ['todos', 'web', 'cotizacion'])) {
    $vista = 'todos';
}

$where = ["1=1"];
$params = [];

// Filtrar por tipo de cliente
if ($vista === 'web') {
    $where[] = "tipo = 'web'";
} elseif ($vista === 'cotizacion') {
    $where[] = "tipo = 'cotizacion'";
}

if ($filtro_tipo) {
    $where[] = "tipo = ?";
    $params[] = $filtro_tipo;
}

$sql_where = implode(" AND ", $where);

// Contar totales
$stmt = $pdo->query("
    SELECT 
        COUNT(CASE WHEN tipo = 'web' THEN 1 END) as total_web,
        COUNT(CASE WHEN tipo = 'cotizacion' THEN 1 END) as total_cotizacion,
        COUNT(*) as total_general
    FROM (
        SELECT id, 'web' as tipo FROM ecommerce_clientes
        UNION ALL
        SELECT id, 'cotizacion' as tipo FROM ecommerce_cotizacion_clientes
    ) AS clientes_union
");
$totales = $stmt->fetch(PDO::FETCH_ASSOC);

// Obtener clientes web
$stmt = $pdo->prepare("
    SELECT 
        id,
        nombre,
        email,
        estado,
        provider,
        fecha_creacion,
        'web' as tipo
    FROM ecommerce_clientes
    WHERE {$sql_where}
    ORDER BY {$orden}
");
$stmt->execute($params);
$clientes_web = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes de cotización
$sql_where_cot = str_replace("tipo = '", "tipo = 'cot", $sql_where);
$where_cot = ["1=1"];
if ($vista === 'web') {
    $where_cot[] = "1=0"; // No mostrar cotizacion si es vista web
} elseif ($vista === 'cotizacion') {
    // Mostrar solo cotización
}

$stmt = $pdo->prepare("
    SELECT 
        id,
        nombre as nombre,
        NULL as email,
        NULL as estado,
        NULL as provider,
        NULL as fecha_creacion,
        'cotizacion' as tipo
    FROM ecommerce_cotizacion_clientes
    WHERE {$vista !== 'web' ? '1=1' : '1=0'}
    ORDER BY {$orden}
");
$stmt->execute();
$clientes_cotizacion = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Combinar resultados
$clientes = [];
if ($vista === 'todos' || $vista === 'web') {
    $clientes = array_merge($clientes, $clientes_web);
}
if ($vista === 'todos' || $vista === 'cotizacion') {
    $clientes = array_merge($clientes, $clientes_cotizacion);
}

// Usar count para estadísticas
$total_clientes = count($clientes);
?>

<style>
    .client-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    
    .client-tab {
        padding: 8px 16px;
        border: 1px solid #dee2e6;
        border-radius: 4px;
        text-decoration: none;
        color: #495057;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .client-tab:hover {
        background: #f8f9fa;
    }
    
    .client-tab.active {
        background: #0d6efd;
        color: white;
        border-color: #0d6efd;
    }
    
    .badge-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        margin-left: 8px;
    }
    
    .badge-web {
        background: #e7f3ff;
        color: #0056b3;
    }
    
    .badge-cotizacion {
        background: #fff3cd;
        color: #856404;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>👥 Clientes Unificado</h1>
        <p class="text-muted">Gestiona clientes web y clientes de cotización en un solo lugar</p>
    </div>
    <div>
        <?php if ($can_access('clientes_web')): ?>
        <a href="clientes_web_crear.php" class="btn btn-primary" style="display: none;">
            + Nuevo Cliente Web
        </a>
        <?php endif; ?>
        <?php if ($can_access('cotizacion_clientes')): ?>
        <a href="cotizacion_clientes_crear.php" class="btn btn-primary" style="display: none;">
            + Nuevo Cliente Cotización
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tabs de Vistas -->
<div class="client-tabs">
    <a href="?vista=todos" class="client-tab <?= $vista === 'todos' ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Todos (<?= $totales['total_general'] ?? 0 ?>)
    </a>
    <?php if ($can_access('clientes_web')): ?>
    <a href="?vista=web" class="client-tab <?= $vista === 'web' ? 'active' : '' ?>">
        <i class="bi bi-globe"></i> Clientes Web (<?= $totales['total_web'] ?? 0 ?>)
    </a>
    <?php endif; ?>
    <?php if ($can_access('cotizacion_clientes')): ?>
    <a href="?vista=cotizacion" class="client-tab <?= $vista === 'cotizacion' ? 'active' : '' ?>">
        <i class="bi bi-file-earmark"></i> Clientes Cotización (<?= $totales['total_cotizacion'] ?? 0 ?>)
    </a>
    <?php endif; ?>
</div>

<!-- Info Alert -->
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i> 
    Esta es una vista unificada que combina <strong>Clientes Web</strong> y <strong>Clientes de Cotización</strong>.
    Puedes ver y gestionar ambos tipos desde aquí.
</div>

<!-- Tabla de Clientes -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-table"></i> 
            Listado de Clientes 
            <span class="badge bg-primary"><?= $total_clientes ?></span>
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($clientes)): ?>
            <div class="alert alert-info">No hay clientes en esta vista.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <?php if ($vista === 'todos' || $vista === 'web'): ?>
                            <th>Email</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Fecha Creación</th>
                            <?php endif; ?>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientes as $cliente): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($cliente['nombre']) ?></strong>
                            </td>
                            <td>
                                <?php if ($cliente['tipo'] === 'web'): ?>
                                    <span class="badge badge-web">Web</span>
                                <?php else: ?>
                                    <span class="badge badge-cotizacion">Cotización</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($vista === 'todos' || $vista === 'web'): ?>
                            <td><?= htmlspecialchars($cliente['email'] ?? '-') ?></td>
                            <td>
                                <?php if ($cliente['provider'] ?? null): ?>
                                    <?php if ($cliente['provider'] === 'google'): ?>
                                        <i class="bi bi-google"></i> Google
                                    <?php else: ?>
                                        <?= htmlspecialchars($cliente['provider']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cliente['estado'] ?? null): ?>
                                    <span class="badge bg-success">Activo</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($cliente['fecha_creacion']): ?>
                                    <?= date('d/m/Y', strtotime($cliente['fecha_creacion'])) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            <td>
                                <?php if ($cliente['tipo'] === 'web' && $can_access('clientes_web')): ?>
                                    <a href="clientes_web_editar.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="clientes_web_detalle.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                <?php elseif ($cliente['tipo'] === 'cotizacion' && $can_access('cotizacion_clientes')): ?>
                                    <a href="cotizacion_clientes_editar.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
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
