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
    global $email_config;

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
