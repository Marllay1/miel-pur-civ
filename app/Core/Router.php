<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Routeur minimal. Motifs : `/boutique/{slug}` ou `/commande/{number:MPCI-[0-9]+-[A-Z0-9]+}`.
 * Paramètre sans motif explicite : [a-z0-9-]+.
 */
final class Router
{
    /** @var array<int, array{method:string, pattern:string, regex:string, handler:array, middleware:array}> */
    private array $routes = [];

    public function get(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    public function post(string $pattern, array $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    public function add(string $method, string $pattern, array $handler, array $middleware = []): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => self::compile($pattern),
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    public static function compile(string $pattern): string
    {
        $parts = preg_split('#(\{[a-zA-Z_]+(?::[^{}]+)?\})#', $pattern, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        foreach ($parts as $i => $part) {
            if ($i % 2 === 1) {
                preg_match('#^\{([a-zA-Z_]+)(?::([^{}]+))?\}$#', $part, $m);
                $regex .= '(?P<' . $m[1] . '>' . ($m[2] ?? '[a-z0-9-]+') . ')';
            } else {
                $regex .= preg_quote($part, '#');
            }
        }
        return '#^' . $regex . '$#';
    }

    /** @return array{0: array, 1: array<string,string>} */
    public function match(string $method, string $path): array
    {
        $method = $method === 'HEAD' ? 'GET' : $method;
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($m, static fn ($k) => is_string($k), ARRAY_FILTER_USE_KEY);
            return [$route, $params];
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    public function all(): array
    {
        return $this->routes;
    }
}
