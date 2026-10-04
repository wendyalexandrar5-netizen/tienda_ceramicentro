<?php
/**
 * Envío de correos con PHPMailer usando la configuración SMTP de config/config.php.
 * Devuelve false (sin lanzar error) si el correo no está configurado o falla; el detalle queda en logs/app.log.
 */
require_once __DIR__ . '/bootstrap.php';

use PHPMailer\PHPMailer\PHPMailer;

function correo_configurado(): bool
{
    return (string)config('smtp.usuario', '') !== '' && (string)config('smtp.clave', '') !== '';
}

function enviar_correo(string $para, string $nombre, string $asunto, string $html, string $texto = '', ?array $responderA = null): bool
{
    if (!correo_configurado()) {
        return false;
    }
    $mail = new PHPMailer(true);
    try {
        $usuario = (string)config('smtp.usuario');
        $mail->CharSet    = 'UTF-8';
        $mail->isSMTP();
        $mail->Host       = (string)config('smtp.host', 'smtp.gmail.com');
        $mail->SMTPAuth   = true;
        $mail->Username   = $usuario;
        $mail->Password   = (string)config('smtp.clave');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)config('smtp.puerto', 587);
        $mail->Timeout    = 10;
        $mail->setFrom((string)(config('smtp.remitente') ?: $usuario), 'CERAMISHOP – CERAMICENTRO');
        $mail->addAddress($para, $nombre);
        if ($responderA) {
            $mail->addReplyTo($responderA[0], $responderA[1] ?? '');
        }
        $mail->isHTML(true);
        $mail->Subject = preg_replace('/[\r\n]+/', ' ', $asunto);
        $mail->Body    = $html;
        $mail->AltBody = $texto !== '' ? $texto : strip_tags($html);
        $mail->send();
        return true;
    } catch (Throwable $e) {
        log_app('error', 'Fallo SMTP: ' . ($mail->ErrorInfo ?: $e->getMessage()), ['para' => $para]);
        return false;
    }
}
