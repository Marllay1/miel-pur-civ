<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envoi d'e-mails. Pilotes (MAIL_DRIVER) :
 *  - smtp : serveur SMTP authentifié (recommandé en production : SMTP_HOST, SMTP_PORT, SMTP_ENCRYPTION, SMTP_USERNAME, SMTP_PASSWORD) ;
 *  - mail : fonction mail() de PHP (nécessite un MTA configuré sur le serveur) ;
 *  - log  : écrit le message dans storage/mail (développement, rien n'est envoyé).
 * Chaque message part en texte brut et en HTML (gabarit sobre aux couleurs de la boutique).
 */
final class Mailer
{
    /** Messages envoyés pendant la requête (lus par les tests). */
    public static array $sent = [];

    /** Tests uniquement : force la réponse de deliversForReal(). */
    public static ?bool $deliveryOverride = null;

    /**
     * Les e-mails partent-ils réellement (et pas seulement dans storage/mail) ? Sert à ne jamais annoncer
     * comme opérationnel un envoi (réponse à un message, notification) qui ne l'est pas.
     */
    public static function deliversForReal(): bool
    {
        if (self::$deliveryOverride !== null) {
            return self::$deliveryOverride;
        }
        $driver = Env::get('MAIL_DRIVER', 'log');
        return ($driver === 'smtp' && (string) Env::get('SMTP_HOST', '') !== '') || $driver === 'mail';
    }

    public static function send(string $to, string $subject, string $text): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $fromAddress = (string) Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost');
        $fromName = (string) Env::get('MAIL_FROM_NAME', 'Miel Pur de Côte d\'Ivoire');
        $html = self::html($subject, $text);
        self::$sent[] = ['to' => $to, 'subject' => $subject, 'body' => $text, 'html' => $html];

        $driver = Env::get('MAIL_DRIVER', 'log');
        if ($driver === 'smtp') {
            try {
                Smtp::fromEnv()->send($fromAddress, $fromName, $to, $subject, $text, $html);
                return true;
            } catch (\Throwable $e) {
                Log::error('Échec SMTP : ' . $e->getMessage(), ['to' => $to, 'subject' => $subject]);
                return false;
            }
        }
        if ($driver === 'mail') {
            $boundaryMessage = Smtp::buildMessage($fromAddress, $fromName, $to, $subject, $text, $html);
            [$head, $body] = explode("\n\n", $boundaryMessage, 2);
            $headers = array_values(array_filter(explode("\n", $head), static fn ($h) => !preg_match('/^(To|Subject):/i', $h)));
            $ok = @mail($to, Smtp::encodeHeader($subject), $body, implode("\r\n", $headers));
            if (!$ok) {
                Log::error('Échec mail()', ['to' => $to, 'subject' => $subject]);
            }
            return $ok;
        }
        if (PHP_SAPI === 'cli' && Env::get('APP_ENV') === 'testing') {
            return true;
        }
        $dir = BASE_PATH . '/storage/mail';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $file = sprintf('%s/%s-%s.eml', $dir, gmdate('Ymd-His'), bin2hex(random_bytes(4)));
        return @file_put_contents($file, Smtp::buildMessage($fromAddress, $fromName, $to, $subject, $text, $html)) !== false;
    }

    /** Version HTML d'un message texte : texte échappé, liens rendus cliquables, mise en page sobre. */
    public static function html(string $subject, string $text): string
    {
        $body = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $body = preg_replace('#(https?://[^\s<]+)#', '<a href="$1" style="color:#184626;font-weight:600">$1</a>', $body) ?? $body;
        $body = nl2br($body);
        $title = htmlspecialchars($subject, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . $title . '</title></head>'
            . '<body style="margin:0;background:#F5F2EC;font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1D1A16">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:28px 12px">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:20px;overflow:hidden">'
            . '<tr><td style="background:#184626;padding:18px 24px;font-size:18px;font-weight:700;color:#F4EEDB">Miel Pur de Côte d\'Ivoire</td></tr>'
            . '<tr><td style="padding:24px;font-size:15px;line-height:1.6">' . $body . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
