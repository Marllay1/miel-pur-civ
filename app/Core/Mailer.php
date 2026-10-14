<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Envoi d'e-mails. Pilotes :
 *  - log  : écrit le message dans storage/mail (par défaut, développement) ;
 *  - mail : fonction mail() de PHP (nécessite un MTA configuré sur le serveur).
 * Un pilote SMTP authentifié reste à intégrer (voir docs/INTEGRATIONS.md).
 */
final class Mailer
{
    /** Messages envoyés pendant la requête (lus par les tests). */
    public static array $sent = [];

    public static function send(string $to, string $subject, string $text): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        $subject = str_replace(["\r", "\n"], ' ', $subject);
        $fromAddress = Env::get('MAIL_FROM_ADDRESS', 'no-reply@localhost');
        $fromName = Env::get('MAIL_FROM_NAME', 'Miel Pur de Côte d\'Ivoire');
        self::$sent[] = ['to' => $to, 'subject' => $subject, 'body' => $text];

        $driver = Env::get('MAIL_DRIVER', 'log');
        if ($driver === 'mail') {
            $headers = [
                'From' => sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($fromName), $fromAddress),
                'MIME-Version' => '1.0',
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Transfer-Encoding' => '8bit',
            ];
            $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $text, $headers);
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
        $content = "From: $fromName <$fromAddress>\nTo: $to\nSubject: $subject\nDate: " . gmdate('r') . "\n\n$text\n";
        return @file_put_contents($file, $content) !== false;
    }
}
