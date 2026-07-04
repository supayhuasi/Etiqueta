<?php
require 'includes/header.php';

$pdo = $GLOBALS['pdo'] ?? ($pdo ?? null);
if (!($pdo instanceof PDO)) {
    throw new RuntimeException('Conexion PDO no disponible en modulo blog.');
}

if (!function_exists('blog_table_exists')) {
    function blog_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('blog_column_exists')) {
    function blog_column_exists(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
            $stmt->execute([$column]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('blog_slugify')) {
    function blog_slugify(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (function_exists('transliterator_transliterate')) {
            $value = transliterator_transliterate('Any-Latin; Latin-ASCII;', $value);
        } elseif (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($converted !== false) {
                $value = $converted;
            }
        }

        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? $value;
        $value = trim($value, '-');
        return $value !== '' ? $value : 'articulo';
    }
}

if (!function_exists('blog_unique_slug')) {
    function blog_unique_slug(PDO $pdo, string $baseSlug, int $excludeId = 0): string
    {
        $slug = $baseSlug !== '' ? $baseSlug : 'articulo';
        $candidate = $slug;
        $suffix = 2;

        while (true) {
            try {
                if ($excludeId > 0) {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ecommerce_blog_articulos WHERE slug = ? AND id <> ?");
                    $stmt->execute([$candidate, $excludeId]);
                } else {
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ecommerce_blog_articulos WHERE slug = ?");
                    $stmt->execute([$candidate]);
                }
                if ((int)$stmt->fetchColumn() === 0) {
                    return $candidate;
                }
            } catch (Throwable $e) {
                return $candidate;
            }

            $candidate = $slug . '-' . $suffix;
            $suffix++;
        }
    }
}

if (!function_exists('blog_uploaded_image_name')) {
    function blog_uploaded_image_name(array $file, string $prefix = 'blog'): ?string
    {
        if (empty($file['name']) || !isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return null;
        }

        $allowed = [
            'jpg' => 'jpg',
            'jpeg' => 'jpg',
            'png' => 'png',
            'gif' => 'gif',
            'webp' => 'webp',
        ];

        $extension = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        if (!isset($allowed[$extension])) {
            return null;
        }

        return $prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$extension];
    }
}

$mensaje = '';
$error = '';

try {
    if (!blog_table_exists($pdo, 'ecommerce_blog_articulos')) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS ecommerce_blog_articulos (
            id INT PRIMARY KEY AUTO_INCREMENT,
            titulo VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            resumen TEXT NULL,
            contenido LONGTEXT NOT NULL,
            imagen VARCHAR(255) NULL,
            estado ENUM('borrador', 'publicado') NOT NULL DEFAULT 'borrador',
            destacado TINYINT(1) NOT NULL DEFAULT 0,
            orden INT NOT NULL DEFAULT 0,
            publicado_en DATETIME NULL,
            creado_por INT NULL,
            actualizado_por INT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_slug (slug),
            INDEX idx_estado_publicado (estado, publicado_en),
            INDEX idx_orden (orden)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $mensaje = 'Tabla del blog creada correctamente.';
    } else {
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'slug')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN slug VARCHAR(255) NOT NULL AFTER titulo");
            $pdo->exec("UPDATE ecommerce_blog_articulos SET slug = CONCAT('articulo-', id) WHERE slug IS NULL OR slug = ''");
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD UNIQUE KEY uniq_slug (slug)");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'resumen')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN resumen TEXT NULL AFTER slug");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'contenido')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN contenido LONGTEXT NOT NULL AFTER resumen");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'imagen')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN imagen VARCHAR(255) NULL AFTER contenido");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'estado')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN estado ENUM('borrador', 'publicado') NOT NULL DEFAULT 'borrador' AFTER imagen");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'destacado')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN destacado TINYINT(1) NOT NULL DEFAULT 0 AFTER estado");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'orden')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN orden INT NOT NULL DEFAULT 0 AFTER destacado");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'publicado_en')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN publicado_en DATETIME NULL AFTER orden");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'creado_por')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN creado_por INT NULL AFTER publicado_en");
        }
        if (!blog_column_exists($pdo, 'ecommerce_blog_articulos', 'actualizado_por')) {
            $pdo->exec("ALTER TABLE ecommerce_blog_articulos ADD COLUMN actualizado_por INT NULL AFTER creado_por");
        }
    }
} catch (Throwable $e) {
    $error = 'No se pudo preparar la tabla del blog: ' . $e->getMessage();
}

admin_require_csrf_post();

$articulo_edit = null;
$edit_id = isset($_GET['editar']) ? (int)$_GET['editar'] : 0;
if ($edit_id > 0) {
    try {
        $stmt = $pdo->prepare('SELECT * FROM ecommerce_blog_articulos WHERE id = ?');
        $stmt->execute([$edit_id]);
        $articulo_edit = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $articulo_edit = null;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string)($_POST['accion'] ?? 'guardar');

    try {
        if ($accion === 'guardar') {
            $id = (int)($_POST['id'] ?? 0);
            $titulo = trim((string)($_POST['titulo'] ?? ''));
            $slug_input = trim((string)($_POST['slug'] ?? ''));
            $resumen = trim((string)($_POST['resumen'] ?? ''));
            $contenido = trim((string)($_POST['contenido'] ?? ''));
            $estado = ($_POST['estado'] ?? 'borrador') === 'publicado' ? 'publicado' : 'borrador';
            $destacado = isset($_POST['destacado']) ? 1 : 0;
            $orden = (int)($_POST['orden'] ?? 0);
            $autor_id = (int)($_SESSION['user']['id'] ?? 0);
            $actualizar_publicado = isset($_POST['publicado_en_manual']) ? trim((string)($_POST['publicado_en_manual']) !== '' ) : false;
            $publicado_en = trim((string)($_POST['publicado_en'] ?? ''));

            if ($titulo === '' || $contenido === '') {
                throw new Exception('El título y el contenido son obligatorios.');
            }

            $base_slug = blog_slugify($slug_input !== '' ? $slug_input : $titulo);
            $slug = blog_unique_slug($pdo, $base_slug, $id);

            $imagen_actual = $articulo_edit['imagen'] ?? null;
            $imagen_guardada = $imagen_actual;
            if (!empty($_FILES['imagen']['name'])) {
                $upload_dir = dirname(__DIR__, 2) . '/uploads/blog';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0775, true);
                }
                $nuevo_nombre = blog_uploaded_image_name($_FILES['imagen']);
                if ($nuevo_nombre === null) {
                    throw new Exception('Formato de imagen no permitido. Use JPG, PNG, GIF o WEBP.');
                }
                $destino = $upload_dir . '/' . $nuevo_nombre;
                if (!move_uploaded_file($_FILES['imagen']['tmp_name'], $destino)) {
                    throw new Exception('No se pudo guardar la imagen subida.');
                }
                $imagen_guardada = $nuevo_nombre;
                if (!empty($imagen_actual)) {
                    $ruta_anterior = $upload_dir . '/' . $imagen_actual;
                    if (is_file($ruta_anterior)) {
                        @unlink($ruta_anterior);
                    }
                }
            }

            if ($estado === 'publicado' && $publicado_en === '') {
                $publicado_en = date('Y-m-d H:i:s');
            }
            if ($estado === 'borrador') {
                $publicado_en = null;
            }

            if ($id > 0) {
                $stmt = $pdo->prepare("UPDATE ecommerce_blog_articulos SET titulo = ?, slug = ?, resumen = ?, contenido = ?, imagen = ?, estado = ?, destacado = ?, orden = ?, publicado_en = ?, actualizado_por = ? WHERE id = ?");
                $stmt->execute([
                    $titulo,
                    $slug,
                    $resumen !== '' ? $resumen : null,
                    $contenido,
                    $imagen_guardada,
                    $estado,
                    $destacado,
                    $orden,
                    $publicado_en !== '' ? $publicado_en : null,
                    $autor_id > 0 ? $autor_id : null,
                    $id,
                ]);
                $mensaje = 'Artículo actualizado correctamente.';
            } else {
                $stmt = $pdo->prepare("INSERT INTO ecommerce_blog_articulos (titulo, slug, resumen, contenido, imagen, estado, destacado, orden, publicado_en, creado_por, actualizado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $titulo,
                    $slug,
                    $resumen !== '' ? $resumen : null,
                    $contenido,
                    $imagen_guardada,
                    $estado,
                    $destacado,
                    $orden,
                    $publicado_en !== '' ? $publicado_en : null,
                    $autor_id > 0 ? $autor_id : null,
                    $autor_id > 0 ? $autor_id : null,
                ]);
                $mensaje = 'Artículo creado correctamente.';
            }

            $articulo_edit = null;
            $edit_id = 0;
        }

        if ($accion === 'toggle') {
            $id = (int)($_POST['id'] ?? 0);
            $estado = (string)($_POST['estado'] ?? 'borrador');
            $nuevo_estado = $estado === 'publicado' ? 'borrador' : 'publicado';
            $publicado_en = $nuevo_estado === 'publicado' ? date('Y-m-d H:i:s') : null;
            $stmt = $pdo->prepare('UPDATE ecommerce_blog_articulos SET estado = ?, publicado_en = ? WHERE id = ?');
            $stmt->execute([$nuevo_estado, $publicado_en, $id]);
            $mensaje = 'Estado del artículo actualizado.';
        }

        if ($accion === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT imagen FROM ecommerce_blog_articulos WHERE id = ?');
                $stmt->execute([$id]);
                $imagen_borrar = (string)($stmt->fetchColumn() ?: '');
                $stmt = $pdo->prepare('DELETE FROM ecommerce_blog_articulos WHERE id = ?');
                $stmt->execute([$id]);
                if ($imagen_borrar !== '') {
                    $ruta = dirname(__DIR__, 2) . '/uploads/blog/' . $imagen_borrar;
                    if (is_file($ruta)) {
                        @unlink($ruta);
                    }
                }
                $mensaje = 'Artículo eliminado correctamente.';
            }
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$articulos = [];
try {
    $stmt = $pdo->query("SELECT * FROM ecommerce_blog_articulos ORDER BY destacado DESC, orden ASC, publicado_en DESC, id DESC");
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $error = $error !== '' ? $error : 'No se pudo cargar el listado: ' . $e->getMessage();
}
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="mb-1">📝 Blog</h1>
        <p class="text-muted mb-0">Creá y administrá artículos para el sitio.</p>
    </div>
    <div>
        <a href="blog.php" class="btn btn-outline-secondary">Nuevo artículo</a>
    </div>
</div>

<?php if (!empty($mensaje)): ?>
    <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <strong><?= $articulo_edit ? 'Editar artículo' : 'Nuevo artículo' ?></strong>
    </div>
    <div class="card-body">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
            <input type="hidden" name="accion" value="guardar">
            <input type="hidden" name="id" value="<?= (int)($articulo_edit['id'] ?? 0) ?>">

            <div class="row g-3">
                <div class="col-12 col-lg-8">
                    <label class="form-label">Título *</label>
                    <input type="text" name="titulo" class="form-control" value="<?= htmlspecialchars($articulo_edit['titulo'] ?? '') ?>" required>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="<?= htmlspecialchars($articulo_edit['slug'] ?? '') ?>" placeholder="Se genera automáticamente si lo dejás vacío">
                </div>
                <div class="col-12 col-lg-8">
                    <label class="form-label">Resumen</label>
                    <textarea name="resumen" class="form-control" rows="3"><?= htmlspecialchars($articulo_edit['resumen'] ?? '') ?></textarea>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Imagen destacada</label>
                    <input type="file" name="imagen" class="form-control" accept="image/*">
                    <?php if (!empty($articulo_edit['imagen'])): ?>
                        <div class="mt-2 small text-muted">Actual: <?= htmlspecialchars($articulo_edit['imagen']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-12">
                    <label class="form-label">Contenido *</label>
                    <textarea id="contenido" name="contenido" class="form-control" rows="12" required><?= htmlspecialchars($articulo_edit['contenido'] ?? '') ?></textarea>
                    <div class="form-text">Utilizá el editor para formatear el texto con negritas, cursivas, títulos, listas y más.</div>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-select">
                        <option value="borrador" <?= (($articulo_edit['estado'] ?? 'borrador') === 'borrador') ? 'selected' : '' ?>>Borrador</option>
                        <option value="publicado" <?= (($articulo_edit['estado'] ?? '') === 'publicado') ? 'selected' : '' ?>>Publicado</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Orden</label>
                    <input type="number" name="orden" class="form-control" value="<?= htmlspecialchars((string)($articulo_edit['orden'] ?? 0)) ?>">
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="destacado" id="destacado" <?= !empty($articulo_edit['destacado']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="destacado">Destacado</label>
                    </div>
                </div>
                <div class="col-12 col-md-3 d-flex align-items-end">
                    <?php if ($articulo_edit): ?>
                        <button type="submit" class="btn btn-primary w-100">Actualizar artículo</button>
                    <?php else: ?>
                        <button type="submit" class="btn btn-primary w-100">Crear artículo</button>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Artículos</strong>
        <span class="badge bg-secondary"><?= count($articulos) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($articulos)): ?>
            <div class="alert alert-info mb-0">No hay artículos cargados todavía.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Orden</th>
                            <th>Artículo</th>
                            <th>Estado</th>
                            <th>Destacado</th>
                            <th>Publicación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($articulos as $art): ?>
                            <tr>
                                <td><?= (int)($art['orden'] ?? 0) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($art['titulo'] ?? '') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars(mb_substr(strip_tags((string)($art['resumen'] ?? '')), 0, 90)) ?></div>
                                    <div class="small text-muted">Slug: <?= htmlspecialchars($art['slug'] ?? '') ?></div>
                                </td>
                                <td>
                                    <?php if (($art['estado'] ?? 'borrador') === 'publicado'): ?>
                                        <span class="badge bg-success">Publicado</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Borrador</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($art['destacado']) ? '<span class="badge bg-warning text-dark">Sí</span>' : '<span class="badge bg-light text-dark">No</span>' ?>
                                </td>
                                <td>
                                    <?= !empty($art['publicado_en']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$art['publicado_en']))) : '-' ?>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-2">
                                        <a href="blog.php?editar=<?= (int)$art['id'] ?>" class="btn btn-sm btn-outline-primary">Editar</a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                                            <input type="hidden" name="accion" value="toggle">
                                            <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
                                            <input type="hidden" name="estado" value="<?= htmlspecialchars((string)($art['estado'] ?? 'borrador')) ?>">
                                            <button type="submit" class="btn btn-sm <?= ($art['estado'] ?? 'borrador') === 'publicado' ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                <?= ($art['estado'] ?? 'borrador') === 'publicado' ? 'Despublicar' : 'Publicar' ?>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este artículo?');">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(admin_csrf_token()) ?>">
                                            <input type="hidden" name="accion" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$art['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.tiny.cloud/1/mysjhtl2zbzhcu7j6pip9bzwzyj97dz2rltrkpbqinn7eam3/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#contenido',
    plugins: 'link image lists code table',
    toolbar: 'undo redo | formatselect | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright | bullist numlist outdent indent | link image | code | removeformat',
    menubar: 'file edit view insert format tools',
    height: 500,
    setup: function(editor) {
        editor.on('change', function() {
            tinymce.triggerSave();
        });
    }
});
</script>

<script>
(function () {
    const titulo = document.querySelector('input[name="titulo"]');
    const slug = document.querySelector('input[name="slug"]');
    if (!titulo || !slug) return;

    let touched = slug.value.trim() !== '';
    slug.addEventListener('input', function () {
        touched = slug.value.trim() !== '';
    });
    titulo.addEventListener('blur', function () {
        if (touched) return;
        const generated = titulo.value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        slug.value = generated || 'articulo';
    });
})();
</script>

<?php require 'includes/footer.php'; ?>
