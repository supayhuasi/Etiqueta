<?php
require 'includes/header.php';

$root_dir = dirname(__DIR__) . '/uploads/fotos_nube';
if (!is_dir($root_dir)) {
    mkdir($root_dir, 0755, true);
}

function fotos_nube_normalize_path($value)
{
    $value = str_replace('\\', '/', (string)$value);
    $value = preg_replace('#/+#', '/', $value);
    $value = trim($value, '/');

    if ($value === '' || $value === '.') {
        return '';
    }

    $segments = array_filter(explode('/', $value), static function ($segment) {
        return $segment !== '' && $segment !== '.' && $segment !== '..';
    });

    return implode('/', $segments);
}

function fotos_nube_normalize_name($value)
{
    $value = trim((string)$value);
    $value = str_replace(['\\', '/'], ' ', $value);
    $value = preg_replace('/[^\pL\pN\s._-]+/u', '', $value);
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    return $value;
}

function fotos_nube_is_image($filename)
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
}

function fotos_nube_is_within_root($target_path, $root_path)
{
    $root_real = realpath($root_path);
    $target_real = realpath($target_path);

    if ($root_real === false) {
        return false;
    }

    if ($target_real === false) {
        return false;
    }

    $root_prefix = rtrim($root_real, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $target_norm = rtrim($target_real, DIRECTORY_SEPARATOR);
    $root_norm = rtrim($root_real, DIRECTORY_SEPARATOR);

    return $target_norm === $root_norm || str_starts_with($target_norm, $root_prefix);
}

function fotos_nube_parent_folder($folder)
{
    if ($folder === '') {
        return '';
    }

    $parts = explode('/', $folder);
    array_pop($parts);

    return implode('/', $parts);
}

function fotos_nube_list_folders($root_path)
{
    $folders = [];
    if (!is_dir($root_path)) {
        return $folders;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root_path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item->isDir()) {
            continue;
        }

        $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root_path) + 1));
        if ($relative === '') {
            continue;
        }

        $folders[] = [
            'folder' => $relative,
            'label' => str_replace('/', ' / ', $relative),
        ];
    }

    usort($folders, static function ($a, $b) {
        return strnatcasecmp($a['label'], $b['label']);
    });

    return $folders;
}

$message = '';
$error = '';
$current_folder = fotos_nube_normalize_path($_GET['folder'] ?? '');
$current_path = $root_dir . ($current_folder !== '' ? '/' . $current_folder : '');

if (!is_dir($current_path)) {
    $current_path = $root_dir;
    $current_folder = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $redirect_to = 'fotos_nube.php' . ($current_folder !== '' ? '?folder=' . urlencode($current_folder) : '');

    if ($accion === 'crear_carpeta') {
        $nombre = fotos_nube_normalize_name($_POST['carpeta_nombre'] ?? '');
        if ($nombre === '') {
            $error = 'Ingresá un nombre válido para la carpeta.';
        } else {
            $target_dir = $current_path . '/' . $nombre;
            if (is_dir($target_dir)) {
                $error = 'Ya existe una carpeta con ese nombre.';
            } elseif (!mkdir($target_dir, 0755, true) && !is_dir($target_dir)) {
                $error = 'No se pudo crear la carpeta.';
            } else {
                $message = 'Carpeta creada correctamente.';
            }
        }
    }

    if ($accion === 'subir') {
        if (empty($_FILES['fotos']['name']) || !is_array($_FILES['fotos']['name'])) {
            $error = 'No se seleccionaron fotos para subir.';
        } else {
            $uploaded = 0;
            foreach ($_FILES['fotos']['name'] as $index => $name) {
                if ($name === '') {
                    continue;
                }

                $tmp = $_FILES['fotos']['tmp_name'][$index] ?? '';
                $error_code = $_FILES['fotos']['error'][$index] ?? UPLOAD_ERR_NO_FILE;

                if ($error_code !== UPLOAD_ERR_OK || !is_uploaded_file($tmp) || !fotos_nube_is_image($name)) {
                    continue;
                }

                $safe_name = preg_replace('/[^A-Za-z0-9_.-]/', '_', basename($name));
                if ($safe_name === '') {
                    continue;
                }

                $destino = $current_path . '/' . $safe_name;
                if (move_uploaded_file($tmp, $destino)) {
                    $uploaded++;
                }
            }

            if ($uploaded > 0) {
                $message = 'Se subieron ' . $uploaded . ' foto(s) correctamente.';
            } else {
                $error = 'No se pudo subir ninguna foto válida.';
            }
        }
    }

    if ($accion === 'eliminar_archivo') {
        $archivo = trim((string)($_POST['archivo'] ?? ''));
        $rel_file = fotos_nube_normalize_path($archivo);
        $target_file = $root_dir . ($rel_file !== '' ? '/' . $rel_file : '');

        if ($rel_file === '' || !is_file($target_file) || !fotos_nube_is_within_root($target_file, $root_dir)) {
            $error = 'Archivo inválido.';
        } else {
            unlink($target_file);
            $message = 'Archivo eliminado.';
        }
    }

    if ($accion === 'eliminar_carpeta') {
        $folder_to_delete = fotos_nube_normalize_path($_POST['carpeta'] ?? $current_folder);
        if ($folder_to_delete === '') {
            $error = 'No se puede borrar la carpeta raíz.';
        } else {
            $target_dir = $root_dir . '/' . $folder_to_delete;
            if (!is_dir($target_dir) || !fotos_nube_is_within_root($target_dir, $root_dir)) {
                $error = 'Carpeta inválida.';
            } else {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($target_dir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );

                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        rmdir($item->getPathname());
                    } else {
                        unlink($item->getPathname());
                    }
                }

                rmdir($target_dir);
                $parent_folder = fotos_nube_parent_folder($folder_to_delete);
                $redirect_to = 'fotos_nube.php' . ($parent_folder !== '' ? '?folder=' . urlencode($parent_folder) : '');
                $message = 'Carpeta eliminada.';
            }
        }
    }

    if ($accion === 'mover_seleccionados') {
        $seleccion = $_POST['seleccion'] ?? [];
        $destino = fotos_nube_normalize_path($_POST['destino_carpeta'] ?? '');
        $destino_dir = $root_dir . ($destino !== '' ? '/' . $destino : '');

        if ($destino === '') {
            $error = 'Elegí una carpeta destino válida.';
        } elseif (!is_dir($destino_dir) || !fotos_nube_is_within_root($destino_dir, $root_dir)) {
            $error = 'La carpeta destino no existe o es inválida.';
        } elseif (empty($seleccion)) {
            $error = 'No seleccionaste ninguna foto para mover.';
        } else {
            $moved = 0;
            foreach ($seleccion as $item) {
                $rel = fotos_nube_normalize_path((string)$item);
                if ($rel === '') {
                    continue;
                }

                $source = $root_dir . '/' . $rel;
                if (!is_file($source) || !fotos_nube_is_within_root($source, $root_dir)) {
                    continue;
                }

                $target = $destino_dir . '/' . basename($source);
                if (file_exists($target)) {
                    $target = $destino_dir . '/' . time() . '_' . basename($source);
                }

                if (rename($source, $target)) {
                    $moved++;
                }
            }

            if ($moved > 0) {
                $message = 'Se movieron ' . $moved . ' foto(s) correctamente.';
            } else {
                $error = 'No se pudo mover ninguna foto.';
            }
        }
    }

    if ($accion === 'descargar_seleccionados') {
        $seleccion = $_POST['seleccion'] ?? [];
        $archivos = [];

        foreach ($seleccion as $item) {
            $rel = fotos_nube_normalize_path((string)$item);
            if ($rel === '') {
                continue;
            }

            $full = $root_dir . '/' . $rel;
            if (is_file($full) && fotos_nube_is_within_root($full, $root_dir)) {
                $archivos[] = $full;
            }
        }

        if (empty($archivos)) {
            $error = 'No seleccionaste ninguna foto para descargar.';
        } else {
            $zip_name = 'fotos_nube_' . date('Ymd_His') . '.zip';
            $zip_path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $zip_name;
            $zip = new ZipArchive();

            if ($zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                foreach ($archivos as $file_path) {
                    $relative_in_zip = str_replace($root_dir . '/', '', $file_path);
                    $zip->addFile($file_path, $relative_in_zip);
                }

                $zip->close();
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . $zip_name . '"');
                readfile($zip_path);
                unlink($zip_path);
                exit;
            }

            $error = 'No se pudo preparar la descarga en ZIP.';
        }
    }

    if ($accion !== 'descargar_seleccionados' && $error === '') {
        header('Location: ' . $redirect_to);
        exit;
    }
}

if (isset($_GET['download'])) {
    $download_rel = fotos_nube_normalize_path($_GET['download'] ?? '');
    if ($download_rel !== '') {
        $download_file = $root_dir . '/' . $download_rel;
        if (is_file($download_file) && fotos_nube_is_within_root($download_file, $root_dir)) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($download_file) . '"');
            header('Content-Length: ' . filesize($download_file));
            readfile($download_file);
            exit;
        }
    }
}

if (isset($_GET['delete'])) {
    $delete_rel = fotos_nube_normalize_path($_GET['delete'] ?? '');
    if ($delete_rel !== '') {
        $delete_file = $root_dir . '/' . $delete_rel;
        if (is_file($delete_file) && fotos_nube_is_within_root($delete_file, $root_dir)) {
            unlink($delete_file);
            $message = 'Foto eliminada.';
        }
    }
}

$folders = [];
$files = [];

if (is_dir($current_path)) {
    $iterator = new DirectoryIterator($current_path);
    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }

        $name = $item->getFilename();
        if ($item->isDir()) {
            $folders[] = [
                'name' => $name,
                'folder' => ($current_folder !== '' ? $current_folder . '/' : '') . $name,
            ];
        } elseif ($item->isFile() && fotos_nube_is_image($name)) {
            $files[] = [
                'name' => $name,
                'rel' => ($current_folder !== '' ? $current_folder . '/' : '') . $name,
            ];
        }
    }
}

usort($folders, static function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});
usort($files, static function ($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

$breadcrumbs = [];
$breadcrumb_parts = $current_folder !== '' ? explode('/', $current_folder) : [];
$acc = '';
$breadcrumbs[] = ['label' => 'Raíz', 'folder' => ''];
foreach ($breadcrumb_parts as $part) {
    $acc = $acc === '' ? $part : $acc . '/' . $part;
    $breadcrumbs[] = ['label' => $part, 'folder' => $acc];
}

$all_folders = fotos_nube_list_folders($root_dir);
?>

<style>
    .cloud-shell {
        background: linear-gradient(180deg, #f8fbff 0%, #eef3ff 100%);
        border-radius: 18px;
        padding: 1.25rem;
    }
    .cloud-toolbar {
        background: rgba(255,255,255,0.8);
        border: 1px solid #e3ebff;
        border-radius: 14px;
        padding: 1rem;
        backdrop-filter: blur(5px);
    }
    .cloud-folder-card {
        border: 1px solid #e8edf6;
        background: linear-gradient(180deg, #ffffff 0%, #f7faff 100%);
        border-radius: 16px;
        height: 100%;
        transition: transform .15s ease, box-shadow .15s ease;
    }
    .cloud-folder-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(45, 73, 145, 0.08);
    }
    .cloud-file-card {
        border: 1px solid #e7edf7;
        border-radius: 16px;
        overflow: hidden;
        height: 100%;
        background: #fff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.04);
    }
    .cloud-file-card img {
        height: 170px;
        object-fit: cover;
    }
    .cloud-stat {
        border-radius: 12px;
        background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
        border: 1px solid #dfeafe;
        padding: .7rem .85rem;
        color: #23427d;
    }
    .cloud-select {
        max-width: 220px;
    }
</style>

<div class="cloud-shell mb-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h1 class="mb-1">☁️ Nube de fotos</h1>
        <p class="text-muted mb-0">Organizá carpetas, subcarpetas y descargá la selección que necesites.</p>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= admin_h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= admin_h($error) ?></div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">Acciones rápidas</h5>
            </div>
            <div class="card-body">
                <div class="cloud-toolbar mb-4">
                    <form method="POST" class="mb-3">
                        <input type="hidden" name="accion" value="crear_carpeta">
                        <label class="form-label fw-semibold">Crear carpeta</label>
                        <div class="input-group">
                            <input type="text" name="carpeta_nombre" class="form-control" placeholder="Ej: clientes / campaña" required>
                            <button type="submit" class="btn btn-primary">Crear</button>
                        </div>
                    </form>

                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="accion" value="subir">
                        <label class="form-label fw-semibold">Subir fotos</label>
                        <input type="file" class="form-control mb-2" name="fotos[]" accept="image/*" multiple required>
                        <button type="submit" class="btn btn-outline-primary w-100">Subir al directorio actual</button>
                    </form>
                </div>

                <?php if ($current_folder !== ''): ?>
                    <div class="cloud-stat mb-3">
                        <div class="small text-uppercase fw-semibold opacity-75">Carpeta actual</div>
                        <div class="fw-bold mt-1"><?= admin_h($current_folder) ?></div>
                    </div>
                    <form method="POST" onsubmit="return confirm('¿Seguro que querés eliminar esta carpeta y todo su contenido?');">
                        <input type="hidden" name="accion" value="eliminar_carpeta">
                        <input type="hidden" name="carpeta" value="<?= admin_h($current_folder) ?>">
                        <button type="submit" class="btn btn-outline-danger w-100">Eliminar carpeta actual</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-light border mb-0">
                        Estás en la raíz de la nube. Acá podés organizar todo por carpetas.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center gap-3 flex-wrap">
                <div>
                    <h5 class="mb-0">Explorador</h5>
                    <nav aria-label="breadcrumb" class="small mt-2">
                        <ol class="breadcrumb mb-0">
                            <?php foreach ($breadcrumbs as $index => $crumb): ?>
                                <?php if ($index === count($breadcrumbs) - 1): ?>
                                    <li class="breadcrumb-item active" aria-current="page"><?= admin_h($crumb['label']) ?></li>
                                <?php else: ?>
                                    <li class="breadcrumb-item">
                                        <a href="fotos_nube.php<?= $crumb['folder'] !== '' ? '?folder=' . urlencode($crumb['folder']) : '' ?>" class="text-decoration-none">
                                            <?= admin_h($crumb['label']) ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($current_folder !== ''): ?>
                        <a href="fotos_nube.php?folder=<?= urlencode(fotos_nube_parent_folder($current_folder)) ?>" class="btn btn-sm btn-outline-secondary">Volver</a>
                    <?php endif; ?>
                    <a href="fotos_nube.php" class="btn btn-sm btn-outline-primary">Inicio</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($folders) && empty($files)): ?>
                    <div class="alert alert-info mb-0">Todavía no hay archivos ni carpetas en esta ubicación.</div>
                <?php else: ?>
                    <?php if (!empty($folders)): ?>
                        <div class="row g-3 mb-4">
                            <?php foreach ($folders as $folder): ?>
                                <div class="col-6 col-md-4 col-xl-3">
                                    <div class="cloud-folder-card p-3">
                                        <a href="fotos_nube.php?folder=<?= urlencode($folder['folder']) ?>" class="text-decoration-none text-dark d-block">
                                            <div class="display-6 text-warning mb-2 text-center"><i class="bi bi-folder-fill"></i></div>
                                            <div class="fw-semibold text-truncate text-center"><?= admin_h($folder['name']) ?></div>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($files)): ?>
                        <form method="POST" id="fotos-download-form" class="mt-2">
                            <input type="hidden" name="accion" value="descargar_seleccionados">
                            <div class="d-flex justify-content-between align-items-center gap-2 mb-3 flex-wrap">
                                <div class="small text-muted">Seleccioná las fotos y descargá en bloque.</div>
                                <div class="d-flex gap-2 align-items-center flex-wrap">
                                    <select name="destino_carpeta" class="form-select form-select-sm cloud-select" aria-label="Carpeta destino">
                                        <option value="">Mover a...</option>
                                        <option value="">Raíz</option>
                                        <?php foreach ($all_folders as $folder): ?>
                                            <option value="<?= admin_h($folder['folder']) ?>"><?= admin_h($folder['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" formaction="" class="btn btn-primary btn-sm" name="accion" value="descargar_seleccionados">Descargar seleccionadas</button>
                                    <button type="submit" class="btn btn-outline-secondary btn-sm" name="accion" value="mover_seleccionados">Mover seleccionadas</button>
                                </div>
                            </div>

                            <div class="row g-3">
                                <?php foreach ($files as $file): ?>
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <div class="cloud-file-card">
                                            <img src="../uploads/fotos_nube/<?= admin_h(str_replace('\\', '/', $file['rel'])) ?>" alt="<?= admin_h($file['name']) ?>">
                                            <div class="card-body p-2">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input" type="checkbox" name="seleccion[]" value="<?= admin_h($file['rel']) ?>" id="check_<?= md5($file['rel']) ?>">
                                                    <label class="form-check-label small" for="check_<?= md5($file['rel']) ?>">Seleccionar</label>
                                                </div>
                                                <div class="d-flex gap-2">
                                                    <a href="fotos_nube.php?download=<?= urlencode($file['rel']) ?>" class="btn btn-sm btn-outline-primary flex-fill">Descargar</a>
                                                    <a href="fotos_nube.php?delete=<?= urlencode($file['rel']) ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar esta foto?');">Eliminar</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
