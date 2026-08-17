<?php

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
