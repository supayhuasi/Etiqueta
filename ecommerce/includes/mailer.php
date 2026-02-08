<?php
if (!isset($email_config)) {
    require __DIR__ . '/../../config.php';
}

function cargar_autoloads_composer(): void {
    $autoloads = [
        __DIR__ . '/../../vendor/autoload.php',
        __DIR__ . '/../vendor/autoload.php'
    ];

    foreach ($autoloads as $autoload) {
        if (file_exists($autoload)) {
            require_once $autoload;
            return;
        }
    }
}

function enviar_email(string $to, string $subject, string $html, ?string $text = null): bool {
    global $email_config, $pdo;

    // Cargar configuración desde la base si existe
    try {
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT * FROM ecommerce_email_config WHERE activo = 1 LIMIT 1");
            $email_db = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($email_db) {
                $email_config = array_merge($email_config ?? [], [
                    'from_email' => $email_db['from_email'] ?? ($email_config['from_email'] ?? null),
                    'from_name' => $email_db['from_name'] ?? ($email_config['from_name'] ?? null),
                    'smtp_host' => $email_db['smtp_host'] ?? ($email_config['smtp_host'] ?? null),
                    'smtp_port' => $email_db['smtp_port'] ?? ($email_config['smtp_port'] ?? null),
                    'smtp_user' => $email_db['smtp_user'] ?? ($email_config['smtp_user'] ?? null),
                    'smtp_pass' => $email_db['smtp_pass'] ?? ($email_config['smtp_pass'] ?? null),
                    'smtp_secure' => $email_db['smtp_secure'] ?? ($email_config['smtp_secure'] ?? null),
                    'smtp_auth' => isset($email_db['smtp_auth']) ? (bool)$email_db['smtp_auth'] : ($email_config['smtp_auth'] ?? null)
                ]);
            }
        }
    } catch (Exception $e) {
        // Ignorar errores de configuración en DB
    }

    $fromEmail = $email_config['from_email'] ?? 'no-reply@localhost';
    $fromName = $email_config['from_name'] ?? 'Ecommerce';

    $smtpHost = trim((string)($email_config['smtp_host'] ?? ''));
    if ($smtpHost !== '') {
        cargar_autoloads_composer();
    }

    if ($smtpHost !== '' && class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->Host = $smtpHost;
            $mail->Port = (int)($email_config['smtp_port'] ?? 587);
            $mail->SMTPAuth = (bool)($email_config['smtp_auth'] ?? true);
            $mail->Username = (string)($email_config['smtp_user'] ?? '');
            $mail->Password = (string)($email_config['smtp_pass'] ?? '');
            $mail->SMTPSecure = (string)($email_config['smtp_secure'] ?? 'tls');

            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text ?: strip_tags($html);

            return $mail->send();
        } catch (Exception $e) {
            return false;
        }
    }

    $headers = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';

    $headersString = implode("\r\n", $headers);
    return mail($to, $subject, $html, $headersString);
}

function ecommerce_base_url(): string {
    $script_path_public = $_SERVER['SCRIPT_NAME'] ?? '';
    $public_base = '';
    if ($script_path_public) {
        if (strpos($script_path_public, '/ecommerce/') !== false) {
            $public_base = preg_replace('#/ecommerce/.*$#', '/ecommerce', $script_path_public);
        } elseif (strpos($script_path_public, '/admin/') !== false) {
            $public_base = rtrim(preg_replace('#/admin/.*$#', '', $script_path_public), '/');
        } else {
            $public_base = rtrim(dirname($script_path_public), '/\\');
        }
    }

    $request_scheme = 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $request_scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $request_scheme = 'https';
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $request_scheme . '://' . $host . $public_base;
}

function formato_precio_email(float $monto): string {
    return '$' . number_format($monto, 2, ',', '.');
}

function render_template_string(string $template, array $vars): string {
    foreach ($vars as $key => $value) {
        $template = str_replace('{{' . $key . '}}', (string)$value, $template);
    }
    return $template;
}

function cargar_email_template(string $templateBase, array $vars_common, array $vars_html = [], array $vars_text = []): array {
    $templates_dir = __DIR__ . '/../email_templates';
    $subject_path = $templates_dir . '/' . $templateBase . '_subject.txt';
    $html_path = $templates_dir . '/' . $templateBase . '.html';
    $text_path = $templates_dir . '/' . $templateBase . '.txt';

    $subject_raw = file_exists($subject_path) ? trim((string)file_get_contents($subject_path)) : '';
    $html_raw = file_exists($html_path) ? (string)file_get_contents($html_path) : '';
    $text_raw = file_exists($text_path) ? (string)file_get_contents($text_path) : '';

    $subject = render_template_string($subject_raw, $vars_common);
    $html = render_template_string($html_raw, array_merge($vars_common, $vars_html));
    $text = render_template_string($text_raw, array_merge($vars_common, $vars_text));

    return [
        'subject' => $subject,
        'html' => $html,
        'text' => $text
    ];
}

function construir_resumen_carrito_email(array $carrito): array {
    $items_html = '';
    $items_text_lines = [];
    $subtotal = 0.0;
    $cantidad_total = 0;

    foreach ($carrito as $item) {
        $nombre = (string)($item['nombre'] ?? 'Producto');
        $cantidad = (int)($item['cantidad'] ?? 1);
        $precio_base = (float)($item['precio'] ?? 0);

        $attrs_text = [];
        $attrs_html = [];
        $costo_atributos = 0.0;

        if (isset($item['atributos']) && is_array($item['atributos'])) {
            foreach ($item['atributos'] as $attr) {
                $attr_nombre = (string)($attr['nombre'] ?? 'Atributo');
                $attr_valor = (string)($attr['valor'] ?? '');
                $attr_costo = (float)($attr['costo_adicional'] ?? 0);
                if ($attr_costo > 0) {
                    $costo_atributos += $attr_costo;
                }
                $attr_text = $attr_nombre . ': ' . $attr_valor;
                if ($attr_costo > 0) {
                    $attr_text .= ' (+' . formato_precio_email($attr_costo) . ')';
                }
                $attrs_text[] = $attr_text;
                $attrs_html[] = htmlspecialchars($attr_text);
            }
        }

        $precio_unitario = $precio_base + $costo_atributos;
        $subtotal += $precio_unitario * $cantidad;
        $cantidad_total += $cantidad;

        $medidas = '';
        $ancho = (float)($item['ancho'] ?? 0);
        $alto = (float)($item['alto'] ?? 0);
        if ($ancho > 0 && $alto > 0) {
            $medidas = $ancho . 'cm × ' . $alto . 'cm';
        }

        $nombre_html = htmlspecialchars($nombre);
        $detalle_html = '';
        if ($medidas !== '') {
            $detalle_html .= '<div style="color:#667085;font-size:12px;">' . htmlspecialchars($medidas) . '</div>';
        }
        if (!empty($attrs_html)) {
            $detalle_html .= '<div style="color:#667085;font-size:12px;">' . implode('<br>', $attrs_html) . '</div>';
        }

        $items_html .= '<li style="margin-bottom:10px;">'
            . '<strong>' . $nombre_html . '</strong> x' . $cantidad
            . $detalle_html
            . '<div style="margin-top:4px;">Precio: ' . formato_precio_email($precio_unitario) . '</div>'
            . '</li>';

        $linea_texto = '- ' . $nombre . ' x' . $cantidad . ' — ' . formato_precio_email($precio_unitario) . ' c/u';
        if ($medidas !== '') {
            $linea_texto .= ' (' . $medidas . ')';
        }
        if (!empty($attrs_text)) {
            $linea_texto .= ' [' . implode('; ', $attrs_text) . ']';
        }
        $items_text_lines[] = $linea_texto;
    }

    return [
        'items_html' => $items_html ?: '<li>Productos en tu carrito</li>',
        'items_text' => !empty($items_text_lines) ? implode("\n", $items_text_lines) : '- Productos en tu carrito',
        'subtotal' => $subtotal,
        'cantidad_total' => $cantidad_total
    ];
}

function enviar_email_carrito_abandonado(array $cliente, array $carrito, array $opciones = []): bool {
    global $pdo;

    if (empty($carrito)) {
        return false;
    }

    $cliente_email = trim((string)($cliente['email'] ?? ($opciones['email'] ?? '')));
    if ($cliente_email === '') {
        return false;
    }

    $empresa_nombre = 'Ecommerce';
    try {
        if (isset($pdo)) {
            $stmt = $pdo->query("SELECT nombre FROM ecommerce_empresa LIMIT 1");
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!empty($empresa['nombre'])) {
                $empresa_nombre = $empresa['nombre'];
            }
        }
    } catch (Exception $e) {
        // Ignorar
    }

    $resumen = construir_resumen_carrito_email($carrito);
    $base_url = ecommerce_base_url();
    $carrito_link = rtrim($base_url, '/') . '/carrito.php';
    $checkout_link = rtrim($base_url, '/') . '/checkout.php';

    $cliente_nombre = trim((string)($cliente['nombre'] ?? ''));
    if ($cliente_nombre === '') {
        $cliente_nombre = 'cliente';
    }

    $vars_common = [
        'cliente_nombre' => $cliente_nombre,
        'empresa_nombre' => $empresa_nombre,
        'carrito_total' => formato_precio_email((float)$resumen['subtotal']),
        'carrito_link' => $carrito_link,
        'checkout_link' => $checkout_link,
        'fecha' => date('d/m/Y H:i')
    ];

    $template = cargar_email_template(
        'carrito_abandonado',
        $vars_common,
        [
            'cliente_nombre' => htmlspecialchars($cliente_nombre),
            'empresa_nombre' => htmlspecialchars($empresa_nombre),
            'carrito_items' => $resumen['items_html']
        ],
        ['carrito_items' => $resumen['items_text']]
    );

    $subject = $template['subject'] !== '' ? $template['subject'] : 'Recordatorio de carrito';
    $html = $template['html'] !== '' ? $template['html'] : nl2br($template['text']);
    $text = $template['text'] !== '' ? $template['text'] : strip_tags($template['html']);

    return enviar_email($cliente_email, $subject, $html, $text);
}
