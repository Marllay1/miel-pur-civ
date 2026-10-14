<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    /** Paramètres extraits de la route. */
    public array $params = [];

    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $post = [],
        public array $cookies = [],
        public array $files = [],
        public array $server = [],
        public string $body = '',
        public array $headers = [],
    ) {
        $this->method = strtoupper($this->method);
        $this->path = self::normalizePath($this->path);
        $this->headers = array_change_key_case($this->headers, CASE_LOWER);
        if ($this->method === 'POST' && isset($this->post['_method']) && in_array(strtoupper((string) $this->post['_method']), ['PUT', 'PATCH', 'DELETE'], true)) {
            $this->method = strtoupper((string) $this->post['_method']);
        }
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[str_replace('_', '-', strtolower(substr($k, 5)))] = $v;
            } elseif (in_array($k, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headers[str_replace('_', '-', strtolower($k))] = $v;
            }
        }
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        return new self(
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            rawurldecode($path),
            $_GET,
            $_POST,
            $_COOKIE,
            $_FILES,
            $_SERVER,
            (string) file_get_contents('php://input'),
            $headers,
        );
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key);
        return is_string($v) ? trim($v) : $default;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $v = $this->input($key);
        if (is_string($v) && preg_match('/^-?\d{1,10}$/', trim($v))) {
            return (int) trim($v);
        }
        if (is_int($v)) {
            return $v;
        }
        return $default;
    }

    public function header(string $name): ?string
    {
        $v = $this->headers[strtolower($name)] ?? null;
        return is_string($v) ? $v : null;
    }

    public function cookie(string $name): ?string
    {
        $v = $this->cookies[$name] ?? null;
        return is_string($v) ? $v : null;
    }

    public function ip(): string
    {
        $ip = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');
        $trusted = Env::get('TRUSTED_PROXY');
        if ($trusted && $ip === $trusted) {
            $fwd = $this->header('x-forwarded-for');
            if ($fwd) {
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    return $first;
                }
            }
        }
        return $ip;
    }

    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        return Env::get('TRUSTED_PROXY') !== null && $this->header('x-forwarded-proto') === 'https';
    }

    public function wantsJson(): bool
    {
        return str_contains((string) $this->header('accept'), 'application/json')
            || $this->header('x-requested-with') === 'fetch';
    }

    public function fullPath(): string
    {
        return $this->path . ($this->query ? '?' . http_build_query($this->query) : '');
    }
}
