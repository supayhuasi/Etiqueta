<?php
require 'includes/header.php';

// Verificar permisos de admin
if ($role !== 'admin') {
    die("Acceso denegado. Solo administradores pueden configurar el menú.");
}

$mensaje = '';
$error = '';
$seccion_id = (int)($_GET['seccion_id'] ?? $_POST['seccion_id'] ?? 0);

// Obtener datos de la sección
$seccion = null;
if ($seccion_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM ecommerce_menu_configuracion WHERE id = ?");
    $stmt->execute([$seccion_id]);
    $seccion = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$seccion) {
    // Si no hay sección, mostrar lista de secciones
    header("Location: menu_configuracion.php");
    exit;
}

// Obtener items de la sección
$stmt = $pdo->prepare("
    SELECT * FROM ecommerce_menu_items 
    WHERE seccion_id = ? 
    ORDER BY orden
");
$stmt->execute([$seccion_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formularios
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_item') {
        $titulo = trim($_POST['titulo'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icono = trim($_POST['icono'] ?? '');
        $permiso = trim($_POST['permiso'] ?? '');
        
        if (!$titulo || !$url) {
            $error = "El título y URL son requeridos.";
        } else {
            try {
                $stmt_orden = $pdo->prepare("SELECT COALESCE(MAX(orden), 0) + 1 FROM ecommerce_menu_items WHERE seccion_id = ?");
                $stmt_orden->execute([$seccion_id]);
                $orden = (int)$stmt_orden->fetchColumn();
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_menu_items
                    (seccion_id, titulo, url, icono, permiso, orden, activo)
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([$seccion_id, $titulo, $url, $icono, $permiso ?: null, $orden]);
                $mensaje = "Item agregado correctamente.";
                header("Location: " . $_SERVER['PHP_SELF'] . "?seccion_id=" . $seccion_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al agregar item: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'eliminar_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM ecommerce_menu_items WHERE id = ?")->execute([$id]);
                $mensaje = "Item eliminado correctamente.";
                header("Location: " . $_SERVER['PHP_SELF'] . "?seccion_id=" . $seccion_id);
                exit;
            } catch (PDOException $e) {
                $error = "Error al eliminar item: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle_activo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_menu_items SET activo = NOT activo WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = "Estado actualizado correctamente.";
            } catch (PDOException $e) {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    }
}

// Recargar items después de cambios
if ($mensaje) {
    $stmt = $pdo->prepare("
        SELECT * FROM ecommerce_menu_items 
        WHERE seccion_id = ? 
        ORDER BY orden
    ");
    $stmt->execute([$seccion_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .item-row {
        background: #f8f9fa;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 10px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .item-info {
        flex: 1;
    }
    
    .item-title {
        font-weight: bold;
        font-size: 14px;
    }
    
    .item-meta {
        color: #6c757d;
        font-size: 12px;
        margin-top: 4px;
    }
    
    .item-actions {
        display: flex;
        gap: 5px;
    }
</style>

<div class="mb-4">
    <a href="menu_configuracion.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Volver a Secciones
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>
            <i class="<?= htmlspecialchars($seccion['icono'] ?? 'bi bi-box') ?>"></i>
            Items de Menú - <?= htmlspecialchars($seccion['label']) ?>
        </h1>
        <p class="text-muted">Gestiona los items dentro de esta sección</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <strong>✓</strong> <?= htmlspecialchars($mensaje) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>✗</strong> <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Formulario Agregar Item -->
<div class="card mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-plus-circle"></i> Agregar Nuevo Item
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
            <input type="hidden" name="accion" value="agregar_item">
            <input type="hidden" name="seccion_id" value="<?= $seccion_id ?>">
            
            <div class="col-md-3">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" placeholder="ej: Productos" required>
            </div>
            
            <div class="col-md-4">
                <label class="form-label">URL</label>
                <input type="text" name="url" class="form-control" placeholder="ej: /ecommerce/admin/productos.php" required>
                <small class="text-muted">URL relativa desde el servidor</small>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Ícono</label>
                <input type="text" name="icono" class="form-control" placeholder="ej: bi bi-box" value="bi bi-box">
                <small class="text-muted"><a href="https://icons.getbootstrap.com/" target="_blank">Ver ícones</a></small>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Permiso</label>
                <input type="text" name="permiso" class="form-control" placeholder="ej: productos">
                <small class="text-muted">Opcional - dejar vacío si no requiere</small>
            </div>
            
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-plus"></i> Agregar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lista de Items -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-list-nested"></i> Items (<?= count($items) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($items)): ?>
            <div class="alert alert-info">No hay items en esta sección. Agrega uno arriba.</div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="item-row">
                    <div class="item-info">
                        <div class="item-title">
                            <i class="<?= htmlspecialchars($item['icono'] ?? 'bi bi-box') ?>"></i>
                            <?= htmlspecialchars($item['titulo']) ?>
                        </div>
                        <div class="item-meta">
                            URL: <code><?= htmlspecialchars($item['url']) ?></code>
                            <?php if ($item['permiso']): ?>
                                | Permiso: <code><?= htmlspecialchars($item['permiso']) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="item-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                            <input type="hidden" name="accion" value="toggle_activo">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" 
                                    class="btn btn-sm <?= $item['activo'] ? 'btn-success' : 'btn-warning' ?>" 
                                    title="<?= $item['activo'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="bi bi-<?= $item['activo'] ? 'eye' : 'eye-slash' ?>"></i>
                            </button>
                        </form>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro?');">
                            <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                            <input type="hidden" name="accion" value="eliminar_item">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
