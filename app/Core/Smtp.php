<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Client SMTP minimal et sans dépendance : STARTTLS (587) ou TLS implicite (465), AUTH LOGIN/PLAIN,
 * message MIME multipart (texte + HTML) encodé en UTF-8. Certificat du serveur vérifié.
 */
final class Smtp
{
    /** @var resource|null */
    private $socket = null;
    public array $log = [];

    public function __construct(
        private string $host,
        private int $port = 587,
        private string $encryption = 'tls',
        private string $username = '',
        private string $password = '',
        private int $timeout = 15,
        private bool $verifyPeer = true,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) Env::get('SMTP_HOST', ''),
            (int) Env::get('SMTP_PORT', '587'),
            strtolower((string) Env::get('SMTP_ENCRYPTION', 'tls')),
            (string) Env::get('SMTP_USERNAME', ''),
            (string) Env::get('SMTP_PASSWORD', ''),
            (int) Env::get('SMTP_TIMEOUT', '15'),
            !Env::bool('SMTP_ALLOW_SELF_SIGNED'),
        );
    }

    /** @throws \RuntimeException */
    public function send(string $fromAddress, string $fromName, string $to, string $subject, string $text, string $html): void
    {
        if ($this->host === '') {
            throw new \RuntimeException('SMTP_HOST non renseigné');
        }
        foreach ([$fromAddress, $to] as $address) {
            if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Adresse invalide');
            }
        }
        try {
            $this->connect();
            $this->command('MAIL FROM:<' . $fromAddress . '>', [250]);
            $this->command('RCPT TO:<' . $to . '>', [250, 251]);
            $this->command('DATA', [354]);
            $message = self::buildMessage($fromAddress, $fromName, $to, $subject, $text, $html);
            // Transparence SMTP : un point en début de ligne est doublé.
            $message = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message)) ?? $message;
            $this->write(str_replace("\n", "\r\n", $message) . "\r\n.");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function connect(): void
    {
        $context = stream_context_create(['ssl' => [
            'verify_peer' => $this->verifyPeer, 'verify_peer_name' => $this->verifyPeer, 'allow_self_signed' => !$this->verifyPeer,
            'peer_name' => $this->host, 'SNI_enabled' => true,
        ]]);
        $scheme = $this->encryption === 'ssl' ? 'ssl' : 'tcp';
        $socket = @stream_socket_client("$scheme://{$this->host}:{$this->port}", $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT, $context);
        if ($socket === false) {
            throw new \RuntimeException("Connexion SMTP impossible ($errno $errstr)");
        }
        stream_set_timeout($socket, $this->timeout);
        $this->socket = $socket;
        $this->expect([220]);
        $hostname = preg_replace('/[^A-Za-z0-9.-]/', '', (string) (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost')) ?: 'localhost';
        $ehlo = $this->command('EHLO ' . $hostname, [250]);
        if ($this->encryption === 'tls') {
            if (!str_contains(strtoupper($ehlo), 'STARTTLS')) {
                throw new \RuntimeException('Le serveur SMTP ne propose pas STARTTLS');
            }
            $this->command('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new \RuntimeException('Négociation TLS impossible');
            }
            $ehlo = $this->command('EHLO ' . $hostname, [250]);
        }
        if ($this->username !== '') {
            if (preg_match('/AUTH[ =][^\r\n]*PLAIN/i', $ehlo)) {
                $this->command('AUTH PLAIN ' . base64_encode("\0" . $this->username . "\0" . $this->password), [235], true);
            } else {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($this->username), [334], true);
                $this->command(base64_encode($this->password), [235], true);
            }
        }
    }

    private function write(string $line): void
    {
        if (@fwrite($this->socket, $line . "\r\n") === false) {
            throw new \RuntimeException('Écriture SMTP impossible');
        }
    }

    private function command(string $line, array $expected, bool $secret = false): string
    {
        $this->log[] = 'C: ' . ($secret ? '***' : $line);
        $this->write($line);
        return $this->expect($expected);
    }

    private function expect(array $codes): string
    {
        $response = '';
        while (($line = fgets($this->socket, 1024)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        $this->log[] = 'S: ' . trim($response);
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException('Réponse SMTP inattendue : ' . trim(substr($response, 0, 200)));
        }
        return $response;
    }

    public static function encodeHeader(string $value): string
    {
        $value = str_replace(["\r", "\n"], ' ', $value);
        return preg_match('/[^\x20-\x7E]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
    }

    public static function buildMessage(string $fromAddress, string $fromName, string $to, string $subject, string $text, string $html): string
    {
        $boundary = 'mpci-' . bin2hex(random_bytes(12));
        $domain = substr(strrchr($fromAddress, '@') ?: '@localhost', 1);
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromAddress . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'Auto-Submitted: auto-generated',
        ];
        $part = static fn (string $type, string $body) => "--$boundary\nContent-Type: $type; charset=UTF-8\nContent-Transfer-Encoding: base64\n\n" . chunk_split(base64_encode($body), 76, "\n");
        return implode("\n", $headers) . "\n\n" . $part('text/plain', $text) . $part('text/html', $html) . "--$boundary--";
    }
}
