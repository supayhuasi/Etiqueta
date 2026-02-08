<?php

function cache_path_for_key(string $key): string {
    $base_dir = __DIR__ . '/../cache';
    return $base_dir . '/cache_' . md5($key) . '.php';
}

function cache_get(string $key, int $ttlSeconds) {
    $path = cache_path_for_key($key);
    if (!file_exists($path)) {
        return null;
    }

    $mtime = filemtime($path);
    if ($mtime === false || (time() - $mtime) > $ttlSeconds) {
        return null;
    }

    $data = @file_get_contents($path);
    if ($data === false || $data === '') {
        return null;
    }

    $value = @unserialize($data);
    return $value === false && $data !== serialize(false) ? null : $value;
}

function cache_set(string $key, $value): bool {
    $path = cache_path_for_key($key);
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    $data = serialize($value);
    return (bool)@file_put_contents($path, $data, LOCK_EX);
}

function cache_delete(string $key): void {
    $path = cache_path_for_key($key);
    if (file_exists($path)) {
        @unlink($path);
    }
}

