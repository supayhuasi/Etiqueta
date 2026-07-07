<?php
require 'includes/header.php';

// Verificar permisos de admin
if ($role !== 'admin') {
    die("Acceso denegado. Solo administradores pueden configurar el menú.");
}

$mensaje = '';
$error = '';

// Obtener todas las secciones
$stmt = $pdo->query("
    SELECT * FROM ecommerce_menu_configuracion 
    ORDER BY orden
");
$secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar formulario de agregar/editar sección
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    admin_require_csrf_post();
    
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar_seccion') {
        $seccion = trim($_POST['seccion'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $icono = trim($_POST['icono'] ?? '');
        $titulo = trim($_POST['titulo'] ?? '');
        
        if (!$seccion || !$label) {
            $error = "La sección y el label son requeridos.";
        } else {
            try {
                $orden = (int)$pdo->query("SELECT COALESCE(MAX(orden), 0) + 1 FROM ecommerce_menu_configuracion")->fetchColumn();
                $stmt = $pdo->prepare("
                    INSERT INTO ecommerce_menu_configuracion
                    (seccion, label, icono, titulo, permisos, orden, activo)
                    VALUES (?, ?, ?, ?, '[]', ?, 1)
                ");
                $stmt->execute([$seccion, $label, $icono, $titulo, $orden]);
                $mensaje = "Sección agregada correctamente.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $error = "Error al agregar sección: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'eliminar_seccion') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $pdo->prepare("DELETE FROM ecommerce_menu_configuracion WHERE id = ?")->execute([$id]);
                $mensaje = "Sección eliminada correctamente.";
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } catch (PDOException $e) {
                $error = "Error al eliminar sección: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'toggle_activo') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $stmt = $pdo->prepare("UPDATE ecommerce_menu_configuracion SET activo = NOT activo WHERE id = ?");
                $stmt->execute([$id]);
                $mensaje = "Estado actualizado correctamente.";
            } catch (PDOException $e) {
                $error = "Error al actualizar: " . $e->getMessage();
            }
        }
    } elseif ($accion === 'reordenar') {
        $ordenes = $_POST['ordenes'] ?? [];
        try {
            foreach ($ordenes as $id => $orden) {
                $stmt = $pdo->prepare("UPDATE ecommerce_menu_configuracion SET orden = ? WHERE id = ?");
                $stmt->execute([(int)$orden, (int)$id]);
            }
            $mensaje = "Orden actualizado correctamente.";
        } catch (PDOException $e) {
            $error = "Error al reordenar: " . $e->getMessage();
        }
    }
}

// Recargar secciones después de cambios
if ($mensaje) {
    $stmt = $pdo->query("
        SELECT * FROM ecommerce_menu_configuracion 
        ORDER BY orden
    ");
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .menu-section-row {
        background: #f8f9fa;
        padding: 12px;
        border: 1px solid #dee2e6;
        margin-bottom: 10px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .menu-section-info {
        flex: 1;
    }
    
    .menu-section-name {
        font-weight: bold;
        font-size: 14px;
    }
    
    .menu-section-label {
        color: #6c757d;
        font-size: 12px;
    }
    
    .menu-section-actions {
        display: flex;
        gap: 5px;
    }
    
    .icon-preview {
        font-size: 18px;
        margin-right: 10px;
    }
    
    .form-section {
        background: white;
        padding: 20px;
        border-radius: 8px;
        border: 1px solid #dee2e6;
        margin-bottom: 30px;
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1>⚙️ Configuración del Menú del Ecommerce</h1>
        <p class="text-muted">Administra las secciones y elementos del menú del ecommerce</p>
    </div>
    <a href="menu_items.php" class="btn btn-info">
        <i class="bi bi-list-nested"></i> Ver Items del Menú
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

<!-- Formulario Agregar Sección -->
<div class="form-section">
    <h5 class="mb-4">
        <i class="bi bi-plus-circle"></i> Agregar Nueva Sección
    </h5>
    
    <form method="POST" class="row g-3">
        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
        <input type="hidden" name="accion" value="agregar_seccion">
        
        <div class="col-md-2">
            <label class="form-label">Clave (ID)</label>
            <input type="text" name="seccion" class="form-control" placeholder="ej: configuracion" required>
            <small class="text-muted">Identificador único, sin espacios</small>
        </div>
        
        <div class="col-md-3">
            <label class="form-label">Etiqueta</label>
            <input type="text" name="label" class="form-control" placeholder="ej: Configuración General" required>
        </div>
        
        <div class="col-md-2">
            <label class="form-label">Ícono (Bootstrap Icons)</label>
            <input type="text" name="icono" class="form-control" placeholder="ej: bi bi-gear" value="bi bi-gear">
            <small class="text-muted"><a href="https://icons.getbootstrap.com/" target="_blank">Ver ícones</a></small>
        </div>
        
        <div class="col-md-3">
            <label class="form-label">Título</label>
            <input type="text" name="titulo" class="form-control" placeholder="ej: Configuración">
        </div>
        
        <div class="col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-plus"></i> Agregar
            </button>
        </div>
    </form>
</div>

<!-- Lista de Secciones -->
<div class="card">
    <div class="card-header bg-light">
        <h5 class="mb-0">
            <i class="bi bi-list"></i> Secciones del Menú (<?= count($secciones) ?>)
        </h5>
    </div>
    <div class="card-body">
        <?php if (empty($secciones)): ?>
            <div class="alert alert-info">No hay secciones creadas. Crea una nueva sección arriba.</div>
        <?php else: ?>
            <form method="POST" id="formReordenar">
                <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                <input type="hidden" name="accion" value="reordenar">
                
                <div class="row">
                    <?php foreach ($secciones as $seccion): ?>
                        <div class="col-md-6 mb-3">
                            <div class="menu-section-row">
                                <div class="menu-section-info">
                                    <div class="menu-section-name">
                                        <i class="<?= htmlspecialchars($seccion['icono'] ?? 'bi bi-box') ?> icon-preview"></i>
                                        <?= htmlspecialchars($seccion['label']) ?>
                                    </div>
                                    <div class="menu-section-label">
                                        Clave: <code><?= htmlspecialchars($seccion['seccion']) ?></code>
                                        <?php if ($seccion['titulo']): ?>
                                            | Título: <?= htmlspecialchars($seccion['titulo']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="menu-section-label mt-2">
                                        <?php 
                                        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM ecommerce_menu_items WHERE seccion_id = ?");
                                        $stmt->execute([$seccion['id']]);
                                        $count = $stmt->fetch()['count'];
                                        ?>
                                        Items: <badge class="badge bg-info"><?= $count ?></badge>
                                    </div>
                                </div>
                                <div class="menu-section-actions">
                                    <a href="menu_items.php?seccion_id=<?= $seccion['id'] ?>" 
                                       class="btn btn-sm btn-outline-info" title="Ver items">
                                        <i class="bi bi-pencil-square"></i>
                                    </a>
                                    
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                                        <input type="hidden" name="accion" value="toggle_activo">
                                        <input type="hidden" name="id" value="<?= $seccion['id'] ?>">
                                        <button type="submit" 
                                                class="btn btn-sm <?= $seccion['activo'] ? 'btn-success' : 'btn-warning' ?>" 
                                                title="<?= $seccion['activo'] ? 'Desactivar' : 'Activar' ?>">
                                            <i class="bi bi-<?= $seccion['activo'] ? 'eye' : 'eye-slash' ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('¿Está seguro de que desea eliminar esta sección y todos sus items?');">
                                        <input type="hidden" name="csrf_token" value="<?= admin_csrf_token() ?>">
                                        <input type="hidden" name="accion" value="eliminar_seccion">
                                        <input type="hidden" name="id" value="<?= $seccion['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<hr>

<div class="alert alert-info">
    <h6><i class="bi bi-info-circle"></i> Descripción</h6>
    <ul class="mb-0">
        <li><strong>Clave (ID):</strong> Identificador único para la sección, usado internamente.</li>
        <li><strong>Etiqueta:</strong> Nombre visible en el menú.</li>
        <li><strong>Ícono:</strong> Ícono de Bootstrap Icons que se mostrará.</li>
        <li><strong>Items:</strong> Elementos individuales dentro de la sección.</li>
        <li><strong>Estado:</strong> Activa/Inactiva determina si se muestra en el menú.</li>
    </ul>
</div>

<?php require 'includes/footer.php'; ?>
