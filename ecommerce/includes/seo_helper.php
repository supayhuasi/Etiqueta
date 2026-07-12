<?php

if (!function_exists('seo_resolver_base')) {
    /**
     * Resuelve scheme/host/base público antes de incluir header.php,
     * para poder armar $seo_canonical / $seo_image con la URL completa.
     */
    function seo_resolver_base(): array
    {
        $scheme = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        $script_path = $_SERVER['SCRIPT_NAME'] ?? '';
        $public_base = '';
        if ($script_path) {
            if (strpos($script_path, '/ecommerce/') !== false) {
                $public_base = preg_replace('#/ecommerce/.*$#', '/ecommerce', $script_path);
            } else {
                $public_base = rtrim(dirname($script_path), '/\\');
            }
        }

        return [
            'scheme' => $scheme,
            'host' => $host,
            'public_base' => $public_base,
            'base_url' => $scheme . '://' . $host . $public_base,
        ];
    }
}

if (!function_exists('seo_truncar_descripcion')) {
    function seo_truncar_descripcion(string $texto, int $largo = 160): string
    {
        $texto = trim(preg_replace('/\s+/', ' ', strip_tags($texto)));
        if (mb_strlen($texto) > $largo) {
            $texto = mb_substr($texto, 0, $largo - 3) . '...';
        }
        return $texto;
    }
}
