<?php
require 'includes/header.php';

// Verificar permisos de admin
if ($role !== 'admin') {
    die("Acceso denegado. Solo administradores pueden configurar el menú.");
}

$mensaje = '';
$error = '';

// Obtener todos los items del menú público
$stmt = $pdo->query("
    SELECT * FROM ecommerce_menu_publico 
    WHERE padre_id IS NULL
    ORDER BY orden
");
$items_menu = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_item') {
        $titulo = trim($_POST['titulo'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $icono = trim($_POST['icono'] ?? '');
        
        if (!$titulo) {
            $error = "El título es requerido.";
        } else {
            try {
                $orden = (int)$pdo->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM ecommerce_menu_publico WHERE padre_id IS NULL")->fetchColumn();
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_menu_publico
                    (titulo, url, icono, orden, activo, mostrar_en_navbar, es_dropdown, padre_id)
                    VALUES (?, ?, ?, ?, 1, 1, 0, NULL)
                ");
                $stmt->execute([$titulo, $url, $icono, $orden]);
                $mensaje = "Item agregado correctamente.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $error = "Error al agregar item: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'eliminar_item') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM ecommerce_menu_publico WHERE id = ? OR padre_id = ?")->execute([$id, $id]);
                $mensaje = "Item eliminado correctamente.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $error = "Error al eliminar item: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle_activo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_menu_publico SET activo = NOT activo WHERE id = ?");
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
    $stmt = $pdo->query("
        SELECT * FROM ecommerce_menu_publico 
        WHERE padre_id IS NULL
        ORDER BY orden
    ");
    $items_menu = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .menu-item-row {
        background: #f8f9fa;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 10px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .menu-item-info {
        flex: 1;
    }
    
    .menu-item-title {
        font-weight: bold;
        font-size: 14px;
    }
    
    .menu-item-meta {
        color: #6c757d;
        font-size: 12px;
        margin-top: 4px;
    }
    
    .menu-item-actions {
        display: flex;
        gap: 5px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>🌐 Configuración del Menú Público del Ecommerce</h1>
        <p class="text-muted">Administra el menú visible en el sitio web</p>
    </div>
    <a href="menu_configuracion.php" class="btn btn-info">
        <i class="bi bi-gear"></i> Menú Admin
    </a>
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
            
            <div class="col-md-3">
                <label class="form-label">Título</label>
                <input type="text" name="titulo" class="form-control" placeholder="ej: Productos" required>
            </div>
            
            <div class="col-md-5">
                <label class="form-label">URL</label>
                <input type="text" name="url" class="form-control" placeholder="ej: productos.php o /ecommerce/productos.php">
                <small class="text-muted">Ruta relativa o absoluta</small>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">Ícono (Opcional)</label>
                <input type="text" name="icono" class="form-control" placeholder="bi bi-box" value="bi bi-box">
                <small class="text-muted"><a href="https://icons.getbootstrap.com/" target="_blank">Ver ícones</a></small>
            </div>
            
            <div class="col-md-2 d-flex align-items-end">
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
            <i class="bi bi-list"></i> Menú del Sitio Web (<?= count($items_menu) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($items_menu)): ?>
            <div class="alert alert-info">No hay items en el menú. Agrega uno arriba.</div>
        <?php else: ?>
            <?php foreach ($items_menu as $item): ?>
                <div class="menu-item-row">
                    <div class="menu-item-info">
                        <div class="menu-item-title">
                            <i class="<?= htmlspecialchars($item['icono'] ?? 'bi bi-box') ?>"></i>
                            <?= htmlspecialchars($item['titulo']) ?>
                        </div>
                        <div class="menu-item-meta">
                            URL: <code><?= htmlspecialchars($item['url'] ?? 'Sin URL') ?></code>
                            | Orden: 
                            <span class="badge bg-secondary"><?= $item['orden'] ?></span>
                        </div>
                    </div>
                    <div class="menu-item-actions">
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

<hr>

<div class="alert alert-info">
    <h6><i class="bi bi-info-circle"></i> Descripción</h6>
    <ul class="mb-0">
        <li><strong>Título:</strong> Texto que aparece en el menú del sitio web.</li>
        <li><strong>URL:</strong> Página a la que lleva el enlace (ej: productos.php o /ecommerce/productos.php).</li>
        <li><strong>Ícono:</strong> Ícono Bootstrap que se muestra junto al título.</li>
        <li><strong>Estado:</strong> Activado/Desactivado determina si aparece en el menú.</li>
        <li><strong>Orden:</strong> Posición en el menú (de izquierda a derecha).</li>
    </ul>
</div>

<?php require 'includes/footer.php'; ?>
