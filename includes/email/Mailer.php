<?php
/**
 * Mailer — тонкий адаптер для отправки email.
 *
 * Транспорт берётся из .env (MAIL_TRANSPORT):
 *  - smtp : Native fsockopen-клиент (без зависимостей; для прода рекомендуется PHPMailer/Symfony Mailer).
 *  - mail : Стандартный mail(). По умолчанию для совместимости.
 *  - log  : Только в логи (для dev — посмотреть письма в logs/mail.log).
 */

require_once __DIR__ . '/../config/Config.php';

final class Mailer
{
    public static function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $transport = strtolower((string)Config::get('MAIL_TRANSPORT', 'mail'));
        $from      = (string)Config::get('MAIL_FROM_ADDRESS', 'noreply@elsesserandco.com');
        $fromName  = (string)Config::get('MAIL_FROM_NAME', 'Elsesser & Co.');

        $textBody ??= strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $htmlBody));
        $wrappedHtml = self::wrap($subject, $htmlBody);

        try {
            switch ($transport) {
                case 'log':
                    self::log($to, $subject, $wrappedHtml);
                    return true;
                case 'smtp':
                    if (is_file(__DIR__ . '/../../vendor/autoload.php')) {
                        return self::sendPhpMailer($to, $subject, $wrappedHtml, $textBody, $from, $fromName);
                    }
                    return self::sendSmtp($to, $subject, $wrappedHtml, $textBody, $from, $fromName);
                case 'mail':
                default:
                    return self::sendMail($to, $subject, $wrappedHtml, $from, $fromName);
            }
        } catch (Throwable $e) {
            error_log('Mailer error: ' . $e->getMessage());
            self::log($to, $subject . ' [FAILED]', $wrappedHtml);
            return false;
        }
    }

    private static function sendPhpMailer(string $to, string $subject, string $htmlBody, string $textBody, string $from, string $fromName): bool
    {
        require_once __DIR__ . '/../../vendor/autoload.php';
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string)Config::get('MAIL_HOST');
        $mail->Port = (int)(Config::get('MAIL_PORT') ?? 587);
        $mail->SMTPAuth = (string)Config::get('MAIL_USERNAME') !== '';
        $mail->Username = (string)Config::get('MAIL_USERNAME');
        $mail->Password = (string)Config::get('MAIL_PASSWORD');
        $enc = strtolower((string)Config::get('MAIL_ENCRYPTION', ''));
        if ($enc === 'ssl') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($enc === 'tls') {
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }
        $mail->setFrom($from, $fromName);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;
        return $mail->send();
    }

    private static function sendMail(string $to, string $subject, string $body, string $from, string $fromName): bool
    {
        $headers = [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . self::encodeName($fromName) . ' <' . $from . '>',
            'Reply-To: ' . $from,
            'X-Mailer: PHP/' . phpversion(),
        ];
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }

    private static function sendSmtp(string $to, string $subject, string $htmlBody, string $textBody, string $from, string $fromName): bool
    {
        $host = (string)Config::get('MAIL_HOST');
        $port = (int)(Config::get('MAIL_PORT') ?? 587);
        $user = (string)Config::get('MAIL_USERNAME');
        $pass = (string)Config::get('MAIL_PASSWORD');
        $enc  = strtolower((string)Config::get('MAIL_ENCRYPTION', ''));

        if (!$host) {
            return self::sendMail($to, $subject, $htmlBody, $from, $fromName);
        }

        $remote = ($enc === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $errno = 0; $errstr = '';
        $fp = @stream_socket_client($remote, $errno, $errstr, 15);
        if (!$fp) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        stream_set_timeout($fp, 15);

        $read = function () use ($fp) { return fgets($fp, 1024); };
        $cmd  = function (string $line) use ($fp, $read) {
            fwrite($fp, $line . "\r\n");
            $resp = '';
            do { $resp .= $read(); } while (str_starts_with(substr($resp, -4, 1), '-'));
            return $resp;
        };

        $read();
        $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

        if ($enc === 'tls') {
            $cmd('STARTTLS');
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $cmd('EHLO ' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        }

        if ($user !== '') {
            $cmd('AUTH LOGIN');
            $cmd(base64_encode($user));
            $cmd(base64_encode($pass));
        }

        $cmd('MAIL FROM:<' . $from . '>');
        $cmd('RCPT TO:<' . $to . '>');
        $cmd('DATA');

        $boundary = 'b' . bin2hex(random_bytes(8));
        $headers  = "From: " . self::encodeName($fromName) . " <$from>\r\n"
                  . "To: <$to>\r\n"
                  . "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n"
                  . "MIME-Version: 1.0\r\n"
                  . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($textBody)) . "\r\n"
               . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
               . chunk_split(base64_encode($htmlBody)) . "\r\n"
               . "--$boundary--\r\n";

        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $resp = $read();
        $cmd('QUIT');
        fclose($fp);

        return str_starts_with($resp, '2');
    }

    private static function wrap(string $title, string $html): string
    {
        $year = date('Y');
        return <<<HTML
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><title>{$title}</title></head>
<body style="margin:0;padding:0;font-family:'Helvetica Neue',Arial,sans-serif;background:#f5f5f5;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);">
      <tr><td style="padding:24px 32px;background:#1a2447;color:#fff;font-family:'Playfair Display',serif;font-size:22px;">Elsesser &amp; Co.</td></tr>
      <tr><td style="padding:32px;color:#333;line-height:1.6;font-size:15px;">{$html}</td></tr>
      <tr><td style="padding:16px 32px;background:#f5f5f5;color:#888;font-size:12px;text-align:center;">© {$year} Elsesser &amp; Co. · Екатеринбург</td></tr>
    </table>
  </td></tr></table></body></html>
HTML;
    }

    private static function encodeName(string $name): string
    {
        return '=?UTF-8?B?' . base64_encode($name) . '?=';
    }

    private static function log(string $to, string $subject, string $body): void
    {
        $dir = __DIR__ . '/../../logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf("[%s] -> %s | %s\n%s\n----\n", date('c'), $to, $subject, $body);
        @file_put_contents($dir . '/mail.log', $line, FILE_APPEND);
    }
}
