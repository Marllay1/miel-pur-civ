<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function __construct(
        public string $body = '',
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8']
        );
    }

    public static function text(string $body, int $status = 200, string $type = 'text/plain'): self
    {
        return new self($body, $status, ['Content-Type' => $type . '; charset=UTF-8']);
    }

    public static function redirect(string $url, int $status = 303): self
    {
        // N'accepte que des chemins internes ou l'URL de l'application (anti open-redirect).
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) {
            $base = rtrim((string) Env::get('APP_URL', ''), '/');
            if ($base === '' || !str_starts_with($url, $base . '/')) {
                $url = '/';
            }
        }
        return new self('', $status, ['Location' => $url]);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            header_remove('X-Powered-By');
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            Cookie::flush();
        }
        echo $this->body;
    }
}
