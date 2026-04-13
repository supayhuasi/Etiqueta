<?php
require 'includes/header.php';
require_once __DIR__ . '/includes/nota_credito_helper.php';

// Inicializar esquema
try {
    ensureNotaCreditoSchema($pdo);
} catch (Throwable $e) {
    die('Error al inicializar módulo de notas de crédito: ' . $e->getMessage());
}

// Procesamiento de acciones
$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';
$resultado = ['ok' => false];

if ($accion === 'crear') {
    try {
        $pedido_id = (int)($_POST['pedido_id'] ?? 0);
        if ($pedido_id <= 0) {
            $resultado = ['ok' => false, 'error' => 'Pedido no seleccionado'];
        } else {
            $nota_credito_id = nota_credito_crear($pdo, $pedido_id, [
                'comprobante_tipo' => $_POST['comprobante_tipo'] ?? 'factura',
                'tipo_nc' => $_POST['tipo_nc'] ?? '03',
                'monto_total' => $_POST['monto_total'] ?? 0,
                'motivo' => $_POST['motivo'] ?? 'Devolución',
                'descripcion' => $_POST['descripcion'] ?? '',
                'creado_por' => $_SESSION['usuario_id'] ?? null
            ]);
            $resultado = ['ok' => true, 'id' => $nota_credito_id, 'message' => 'Nota de crédito creada'];
        }
    } catch (Throwable $e) {
        $resultado = ['ok' => false, 'error' => 'Error al crear NC: ' . $e->getMessage()];
    }
}

if ($accion === 'emitir') {
    try {
        $nota_credito_id = (int)($_POST['nota_credito_id'] ?? 0);
        $resultado = nota_credito_emitir($pdo, $nota_credito_id);
    } catch (Throwable $e) {
        $resultado = ['ok' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

if ($accion === 'cancelar') {
    try {
        $nota_credito_id = (int)($_POST['nota_credito_id'] ?? 0);
        $resultado = nota_credito_cancelar($pdo, $nota_credito_id);
    } catch (Throwable $e) {
        $resultado = ['ok' => false, 'error' => 'Error: ' . $e->getMessage()];
    }
}

// Si es AJAX, retornar JSON
if (!empty($_POST['accion']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
    header('Content-Type: application/json');
    echo json_encode($resultado);
    exit;
}

// Filtros
$filtro_estado = $_GET['estado'] ?? '';
$filtro_numero = $_GET['numero'] ?? '';
$filtro_pedido = $_GET['pedido_id'] ?? '';

// Obtener listado
$filtros = [];
if (!empty($filtro_estado)) $filtros['estado'] = $filtro_estado;
if (!empty($filtro_numero)) $filtros['numero_nc'] = $filtro_numero;
if (!empty($filtro_pedido)) $filtros['pedido_id'] = (int)$filtro_pedido;

$notas_credito = nota_credito_listar($pdo, $filtros);

// Obtener últimos pedidos para crear NC
$stmt = $pdo->prepare("
    SELECT id, numero_factura, cliente_id, total, estado, 
           (SELECT nombre FROM ecommerce_clientes WHERE id = cliente_id) as cliente_nombre
    FROM ecommerce_pedidos
    WHERE numero_factura IS NOT NULL
    ORDER BY id DESC
    LIMIT 20
");
$stmt->execute();
$pedidos_recientes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Notas de Crédito</title>
    <style>
        .estado-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            font-weight: bold;
        }
        .estado-borrador { background-color: #ffc107; color: #000; }
        .estado-emitida { background-color: #28a745; color: #fff; }
        .estado-cancelada { background-color: #dc3545; color: #fff; }
    </style>
</head>
<body>
<div class="container-fluid mt-4">

    <!-- Encabezado -->
    <div class="row mb-4">
        <div class="col">
            <h1>Notas de Crédito</h1>
            <p class="text-muted">Gestión de devoluciones y ajustes de facturación</p>
        </div>
        <div class="col-auto">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCrearNC">
                ➕ Nueva Nota de Crédito
            </button>
        </div>
    </div>

    <!-- Mensajes de resultado -->
    <?php if ($resultado['ok']): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <strong>✓ Éxito</strong> <?= htmlspecialchars($resultado['message'] ?? 'Operación completada') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php elseif (!$resultado['ok'] && !empty($resultado['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <strong>✗ Error</strong> <?= htmlspecialchars($resultado['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Número NC</label>
                    <input type="text" class="form-control" name="numero" value="<?= htmlspecialchars($filtro_numero) ?>" placeholder="Búsqueda...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-control" name="estado">
                        <option value="">-- Todos --</option>
                        <option value="borrador" <?= $filtro_estado === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                        <option value="emitida" <?= $filtro_estado === 'emitida' ? 'selected' : '' ?>>Emitida</option>
                        <option value="cancelada" <?= $filtro_estado === 'cancelada' ? 'selected' : '' ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Pedido ID</label>
                    <input type="number" class="form-control" name="pedido_id" value="<?= htmlspecialchars($filtro_pedido) ?>" placeholder="ID del pedido">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary w-100">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla de notas de crédito -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Número NC</th>
                        <th>Pedido/Factura</th>
                        <th>Cliente</th>
                        <th>Monto</th>
                        <th>Motivo</th>
                        <th>Estado</th>
                        <th>Emisión</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notas_credito)): ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                No hay notas de crédito registradas
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notas_credito as $nc): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($nc['numero_nc'] ?? 'N/A') ?></strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($nc['factura_original'] ?? 'Sin factura') ?>
                                    <br><small class="text-muted">Pedido #<?= $nc['pedido_id'] ?? 'N/A' ?></small>
                                </td>
                                <td><?= htmlspecialchars($nc['cliente_nombre'] ?? 'Desconocido') ?></td>
                                <td>
                                    <strong>$<?= number_format($nc['monto_total'], 2, ',', '.') ?></strong>
                                </td>
                                <td><?= htmlspecialchars($nc['motivo'] ?? '') ?></td>
                                <td>
                                    <span class="estado-badge estado-<?= htmlspecialchars($nc['estado']) ?>">
                                        <?= ucfirst($nc['estado']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($nc['fecha_emision'])): ?>
                                        <?= date('d/m/Y H:i', strtotime($nc['fecha_emision'])) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="nota_credito_detalle.php?id=<?= $nc['id'] ?>" class="btn btn-info" title="Ver detalle">
                                            👁 Ver
                                        </a>
                                        <?php if ($nc['estado'] === 'borrador'): ?>
                                            <button type="button" class="btn btn-success btn-emitir" data-id="<?= $nc['id'] ?>" title="Emitir">
                                                ✓ Emitir
                                            </button>
                                            <button type="button" class="btn btn-danger btn-cancelar" data-id="<?= $nc['id'] ?>" title="Cancelar">
                                                ✕ Cancelar
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Modal: Crear NC -->
<div class="modal fade" id="modalCrearNC" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nueva Nota de Crédito</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="formCrearNC" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="accion" value="crear">
                    
                    <div class="mb-3">
                        <label for="pedido_id_crear" class="form-label">Pedido/Factura *</label>
                        <select class="form-select" id="pedido_id_crear" name="pedido_id" required>
                            <option value="">-- Seleccionar pedido --</option>
                            <?php foreach ($pedidos_recientes as $ped): ?>
                                <option value="<?= $ped['id'] ?>">
                                    <?= htmlspecialchars($ped['numero_factura']) ?> - <?= htmlspecialchars($ped['cliente_nombre']) ?> ($<?= number_format($ped['total'], 2, ',', '.') ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar un pedido</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="comprobante_tipo_crear" class="form-label">Tipo de Comprobante</label>
                                <select class="form-select" id="comprobante_tipo_crear" name="comprobante_tipo">
                                    <option value="factura">Nota de Crédito Fiscal</option>
                                    <option value="recibo">Recibo Interno</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="tipo_nc_crear" class="form-label">Tipo NC (si es fiscal)</label>
                                <select class="form-select" id="tipo_nc_crear" name="tipo_nc">
                                    <option value="03">03 - NC A</option>
                                    <option value="08">08 - NC B</option>
                                    <option value="13">13 - NC C</option>
                                </select>
                                <small class="form-text text-muted">Solo aplica si selecciona Nota de Crédito Fiscal</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="monto_total_crear" class="form-label">Monto Total de Crédito ($) *</label>
                        <input type="number" class="form-control" id="monto_total_crear" name="monto_total" step="0.01" min="0.01" required>
                        <small class="form-text text-muted">Monto del crédito a aplicar</small>
                        <div class="invalid-feedback">Ingrese un monto válido</div>
                    </div>

                    <div class="mb-3">
                        <label for="motivo_crear" class="form-label">Motivo *</label>
                        <select class="form-select" id="motivo_crear" name="motivo" required>
                            <option value="">-- Seleccionar --</option>
                            <option value="Devolución de producto">Devolución de producto</option>
                            <option value="Ajuste de precio">Ajuste de precio</option>
                            <option value="Descuento adicional">Descuento adicional</option>
                            <option value="Cancelación parcial">Cancelación parcial</option>
                            <option value="Error en facturación">Error en facturación</option>
                            <option value="Otro">Otro</option>
                        </select>
                        <div class="invalid-feedback">Debe seleccionar un motivo</div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion_crear" class="form-label">Descripción Adicional</label>
                        <textarea class="form-control" id="descripcion_crear" name="descripcion" rows="3" placeholder="Detalles del motivo de la nota de crédito..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Crear Nota de Crédito</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Formulario de crear NC
document.getElementById('formCrearNC')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const form = e.target;
    
    if (!form.checkValidity()) {
        form.classList.add('was-validated');
        return;
    }
    
    const formData = new FormData(form);
    try {
        const response = await fetch(window.location.href, {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const result = await response.json();
        
        if (result.ok) {
            alert('Nota de crédito creada exitosamente');
            window.location.reload();
        } else {
            alert('Error: ' + result.error);
        }
    } catch (err) {
        console.error(err);
        alert('Error al procesar la solicitud');
    }
});

// Botones de emitir y cancelar
document.querySelectorAll('.btn-emitir').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('¿Emitir esta nota de crédito?')) return;
        
        const formData = new FormData();
        formData.append('accion', 'emitir');
        formData.append('nota_credito_id', this.dataset.id);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.ok) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (err) {
            alert('Error al procesar la solicitud');
        }
    });
});

document.querySelectorAll('.btn-cancelar').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('¿Cancelar esta nota de crédito?')) return;
        
        const formData = new FormData();
        formData.append('accion', 'cancelar');
        formData.append('nota_credito_id', this.dataset.id);
        
        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await response.json();
            
            if (result.ok) {
                alert(result.message);
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (err) {
            alert('Error al procesar la solicitud');
        }
    });
});
</script>

</body>
</html>
