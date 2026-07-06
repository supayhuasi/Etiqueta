<?php
if (!function_exists('blog_publico_imagen_url')) {
    function blog_publico_imagen_url(?string $archivo, string $public_base = ''): ?string
    {
        if (empty($archivo)) {
            return null;
        }

        $local = __DIR__ . '/../uploads/blog/' . $archivo; // ecommerce/uploads/blog
        $root = __DIR__ . '/../../uploads/blog/' . $archivo; // uploads/blog en la raiz del sitio

        if (file_exists($local)) {
            return $public_base . '/uploads/blog/' . $archivo;
        }

        // Los artículos se guardan en la raíz del sitio (ver admin/blog.php)
        return '/uploads/blog/' . $archivo;
    }
}
